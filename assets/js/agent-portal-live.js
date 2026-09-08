(function () {
    'use strict';

    var config = window.VigilanceAgentPortalConfig || null;
    var Portal = window.VigilancePortal || null;
    if (!config || !Portal) {
        return;
    }

    var context = {
        role: 'agent',
        id: String(config.agentId || config.id || '').trim(),
        agent_id: String(config.agentId || config.id || '').trim(),
        client_id: String(config.activeClientId || '').trim()
    };
    var messages = Array.isArray(config.initialMessages) ? config.initialMessages.slice() : [];
    var lastSignature = '';
    var reloadTimer = null;
    var ws = null;
    var connected = false;
    var wsCandidates = Portal.buildWsCandidates(config.wsPort || 8765);
    var wsCandidateIndex = 0;
    var reconnectTimer = 0;
    var callRequestsByLiveId = {};
    var callController = null;

    if (window.Notification && Notification.permission === 'default') {
        Notification.requestPermission().catch(function () { return null; });
    }

    function notify(title, body) {
        if (window.Notification && Notification.permission === 'granted') {
            try {
                new Notification(title, { body: body });
            } catch (error) {
                // Ignore notification failures.
            }
        }
    }

    function scheduleReload() {
        if (reloadTimer !== null) {
            return;
        }

        reloadTimer = window.setTimeout(function () {
            window.location.reload();
        }, 900);
    }

    function clearReconnectTimer() {
        if (!reconnectTimer) {
            return;
        }
        window.clearTimeout(reconnectTimer);
        reconnectTimer = 0;
    }

    function scheduleReconnect(rotateCandidate) {
        clearReconnectTimer();
        if (rotateCandidate && wsCandidates.length > 1) {
            wsCandidateIndex = (wsCandidateIndex + 1) % wsCandidates.length;
        }
        reconnectTimer = window.setTimeout(function () {
            reconnectTimer = 0;
            if (document.hidden) {
                scheduleReconnect(false);
                return;
            }
            connect();
        }, 3500);
    }

    function signatureFromPayload(payload) {
        var currentMessages = Array.isArray(payload.messages) ? payload.messages : [];
        var currentCalls = Array.isArray(payload.calls) ? payload.calls : [];
        var currentInterventions = Array.isArray(payload.interventions) ? payload.interventions : [];
        return currentMessages.slice(0, 12).map(function (message) {
            return String(message.id || '') + ':' + String(message.ack_status || '') + ':' + String(message.updated_at || message.created_at || '');
        }).join('|')
            + '|c:' + currentCalls.slice(0, 8).map(function (call) {
                return String(call.id || '') + ':' + String(call.status || '') + ':' + String(call.updated_at || call.created_at || '');
            }).join(',')
            + '|i:' + currentInterventions.slice(0, 8).map(function (intervention) {
                return String(intervention.id || '') + ':' + String(intervention.status || '') + ':' + String(intervention.updated_at || intervention.created_at || '');
            }).join(',');
    }

    function rememberCallRequest(call) {
        if (!call || typeof call !== 'object') {
            return;
        }

        var liveCallId = String(call.live_call_id || '').trim();
        var requestId = String(call.id || '').trim();
        if (!liveCallId || !requestId) {
            return;
        }

        callRequestsByLiveId[liveCallId] = requestId;
    }

    function rememberCallRequests(calls) {
        (Array.isArray(calls) ? calls : []).forEach(rememberCallRequest);
    }

    function syncLoop() {
        Portal.syncMessageLoop(messages, context);
    }

    function refreshFeed() {
        if (!config.secureFeedUrl || typeof fetch !== 'function') {
            return Promise.resolve(null);
        }

        return fetch(config.secureFeedUrl, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json().catch(function () {
                return null;
            }).then(function (data) {
                if (!response.ok || !data || data.ok !== true) {
                    throw new Error(data && data.message ? data.message : 'Flux indisponible');
                }
                return data;
            });
        }).then(function (data) {
            var nextSignature = signatureFromPayload(data);
            messages = Array.isArray(data.messages) ? data.messages.slice() : [];
            rememberCallRequests(data.calls);
            syncLoop();
            if (lastSignature !== '' && nextSignature !== lastSignature) {
                scheduleReload();
            }
            lastSignature = nextSignature;
            return data;
        }).catch(function () {
            return null;
        });
    }

    function postCallRequest(payload) {
        if (!config.startCallUrl || typeof fetch !== 'function') {
            return Promise.resolve(null);
        }

        var form = new FormData();
        Object.keys(payload || {}).forEach(function (key) {
            form.append(key, String(payload[key] || ''));
        });

        return fetch(config.startCallUrl, {
            method: 'POST',
            body: form,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            return response.json().catch(function () {
                return null;
            }).then(function (data) {
                if (!response.ok || !data || data.ok !== true) {
                    throw new Error(data && data.message ? data.message : 'Demande d appel refusee');
                }
                return data;
            });
        }).catch(function () {
            return null;
        });
    }

    function updateCallRequestStatus(liveCallId, status, notes) {
        var requestId = callRequestsByLiveId[String(liveCallId || '')] || '';
        if (!requestId || !config.updateCallUrl || typeof fetch !== 'function') {
            return Promise.resolve(null);
        }

        var form = new FormData();
        form.append('request_id', requestId);
        form.append('status', String(status || 'ended'));
        form.append('live_call_id', String(liveCallId || ''));
        if (notes) {
            form.append('notes', String(notes));
        }

        return fetch(config.updateCallUrl, {
            method: 'POST',
            body: form,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (response) {
            return response.json().catch(function () {
                return null;
            });
        }).catch(function () {
            return null;
        });
    }

    function sendJson(message) {
        if (!ws || ws.readyState !== 1) {
            return false;
        }

        ws.send(JSON.stringify(message));
        return true;
    }

    callController = Portal.createCallController({
        role: 'agent',
        label: String(config.agentName || 'Agent VIGILANCE'),
        sendJson: sendJson,
        onOutgoingInvite: function (payload) {
            postCallRequest({
                target_role: 'admin',
                target_id: 'control-center',
                target_name: 'Centre OPS VIGILANCE',
                client_id: String(config.activeClientId || ''),
                client_name: String(config.activeClientName || ''),
                live_call_id: String(payload.call_id || ''),
                notes: 'Demande d appel live declenchee depuis le portail agent.',
                channel: 'Portail agent live',
                status: 'ringing',
                context_type: 'portal_live_call',
                context_id: String(config.agentId || '')
            }).then(function (response) {
                if (response && response.call && response.call.id) {
                    callRequestsByLiveId[String(payload.call_id || '')] = String(response.call.id || '');
                }
            });
        },
        onIncomingInvite: function (payload) {
            notify('Appel entrant VIGILANCE', String(payload.target_label || 'Le centre OPS') + ' demande une liaison audio.');
        },
        onEnded: function (payload) {
            updateCallRequestStatus(payload.call_id || '', payload.reason === 'declined' ? 'declined' : 'ended', 'Appel live termine.');
            refreshFeed();
        }
    });

    function isRelevantAlertPayload(payload) {
        if (!payload || typeof payload !== 'object') {
            return false;
        }
        if (String(payload.agent_id || '') === context.agent_id) {
            return true;
        }
        if (Array.isArray(payload.agent_ids) && payload.agent_ids.map(String).indexOf(context.agent_id) !== -1) {
            return true;
        }
        return context.client_id !== '' && String(payload.client_id || '') === context.client_id;
    }

    function isRelevantMissionPayload(payload) {
        if (!payload || typeof payload !== 'object') {
            return false;
        }
        if (String(payload.agent_id || '') === context.agent_id) {
            return true;
        }
        if (Array.isArray(payload.agent_ids) && payload.agent_ids.map(String).indexOf(context.agent_id) !== -1) {
            return true;
        }
        return context.client_id !== '' && String(payload.client_id || '') === context.client_id;
    }

    function isRelevantCallPayload(payload) {
        if (!payload || typeof payload !== 'object') {
            return false;
        }
        var requesterId = String(payload.requester_id || '').trim();
        var targetId = String(payload.target_id || '').trim();
        return requesterId === context.agent_id
            || targetId === context.agent_id
            || (context.client_id !== '' && String(payload.client_id || '') === context.client_id);
    }

    function joinChannel() {
        if (ws && ws.readyState === 1) {
            ws.send(JSON.stringify({
                type: 'join',
                channel: 'alpha',
                name: String(config.agentName || 'Agent VIGILANCE'),
                role: 'agent',
                user_id: context.agent_id,
                client_id: context.client_id
            }));
        }
    }

    function connect() {
        clearReconnectTimer();
        if (!wsCandidates.length) {
            return;
        }

        var targetUrl = wsCandidates[wsCandidateIndex % wsCandidates.length];
        var opened = false;

        try {
            ws = new WebSocket(targetUrl);
        } catch (error) {
            scheduleReconnect(true);
            return;
        }

        ws.onopen = function () {
            opened = true;
            connected = true;
            joinChannel();
        };
        ws.onclose = function () {
            connected = false;
            ws = null;
            scheduleReconnect(!opened);
        };
        ws.onerror = function () {
            try {
                ws.close();
            } catch (error) {
                // Ignore close errors.
            }
        };
        ws.onmessage = function (event) {
            try {
                handleMessage(JSON.parse(event.data));
            } catch (error) {
                // Ignore malformed socket payloads.
            }
        };
    }

    function handleMessage(message) {
        if (!message || typeof message !== 'object') {
            return;
        }

        if (message.type === 'call_accept') {
            updateCallRequestStatus(message.call_id || '', 'accepted_live', 'Appel live accepte.');
        } else if (message.type === 'call_decline') {
            updateCallRequestStatus(message.call_id || '', message.reason === 'busy' ? 'missed' : 'declined', 'Appel live refuse ou indisponible.');
        } else if (message.type === 'call_end') {
            updateCallRequestStatus(message.call_id || '', 'ended', 'Appel live termine.');
        }

        if (callController && callController.handleSocketMessage(message)) {
            return;
        }

        if (message.type !== 'server_event' || !message.channel) {
            return;
        }

        if (
            Portal.matchesRealtimeChannel(message.channel, 'vigilance:calls:new')
            || Portal.matchesRealtimeChannel(message.channel, 'vigilance:calls:updated')
        ) {
            rememberCallRequest(message.data);
        }

        if (
            Portal.matchesRealtimeChannel(message.channel, 'vigilance:communications:new')
            && Portal.messageNeedsAck(message.data, context)
        ) {
            notify('Nouvelle consigne OPS', String((message.data && message.data.subject) || 'Message centre') + ' • ' + String((message.data && message.data.message) || ''));
            messages = messages.concat([message.data]);
            syncLoop();
            refreshFeed();
            return;
        }

        if (Portal.matchesRealtimeChannel(message.channel, 'vigilance:alerts:new') && isRelevantAlertPayload(message.data)) {
            notify('Alerte terrain VIGILANCE', String((message.data && (message.data.message || message.data.type || message.data.type_alerte)) || 'Nouvelle alerte critique'));
            refreshFeed();
            return;
        }

        if (
            (Portal.matchesRealtimeChannel(message.channel, 'vigilance:interventions:assigned')
                || Portal.matchesRealtimeChannel(message.channel, 'vigilance:interventions:updated')
                || Portal.matchesRealtimeChannel(message.channel, 'vigilance:interventions:closed'))
            && isRelevantMissionPayload(message.data)
        ) {
            refreshFeed();
            return;
        }

        if (
            (Portal.matchesRealtimeChannel(message.channel, 'vigilance:calls:new')
                || Portal.matchesRealtimeChannel(message.channel, 'vigilance:calls:updated'))
            && isRelevantCallPayload(message.data)
        ) {
            refreshFeed();
            return;
        }

        if (Portal.matchesRealtimeChannel(message.channel, 'vigilance:communications:acknowledged')) {
            refreshFeed();
        }
    }

    document.addEventListener('click', function (event) {
        var ackButton = event.target.closest('[data-agent-ack]');
        if (ackButton) {
            event.preventDefault();
            if (ackButton.disabled) {
                return;
            }

            ackButton.disabled = true;
            Portal.acknowledgeMessage(config.ackUrl, ackButton.getAttribute('data-agent-ack'), 'Reception confirmee depuis le portail agent.').then(function (response) {
                var messageId = ackButton.getAttribute('data-agent-ack');
                var bubble = ackButton.closest('[data-message-id]');
                if (bubble) {
                    bubble.setAttribute('data-ack-status', 'acknowledged');
                    var badge = bubble.querySelector('[data-ack-badge]');
                    if (badge) {
                        badge.textContent = 'ACK confirme';
                    }
                }
                var ackForm = ackButton.closest('.ag-ack-form');
                if (ackForm) {
                    ackForm.remove();
                } else {
                    ackButton.remove();
                }
                messages = messages.map(function (message) {
                    if (String(message.id || '') !== String(messageId || '')) {
                        return message;
                    }
                    var nextMessage = Object.assign({}, message);
                    nextMessage.ack_status = 'acknowledged';
                    return nextMessage;
                });
                syncLoop();
                if (response && response.pending_count === 0 && window.Vigilance && typeof window.Vigilance.stopMessageLoop === 'function') {
                    window.Vigilance.stopMessageLoop();
                }
            }).catch(function () {
                ackButton.disabled = false;
            });
            return;
        }

        var liveButton = event.target.closest('[data-live-call-target]');
        if (!liveButton) {
            return;
        }

        event.preventDefault();
        if (!connected || !callController) {
            notify('Appel live indisponible', 'Le canal audio n est pas encore connecte.');
            postCallRequest({
                target_role: liveButton.getAttribute('data-live-call-role') || 'admin',
                target_id: liveButton.getAttribute('data-live-call-target') || 'control-center',
                target_name: liveButton.getAttribute('data-live-call-name') || 'Centre OPS VIGILANCE',
                client_id: String(config.activeClientId || ''),
                client_name: String(config.activeClientName || ''),
                notes: 'Tentative d appel live alors que le canal audio n etait pas encore connecte.',
                channel: 'Portail agent live',
                status: 'pending',
                context_type: 'portal_live_call',
                context_id: String(config.agentId || '')
            }).then(function () {
                refreshFeed();
            });
            return;
        }

        callController.startOutgoingCall({
            id: liveButton.getAttribute('data-live-call-target') || 'control-center',
            name: liveButton.getAttribute('data-live-call-name') || 'Centre OPS VIGILANCE'
        });
    });

    syncLoop();
    lastSignature = signatureFromPayload({ messages: messages, calls: [], interventions: [] });
    refreshFeed();
    connect();

    window.setInterval(function () {
        if (!document.hidden) {
            refreshFeed();
        }
    }, 12000);

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            refreshFeed();
        }
    });
})();

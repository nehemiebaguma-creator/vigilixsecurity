(function () {
    'use strict';

    var config = window.VigilanceClientPortalConfig || null;
    var Portal = window.VigilancePortal || null;
    if (!config || !Portal) {
        return;
    }

    var context = {
        role: 'client',
        id: String(config.clientId || config.id || '').trim(),
        client_id: String(config.clientId || config.id || '').trim()
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
        var currentAlerts = Array.isArray(payload.alerts) ? payload.alerts : [];
        return currentMessages.slice(0, 12).map(function (message) {
            return String(message.id || '') + ':' + String(message.ack_status || '') + ':' + String(message.updated_at || message.created_at || '');
        }).join('|')
            + '|c:' + currentCalls.map(function (call) {
                return String(call.id || '') + ':' + String(call.status || '');
            }).join(',')
            + '|a:' + currentAlerts.map(function (alert) {
                return String(alert.id || '') + ':' + String(alert.status || '');
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
        role: 'client',
        label: String(config.clientName || 'Client VIGILANCE'),
        sendJson: sendJson,
        onOutgoingInvite: function (payload) {
            postCallRequest({
                target_role: 'admin',
                target_id: 'control-center',
                target_name: 'Centre OPS VIGILANCE',
                client_id: context.client_id,
                client_name: String(config.clientName || 'Client VIGILANCE'),
                live_call_id: String(payload.call_id || ''),
                notes: 'Demande d appel live declenchee depuis le portail client.',
                channel: 'Portail client live',
                status: 'ringing'
            }).then(function (response) {
                if (response && response.call && response.call.id) {
                    callRequestsByLiveId[String(payload.call_id || '')] = String(response.call.id || '');
                }
            });
        },
        onIncomingInvite: function (payload) {
            notify('Appel entrant VIGILANCE', String(payload.target_label || 'Un portail VIGILANCE') + ' demande une liaison audio.');
        },
        onEnded: function (payload) {
            updateCallRequestStatus(payload.call_id || '', payload.reason === 'declined' ? 'declined' : 'ended', 'Appel live termine.');
            refreshFeed();
        }
    });

    function joinChannel() {
        if (ws && ws.readyState === 1) {
            ws.send(JSON.stringify({
                type: 'join',
                channel: 'alpha',
                name: String(config.clientName || 'Client VIGILANCE'),
                role: 'client',
                user_id: context.client_id,
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
            notify('Nouveau message VIGILANCE', String((message.data && message.data.subject) || 'Message centre') + ' • ' + String((message.data && message.data.message) || ''));
            messages = messages.concat([message.data]);
            syncLoop();
            refreshFeed();
            return;
        }

        if (
            Portal.matchesRealtimeChannel(message.channel, 'vigilance:communications:acknowledged')
            || Portal.matchesRealtimeChannel(message.channel, 'vigilance:calls:new')
            || Portal.matchesRealtimeChannel(message.channel, 'vigilance:calls:updated')
            || Portal.matchesRealtimeChannel(message.channel, 'vigilance:alerts:new')
            || Portal.matchesRealtimeChannel(message.channel, 'vigilance:alerts:updated')
        ) {
            refreshFeed();
        }
    }

    document.addEventListener('click', function (event) {
        var ackButton = event.target.closest('[data-client-ack]');
        if (ackButton) {
            event.preventDefault();
            if (ackButton.disabled) {
                return;
            }

            ackButton.disabled = true;
            Portal.acknowledgeMessage(config.ackUrl, ackButton.getAttribute('data-client-ack'), 'Lecture confirmee depuis le portail client.').then(function (response) {
                var messageId = ackButton.getAttribute('data-client-ack');
                var bubble = ackButton.closest('[data-message-id]');
                if (bubble) {
                    bubble.setAttribute('data-ack-status', 'acknowledged');
                    var badge = bubble.querySelector('[data-ack-badge]');
                    if (badge) {
                        badge.textContent = 'ACK confirme';
                        badge.style.borderColor = 'rgba(89,216,160,.22)';
                        badge.style.background = 'rgba(10,36,29,.78)';
                        badge.style.color = '#bff5dc';
                    }
                }
                ackButton.remove();
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
                client_id: context.client_id,
                client_name: String(config.clientName || 'Client VIGILANCE'),
                notes: 'Tentative d appel live alors que le canal audio n etait pas encore connecte.',
                channel: 'Portail client live',
                status: 'pending'
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
    lastSignature = signatureFromPayload({ messages: messages, calls: [], alerts: [] });
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

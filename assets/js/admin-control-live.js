(function () {
    'use strict';

    var config = window.VigilanceAdminPortalConfig || null;
    var Portal = window.VigilancePortal || null;
    if (!config || !Portal) {
        return;
    }

    var context = {
        role: 'admin',
        id: String(config.userId || 'control-center').trim()
    };
    var messages = Array.isArray(config.initialMessages) ? config.initialMessages.slice() : [];
    var lastSignature = '';
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
        return currentMessages.slice(0, 16).map(function (message) {
            return String(message.id || '') + ':' + String(message.ack_status || '') + ':' + String(message.updated_at || message.created_at || '');
        }).join('|')
            + '|c:' + currentCalls.slice(0, 10).map(function (call) {
                return String(call.id || '') + ':' + String(call.status || '') + ':' + String(call.updated_at || call.created_at || '');
            }).join(',')
            + '|a:' + currentAlerts.slice(0, 10).map(function (alert) {
                return String(alert.id || '') + ':' + String(alert.status || '') + ':' + String(alert.updated_at || alert.created_at || '');
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

    function emitPortalState() {
        if (!document || typeof document.dispatchEvent !== 'function' || typeof window.CustomEvent !== 'function') {
            return;
        }
        try {
            document.dispatchEvent(new CustomEvent('vigilance:admin-portal-state', {
                detail: {
                    connected: !!connected,
                    wsPort: Number(config.wsPort || 8765),
                    wsUrl: String(wsCandidates[wsCandidateIndex % wsCandidates.length] || '')
                }
            }));
        } catch (error) {
            // Ignore DOM event dispatch failures.
        }
    }

    function refreshUi() {
        var feed = window.VigilanceControlCenterFeed || window.VigilixControlCenterFeed;
        if (feed && typeof feed.refresh === 'function') {
            feed.refresh();
        }
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
            messages = Array.isArray(data.messages) ? data.messages.slice() : [];
            rememberCallRequests(data.calls);
            syncLoop();
            lastSignature = signatureFromPayload(data);
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
        role: 'admin',
        label: String(config.userName || 'Centre OPS VIGILANCE'),
        sendJson: sendJson,
        onOutgoingInvite: function (payload) {
            postCallRequest({
                target_role: payload.target_role || 'client',
                target_id: String(payload.target_id || ''),
                target_name: String(payload.target_label || ''),
                client_id: String(payload.client_id || ''),
                client_name: String(payload.client_name || ''),
                live_call_id: String(payload.call_id || ''),
                notes: 'Appel live declenche depuis le centre OPS.',
                channel: 'Centre OPS live',
                status: 'ringing',
                context_type: 'portal_live_call',
                context_id: String(config.userId || 'control-center')
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
            updateCallRequestStatus(payload.call_id || '', payload.reason === 'declined' ? 'declined' : 'ended', 'Appel live termine depuis le centre.');
            refreshFeed().then(refreshUi);
        }
    });

    function joinChannel() {
        if (ws && ws.readyState === 1) {
            ws.send(JSON.stringify({
                type: 'join',
                channel: 'alpha',
                name: String(config.userName || 'Centre OPS VIGILANCE'),
                role: 'admin',
                user_id: String(config.userId || 'control-center'),
                client_id: ''
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
            connected = false;
            emitPortalState();
            scheduleReconnect(true);
            return;
        }

        ws.onopen = function () {
            opened = true;
            connected = true;
            joinChannel();
            emitPortalState();
        };
        ws.onclose = function () {
            connected = false;
            ws = null;
            emitPortalState();
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
            notify('Message VIGILANCE', String((message.data && message.data.subject) || 'Nouveau message') + ' • ' + String((message.data && message.data.message) || ''));
        }

        if (
            Portal.matchesRealtimeChannel(message.channel, 'vigilance:calls:new')
            || Portal.matchesRealtimeChannel(message.channel, 'vigilance:calls:updated')
            || Portal.matchesRealtimeChannel(message.channel, 'vigilance:communications:new')
            || Portal.matchesRealtimeChannel(message.channel, 'vigilance:communications:acknowledged')
            || Portal.matchesRealtimeChannel(message.channel, 'vigilance:alerts:new')
            || Portal.matchesRealtimeChannel(message.channel, 'vigilance:alerts:updated')
            || Portal.matchesRealtimeChannel(message.channel, 'vigilance:interventions:assigned')
            || Portal.matchesRealtimeChannel(message.channel, 'vigilance:interventions:updated')
            || Portal.matchesRealtimeChannel(message.channel, 'vigilance:interventions:closed')
        ) {
            refreshFeed().then(refreshUi);
        }
    }

    document.addEventListener('click', function (event) {
        var ackButton = event.target.closest('[data-admin-ack]');
        if (ackButton) {
            event.preventDefault();
            if (ackButton.disabled) {
                return;
            }

            ackButton.disabled = true;
            Portal.acknowledgeMessage(config.ackUrl, ackButton.getAttribute('data-admin-ack'), 'Lecture confirmee depuis le centre OPS.').then(function () {
                refreshFeed().then(refreshUi);
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
            notify('Appel live indisponible', 'Le canal audio du centre n est pas encore connecte.');
            return;
        }

        var targetId = liveButton.getAttribute('data-live-call-target') || '';
        if (!targetId) {
            return;
        }

        callController.startOutgoingCall({
            id: targetId,
            name: liveButton.getAttribute('data-live-call-name') || targetId,
            target_role: liveButton.getAttribute('data-live-call-role') || 'client',
            client_id: liveButton.getAttribute('data-client-id') || '',
            client_name: liveButton.getAttribute('data-client-name') || ''
        });
    });

    syncLoop();
    lastSignature = signatureFromPayload({ messages: messages, calls: [], alerts: [] });
    emitPortalState();
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

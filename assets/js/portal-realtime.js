window.VigilancePortal = window.VigilancePortal || {};

(function () {
    'use strict';

    var Portal = window.VigilancePortal;

    function esc(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function truthyFlag(value) {
        var normalized = String(value || '').toLowerCase();
        return value === true || value === 1 || normalized === '1' || normalized === 'true' || normalized === 'yes' || normalized === 'oui';
    }

    function runtime() {
        return window.Vigilance || window.Vigilix || {};
    }

    function isBackofficeRole(role) {
        role = String(role || '').toLowerCase().trim();
        return ['admin', 'supervisor', 'operator', 'user'].indexOf(role) !== -1;
    }

    function normalizeContext(context) {
        return {
            role: String((context && context.role) || '').toLowerCase().trim(),
            id: String((context && context.id) || '').trim(),
            client_id: String((context && (context.client_id || context.id)) || '').trim(),
            agent_id: String((context && (context.agent_id || context.id)) || '').trim()
        };
    }

    function isInboundMessageForContext(message, context) {
        if (!message || typeof message !== 'object') {
            return false;
        }

        var scope = normalizeContext(context);
        var fromRole = String(message.from_role || '').toLowerCase();
        var fromId = String(message.from_id || '').trim();
        var toRole = String(message.to_role || '').toLowerCase();
        var toId = String(message.to_id || '').trim();
        var messageClientId = String(message.client_id || '').trim();
        var messageAgentId = String(message.agent_id || '').trim();

        if (fromRole === scope.role && fromId !== '' && fromId === scope.id) {
            return false;
        }

        if (scope.role === 'client') {
            return toRole === 'client' && (toId === scope.client_id || messageClientId === scope.client_id);
        }

        if (scope.role === 'agent') {
            return (toRole === 'agent' && (toId === '' || toId === scope.agent_id)) || messageAgentId === scope.agent_id;
        }

        if (isBackofficeRole(scope.role)) {
            return toRole === 'admin' || toRole === scope.role;
        }

        return toRole === scope.role && (toId === '' || toId === scope.id);
    }

    function messageNeedsAck(message, context) {
        if (!message || typeof message !== 'object') {
            return false;
        }

        if (!isInboundMessageForContext(message, context)) {
            return false;
        }

        return truthyFlag(message.requires_ack) && String(message.ack_status || 'pending').toLowerCase() !== 'acknowledged';
    }

    function pendingAckMessages(messages, context) {
        if (!Array.isArray(messages)) {
            return [];
        }

        return messages.filter(function (message) {
            return messageNeedsAck(message, context);
        });
    }

    function syncMessageLoop(messages, context) {
        var pending = pendingAckMessages(messages, context);
        if (pending.length > 0) {
            if (typeof runtime().startMessageLoop === 'function') {
                runtime().startMessageLoop();
            }
        } else if (typeof runtime().stopMessageLoop === 'function') {
            runtime().stopMessageLoop();
        }

        return pending;
    }

    function matchesRealtimeChannel(actual, expected) {
        var current = String(actual || '').toLowerCase().trim();
        var target = String(expected || '').toLowerCase().trim();
        if (!current || !target) {
            return false;
        }
        if (current === target) {
            return true;
        }
        if (target.indexOf('vigilance:') === 0) {
            return current === ('vigilix:' + target.slice('vigilance:'.length));
        }
        if (target.indexOf('vigilix:') === 0) {
            return current === ('vigilance:' + target.slice('vigilix:'.length));
        }
        return false;
    }

    function pushUnique(list, value) {
        value = String(value || '').trim();
        if (!value || list.indexOf(value) !== -1) {
            return;
        }
        list.push(value);
    }

    function buildWsCandidates(port) {
        var hosts = [];
        var urls = [];
        var securePage = window.location.protocol === 'https:';
        var wsPort = parseInt(port, 10) || 8765;

        pushUnique(hosts, window.location.hostname || '');
        pushUnique(hosts, '127.0.0.1');
        pushUnique(hosts, 'localhost');

        hosts.forEach(function (host) {
            if (securePage) {
                pushUnique(urls, 'wss://' + host + ':' + wsPort);
            }
            pushUnique(urls, 'ws://' + host + ':' + wsPort);
        });

        return urls;
    }

    function acknowledgeMessage(url, messageId, note) {
        if (!url || !messageId || typeof fetch !== 'function') {
            return Promise.reject(new Error('ACK indisponible'));
        }

        var form = new FormData();
        form.append('message_id', String(messageId));
        if (note) {
            form.append('note', String(note));
        }

        return fetch(url, {
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
                    throw new Error(data && data.message ? data.message : 'Accuse impossible');
                }
                return data;
            });
        });
    }

    function injectCallStyles() {
        if (document.getElementById('vg-portal-call-styles')) {
            return;
        }

        var style = document.createElement('style');
        style.id = 'vg-portal-call-styles';
        style.textContent = ''
            + '.vg-portal-call{position:fixed;right:24px;bottom:24px;z-index:10020;width:min(360px,calc(100vw - 32px));display:none;flex-direction:column;border-radius:24px;overflow:hidden;'
            + 'background:linear-gradient(180deg,rgba(5,14,28,.98),rgba(2,8,18,.98));border:1px solid rgba(89,170,255,.2);box-shadow:0 28px 64px rgba(0,0,0,.45),0 0 32px rgba(0,128,208,.08);color:#e9f4ff}'
            + '.vg-portal-call.is-visible{display:flex}'
            + '.vg-portal-call__head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 18px;border-bottom:1px solid rgba(89,170,255,.12);background:rgba(89,170,255,.05)}'
            + '.vg-portal-call__kicker{font-size:.66rem;letter-spacing:.18em;text-transform:uppercase;color:#7db6ef}'
            + '.vg-portal-call__title{margin-top:6px;font-size:1rem;font-weight:700;color:#f4fbff}'
            + '.vg-portal-call__state{display:inline-flex;align-items:center;min-height:28px;padding:0 10px;border-radius:999px;border:1px solid rgba(89,216,160,.2);background:rgba(6,22,18,.78);color:#bff5dc;font-size:.68rem;letter-spacing:.08em}'
            + '.vg-portal-call__state[data-tone="ringing"]{border-color:rgba(255,191,102,.28);background:rgba(42,24,4,.84);color:#ffd58e}'
            + '.vg-portal-call__state[data-tone="error"]{border-color:rgba(255,92,113,.26);background:rgba(38,9,14,.82);color:#ffb6c0}'
            + '.vg-portal-call__body{padding:18px;display:grid;gap:12px}'
            + '.vg-portal-call__target{display:grid;gap:6px}'
            + '.vg-portal-call__target strong{font-size:1.18rem;color:#f4fbff;line-height:1.1}'
            + '.vg-portal-call__target span{color:#96b7d8;font-size:.82rem;line-height:1.5}'
            + '.vg-portal-call__actions{display:flex;flex-wrap:wrap;gap:10px}'
            + '.vg-portal-call__btn{flex:1 1 0;min-width:104px;display:inline-flex;align-items:center;justify-content:center;padding:12px 14px;border-radius:14px;border:1px solid rgba(89,170,255,.18);background:linear-gradient(180deg,rgba(10,24,42,.96),rgba(5,14,24,.96));color:#f4fbff;font:inherit;font-size:.84rem;font-weight:700;cursor:pointer}'
            + '.vg-portal-call__btn[hidden]{display:none!important}'
            + '.vg-portal-call__btn--ok{background:linear-gradient(180deg,#0aa35c,#047a44);border-color:rgba(4,122,68,.38)}'
            + '.vg-portal-call__btn--warn{background:linear-gradient(180deg,#ff8c2e,#d85b00);border-color:rgba(216,91,0,.38)}'
            + '.vg-portal-call__btn--danger{background:linear-gradient(180deg,#ff5d66,#cf1731);border-color:rgba(207,23,49,.38)}'
            + '.vg-portal-call__hint{font-size:.76rem;line-height:1.58;color:#8fb0d1}'
            + 'html[data-theme="light"] .vg-portal-call{background:linear-gradient(180deg,#ffffff,#edf4fb);border-color:rgba(38,67,103,.18);box-shadow:0 24px 48px rgba(31,45,66,.14);color:#172033}'
            + 'html[data-theme="light"] .vg-portal-call__head{background:rgba(0,111,201,.05);border-color:rgba(38,67,103,.12)}'
            + 'html[data-theme="light"] .vg-portal-call__kicker{color:#005fa8}'
            + 'html[data-theme="light"] .vg-portal-call__title,html[data-theme="light"] .vg-portal-call__target strong{color:#172033}'
            + 'html[data-theme="light"] .vg-portal-call__target span,html[data-theme="light"] .vg-portal-call__hint{color:#4b5f78}'
            + 'html[data-theme="light"] .vg-portal-call__btn{background:linear-gradient(180deg,#ffffff,#edf4fb);border-color:rgba(38,67,103,.18);color:#172033}'
            + 'html[data-theme="light"] .vg-portal-call__btn--ok{background:linear-gradient(180deg,#12b76a,#039855);color:#ffffff}'
            + 'html[data-theme="light"] .vg-portal-call__btn--warn{background:linear-gradient(180deg,#ffb84c,#f79009);color:#172033}'
            + 'html[data-theme="light"] .vg-portal-call__btn--danger{background:linear-gradient(180deg,#ff5d66,#cf1731);color:#ffffff}';
        document.head.appendChild(style);
    }

    function createCallController(config) {
        injectCallStyles();

        var settings = config || {};
        var stunServers = Array.isArray(settings.iceServers) && settings.iceServers.length
            ? settings.iceServers
            : [{ urls: 'stun:stun.l.google.com:19302' }];
        var ui = document.createElement('section');
        ui.className = 'vg-portal-call';
        ui.innerHTML = ''
            + '<div class="vg-portal-call__head">'
            + '  <div><div class="vg-portal-call__kicker">Canal audio live</div><div class="vg-portal-call__title">Connexion portail</div></div>'
            + '  <span class="vg-portal-call__state" data-call-state data-tone="ringing">En attente</span>'
            + '</div>'
            + '<div class="vg-portal-call__body">'
            + '  <div class="vg-portal-call__target"><strong>Centre OPS</strong><span>Le portail prépare l’audio bidirectionnel sécurisé.</span></div>'
            + '  <div class="vg-portal-call__actions">'
            + '    <button type="button" class="vg-portal-call__btn vg-portal-call__btn--ok" data-call-accept hidden>Accepter</button>'
            + '    <button type="button" class="vg-portal-call__btn vg-portal-call__btn--warn" data-call-decline hidden>Refuser</button>'
            + '    <button type="button" class="vg-portal-call__btn" data-call-mute hidden>Couper micro</button>'
            + '    <button type="button" class="vg-portal-call__btn vg-portal-call__btn--danger" data-call-end hidden>Terminer</button>'
            + '  </div>'
            + '  <div class="vg-portal-call__hint" data-call-hint>Le navigateur demandera l’accès microphone au moment de la connexion.</div>'
            + '  <audio autoplay playsinline data-call-remote></audio>'
            + '</div>';
        document.body.appendChild(ui);

        var stateNode = ui.querySelector('[data-call-state]') || ui.querySelector('.vg-portal-call__state');
        var targetNode = ui.querySelector('.vg-portal-call__target strong');
        var copyNode = ui.querySelector('.vg-portal-call__target span');
        var hintNode = ui.querySelector('[data-call-hint]');
        var remoteAudio = ui.querySelector('[data-call-remote]');
        var acceptBtn = ui.querySelector('[data-call-accept]');
        var declineBtn = ui.querySelector('[data-call-decline]');
        var muteBtn = ui.querySelector('[data-call-mute]');
        var endBtn = ui.querySelector('[data-call-end]');

        var state = {
            currentCallId: '',
            direction: '',
            targetId: '',
            targetLabel: '',
            pendingOffer: null,
            pendingIce: [],
            incomingPayload: null,
            localStream: null,
            remoteStream: null,
            peerConnection: null,
            micMuted: false,
            stage: 'idle'
        };

        function emit(eventName, payload) {
            if (typeof settings[eventName] === 'function') {
                settings[eventName](payload || {});
            }
        }

        function sendJson(message) {
            if (typeof settings.sendJson !== 'function') {
                return false;
            }
            return settings.sendJson(message) !== false;
        }

        function showPanel(visible) {
            ui.classList.toggle('is-visible', !!visible);
        }

        function setTone(tone) {
            stateNode.setAttribute('data-tone', tone || 'ringing');
        }

        function updateButtons(mode) {
            acceptBtn.hidden = mode !== 'incoming';
            declineBtn.hidden = !(mode === 'incoming' || mode === 'outgoing');
            muteBtn.hidden = mode !== 'live';
            endBtn.hidden = !(mode === 'connecting' || mode === 'live' || mode === 'outgoing');
        }

        function updateView(payload) {
            var targetLabel = String((payload && payload.targetLabel) || state.targetLabel || 'Centre OPS');
            var statusLabel = String((payload && payload.statusLabel) || 'En attente');
            var bodyLabel = String((payload && payload.bodyLabel) || 'Le portail prépare l’audio bidirectionnel sécurisé.');
            var hintLabel = String((payload && payload.hintLabel) || 'Le navigateur demandera l’accès microphone au moment de la connexion.');
            var tone = String((payload && payload.tone) || 'ringing');
            var mode = String((payload && payload.mode) || 'outgoing');

            targetNode.textContent = targetLabel;
            copyNode.textContent = bodyLabel;
            hintNode.textContent = hintLabel;
            stateNode.textContent = statusLabel;
            setTone(tone);
            updateButtons(mode);
            showPanel(true);
        }

        function stopCallTone() {
            var portalRuntime = runtime();
            if (portalRuntime && typeof portalRuntime.stopCallLoop === 'function') {
                portalRuntime.stopCallLoop();
            }
        }

        function startCallTone() {
            var portalRuntime = runtime();
            if (portalRuntime && typeof portalRuntime.startCallLoop === 'function') {
                portalRuntime.startCallLoop();
            }
        }

        function cleanupPeer() {
            if (state.peerConnection) {
                try {
                    state.peerConnection.onicecandidate = null;
                    state.peerConnection.ontrack = null;
                    state.peerConnection.onconnectionstatechange = null;
                    state.peerConnection.close();
                } catch (error) {
                    // Ignore close issues.
                }
            }

            state.peerConnection = null;
            state.pendingIce = [];
            state.pendingOffer = null;

            if (state.localStream) {
                state.localStream.getTracks().forEach(function (track) {
                    track.stop();
                });
            }

            state.localStream = null;
            state.remoteStream = null;
            remoteAudio.srcObject = null;
            state.micMuted = false;
            muteBtn.textContent = 'Couper micro';
        }

        function resetState() {
            cleanupPeer();
            state.currentCallId = '';
            state.direction = '';
            state.targetId = '';
            state.targetLabel = '';
            state.incomingPayload = null;
            state.stage = 'idle';
        }

        function hidePanelLater(delayMs) {
            window.setTimeout(function () {
                if (state.stage === 'idle') {
                    showPanel(false);
                }
            }, delayMs || 1400);
        }

        function endCall(reason, options) {
            var details = options || {};
            stopCallTone();
            if (details.sendSignal !== false && state.currentCallId && state.targetId) {
                sendJson({
                    type: 'call_end',
                    call_id: state.currentCallId,
                    target_id: state.targetId,
                    reason: String(reason || 'ended')
                });
            }

            cleanupPeer();
            state.stage = 'idle';
            updateView({
                targetLabel: state.targetLabel || 'Canal audio',
                statusLabel: String(details.statusLabel || 'Termine'),
                bodyLabel: String(details.bodyLabel || 'La connexion audio a ete fermee.'),
                hintLabel: String(details.hintLabel || 'Le canal reste disponible pour un nouvel appel.'),
                tone: details.tone || 'error',
                mode: 'ended'
            });
            emit('onEnded', { reason: reason || 'ended', call_id: state.currentCallId, target_id: state.targetId });
            resetState();
            hidePanelLater(details.hideAfter || 1600);
        }

        function ensureLocalStream() {
            if (state.localStream) {
                return Promise.resolve(state.localStream);
            }

            if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
                return Promise.reject(new Error('Microphone indisponible'));
            }

            return navigator.mediaDevices.getUserMedia({ audio: true, video: false }).then(function (stream) {
                state.localStream = stream;
                return stream;
            });
        }

        function createPeerConnection() {
            if (state.peerConnection) {
                return state.peerConnection;
            }

            state.remoteStream = new MediaStream();
            remoteAudio.srcObject = state.remoteStream;

            var peerConnection = new RTCPeerConnection({ iceServers: stunServers });
            peerConnection.onicecandidate = function (event) {
                if (!event.candidate || !state.currentCallId || !state.targetId) {
                    return;
                }

                sendJson({
                    type: 'webrtc_ice',
                    call_id: state.currentCallId,
                    target_id: state.targetId,
                    candidate: event.candidate
                });
            };
            peerConnection.ontrack = function (event) {
                var stream = event.streams && event.streams[0] ? event.streams[0] : null;
                if (stream) {
                    stream.getTracks().forEach(function (track) {
                        state.remoteStream.addTrack(track);
                    });
                }
                state.stage = 'live';
                stopCallTone();
                updateView({
                    targetLabel: state.targetLabel || 'Canal audio',
                    statusLabel: 'Connecte',
                    bodyLabel: 'Audio bidirectionnel actif entre les deux portails.',
                    hintLabel: 'Vous pouvez parler normalement. Coupez le micro ou terminez l’appel quand c’est fini.',
                    tone: 'ok',
                    mode: 'live'
                });
            };
            peerConnection.onconnectionstatechange = function () {
                var connectionState = String(peerConnection.connectionState || '').toLowerCase();
                if (connectionState === 'connected') {
                    state.stage = 'live';
                    stopCallTone();
                    updateView({
                        targetLabel: state.targetLabel || 'Canal audio',
                        statusLabel: 'Connecte',
                        bodyLabel: 'Audio bidirectionnel actif entre les deux portails.',
                        hintLabel: 'La communication reste ouverte jusqu’à fermeture manuelle.',
                        tone: 'ok',
                        mode: 'live'
                    });
                } else if (connectionState === 'failed' || connectionState === 'closed' || connectionState === 'disconnected') {
                    endCall(connectionState, {
                        sendSignal: false,
                        statusLabel: connectionState === 'failed' ? 'Echec audio' : 'Connexion fermee',
                        bodyLabel: 'Le canal audio n’est plus actif.',
                        tone: 'error',
                        hideAfter: 1800
                    });
                }
            };

            state.peerConnection = peerConnection;
            if (state.localStream) {
                state.localStream.getTracks().forEach(function (track) {
                    peerConnection.addTrack(track, state.localStream);
                });
            }
            return peerConnection;
        }

        function flushPendingIce() {
            if (!state.peerConnection || !state.peerConnection.remoteDescription) {
                return Promise.resolve();
            }

            var pending = state.pendingIce.slice();
            state.pendingIce = [];
            return Promise.all(pending.map(function (candidate) {
                return state.peerConnection.addIceCandidate(new RTCIceCandidate(candidate)).catch(function () {
                    return null;
                });
            }));
        }

        function acceptIncomingCall() {
            if (!state.incomingPayload) {
                return;
            }

            stopCallTone();
            state.stage = 'connecting';
            updateView({
                targetLabel: state.targetLabel || 'Appel entrant',
                statusLabel: 'Connexion...',
                bodyLabel: 'Le micro est en cours d’ouverture et l’audio est en train de se négocier.',
                hintLabel: 'Gardez cette fenêtre ouverte pendant la mise en relation.',
                tone: 'ringing',
                mode: 'connecting'
            });

            ensureLocalStream().then(function () {
                createPeerConnection();
                sendJson({
                    type: 'call_accept',
                    call_id: state.currentCallId,
                    target_id: state.targetId
                });

                if (state.pendingOffer) {
                    applyOffer(state.pendingOffer);
                }
            }).catch(function (error) {
                sendJson({
                    type: 'call_decline',
                    call_id: state.currentCallId,
                    target_id: state.targetId,
                    reason: 'microphone_refused'
                });
                endCall('microphone_refused', {
                    sendSignal: false,
                    statusLabel: 'Micro refuse',
                    bodyLabel: error && error.message ? error.message : 'Le navigateur a refuse le microphone.',
                    tone: 'error'
                });
            });
        }

        function declineIncomingCall() {
            if (!state.currentCallId || !state.targetId) {
                resetState();
                showPanel(false);
                return;
            }

            stopCallTone();
            sendJson({
                type: 'call_decline',
                call_id: state.currentCallId,
                target_id: state.targetId,
                reason: 'declined'
            });
            endCall('declined', {
                sendSignal: false,
                statusLabel: 'Refuse',
                bodyLabel: 'L’appel entrant a ete refuse.',
                tone: 'error'
            });
        }

        function toggleMute() {
            if (!state.localStream) {
                return;
            }

            state.micMuted = !state.micMuted;
            state.localStream.getAudioTracks().forEach(function (track) {
                track.enabled = !state.micMuted;
            });
            muteBtn.textContent = state.micMuted ? 'Rendre le micro' : 'Couper micro';
        }

        function applyOffer(description) {
            if (!description) {
                return Promise.resolve();
            }

            state.pendingOffer = description;

            return ensureLocalStream().then(function () {
                var peerConnection = createPeerConnection();
                return peerConnection.setRemoteDescription(new RTCSessionDescription(description)).then(function () {
                    return flushPendingIce();
                }).then(function () {
                    return peerConnection.createAnswer();
                }).then(function (answer) {
                    return peerConnection.setLocalDescription(answer).then(function () {
                        sendJson({
                            type: 'webrtc_answer',
                            call_id: state.currentCallId,
                            target_id: state.targetId,
                            description: peerConnection.localDescription
                        });
                    });
                }).then(function () {
                    state.stage = 'connecting';
                    updateView({
                        targetLabel: state.targetLabel || 'Canal audio',
                        statusLabel: 'Connexion...',
                        bodyLabel: 'Le canal audio est en cours de stabilisation.',
                        hintLabel: 'Patientez quelques secondes le temps que les deux portails se synchronisent.',
                        tone: 'ringing',
                        mode: 'connecting'
                    });
                });
            }).catch(function (error) {
                endCall('offer_failed', {
                    sendSignal: false,
                    statusLabel: 'Connexion echouee',
                    bodyLabel: error && error.message ? error.message : 'Impossible de finaliser l’audio entrant.',
                    tone: 'error'
                });
            });
        }

        function startOutgoingCall(target) {
            if (!target || !target.id) {
                return false;
            }

            if (state.currentCallId) {
                endCall('replaced', { sendSignal: false, hideAfter: 0 });
            }

            state.currentCallId = 'live-' + Date.now() + '-' + Math.random().toString(16).slice(2, 8);
            state.direction = 'outgoing';
            state.targetId = String(target.id || '').trim();
            state.targetLabel = String(target.name || target.label || state.targetId);
            state.stage = 'outgoing';
            state.pendingOffer = null;
            state.pendingIce = [];
            state.incomingPayload = null;

            updateView({
                targetLabel: state.targetLabel,
                statusLabel: 'Sonnerie...',
                bodyLabel: 'Invitation audio transmise. Le portail distant doit accepter avant l’ouverture du micro.',
                hintLabel: 'L’appel reste en attente tant que la personne ne décroche pas.',
                tone: 'ringing',
                mode: 'outgoing'
            });
            startCallTone();

            emit('onOutgoingInvite', {
                call_id: state.currentCallId,
                target_id: state.targetId,
                target_label: state.targetLabel,
                target_role: String((target && (target.role || target.target_role)) || ''),
                client_id: String((target && target.client_id) || ''),
                client_name: String((target && target.client_name) || '')
            });

            sendJson({
                type: 'call_invite',
                call_id: state.currentCallId,
                target_id: state.targetId,
                target_name: state.targetLabel,
                mode: 'audio',
                role: settings.role || '',
                label: settings.label || ''
            });

            return true;
        }

        function handleIncomingInvite(message) {
            var senderId = String(message.sender_id || '').trim();
            if (!senderId) {
                return;
            }

            if (state.currentCallId && state.currentCallId !== String(message.call_id || '')) {
                sendJson({
                    type: 'call_decline',
                    call_id: message.call_id,
                    target_id: senderId,
                    reason: 'busy'
                });
                return;
            }

            state.currentCallId = String(message.call_id || '');
            state.direction = 'incoming';
            state.targetId = senderId;
            state.targetLabel = String(message.sender || message.label || message.sender_label || senderId);
            state.incomingPayload = message;
            state.stage = 'incoming';
            state.pendingOffer = null;
            state.pendingIce = [];

            updateView({
                targetLabel: state.targetLabel,
                statusLabel: 'Appel entrant',
                bodyLabel: 'Un autre portail VIGILANCE demande une liaison audio directe.',
                hintLabel: 'Acceptez pour ouvrir un vrai appel audio bidirectionnel.',
                tone: 'ringing',
                mode: 'incoming'
            });
            startCallTone();
            emit('onIncomingInvite', {
                call_id: state.currentCallId,
                target_id: state.targetId,
                target_label: state.targetLabel
            });
        }

        function handleIncomingAccept(message) {
            if (String(message.call_id || '') !== state.currentCallId || state.direction !== 'outgoing') {
                return;
            }

            stopCallTone();
            state.stage = 'connecting';
            updateView({
                targetLabel: state.targetLabel,
                statusLabel: 'Connexion...',
                bodyLabel: 'Le portail distant a accepte. Ouverture du micro et negociation audio en cours.',
                hintLabel: 'Le micro sera active uniquement pour cet appel.',
                tone: 'ringing',
                mode: 'connecting'
            });

            ensureLocalStream().then(function () {
                var peerConnection = createPeerConnection();
                return peerConnection.createOffer({ offerToReceiveAudio: true }).then(function (offer) {
                    return peerConnection.setLocalDescription(offer).then(function () {
                        sendJson({
                            type: 'webrtc_offer',
                            call_id: state.currentCallId,
                            target_id: state.targetId,
                            description: peerConnection.localDescription
                        });
                    });
                });
            }).catch(function (error) {
                endCall('microphone_refused', {
                    sendSignal: true,
                    statusLabel: 'Micro refuse',
                    bodyLabel: error && error.message ? error.message : 'Impossible d’ouvrir le microphone.',
                    tone: 'error'
                });
            });
        }

        function handleIncomingAnswer(message) {
            if (!state.peerConnection || String(message.call_id || '') !== state.currentCallId || !message.description) {
                return;
            }

            state.peerConnection.setRemoteDescription(new RTCSessionDescription(message.description)).then(function () {
                return flushPendingIce();
            }).catch(function () {
                endCall('answer_failed', {
                    sendSignal: false,
                    statusLabel: 'Audio interrompu',
                    bodyLabel: 'La reponse audio distante n’a pas pu etre appliquee.',
                    tone: 'error'
                });
            });
        }

        function handleIncomingIce(message) {
            if (String(message.call_id || '') !== state.currentCallId || !message.candidate) {
                return;
            }

            if (!state.peerConnection || !state.peerConnection.remoteDescription) {
                state.pendingIce.push(message.candidate);
                return;
            }

            state.peerConnection.addIceCandidate(new RTCIceCandidate(message.candidate)).catch(function () {
                return null;
            });
        }

        function handleSocketMessage(message) {
            if (!message || typeof message !== 'object') {
                return false;
            }

            switch (String(message.type || '')) {
                case 'call_invite':
                    handleIncomingInvite(message);
                    return true;
                case 'call_accept':
                    handleIncomingAccept(message);
                    return true;
                case 'call_decline':
                    if (String(message.call_id || '') === state.currentCallId) {
                        endCall('declined', {
                            sendSignal: false,
                            statusLabel: message.reason === 'busy' ? 'Occupe' : 'Refuse',
                            bodyLabel: message.reason === 'busy'
                                ? 'Le portail distant est deja en communication.'
                                : 'Le portail distant a refuse l’appel.',
                            tone: 'error'
                        });
                    }
                    return true;
                case 'call_end':
                    if (String(message.call_id || '') === state.currentCallId) {
                        endCall('remote_end', {
                            sendSignal: false,
                            statusLabel: 'Termine',
                            bodyLabel: 'Le portail distant a ferme l’appel.',
                            tone: 'error'
                        });
                    }
                    return true;
                case 'webrtc_offer':
                    if (String(message.call_id || '') === state.currentCallId) {
                        applyOffer(message.description || null);
                    }
                    return true;
                case 'webrtc_answer':
                    handleIncomingAnswer(message);
                    return true;
                case 'webrtc_ice':
                    handleIncomingIce(message);
                    return true;
                default:
                    return false;
            }
        }

        acceptBtn.addEventListener('click', acceptIncomingCall);
        declineBtn.addEventListener('click', declineIncomingCall);
        muteBtn.addEventListener('click', toggleMute);
        endBtn.addEventListener('click', function () {
            endCall('manual_end', {
                sendSignal: true,
                statusLabel: 'Termine',
                bodyLabel: 'L’appel a ete coupe depuis ce portail.',
                tone: 'error'
            });
        });

        window.addEventListener('beforeunload', function () {
            if (state.currentCallId && state.targetId) {
                sendJson({
                    type: 'call_end',
                    call_id: state.currentCallId,
                    target_id: state.targetId,
                    reason: 'page_unload'
                });
            }
        });

        return {
            startOutgoingCall: startOutgoingCall,
            handleSocketMessage: handleSocketMessage,
            endCall: endCall,
            isBusy: function () {
                return !!state.currentCallId;
            }
        };
    }

    Portal.esc = esc;
    Portal.truthyFlag = truthyFlag;
    Portal.isBackofficeRole = isBackofficeRole;
    Portal.normalizeContext = normalizeContext;
    Portal.isInboundMessageForContext = isInboundMessageForContext;
    Portal.messageNeedsAck = messageNeedsAck;
    Portal.pendingAckMessages = pendingAckMessages;
    Portal.syncMessageLoop = syncMessageLoop;
    Portal.matchesRealtimeChannel = matchesRealtimeChannel;
    Portal.buildWsCandidates = buildWsCandidates;
    Portal.acknowledgeMessage = acknowledgeMessage;
    Portal.createCallController = createCallController;
})();

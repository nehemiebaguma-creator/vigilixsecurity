window.Vigilance = window.Vigilance || window.Vigilix || {};
window.Vigilix = window.Vigilance;

(function () {
    var sirenContext = null;
    var sirenTimer = null;
    var sirenOscillator = null;
    var sirenGain = null;
    var signalLoops = {};
    var lastAlertKey = '';
    var audioArmed = false;
    var audioUnlockBound = false;
    var pendingSirenRequest = null;
    var pendingSignalRequests = {};
    var audioUnlockEvents = ['pointerdown', 'click', 'keydown', 'touchstart'];

    function toggleBodyClass(className, active) {
        if (!document.body) {
            return;
        }
        document.body.classList.toggle(className, !!active);
    }

    function ensureAudio() {
        if (!window.AudioContext && !window.webkitAudioContext) {
            return null;
        }

        if (!sirenContext) {
            var Context = window.AudioContext || window.webkitAudioContext;
            sirenContext = new Context();
        }

        return sirenContext;
    }

    function flushPendingSiren() {
        if (pendingSirenRequest === null || sirenOscillator) {
            return;
        }

        var duration = pendingSirenRequest.duration;
        pendingSirenRequest = null;
        startSiren(duration);
    }

    function flushPendingSignalRequests() {
        Object.keys(pendingSignalRequests).forEach(function (name) {
            var request = pendingSignalRequests[name];
            if (!request) {
                return;
            }

            delete pendingSignalRequests[name];
            startSignalLoop(name, request);
        });
    }

    function armAudio() {
        var context = ensureAudio();
        if (!context) {
            return Promise.resolve(false);
        }

        if (context.state === 'running') {
            audioArmed = true;
            toggleBodyClass('siren-armed', true);
            flushPendingSiren();
            flushPendingSignalRequests();
            return Promise.resolve(true);
        }

        return context.resume().then(function () {
            audioArmed = true;
            toggleBodyClass('siren-armed', true);
            flushPendingSiren();
            flushPendingSignalRequests();
            return true;
        }).catch(function () {
            return false;
        });
    }

    function bindAudioUnlock() {
        if (audioUnlockBound) {
            return;
        }

        audioUnlockBound = true;

        function tryUnlock() {
            armAudio().then(function (ok) {
                if (!ok) {
                    return;
                }

                audioUnlockEvents.forEach(function (eventName) {
                    document.removeEventListener(eventName, tryUnlock, true);
                });
            });
        }

        audioUnlockEvents.forEach(function (eventName) {
            document.addEventListener(eventName, tryUnlock, true);
        });
    }

    function startSiren(duration) {
        var context = ensureAudio();
        if (!context || sirenOscillator) {
            return false;
        }

        if (context.state !== 'running') {
            pendingSirenRequest = {
                duration: typeof duration === 'number' && duration > 0 ? duration : null
            };
            armAudio();
            return false;
        }

        audioArmed = true;
        toggleBodyClass('siren-armed', true);
        sirenOscillator = context.createOscillator();
        sirenGain = context.createGain();
        sirenOscillator.type = 'sawtooth';
        sirenOscillator.connect(sirenGain);
        sirenGain.connect(context.destination);
        sirenGain.gain.value = 0.04;
        sirenOscillator.frequency.value = 520;
        sirenOscillator.start();

        var up = true;
        sirenTimer = setInterval(function () {
            if (!sirenOscillator) {
                return;
            }
            sirenOscillator.frequency.setTargetAtTime(up ? 920 : 420, context.currentTime, 0.12);
            up = !up;
        }, 520);

        if (typeof duration === 'number' && duration > 0) {
            window.setTimeout(stopSiren, duration);
        }

        toggleBodyClass('siren-active', true);
        return true;
    }

    function stopSiren() {
        pendingSirenRequest = null;
        if (sirenTimer) {
            clearInterval(sirenTimer);
            sirenTimer = null;
        }
        if (sirenOscillator) {
            sirenOscillator.stop();
            sirenOscillator.disconnect();
            sirenOscillator = null;
        }
        if (sirenGain) {
            sirenGain.disconnect();
            sirenGain = null;
        }
        toggleBodyClass('siren-active', false);
    }

    function createBurstTone(context, tone) {
        var startAt = context.currentTime + (tone.start || 0);
        var stopAt = context.currentTime + (tone.stop || 0.25);
        var oscillator = context.createOscillator();
        var gain = context.createGain();

        oscillator.type = tone.type || 'sine';
        oscillator.frequency.setValueAtTime(tone.frequency || 680, startAt);
        gain.gain.setValueAtTime(0.0001, startAt);
        gain.gain.exponentialRampToValueAtTime(tone.gain || 0.18, startAt + 0.015);
        gain.gain.exponentialRampToValueAtTime(0.0001, stopAt);
        oscillator.connect(gain);
        gain.connect(context.destination);
        oscillator.start(startAt);
        oscillator.stop(stopAt);

        return { oscillator: oscillator, gain: gain };
    }

    function playSignalBurst(pattern) {
        var context = ensureAudio();
        if (!context) {
            return false;
        }

        if (context.state !== 'running') {
            armAudio();
            return false;
        }

        (Array.isArray(pattern) ? pattern : []).forEach(function (tone) {
            createBurstTone(context, tone);
        });

        return true;
    }

    function startSignalLoop(name, options) {
        var context = ensureAudio();
        if (!context) {
            return false;
        }

        var normalizedName = String(name || '').trim();
        if (!normalizedName) {
            return false;
        }

        if (context.state !== 'running') {
            pendingSignalRequests[normalizedName] = options;
            armAudio();
            return false;
        }

        audioArmed = true;
        toggleBodyClass('siren-armed', true);

        if (signalLoops[normalizedName] && signalLoops[normalizedName].timer) {
            return true;
        }

        var pattern = Array.isArray(options && options.pattern) ? options.pattern : [];
        var intervalMs = Math.max(900, parseInt(options && options.interval, 10) || 2400);

        playSignalBurst(pattern);
        signalLoops[normalizedName] = {
            timer: window.setInterval(function () {
                playSignalBurst(pattern);
            }, intervalMs)
        };
        toggleBodyClass('signal-loop-active', true);
        toggleBodyClass('signal-loop-active-' + normalizedName, true);
        return true;
    }

    function stopSignalLoop(name) {
        var normalizedName = String(name || '').trim();
        if (!normalizedName) {
            return;
        }

        delete pendingSignalRequests[normalizedName];
        if (!signalLoops[normalizedName]) {
            return;
        }

        if (signalLoops[normalizedName].timer) {
            window.clearInterval(signalLoops[normalizedName].timer);
        }

        delete signalLoops[normalizedName];
        toggleBodyClass('signal-loop-active-' + normalizedName, false);
        if (Object.keys(signalLoops).length === 0) {
            toggleBodyClass('signal-loop-active', false);
        }
    }

    function startMessageLoop() {
        return startSignalLoop('message', {
            interval: 2800,
            pattern: [
                { frequency: 784, start: 0.00, stop: 0.16, type: 'triangle', gain: 0.12 },
                { frequency: 988, start: 0.24, stop: 0.42, type: 'triangle', gain: 0.14 }
            ]
        });
    }

    function stopMessageLoop() {
        stopSignalLoop('message');
    }

    function startCallLoop() {
        return startSignalLoop('call', {
            interval: 2100,
            pattern: [
                { frequency: 660, start: 0.00, stop: 0.22, type: 'sine', gain: 0.18 },
                { frequency: 880, start: 0.30, stop: 0.56, type: 'sine', gain: 0.2 },
                { frequency: 660, start: 0.92, stop: 1.16, type: 'sine', gain: 0.18 },
                { frequency: 880, start: 1.24, stop: 1.50, type: 'sine', gain: 0.2 }
            ]
        });
    }

    function stopCallLoop() {
        stopSignalLoop('call');
    }

    function badgeClass(status) {
        var normalized = (status || '').toLowerCase();
        if (normalized.indexOf('resol') !== -1 || normalized.indexOf('paye') !== -1 || normalized.indexOf('disponible') !== -1) {
            return 'badge-success';
        }
        if (normalized.indexOf('retard') !== -1 || normalized.indexOf('critique') !== -1 || normalized.indexOf('nouvelle') !== -1) {
            return 'badge-danger';
        }
        if (normalized.indexOf('attente') !== -1 || normalized.indexOf('analyse') !== -1 || normalized.indexOf('route') !== -1 || normalized.indexOf('mission') !== -1) {
            return 'badge-warning';
        }
        return 'badge-info';
    }

    function escapeHtml(value) {
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

    function alertRequiresSiren(alert) {
        if (!alert) {
            return false;
        }
        var message = String((alert.message || alert.detection_summary || alert.type || alert.type_alerte || '')).toLowerCase();
        return truthyFlag(alert.requires_siren)
            || truthyFlag(alert.sound_alert)
            || truthyFlag(alert.weapon_detected)
            || truthyFlag(alert.danger_detected)
            || message.indexOf('arme') !== -1
            || message.indexOf('weapon') !== -1
            || message.indexOf('gun') !== -1
            || message.indexOf('pistol') !== -1
            || message.indexOf('knife') !== -1
            || message.indexOf('couteau') !== -1;
    }

    function resolveAppRoot() {
        var path = String(window.location.pathname || '/');
        var segments = path.split('/').filter(Boolean);

        for (var i = 0; i < segments.length; i += 1) {
            var segment = String(segments[i] || '').toLowerCase();
            if (segment === 'vigilance' || segment === 'vigilix' || segment === 'viglix') {
                return '/' + segments.slice(0, i + 1).join('/');
            }
        }

        return '/vigilance';
    }

    function appUrl(path) {
        var root = resolveAppRoot().replace(/\/+$/, '');
        var cleanPath = String(path || '').replace(/^\/+/, '');
        return cleanPath ? root + '/' + cleanPath : root + '/';
    }

    function mapDataFromNode(node) {
        return {
            centerLat: parseFloat(node.dataset.centerLat || '-4.3250'),
            centerLng: parseFloat(node.dataset.centerLng || '15.3222'),
            clients: JSON.parse(node.dataset.clients || '[]'),
            alerts: JSON.parse(node.dataset.alerts || '[]'),
            agents: JSON.parse(node.dataset.agents || '[]')
        };
    }

    function drawMap(node) {
        if (!window.L) {
            node.innerHTML = "<div style=\"padding:24px;color:#c9d6e7;\">La bibliotheque cartographique n'a pas pu etre chargee.</div>";
            return;
        }

        if (node._leaflet_id) {
            return;
        }

        var payload = mapDataFromNode(node);
        var map = L.map(node, { zoomControl: true }).setView([payload.centerLat, payload.centerLng], 12);
        var streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);
        var satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19,
            attribution: 'Tiles &copy; Esri'
        });

        var layers = { avenue: streetLayer, satellite: satelliteLayer };
        map.vigilanceLayers = layers;
        map.vigilanceCurrentLayer = 'avenue';
        node.vigilanceMap = map;

        L.marker([payload.centerLat, payload.centerLng]).addTo(map)
            .bindPopup('<strong>Centre VIGILANCE</strong><br>Quartier general');

        payload.clients.forEach(function (client) {
            L.circleMarker([client.latitude, client.longitude], {
                radius: 8,
                color: '#39c7ff',
                fillColor: '#39c7ff',
                fillOpacity: 0.75
            }).addTo(map).bindPopup(
                '<strong>' + escapeHtml(client.full_name) + '</strong><br>' +
                escapeHtml(client.address + ', ' + client.commune) + '<br>' +
                'Abonnement: ' + escapeHtml(client.subscription_name || '')
            );
        });

        payload.alerts.forEach(function (alert) {
            var clientName = alert.client_name || 'Client';
            L.circleMarker([alert.latitude, alert.longitude], {
                radius: 10,
                color: '#ff4d63',
                fillColor: '#ff4d63',
                fillOpacity: 0.85
            }).addTo(map).bindPopup(
                '<strong>' + escapeHtml(alert.type) + '</strong><br>' +
                escapeHtml(clientName) + '<br>' +
                escapeHtml(alert.message || '') + '<br>' +
                'Statut: ' + escapeHtml(alert.status || '')
            );

            L.polyline([[payload.centerLat, payload.centerLng], [alert.latitude, alert.longitude]], {
                color: '#8fdcff',
                weight: 3,
                opacity: 0.55,
                dashArray: '8,8'
            }).addTo(map);
        });

        payload.agents.forEach(function (agent) {
            if (typeof agent.latitude === 'undefined' || typeof agent.longitude === 'undefined') {
                return;
            }
            L.circleMarker([agent.latitude, agent.longitude], {
                radius: 6,
                color: '#2ed39f',
                fillColor: '#2ed39f',
                fillOpacity: 0.8
            }).addTo(map).bindPopup(
                '<strong>' + escapeHtml(agent.name) + '</strong><br>' +
                escapeHtml(agent.role || '') + '<br>' +
                escapeHtml(agent.status || '')
            );
        });
    }

    function toggleMapView(button) {
        var mapId = button.dataset.mapTarget;
        var mode = button.dataset.mode;
        var node = document.getElementById(mapId);
        if (!node || !node.vigilanceMap || !node.vigilanceMap.vigilanceLayers) {
            return;
        }

        var map = node.vigilanceMap;
        if (mode === map.vigilanceCurrentLayer) {
            return;
        }

        if (mode === 'satellite') {
            map.removeLayer(map.vigilanceLayers.avenue);
            map.vigilanceLayers.satellite.addTo(map);
        } else {
            map.removeLayer(map.vigilanceLayers.satellite);
            map.vigilanceLayers.avenue.addTo(map);
        }

        map.vigilanceCurrentLayer = mode;

        document.querySelectorAll('[data-map-target="' + mapId + '"]').forEach(function (btn) {
            btn.classList.toggle('btn-primary', btn === button);
            btn.classList.toggle('btn-secondary', btn !== button);
        });
    }

    function updatePreviewMap(mapId, lat, lng, label) {
        var node = document.getElementById(mapId);
        if (!node || !node.vigilanceMap || !window.L) {
            return;
        }

        var map = node.vigilanceMap;
        if (node.vigilancePreviewMarker) {
            map.removeLayer(node.vigilancePreviewMarker);
        }

        node.vigilancePreviewMarker = L.marker([lat, lng]).addTo(map)
            .bindPopup('<strong>' + escapeHtml(label || 'Client') + '</strong><br>' + escapeHtml(lat + ', ' + lng))
            .openPopup();

        map.setView([lat, lng], 14);
    }

    function readFieldValue(scope, names) {
        var fieldNames = Array.isArray(names) ? names : [names];
        for (var index = 0; index < fieldNames.length; index += 1) {
            var field = scope.querySelector('[name="' + fieldNames[index] + '"]');
            if (field) {
                return String(field.value || '').trim();
            }
        }

        return '';
    }

    function updateGeocodeFeedback(node, message, isError) {
        if (!node) {
            return;
        }

        node.textContent = message || '';
        node.style.color = isError ? '#ffb3b3' : '#7ecfff';
    }

    function hasGeneratedCoordinates(data) {
        return !!(data && data.latitude && data.longitude && data.ok !== false);
    }

    function requestGeneratedCoords(url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: payload.toString()
        }).then(function (response) {
            return response.json().catch(function () {
                return null;
            }).then(function (data) {
                if (!response.ok || !hasGeneratedCoordinates(data)) {
                    throw new Error(data && data.message ? data.message : 'Generation impossible');
                }

                return data;
            });
        });
    }

    function updateAlertsPanel(data) {
        var liveNode = document.getElementById('live-alerts');
        if (!liveNode || !data.alerts) {
            return;
        }

        liveNode.innerHTML = '';
        data.alerts.slice(0, 6).forEach(function (alert) {
            var item = document.createElement('div');
            item.className = 'panel-item';
            item.innerHTML =
                '<strong>' + escapeHtml(alert.type) + ' • ' + escapeHtml(alert.client_name || '') + '</strong>' +
                '<div class="tiny">' + escapeHtml(alert.created_at || '') + '</div>' +
                '<p>' + escapeHtml(alert.message || '') + '</p>' +
                '<span class="badge ' + badgeClass(alert.status) + '">' + escapeHtml(alert.status || '') + '</span>';
            liveNode.appendChild(item);
        });
    }

    function updateMetrics(data) {
        var counters = document.querySelectorAll('[data-counter]');
        counters.forEach(function (counter) {
            var key = counter.dataset.counter;
            if (data.metrics && Object.prototype.hasOwnProperty.call(data.metrics, key)) {
                counter.textContent = data.metrics[key];
            }
        });
    }

    function pollAlerts() {
        var feedNode = document.querySelector('[data-feed-url]');
        if (!feedNode) {
            return;
        }

        var url = feedNode.dataset.feedUrl;
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                updateAlertsPanel(data);
                updateMetrics(data);

                if (data.alerts && data.alerts.length) {
                    var firstAlert = data.alerts[0];
                    var firstKey = String(firstAlert.id || '') + '|' + String(firstAlert.created_at || '');
                    if (lastAlertKey && firstKey !== lastAlertKey && alertRequiresSiren(firstAlert)) {
                        startSiren();
                    }
                    lastAlertKey = firstKey;
                }
            })
            .catch(function () {
                return null;
            });
    }

    function initMapToggles() {
        document.querySelectorAll('[data-map-target]').forEach(function (button) {
            button.addEventListener('click', function () {
                toggleMapView(button);
            });
        });
    }

    function initMaps() {
        document.querySelectorAll('.vg-map').forEach(drawMap);
    }

    function initForms() {
        document.querySelectorAll('[data-geocode-form]').forEach(function (form) {
            form.addEventListener('submit', function () {
                return true;
            });
        });

        document.querySelectorAll('[data-generate-coords]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();

                var form = button.closest('form');
                var geocodeUrl = button.dataset.geocodeUrl;
                if (!form || !geocodeUrl) {
                    return;
                }

                var address = readFieldValue(form, ['address', 'address_line']);
                var commune = readFieldValue(form, 'commune');
                var district = readFieldValue(form, ['district', 'quartier']);
                var avenue = readFieldValue(form, 'avenue');
                var statusNode = button.dataset.statusTarget ? document.getElementById(button.dataset.statusTarget) : null;
                var previewNode = button.dataset.previewTarget ? document.getElementById(button.dataset.previewTarget) : null;
                var defaultLabel = button.dataset.defaultLabel || button.textContent.trim();

                if (!address && !commune && !district && !avenue) {
                    updateGeocodeFeedback(statusNode, 'Ajoutez au moins une adresse, une commune ou une avenue avant la generation.', true);
                    if (previewNode) {
                        previewNode.textContent = 'Aucune coordonnee generee pour le moment.';
                    }
                    return;
                }

                var payload = new URLSearchParams();
                payload.append('address', address);
                payload.append('address_line', address);
                payload.append('commune', commune);
                payload.append('district', district);
                payload.append('quartier', district);
                payload.append('avenue', avenue);

                button.disabled = true;
                button.textContent = button.dataset.loadingText || 'Generation GPS...';
                updateGeocodeFeedback(statusNode, 'Generation des coordonnees en cours...', false);

                requestGeneratedCoords(geocodeUrl, payload)
                    .then(function (data) {
                        var latInput = form.querySelector('[name="latitude"]');
                        var lngInput = form.querySelector('[name="longitude"]');
                        if (latInput) {
                            latInput.value = data.latitude;
                        }
                        if (lngInput) {
                            lngInput.value = data.longitude;
                        }

                        var nameInput = form.querySelector('[name="full_name"]') || form.querySelector('[name="name"]');
                        updatePreviewMap(button.dataset.mapId, data.latitude, data.longitude, nameInput ? nameInput.value : 'Client');
                        updateGeocodeFeedback(statusNode, data.message || 'Coordonnees generees avec succes.', false);
                        if (previewNode) {
                            previewNode.textContent = (data.label || 'Kinshasa') + ' • ' + data.latitude + ' / ' + data.longitude;
                        }
                    })
                    .catch(function (error) {
                        updateGeocodeFeedback(statusNode, error && error.message ? error.message : 'Echec de generation. Verifiez les informations de localisation.', true);
                        if (previewNode) {
                            previewNode.textContent = 'Aucune coordonnee generee pour le moment.';
                        }
                    })
                    .then(function () {
                        button.disabled = false;
                        button.textContent = defaultLabel;
                    });
            });
        });
    }

    function initSirenButtons() {
        var start = document.querySelector('[data-start-siren]');
        var stop = document.querySelector('[data-stop-siren]');
        if (start) {
            start.addEventListener('click', function () {
                startSiren();
            });
        }
        if (stop) {
            stop.addEventListener('click', function () {
                stopSiren();
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindAudioUnlock();
        initMaps();
        initMapToggles();
        initForms();
        initSirenButtons();
        pollAlerts();

        var feedNode = document.querySelector('[data-feed-url]');
        if (feedNode) {
            var interval = parseInt(feedNode.dataset.feedInterval || '10000', 10);
            setInterval(function () {
                if (document.hidden) {
                    return;
                }
                pollAlerts();
            }, interval);
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    pollAlerts();
                }
            });
        }
    });

    window.Vigilance.startSiren = startSiren;
    window.Vigilance.stopSiren = stopSiren;
    window.Vigilance.startSignalLoop = startSignalLoop;
    window.Vigilance.stopSignalLoop = stopSignalLoop;
    window.Vigilance.startMessageLoop = startMessageLoop;
    window.Vigilance.stopMessageLoop = stopMessageLoop;
    window.Vigilance.startCallLoop = startCallLoop;
    window.Vigilance.stopCallLoop = stopCallLoop;
    window.Vigilance.armAudio = armAudio;
    window.Vigilance.resolveAppRoot = resolveAppRoot;
    window.Vigilance.appUrl = appUrl;
    window.Vigilance.readFieldValue = readFieldValue;
    window.Vigilance.updateGeocodeFeedback = updateGeocodeFeedback;
    window.Vigilance.requestGeneratedCoords = requestGeneratedCoords;
})();
(function () {
    function injectSecurityShortcut() {
        return;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectSecurityShortcut);
    } else {
        injectSecurityShortcut();
    }
})();

(function () {
    function normalizeHomepageHeroCopy() {
        var path = window.location.pathname.toLowerCase();
        var root = (window.Vigilance && typeof window.Vigilance.resolveAppRoot === 'function'
            ? window.Vigilance.resolveAppRoot()
            : '/vigilance'
        ).toLowerCase();
        if (path !== root + '/' && path !== root + '/index.php' && path !== root) {
            return;
        }

        var desired = 'Meme quand tout le monde dort, VIGILANCE veille.';
        var candidates = document.querySelectorAll('h1, .hero h1, .hero-copy h1, .hero-content h1');

        candidates.forEach(function (node) {
            var text = (node.textContent || '').trim().toLowerCase();
            if (
                text.indexOf('quand le danger cherche une faille') !== -1 ||
                text.indexOf('quand tout le monde dort') !== -1
            ) {
                node.textContent = desired;
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', normalizeHomepageHeroCopy);
    } else {
        normalizeHomepageHeroCopy();
    }
})();

(function () {
    function setupClientGeocode() {
        var vigilance = window.Vigilance || {};
        var button = document.getElementById('generate-coords-button');
        if (!button) {
            return;
        }

        var latitude = document.querySelector('input[name="latitude"]');
        var longitude = document.querySelector('input[name="longitude"]');
        var address = document.querySelector('input[name="address_line"]');
        var commune = document.querySelector('input[name="commune"]');
        var quartier = document.querySelector('input[name="quartier"]');
        var avenue = document.querySelector('input[name="avenue"]');
        var status = document.getElementById('coords-status');
        var preview = document.getElementById('coords-preview-text');

        button.addEventListener('click', function (event) {
            event.preventDefault();

            var addressValue = address ? String(address.value || '').trim() : vigilance.readFieldValue(document, ['address_line', 'address']);
            var communeValue = commune ? String(commune.value || '').trim() : vigilance.readFieldValue(document, 'commune');
            var quartierValue = quartier ? String(quartier.value || '').trim() : vigilance.readFieldValue(document, ['quartier', 'district']);
            var avenueValue = avenue ? String(avenue.value || '').trim() : vigilance.readFieldValue(document, 'avenue');

            if (!addressValue && !communeValue && !quartierValue && !avenueValue) {
                vigilance.updateGeocodeFeedback(status, 'Ajoutez une adresse ou une zone avant la generation.', true);
                if (preview) {
                    preview.textContent = 'Aucune coordonnee generee pour le moment.';
                }
                return;
            }

            var payload = new URLSearchParams();
            payload.set('address', addressValue);
            payload.set('address_line', addressValue);
            payload.set('commune', communeValue);
            payload.set('quartier', quartierValue);
            payload.set('district', quartierValue);
            payload.set('avenue', avenueValue);

            vigilance.updateGeocodeFeedback(status, 'Generation en cours...', false);

            vigilance.requestGeneratedCoords(vigilance.appUrl('api/geocode_client.php'), payload)
                .then(function (data) {
                    if (latitude) {
                        latitude.value = data.latitude || '';
                    }
                    if (longitude) {
                        longitude.value = data.longitude || '';
                    }
                    vigilance.updateGeocodeFeedback(status, data.message || 'Coordonnees generees avec succes.', false);
                    if (preview) {
                        preview.textContent = (data.label || 'Kinshasa') + ' • ' + (data.latitude || '') + ' / ' + (data.longitude || '');
                    }
                })
                .catch(function (error) {
                    vigilance.updateGeocodeFeedback(status, error && error.message ? error.message : 'Echec de generation. Verifie les informations de localisation.', true);
                    if (preview) {
                        preview.textContent = 'Aucune coordonnee generee pour le moment.';
                    }
                });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupClientGeocode);
    } else {
        setupClientGeocode();
    }
})();

(function () {
    function renderSecureFeedList(targetId, rows, formatter) {
        var container = document.getElementById(targetId);
        if (!container) {
            return;
        }

        if (!Array.isArray(rows) || rows.length === 0) {
            return;
        }

        container.innerHTML = rows.map(formatter).join('');
    }

    function safeText(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function setupSecureFeed() {
        if (!document.body) {
            return;
        }

        var vigilance = window.Vigilance || {};

        var needsFeed =
            document.getElementById('secure-admin-messages') ||
            document.getElementById('secure-client-messages') ||
            document.getElementById('secure-agent-messages');

        if (!needsFeed) {
            return;
        }

        function applyFeed(data) {
            if (!data || !data.ok) {
                return;
            }

            var alertCount = document.getElementById('secure-alert-count');
            if (alertCount && Array.isArray(data.alerts)) {
                alertCount.textContent = data.alerts.length;
            }

            var callCount = document.getElementById('secure-call-count');
            if (callCount && Array.isArray(data.calls)) {
                callCount.textContent = data.calls.length;
            }

            var missionCount = document.getElementById('secure-mission-count');
            if (missionCount && Array.isArray(data.interventions)) {
                missionCount.textContent = data.interventions.length;
            }

            var messageCount = document.getElementById('secure-message-count');
            if (messageCount && Array.isArray(data.messages)) {
                messageCount.textContent = data.messages.length;
            }

            renderSecureFeedList('secure-admin-messages', data.messages, function (row) {
                return '<div class="ops-item"><strong>' + safeText(row.subject || 'Message VIGILANCE') + '</strong><div>' + safeText((row.sender_label || row.from_role || 'VIGILANCE') + ' → ' + (row.recipient_role_label || row.to_role || 'Centre')) + ' • ' + safeText(row.urgency || 'normal') + '</div><div>' + safeText(row.message || '') + '</div></div>';
            });

            renderSecureFeedList('secure-admin-calls', data.calls, function (row) {
                return '<div class="ops-item"><strong>' + safeText(row.client_name || 'Client VIGILANCE') + '</strong><div>' + safeText((row.phone || '') + ' • ' + (row.status || 'pending')) + '</div><div>' + safeText(row.notes || '') + '</div></div>';
            });

            renderSecureFeedList('secure-client-messages', data.messages, function (row) {
                return '<div class="client-item"><strong>' + safeText(row.subject || 'Message VIGILANCE') + '</strong><div>' + safeText((row.sender_label || row.from_role || 'VIGILANCE') + ' • ' + (row.urgency || 'normal')) + '</div><div>' + safeText(row.message || '') + '</div></div>';
            });

            renderSecureFeedList('secure-agent-messages', data.messages, function (row) {
                return '<div class="agent-item"><strong>' + safeText(row.subject || 'Message centre') + '</strong><div>' + safeText((row.sender_label || row.from_role || 'VIGILANCE') + ' • ' + (row.urgency || 'normal')) + '</div><div>' + safeText(row.message || '') + '</div></div>';
            });

            renderSecureFeedList('secure-agent-missions', data.interventions, function (row) {
                return '<div class="agent-item"><strong>Mission ' + safeText(row.id || '') + '</strong><div>' + safeText(row.status || row.mission_status || '') + '</div><div>' + safeText(row.route || row.route_summary || '') + '</div></div>';
            });
        }

        function pullFeed() {
            fetch((typeof vigilance.appUrl === 'function' ? vigilance.appUrl('api/secure-feed.php') : '/vigilance/api/secure-feed.php'), { credentials: 'same-origin' })
                .then(function (response) { return response.json(); })
                .then(applyFeed)
                .catch(function () { return null; });
        }

        pullFeed();
        window.setInterval(function () {
            if (document.hidden) {
                return;
            }
            pullFeed();
        }, 8000);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                pullFeed();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupSecureFeed);
    } else {
        setupSecureFeed();
    }
})();

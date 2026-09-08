document.addEventListener('DOMContentLoaded', function () {
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

    document.querySelectorAll('[data-geocode-form]').forEach(function (form) {
        var button = form.querySelector('[data-generate-coords]');
        if (!button) {
            return;
        }

        button.addEventListener('click', function (event) {
            event.preventDefault();

            var geocodeUrl = button.dataset.geocodeUrl;
            if (!geocodeUrl) {
                return;
            }

            var address = readFieldValue(form, ['address', 'address_line']);
            var commune = readFieldValue(form, 'commune');
            var district = readFieldValue(form, ['district', 'quartier']);
            var avenue = readFieldValue(form, 'avenue');
            var statusNode = button.dataset.statusTarget ? document.getElementById(button.dataset.statusTarget) : null;
            var previewNode = button.dataset.previewTarget ? document.getElementById(button.dataset.previewTarget) : null;
            var defaultLabel = button.textContent.trim();

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
});

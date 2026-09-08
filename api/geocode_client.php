<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$input = file_get_contents('php://input');
$payload = [];
$contentType = strtolower(trim((string) ($_SERVER['CONTENT_TYPE'] ?? '')));

if ($input !== '' && str_contains($contentType, 'application/json')) {
    $decoded = json_decode($input, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}

if ($payload === [] && $_POST !== []) {
    $payload = $_POST;
}

if ($payload === [] && $input !== '') {
    $formPayload = [];
    parse_str($input, $formPayload);
    if (is_array($formPayload)) {
        $payload = $formPayload;
    }
}

if (!is_array($payload)) {
    $payload = [];
}

$normalize = static function (string $value): string {
    $value = trim($value);
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
};

$addressLabel = trim((string) ($payload['address'] ?? $payload['address_line'] ?? ''));
$communeLabel = trim((string) ($payload['commune'] ?? ''));
$quartierLabel = trim((string) ($payload['quartier'] ?? $payload['district'] ?? ''));
$avenueLabel = trim((string) ($payload['avenue'] ?? ''));

$address = $normalize($addressLabel);
$commune = $normalize($communeLabel);
$quartier = $normalize($quartierLabel);
$avenue = $normalize($avenueLabel);

$needle = trim(implode(' ', array_filter([$address, $commune, $quartier, $avenue], static fn($value): bool => $value !== '')));

if ($needle === '') {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => 'Renseignez au moins une adresse, une commune, un quartier ou une avenue.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$zones = [
    'gombe' => ['lat' => -4.3187, 'lng' => 15.3003],
    'ngaliema' => ['lat' => -4.3842, 'lng' => 15.2509],
    'limete' => ['lat' => -4.3293, 'lng' => 15.3361],
    'masina' => ['lat' => -4.3837, 'lng' => 15.4206],
    'matete' => ['lat' => -4.3771, 'lng' => 15.3647],
    'kinshasa' => ['lat' => -4.3250, 'lng' => 15.3222],
    'kintambo' => ['lat' => -4.3318, 'lng' => 15.2830],
    'bandalungwa' => ['lat' => -4.3376, 'lng' => 15.2862],
    'masamukini' => ['lat' => -4.3760, 'lng' => 15.3535],
    'ngombe' => ['lat' => -4.3208, 'lng' => 15.2957],
    'kalamu' => ['lat' => -4.3368, 'lng' => 15.3184],
    'barumbu' => ['lat' => -4.3141, 'lng' => 15.3328],
];

$match = $zones['kinshasa'];
foreach ($zones as $keyword => $coords) {
    if ($needle !== '' && str_contains($needle, $keyword)) {
        $match = $coords;
        break;
    }
}

$seed = crc32($needle !== '' ? $needle : 'kinshasa');
$latOffset = (($seed % 1000) / 100000) - 0.005;
$lngOffset = (((int) ($seed / 1000) % 1000) / 100000) - 0.005;

$latitude = round($match['lat'] + $latOffset, 6);
$longitude = round($match['lng'] + $lngOffset, 6);
$labelParts = array_values(array_filter([$addressLabel, $avenueLabel, $quartierLabel, $communeLabel], static fn($value): bool => $value !== ''));
$label = $labelParts !== [] ? implode(', ', $labelParts) : 'Kinshasa';

echo json_encode([
    'ok' => true,
    'label' => $label,
    'latitude' => number_format($latitude, 6, '.', ''),
    'longitude' => number_format($longitude, 6, '.', ''),
    'message' => 'Coordonnees generees localement pour Kinshasa.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

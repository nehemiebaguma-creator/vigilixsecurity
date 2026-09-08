<?php

$resolveAppRoot = static function (string $script = ''): string {
    $script = str_replace('\\', '/', trim($script));
    $segments = array_values(array_filter(explode('/', trim($script, '/')), 'strlen'));
    if ($segments === []) {
        return '/vigilance';
    }

    foreach ($segments as $index => $segment) {
        if (in_array(strtolower($segment), ['vigilance', 'vigilix', 'viglix'], true)) {
            return '/' . implode('/', array_slice($segments, 0, $index + 1));
        }
    }

    foreach ($segments as $index => $segment) {
        if ($index > 0 && in_array(strtolower($segment), ['admin', 'auth', 'api', 'client', 'agent', 'account', 'public', 'fulcrum'], true)) {
            return '/' . implode('/', array_slice($segments, 0, $index));
        }
    }

    $firstSegment = (string) ($segments[0] ?? '');
    if ($firstSegment !== '' && strpos($firstSegment, '.') === false) {
        return '/' . $firstSegment;
    }

    return '/vigilance';
};

$resolvedRoot = $resolveAppRoot((string) ($_SERVER['SCRIPT_NAME'] ?? ''));

$resolvePublicUrl = static function () use ($resolveAppRoot): string {
    $forwardedHost = trim((string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? ''));
    $host = $forwardedHost !== '' ? $forwardedHost : trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $forwardedProtoRaw = (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
    $forwardedProto = strtolower(trim(explode(',', $forwardedProtoRaw)[0] ?? ''));
    $scheme = ($forwardedProto === 'https' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) ? 'https' : 'http';

    $root = '/vigilance';
    $script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', (string) $_SERVER['SCRIPT_NAME']) : '';
    if ($script !== '') {
        $root = $resolveAppRoot($script);
    }

    if ($host !== '') {
        return $scheme . '://' . $host . rtrim($root, '/');
    }

    $configured = (string) (getenv('VGX_PUBLIC_URL') ?: getenv('APP_URL') ?: 'https://vigulivsecurity.com');

    return rtrim($configured, '/');
};

return array(
    'name' => 'VIGILANCE Security',
    'slogan' => "Quand tout le monde dort, VIGILANCE veille.",
    'headline' => "VIGILANCE relie surveillance, alerte, coordination et intervention dans une seule chaine operationnelle.",
    'base_url' => $resolvedRoot,
    'public_url' => $resolvePublicUrl(),
    'storage' => __DIR__ . '/../storage/store.json',
    'timezone' => 'Africa/Kinshasa',
    'company_email' => 'vigilencesec558@gmail.com',
    'commercial_email' => 'vigilencesec558@gmail.com',
    'company_phone' => '+243 819 174 732',
    'whatsapp_phone' => '243819174732',
    'company_address' => 'Gombe, Kinshasa, RDC',
    'ops_notification_emails' => array(
        'vigilencesec558@gmail.com',
    ),
    'control_hq' => array(
        'label' => 'Centre VIGILANCE Command',
        'latitude' => -4.3250,
        'longitude' => 15.3222,
        'address' => 'Boulevard du 30 Juin, Gombe, Kinshasa',
    ),
    'flag' => array(
        'country' => 'Republique Democratique du Congo',
        'motto' => 'Protection privee, reponse locale, supervision continue.',
    ),
);

<?php

require __DIR__ . '/../includes/bootstrap.php';

if (!vg_is_post()) {
    http_response_code(405);
    exit('Methode non autorisee.');
}

if (!function_exists('vg_public_feedback_redirect')) {
    function vg_public_feedback_redirect(string $status, string $message): void
    {
        $query = http_build_query([
            'status' => $status,
            'message' => $message,
        ]);

        header('Location: ' . vg_url('index.php?' . $query . '#contact'));
        exit;
    }
}

$fullName = trim((string) ($_POST['full_name'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$address = trim((string) ($_POST['address'] ?? ''));
$commune = trim((string) ($_POST['commune'] ?? ''));
$district = trim((string) ($_POST['district'] ?? ''));
$avenue = trim((string) ($_POST['avenue'] ?? ''));
$siteType = trim((string) ($_POST['site_type'] ?? $_POST['infrastructure_type'] ?? $_POST['project_type'] ?? ''));
$securityNeed = trim((string) ($_POST['security_need'] ?? $_POST['message'] ?? $_POST['water_issue'] ?? ''));
$latitude = trim((string) ($_POST['latitude'] ?? ''));
$longitude = trim((string) ($_POST['longitude'] ?? ''));

if ($fullName === '' || $phone === '' || $securityNeed === '') {
    vg_public_feedback_redirect('error', 'Nom, telephone et besoin de securite sont obligatoires.');
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    vg_public_feedback_redirect('error', 'L adresse email fournie est invalide.');
}

$coordinates = ['latitude' => $latitude, 'longitude' => $longitude];
if (($latitude === '' || $longitude === '') && function_exists('vg_geocode')) {
    $coordinates = vg_geocode($address, $commune, $district, $avenue);
}

$store = function_exists('vgx_store_bootstrap') ? vgx_store_bootstrap() : vgx_store();
$store['prospects'] = isset($store['prospects']) && is_array($store['prospects']) ? $store['prospects'] : [];

$prospect = [
    'id' => 'prs-' . date('YmdHis') . '-' . substr(md5($fullName . $phone . microtime(true)), 0, 6),
    'full_name' => $fullName,
    'phone' => $phone,
    'email' => $email,
    'address' => $address,
    'commune' => $commune,
    'quartier' => $district,
    'district' => $district,
    'avenue' => $avenue,
    'latitude' => (string) ($coordinates['latitude'] ?? ''),
    'longitude' => (string) ($coordinates['longitude'] ?? ''),
    'site_type' => $siteType,
    'security_need' => $securityNeed,
    'message' => $securityNeed,
    'source' => 'Landing VIGILANCE',
    'last_channel' => 'Web',
    'priority' => 'Normale',
    'status' => 'Nouveau',
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s'),
];

$store['prospects'][] = $prospect;

$saved = function_exists('vgx_write_bootstrap_store')
    ? vgx_write_bootstrap_store($store)
    : vgx_store_save($store);

if (!$saved) {
    vg_public_feedback_redirect('error', 'Impossible d enregistrer la demande de securisation pour le moment.');
}

vg_public_feedback_redirect('success', 'Votre demande de diagnostic securite a ete enregistree sous le dossier #' . $prospect['id'] . '.');

<?php

declare(strict_types=1);

$generated = __DIR__ . '/communes.generated.php';
if (is_file($generated)) {
    /** @var array<string, array<string, mixed>> $data */
    $data = require $generated;
    return $data;
}

/**
 * Fallback minimal si le referentiel polygonal genere n'est pas disponible.
 */
return [
    'bandalungwa' => ['label' => 'Bandalungwa', 'lat' => -4.3603, 'lng' => 15.2894, 'lat_offset' => 0.014, 'lng_offset' => 0.013],
    'barumbu' => ['label' => 'Barumbu', 'lat' => -4.3124, 'lng' => 15.3205, 'lat_offset' => 0.012, 'lng_offset' => 0.012],
    'bumbu' => ['label' => 'Bumbu', 'lat' => -4.3805, 'lng' => 15.2968, 'lat_offset' => 0.018, 'lng_offset' => 0.015],
    'gombe' => ['label' => 'Gombe', 'lat' => -4.3187, 'lng' => 15.3003, 'lat_offset' => 0.015, 'lng_offset' => 0.014],
    'kalamu' => ['label' => 'Kalamu', 'lat' => -4.3485, 'lng' => 15.3102, 'lat_offset' => 0.015, 'lng_offset' => 0.014],
    'kasa-vubu' => ['label' => 'Kasa-Vubu', 'lat' => -4.3382, 'lng' => 15.2956, 'lat_offset' => 0.014, 'lng_offset' => 0.013],
    'kimbanseke' => ['label' => 'Kimbanseke', 'lat' => -4.425, 'lng' => 15.476, 'lat_offset' => 0.04, 'lng_offset' => 0.034],
    'kinshasa' => ['label' => 'Kinshasa', 'lat' => -4.3250, 'lng' => 15.3222, 'lat_offset' => 0.018, 'lng_offset' => 0.018],
    'kintambo' => ['label' => 'Kintambo', 'lat' => -4.3312, 'lng' => 15.2740, 'lat_offset' => 0.016, 'lng_offset' => 0.014],
    'kisenso' => ['label' => 'Kisenso', 'lat' => -4.445, 'lng' => 15.345, 'lat_offset' => 0.024, 'lng_offset' => 0.02],
    'lemba' => ['label' => 'Lemba', 'lat' => -4.4302, 'lng' => 15.3086, 'lat_offset' => 0.022, 'lng_offset' => 0.018],
    'limete' => ['label' => 'Limete', 'lat' => -4.3554, 'lng' => 15.3428, 'lat_offset' => 0.02, 'lng_offset' => 0.02],
    'lingwala' => ['label' => 'Lingwala', 'lat' => -4.3079, 'lng' => 15.2989, 'lat_offset' => 0.014, 'lng_offset' => 0.014],
    'makala' => ['label' => 'Makala', 'lat' => -4.3922, 'lng' => 15.3035, 'lat_offset' => 0.018, 'lng_offset' => 0.015],
    'maluku' => ['label' => 'Maluku', 'lat' => -4.12, 'lng' => 16.1, 'lat_offset' => 0.19, 'lng_offset' => 0.21],
    'masina' => ['label' => 'Masina', 'lat' => -4.3837, 'lng' => 15.4206, 'lat_offset' => 0.028, 'lng_offset' => 0.024],
    'matete' => ['label' => 'Matete', 'lat' => -4.3901, 'lng' => 15.3514, 'lat_offset' => 0.018, 'lng_offset' => 0.016],
    'mont-ngafula' => ['label' => 'Mont Ngafula', 'lat' => -4.4478, 'lng' => 15.2705, 'lat_offset' => 0.04, 'lng_offset' => 0.03],
    'ndjili' => ['label' => 'Ndjili', 'lat' => -4.3855, 'lng' => 15.377, 'lat_offset' => 0.02, 'lng_offset' => 0.018],
    'nsele' => ['label' => 'Nsele', 'lat' => -4.32, 'lng' => 15.55, 'lat_offset' => 0.08, 'lng_offset' => 0.07],
    'ngaba' => ['label' => 'Ngaba', 'lat' => -4.4048, 'lng' => 15.3048, 'lat_offset' => 0.012, 'lng_offset' => 0.011],
    'ngaliema' => ['label' => 'Ngaliema', 'lat' => -4.3908, 'lng' => 15.2523, 'lat_offset' => 0.03, 'lng_offset' => 0.026],
    'ngiri-ngiri' => ['label' => 'Ngiri-Ngiri', 'lat' => -4.3509, 'lng' => 15.2868, 'lat_offset' => 0.012, 'lng_offset' => 0.011],
    'selembao' => ['label' => 'Selembao', 'lat' => -4.3909, 'lng' => 15.2748, 'lat_offset' => 0.02, 'lng_offset' => 0.016],
];

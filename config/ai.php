<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Layanan AI Python YOLO - PLN UP3 Tegal
    |--------------------------------------------------------------------------
    */

    'enabled' => env('AI_SERVICE_ENABLED', true),

    // URL endpoint API Python lokal
    'base_url' => env('AI_SERVICE_URL', 'http://127.0.0.1:8001'),

    // Batas waktu timeout request HTTP dalam detik
    'timeout' => env('AI_SERVICE_TIMEOUT', 15),

    // API Key autentikasi komunikasi internal Laravel <-> Python
    'api_key' => env('AI_SERVICE_API_KEY', 'pln-tegal-internal-token-2026'),

    // Target kelas objek deteksi
    'target_classes' => ['kWh', 'Tiang', 'Trafo'],

    // Ambang batas keyakinan (confidence threshold)
    'confidence_threshold' => env('AI_CONFIDENCE_THRESHOLD', 0.60),
];

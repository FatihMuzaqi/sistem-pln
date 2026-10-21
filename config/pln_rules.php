<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Aturan Validasi Hasil Deteksi & OCR Foto kWh (PLN UP3 Tegal)
    |--------------------------------------------------------------------------
    | Modul ini menggunakan pendekatan Rule-Based Engine yang dapat dikonfigurasi.
    | Seluruh asumsi yang digunakan dicatat secara transparan dan terpisah dari
    | aturan resmi yang menunggu konfirmasi dari pembimbing lapangan PLN.
    */

    // 1. ATURAN AKTIF YANG SUDAH DISETUJUI & DIIMPLEMENTASIKAN (Active Rules)
    'active_rules' => [
        // Foto Tiang dan Trafo tidak diproses untuk OCR kWh dan statusnya selalu "-"
        'non_kwh_status_dash' => true,

        // Validasi Struktural ID Pelanggan (Format 11 - 12 digit numerik)
        'validate_customer_id_format' => true,
        'customer_id_min_digits' => 11,
        'customer_id_max_digits' => 12,

        // Filter Angka Teknis (Tegangan 230V, Frekuensi 50Hz, Konstanta 1000 imp/kWh tidak boleh jadi IDPEL)
        'filter_technical_numbers' => true,

        // Validasi Merek Meter dari Kamus Resmi yang Diakui
        'validate_meter_brand_from_dictionary' => true,
        'known_brands' => [
            'Melcoinda', 'Itron', 'Sanxing', 'Actaris', 'Star',
            'Hexing', 'Landis+Gyr', 'Meeco', 'Fuji Dharma', 'SmartMeter'
        ],

        // Merek tidak dikenal TIDAK berarti meter tidak sesuai (tetap dipertahankan status valid / perlu verifikasi)
        'unknown_brand_does_not_fail_meter' => true,

        // Ambang Batas Minimum Keyakinan (Thresholds)
        'min_yolo_confidence' => 75.00, // Ambang batas deteksi objek YOLO
        'min_ocr_confidence' => 70.00,  // Ambang batas pembacaan karakter OCR

        // Perlindungan Integritas Teks Asli (Hasil OCR mentah tidak boleh tertimpa saat koreksi)
        'preserve_raw_ocr_text' => true,
    ],

    // 2. ATURAN YANG MASIH MENUNGGU KONFIRMASI PEMBIMBING PLN (Pending Confirmation)
    'pending_confirmation' => [
        // Konfirmasi Faktual ID Pelanggan terhadap Basis Data Pelanggan Resmi (DIL / AP2T)
        'factual_customer_verification' => [
            'status' => 'MENUNGGU_INTEGRASI_API_AP2T',
            'description' => 'Pengecekan kebenaran faktual ID Pelanggan memerlukan izin integrasi Web Service resmi ke server DIL/AP2T PLN. Saat ini sistem HANYA memvalidasi kecocokan format struktural digit, BUKAN kebenaran kepemilikan faktual.',
            'is_integrated' => false,
        ],

        // Kebijakan Status Otomatis "Sesuai" vs Wajib Konfirmasi Petugas
        'auto_compliance_policy' => [
            'status' => 'KEBIJAKAN_MANUAL_FIRST',
            'description' => 'Apakah foto yang memenuhi format struktural diizinkan berstatus "Sesuai" secara otomatis, atau HARUS melalui otorisasi verifikasi manual staf Transaksi Energi Listrik (TEL). Default saat ini: Mengharuskan verifikasi manual sebelum dinyatakan Sesuai.',
            'allow_auto_compliant' => false, // Default false: diarahkan ke 'Perlu Verifikasi'
        ],

        // Prefix Awalan Kode Wilayah IDPEL UP3 Tegal
        'tegal_area_idpel_prefix' => [
            'status' => 'MENUNGGU_DAFTAR_PREFIX_RESMI',
            'description' => 'Daftar awalan kode area 2-4 digit ID Pelanggan untuk UP3 Tegal (contoh umum: 5210, 5211, 5212). Saat ini menerima seluruh awalan angka 11-12 digit.',
            'enforce_prefix' => false,
            'prefixes' => ['5210', '5211', '5212', '5213', '5214', '5215'],
        ],

        // Batas Toleransi Sudut Miring & Box APP Tertutup
        'meter_box_obstruction_policy' => [
            'status' => 'MENUNGGU_SOP_SURVEY_LAPANGAN',
            'description' => 'Penanganan foto meteran di dalam Box APP besi berkawat segel yang menghalangi pembacaan nomor seri atau stan meter.',
        ],
    ],
];

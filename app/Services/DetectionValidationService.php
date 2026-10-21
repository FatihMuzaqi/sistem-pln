<?php

namespace App\Services;

use App\Models\Photo;
use Illuminate\Support\Carbon;

class DetectionValidationService
{
    /**
     * Memvalidasi hasil deteksi dan OCR untuk menentukan status foto kWh
     * Mematuhi seluruh batasan: tidak menebak data, configurable, dan dokumentasi alasan.
     */
    public function validatePhoto(Photo $photo): array
    {
        $activeRules = config('pln_rules.active_rules', []);
        $pendingRules = config('pln_rules.pending_confirmation', []);
        $reasons = [];
        $rulesEvaluated = [];
        $needsVerification = false;

        // ATURAN 1: Foto Tiang dan Trafo menggunakan status "-"
        if ($photo->photo_type !== 'kWh') {
            $status = '-';
            $reasons[] = "Objek merupakan {$photo->photo_type} (non-kWh), aturan validasi ID Pelanggan dan Merek tidak diberlakukan.";
            
            $photo->update([
                'status' => $status,
                'status_reason' => implode(" | ", $reasons),
                'needs_verification' => false,
                'validation_rules_applied' => [
                    'rule' => 'non_kwh_status_dash',
                    'object_type' => $photo->photo_type,
                    'result' => 'exempted',
                ],
            ]);

            return [
                'status' => $status,
                'status_reason' => implode(" | ", $reasons),
                'needs_verification' => false,
                'reasons' => $reasons,
                'rules_evaluated' => $rulesEvaluated,
            ];
        }

        // ATURAN 2: Jika sudah diperiksa dan diverifikasi secara manual oleh petugas
        if ($photo->is_verified && $photo->verified_at) {
            $verifier = $photo->verified_by ?: 'Petugas Operator';
            $verifiedTime = Carbon::parse($photo->verified_at)->format('d/m/Y H:i:s');
            $reasons[] = "Hasil telah diperiksa dan diverifikasi oleh petugas ({$verifier}) pada {$verifiedTime}.";

            return [
                'status' => $photo->status,
                'status_reason' => $photo->status_reason ?: implode(" | ", $reasons),
                'needs_verification' => false,
                'reasons' => $reasons,
                'rules_evaluated' => ['manual_verification_applied' => true],
            ];
        }

        // ATURAN 3: Pemeriksaan Ketersediaan & Format ID Pelanggan
        $customerId = $photo->verified_customer_id ?: ($photo->customer_id ?: $photo->candidate_customer_id);

        if (empty($customerId)) {
            $reasons[] = "ID pelanggan belum terbaca dari plat meter.";
            $needsVerification = true;
            $rulesEvaluated['customer_id_check'] = 'missing';
        } else {
            // Validasi Format Struktural (11 - 12 digit numerik)
            $digitsOnly = preg_replace('/[^\d]/', '', (string) $customerId);
            $length = strlen($digitsOnly);
            $minDigits = $activeRules['customer_id_min_digits'] ?? 11;
            $maxDigits = $activeRules['customer_id_max_digits'] ?? 12;

            if ($length < $minDigits || $length > $maxDigits) {
                $reasons[] = "Format ID pelanggan tidak standar (terbaca {$length} digit, aturan terkonfirmasi: {$minDigits}-{$maxDigits} digit).";
                $needsVerification = true;
                $rulesEvaluated['customer_id_check'] = 'invalid_format';
            } else {
                // Catatan penting: Format valid BUKAN berarti benar secara faktual!
                $reasons[] = "Format ID pelanggan memenuhi aturan struktural ({$length} digit). Catatan: Verifikasi kebenaran faktual memerlukan izin integrasi data induk AP2T PLN.";
                $rulesEvaluated['customer_id_check'] = 'structural_format_valid';
            }
        }

        // ATURAN 4: Pemeriksaan Pengenalan Merek kWh
        $meterBrand = $photo->verified_meter_brand ?: ($photo->meter_brand ?: $photo->candidate_meter_brand);
        $knownBrands = $activeRules['known_brands'] ?? [];

        if (empty($meterBrand)) {
            $reasons[] = "Merek belum dikenali dari plat meter.";
            $needsVerification = true;
            $rulesEvaluated['brand_check'] = 'missing';
        } else {
            $matched = false;
            foreach ($knownBrands as $kb) {
                if (strcasecmp($meterBrand, $kb) === 0) {
                    $matched = true;
                    break;
                }
            }

            if ($matched) {
                $reasons[] = "Merek kWh meter ({$meterBrand}) sesuai dengan kamus resmi PLN.";
                $rulesEvaluated['brand_check'] = 'recognized';
            } else {
                // Aturan: Merek tidak dikenal TIDAK berarti meter tidak sesuai
                $reasons[] = "Merek meter ({$meterBrand}) belum terdaftar pada kamus umum PLN, perlu verifikasi petugas (tidak otomatis dinyatakan tidak sesuai).";
                $needsVerification = true;
                $rulesEvaluated['brand_check'] = 'unregistered_brand';
            }
        }

        // ATURAN 5: Pemeriksaan Keyakinan Deteksi Objek YOLO
        $confidenceService = app(\App\Services\DetectionConfidenceService::class);
        $minYoloConf = $confidenceService->getThreshold();

        if ($photo->detection_confidence === null) {
            $reasons[] = "Tingkat deteksi objek YOLO belum tersedia (-).";
            $needsVerification = true;
            $rulesEvaluated['yolo_confidence_check'] = 'missing';
        } elseif ($confidenceService->isBelowThreshold($photo->detection_confidence, $minYoloConf)) {
            $displayConf = $photo->display_confidence;
            $reasons[] = "Confidence deteksi objek rendah ({$displayConf} < {$minYoloConf}%).";
            $needsVerification = true;
            $rulesEvaluated['yolo_confidence_check'] = 'below_threshold';
        } else {
            $rulesEvaluated['yolo_confidence_check'] = 'passed';
        }

        // ATURAN 6: Pemeriksaan Keyakinan Pembacaan OCR
        $minOcrConf = $activeRules['min_ocr_confidence'] ?? 70.00;
        if ($photo->ocr_confidence !== null && $photo->ocr_confidence < $minOcrConf) {
            $reasons[] = "Confidence pembacaan OCR rendah ({$photo->ocr_confidence}% < {$minOcrConf}%).";
            $needsVerification = true;
            $rulesEvaluated['ocr_confidence_check'] = 'below_threshold';
        } elseif ($photo->raw_ocr_text === null) {
            $reasons[] = "Pembacaan teks OCR belum dijalankan.";
            $needsVerification = true;
            $rulesEvaluated['ocr_confidence_check'] = 'not_executed';
        } else {
            $rulesEvaluated['ocr_confidence_check'] = 'passed';
        }

        // ATURAN 7: Penentuan Status Akhir
        $allowAutoCompliant = $pendingRules['auto_compliance_policy']['allow_auto_compliant'] ?? false;

        if ($needsVerification) {
            $status = 'Perlu Verifikasi';
        } else {
            if ($allowAutoCompliant) {
                $status = 'Sesuai';
                $reasons[] = "Memenuhi seluruh aturan yang telah dikonfirmasi.";
            } else {
                // Kebijakan kehati-hatian: Hasil format lengkap tetap memerlukan otorisasi petugas
                $status = 'Perlu Verifikasi';
                $reasons[] = "Memenuhi seluruh aturan format yang telah dikonfirmasi. Menunggu konfirmasi akhir petugas operator.";
                $needsVerification = true;
            }
        }

        $statusReasonText = implode(" | ", $reasons);

        // Simpan hasil validasi ke basis data
        $photo->update([
            'status' => $status,
            'status_reason' => $statusReasonText,
            'needs_verification' => $needsVerification,
            'validation_rules_applied' => $rulesEvaluated,
        ]);

        return [
            'status' => $status,
            'status_reason' => $statusReasonText,
            'needs_verification' => $needsVerification,
            'reasons' => $reasons,
            'rules_evaluated' => $rulesEvaluated,
        ];
    }

    /**
     * Memproses Fasilitas Koreksi Manual Operator dengan Pencatatan Waktu & Audit Trail
     */
    public function applyManualCorrection(Photo $photo, array $data, ?string $officerName = null): Photo
    {
        $officer = $officerName ?: 'Petugas Transaksi Energi Listrik';
        $now = now();
        $notes = trim($data['notes'] ?? '');

        $reason = "Hasil telah diperiksa dan diverifikasi oleh petugas ({$officer}) pada {$now->format('d/m/Y H:i:s')}.";
        if (!empty($notes)) {
            $reason .= " Catatan Petugas: {$notes}.";
        }

        $photo->update([
            'verified_customer_id' => $data['verified_customer_id'] ?? $photo->verified_customer_id,
            'verified_meter_brand' => $data['verified_meter_brand'] ?? $photo->verified_meter_brand,
            'customer_id' => $data['verified_customer_id'] ?: $photo->customer_id,
            'meter_brand' => $data['verified_meter_brand'] ?: $photo->meter_brand,
            'status' => $data['verification_status'] ?? 'Sesuai',
            'status_reason' => $reason,
            'verified_at' => $now,
            'verified_by' => $officer,
            'is_verified' => true,
            'needs_verification' => ($data['verification_status'] ?? '') === 'Perlu Verifikasi',
        ]);

        return $photo->fresh();
    }
}

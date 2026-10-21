<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Photo extends Model
{
    use HasFactory;

    protected $table = 'photos';

    protected $fillable = [
        'batch_id',
        'photo_name',
        'photo_path',
        'photo_type',
        'customer_id',
        'meter_brand',
        'detection_confidence',
        'status',
        'status_reason',
        'processing_status',
        'placemark_name',
        'latitude',
        'longitude',
        'file_size',
        'mime_type',
        'ocr_result',
        'raw_ocr_text',
        'ocr_confidence',
        'ocr_status',
        'candidate_customer_id',
        'candidate_meter_brand',
        'verified_customer_id',
        'verified_meter_brand',
        'verified_at',
        'verified_by',
        'crop_path',
        'crop_box',
        'needs_verification',
        'is_verified',
        'validation_rules_applied',
        'detection_notes',
        'is_simulation',
    ];

    protected $casts = [
        'batch_id' => 'integer',
        'detection_confidence' => 'float',
        'ocr_confidence' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
        'file_size' => 'integer',
        'crop_box' => 'array',
        'validation_rules_applied' => 'array',
        'needs_verification' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'is_simulation' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relasi ke Batch KMZ asal foto
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(KmzBatch::class, 'batch_id');
    }

    /**
     * URL publik untuk menampilkan foto di web
     */
    public function getPhotoUrlAttribute(): string
    {
        if (empty($this->photo_path)) {
            return asset('images/placeholder.png');
        }

        if (str_starts_with($this->photo_path, 'http://') || str_starts_with($this->photo_path, 'https://')) {
            return $this->photo_path;
        }

        return Storage::disk('public')->url($this->photo_path);
    }

    /**
     * URL crop gambar kWh jika tersedia
     */
    public function getCropUrlAttribute(): ?string
    {
        if (empty($this->crop_path)) {
            return null;
        }

        if (str_starts_with($this->crop_path, 'http://') || str_starts_with($this->crop_path, 'https://')) {
            return $this->crop_path;
        }

        return Storage::disk('public')->url($this->crop_path);
    }

    /**
     * Tampilan ID Pelanggan sesuai aturan:
     * - Hanya untuk foto kWh
     * - Untuk foto Tiang dan Trafo menampilkan "-"
     * - Menampilkan verified_customer_id jika telah dikoreksi operator
     */
    public function getDisplayCustomerIdAttribute(): string
    {
        if ($this->photo_type !== 'kWh') {
            return '-';
        }

        if (!empty($this->verified_customer_id)) {
            return (string) $this->verified_customer_id;
        }

        if (!empty($this->customer_id)) {
            return (string) $this->customer_id;
        }

        if (!empty($this->candidate_customer_id)) {
            return (string) $this->candidate_customer_id;
        }

        return '-';
    }

    /**
     * Tampilan Merek kWh sesuai aturan:
     * - Hanya untuk foto kWh
     * - Untuk foto Tiang dan Trafo menampilkan "-"
     * - Menampilkan verified_meter_brand jika telah dikoreksi operator
     */
    public function getDisplayMeterBrandAttribute(): string
    {
        if ($this->photo_type !== 'kWh') {
            return '-';
        }

        if (!empty($this->verified_meter_brand)) {
            return (string) $this->verified_meter_brand;
        }

        if (!empty($this->meter_brand)) {
            return (string) $this->meter_brand;
        }

        if (!empty($this->candidate_meter_brand)) {
            return (string) $this->candidate_meter_brand;
        }

        return '-';
    }

    /**
     * Tampilan Tingkat Deteksi (Confidence YOLO)
     * - Mengubah nilai numerik menjadi persentase untuk tampilan (misal: 0.8950 -> 89.50%)
     * - Menggunakan pembulatan konsisten 2 desimal
     * - Jika deteksi tidak tersedia, menampilkan "-"
     */
    public function getDisplayConfidenceAttribute(): string
    {
        if ($this->detection_confidence === null) {
            return '-';
        }

        return app(\App\Services\DetectionConfidenceService::class)->formatToPercentage((float) $this->detection_confidence);
    }

    /**
     * Nilai numerik persentase (skala 0.00 - 100.00) untuk progress bar dan logika styling
     */
    public function getConfidencePercentageValueAttribute(): ?float
    {
        if ($this->detection_confidence === null) {
            return null;
        }

        $val = (float) $this->detection_confidence;
        return ($val <= 1.0 && $val >= 0.0) ? round($val * 100.0, 2) : round($val, 2);
    }

    /**
     * Keterangan resmi mengenai makna persentase tingkat deteksi
     */
    public function getConfidenceExplanationAttribute(): string
    {
        return \App\Services\DetectionConfidenceService::EXPLANATION;
    }

    /**
     * Tampilan Confidence OCR
     * - Disimpan sebagai informasi terpisah dan tidak menggantikan confidence deteksi YOLO
     * - Hanya untuk foto kWh dan jika OCR telah dijalankan
     */
    public function getDisplayOcrConfidenceAttribute(): string
    {
        if ($this->photo_type !== 'kWh' || $this->ocr_confidence === null) {
            return '-';
        }

        return app(\App\Services\DetectionConfidenceService::class)->formatToPercentage((float) $this->ocr_confidence);
    }

    /**
     * Status Verifikasi Foto
     */
    public function getDisplayStatusAttribute(): string
    {
        if ($this->photo_type !== 'kWh') {
            return '-';
        }

        if ($this->is_verified && $this->status === 'Sesuai') {
            return 'Sesuai';
        }

        if ($this->needs_verification || $this->ocr_status === 'needs_verification') {
            return 'Perlu Verifikasi';
        }

        if ($this->processing_status === 'unprocessed' || empty($this->status) || $this->status === 'unprocessed' || $this->status === 'active') {
            if ($this->status === 'Sesuai' || $this->status === 'Tidak Sesuai' || $this->status === 'Perlu Verifikasi') {
                return $this->status;
            }
            return 'Belum Diproses';
        }

        return match (strtolower($this->status)) {
            'sesuai' => 'Sesuai',
            'tidak sesuai', 'flagged' => 'Tidak Sesuai',
            'perlu verifikasi' => 'Perlu Verifikasi',
            default => $this->status,
        };
    }

    /**
     * Badge status utama
     */
    public function getDisplayStatusBadgeAttribute(): string
    {
        $status = $this->display_status;

        return match ($status) {
            '-' => '<span class="text-muted fw-bold">-</span>',
            'Sesuai' => '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-check-circle-fill me-1"></i>Sesuai</span>',
            'Tidak Sesuai' => '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1"><i class="bi bi-x-circle-fill me-1"></i>Tidak Sesuai</span>',
            'Perlu Verifikasi' => '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Perlu Verifikasi</span>',
            default => '<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1"><i class="bi bi-hourglass me-1"></i>Belum Diproses</span>',
        };
    }

    /**
     * Badge status pembacaan OCR
     */
    public function getDisplayOcrStatusBadgeAttribute(): string
    {
        if ($this->photo_type !== 'kWh') {
            return '<span class="text-muted fw-bold">-</span>';
        }

        return match ($this->ocr_status) {
            'success' => '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle me-1"></i>Berhasil Terbaca</span>',
            'partial' => '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle"><i class="bi bi-exclamation-circle me-1"></i>Terbaca Sebagian</span>',
            'needs_verification' => '<span class="badge bg-warning text-dark"><i class="bi bi-person-exclamation me-1"></i>Perlu Verifikasi</span>',
            'failed' => '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-x-circle me-1"></i>Tidak Terbaca</span>',
            default => '<span class="badge bg-light text-muted border"><i class="bi bi-dash me-1"></i>Belum Diproses</span>',
        };
    }

    /**
     * Badge Jenis Foto
     */
    public function getPhotoTypeBadgeAttribute(): string
    {
        return match ($this->photo_type) {
            'kWh' => '<span class="badge bg-primary"><i class="bi bi-speedometer2 me-1"></i>kWh</span>',
            'Tiang' => '<span class="badge bg-secondary"><i class="bi bi-reception-4 me-1"></i>Tiang</span>',
            'Trafo' => '<span class="badge bg-warning text-dark"><i class="bi bi-lightning-charge me-1"></i>Trafo</span>',
            default => '<span class="badge bg-light text-dark">-</span>',
        };
    }

    /**
     * Format ukuran file
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        if (!$bytes || $bytes <= 0) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 2) . ' ' . ($units[$i] ?? 'B');
    }
}

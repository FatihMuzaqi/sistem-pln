<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KmzBatch extends Model
{
    use HasFactory;

    protected $table = 'kmz_batches';

    protected $fillable = [
        'batch_code',
        'original_filename',
        'file_path',
        'file_size',
        'status',
        'total_files_found',
        'total_photos_extracted',
        'kml_found',
        'kml_placemarks_count',
        'error_message',
        'notes',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'total_files_found' => 'integer',
        'total_photos_extracted' => 'integer',
        'kml_found' => 'boolean',
        'kml_placemarks_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relasi ke seluruh foto yang diekstrak dari batch ini
     */
    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class, 'batch_id');
    }

    /**
     * Relasi hanya untuk foto kWh
     */
    public function kwhPhotos(): HasMany
    {
        return $this->hasMany(Photo::class, 'batch_id')->where('photo_type', 'kWh');
    }

    /**
     * Relasi hanya untuk foto Tiang
     */
    public function tiangPhotos(): HasMany
    {
        return $this->hasMany(Photo::class, 'batch_id')->where('photo_type', 'Tiang');
    }

    /**
     * Relasi hanya untuk foto Trafo
     */
    public function trafoPhotos(): HasMany
    {
        return $this->hasMany(Photo::class, 'batch_id')->where('photo_type', 'Trafo');
    }

    /**
     * Format ukuran file menjadi satuan yang mudah dibaca (KB / MB)
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 2) . ' ' . ($units[$i] ?? 'B');
    }

    /**
     * Helper kelas badge Bootstrap berdasarkan status proses batch (menunggu, berjalan, selesai, gagal)
     */
    public function getStatusBadgeClassAttribute(): string
    {
        return match (strtolower($this->status)) {
            'completed', 'selesai' => 'bg-success',
            'processing', 'berjalan', 'extracting' => 'bg-primary',
            'pending', 'menunggu', 'uploaded' => 'bg-warning text-dark',
            'failed', 'gagal' => 'bg-danger',
            default => 'bg-secondary',
        };
    }

    /**
     * Helper label teks status bahasa Indonesia (menunggu, berjalan, selesai, gagal)
     */
    public function getStatusLabelAttribute(): string
    {
        return match (strtolower($this->status)) {
            'completed', 'selesai' => 'Selesai',
            'processing', 'berjalan', 'extracting' => 'Berjalan',
            'pending', 'menunggu', 'uploaded' => 'Menunggu',
            'failed', 'gagal' => 'Gagal',
            default => ucfirst($this->status),
        };
    }

    /**
     * Jumlah foto yang sudah selesai diproses (sukses atau gagal)
     */
    public function getProcessedPhotosCountAttribute(): int
    {
        return $this->photos()->whereIn('processing_status', ['success', 'failed'])->count();
    }

    /**
     * Jumlah foto yang masih dalam antrean (belum diproses)
     */
    public function getPendingPhotosCountAttribute(): int
    {
        return $this->photos()->where('processing_status', 'unprocessed')->count();
    }

    /**
     * Jumlah foto yang gagal diproses
     */
    public function getFailedPhotosCountAttribute(): int
    {
        return $this->photos()->where('processing_status', 'failed')->count();
    }

    /**
     * Jumlah foto yang berhasil diproses
     */
    public function getSuccessPhotosCountAttribute(): int
    {
        return $this->photos()->where('processing_status', 'success')->count();
    }

    /**
     * Persentase progres pemrosesan batch (0 - 100%)
     */
    public function getProgressPercentageAttribute(): int
    {
        if ($this->total_photos_extracted <= 0) {
            return 0;
        }

        return (int) min(100, round(($this->processed_photos_count / $this->total_photos_extracted) * 100));
    }

    /**
     * Apakah batch sedang berjalan dalam antrean pemrosesan
     */
    public function getIsProcessingAttribute(): bool
    {
        return in_array(strtolower($this->status), ['berjalan', 'processing', 'extracting']);
    }
}

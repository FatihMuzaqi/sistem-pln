<?php

namespace App\Services;

use App\Models\KmzBatch;
use App\Models\Photo;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class KmzExtractorService
{
    /**
     * Ekstensi gambar yang diizinkan untuk diekstrak
     */
    protected array $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'webp', 'bmp'];

    /**
     * MIME types gambar yang valid
     */
    protected array $allowedMimeTypes = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/bmp',
        'image/x-ms-bmp',
    ];

    /**
     * Memproses file KMZ yang diunggah
     *
     * @param UploadedFile $file
     * @return array [bool $success, KmzBatch $batch, string $message]
     */
    public function processKmzUpload(UploadedFile $file): array
    {
        // 1. Generate Kode Unik Batch
        $batchCode = 'BATCH-' . date('Ymd-His') . '-' . strtoupper(Str::random(5));
        $originalName = $file->getClientOriginalName();
        $fileSize = $file->getSize();

        // 2. Simpan file asli ke direktori aman (storage/app/kmz_uploads/{batchCode}/)
        $uploadDir = "kmz_uploads/{$batchCode}";
        $safeOriginalPath = Storage::disk('local')->putFileAs(
            $uploadDir,
            $file,
            $originalName
        );

        if (!$safeOriginalPath) {
            throw new Exception("Gagal menyimpan file KMZ asli ke sistem penyimpanan.");
        }

        // 3. Buat Catatan Batch Awal di Database
        $batch = KmzBatch::create([
            'batch_code' => $batchCode,
            'original_filename' => $originalName,
            'file_path' => $safeOriginalPath,
            'file_size' => $fileSize,
            'status' => 'extracting',
            'total_files_found' => 0,
            'total_photos_extracted' => 0,
            'kml_found' => false,
            'kml_placemarks_count' => 0,
            'error_message' => null,
            'notes' => 'Memulai proses ekstraksi file KMZ.',
        ]);

        // Path absolut file asli untuk ZipArchive
        $absoluteZipPath = Storage::disk('local')->path($safeOriginalPath);

        // 4. Ekstrak Arsip Menggunakan ZipArchive
        return $this->extractAndStorePhotos($batch, $absoluteZipPath);
    }

    /**
     * Mengekstrak file KMZ secara aman dan membaca metadata KML
     */
    public function extractAndStorePhotos(KmzBatch $batch, string $absoluteZipPath): array
    {
        if (!class_exists('ZipArchive')) {
            $batch->update([
                'status' => 'failed',
                'error_message' => 'Ekstensi PHP ZipArchive tidak aktif pada server.',
            ]);
            return [
                'success' => false,
                'batch' => $batch,
                'message' => 'PHP ZipArchive extension tidak tersedia.',
            ];
        }

        $zip = new ZipArchive();
        $openResult = $zip->open($absoluteZipPath);

        if ($openResult !== true) {
            $errorMessage = match ($openResult) {
                ZipArchive::ER_NOZIP => 'File yang diunggah bukan format arsip ZIP/KMZ yang valid.',
                ZipArchive::ER_INCONS => 'Arsip KMZ tidak konsisten atau rusak.',
                ZipArchive::ER_CRC => 'Arsip KMZ mengalami kesalahan checksum (CRC Error).',
                default => 'Gagal membuka arsip KMZ (Kode error: ' . $openResult . ').',
            };

            $batch->update([
                'status' => 'failed',
                'error_message' => $errorMessage,
            ]);

            return [
                'success' => false,
                'batch' => $batch,
                'message' => $errorMessage,
            ];
        }

        $numFiles = $zip->numFiles;
        $extractedDir = "extracted_photos/{$batch->batch_code}";
        $photosData = [];
        $kmlContent = null;
        $kmlFound = false;
        $totalFiles = 0;

        // Cari file KML terlebih dahulu untuk mapping placemark
        for ($i = 0; $i < $numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (empty($filename) || str_ends_with($filename, '/')) {
                continue;
            }
            $totalFiles++;

            if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'kml') {
                $kmlContent = $zip->getFromIndex($i);
                $kmlFound = true;
            }
        }

        // Parsing KML jika ditemukan
        $placemarks = [];
        if ($kmlContent) {
            $placemarks = $this->parseKmlMetadata($kmlContent);
        }

        // Loop kedua: Ekstraksi foto secara aman
        $seenFilenames = [];
        for ($i = 0; $i < $numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);

            // Lewati folder atau nama kosong
            if (empty($entryName) || str_ends_with($entryName, '/') || str_ends_with($entryName, '\\')) {
                continue;
            }

            // PENCEGAHAN PATH TRAVERSAL:
            // Cegah traversal dengan memeriksa '../', prefix '/', atau backslash berbahaya
            $normalizedName = str_replace('\\', '/', $entryName);
            if (str_contains($normalizedName, '../') || str_starts_with($normalizedName, '/') || str_starts_with($normalizedName, '..')) {
                Log::warning("Potensi Path Traversal terdeteksi dan dilewati: {$entryName} pada batch {$batch->batch_code}");
                continue;
            }

            // Validasi ekstensi
            $ext = strtolower(pathinfo($normalizedName, PATHINFO_EXTENSION));
            if (!in_array($ext, $this->allowedImageExtensions)) {
                // Bukan file gambar (misal file .kml, .txt, .xml, atau script berbahaya)
                continue;
            }

            // Baca konten file dari ZIP stream
            $content = $zip->getFromIndex($i);
            if ($content === false || strlen($content) === 0) {
                continue;
            }

            // Validasi integritas gambar nyata menggunakan finfo / getimagesize
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_buffer($finfo, $content);
            finfo_close($finfo);

            if (!in_array($mimeType, $this->allowedMimeTypes)) {
                Log::warning("File dengan ekstensi gambar namun MIME type tidak valid: {$normalizedName} ({$mimeType})");
                continue;
            }

            // Bersihkan nama file untuk penyimpanan lokal
            $baseName = basename($normalizedName);
            $cleanBaseName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $baseName);

            // Tangani nama duplikat dalam arsip
            $uniqueName = $cleanBaseName;
            $counter = 1;
            while (in_array(strtolower($uniqueName), $seenFilenames)) {
                $nameOnly = pathinfo($cleanBaseName, PATHINFO_FILENAME);
                $uniqueName = "{$nameOnly}_{$counter}.{$ext}";
                $counter++;
            }
            $seenFilenames[] = strtolower($uniqueName);

            // Simpan gambar ke storage publik: storage/app/public/extracted_photos/{batch_code}/{uniqueName}
            $publicFilePath = "{$extractedDir}/{$uniqueName}";
            Storage::disk('public')->put($publicFilePath, $content);

            // Hubungkan dengan metadata KML jika ada placemark yang mereferensikan file ini
            $metadata = $this->matchPlacemarkMetadata($normalizedName, $baseName, $placemarks);

            // Klasifikasi awal:
            // "photo_type hanya boleh berisi kWh, Tiang, atau Trafo."
            // "Jangan menganggap seluruh gambar merupakan foto kWh."
            $inferredType = $this->inferInitialPhotoType($uniqueName, $metadata['placemark_name'] ?? '');

            $photosData[] = [
                'batch_id' => $batch->id,
                'photo_name' => $uniqueName,
                'photo_path' => $publicFilePath,
                'photo_type' => $inferredType,
                'customer_id' => null, // Tidak membuat data pelanggan fiktif
                'meter_brand' => null, // Tidak membuat data fiktif
                'detection_confidence' => null, // Belum diproses AI
                'status' => 'active',
                'processing_status' => 'unprocessed', // Menandai foto belum diproses
                'placemark_name' => $metadata['placemark_name'] ?? null,
                'latitude' => $metadata['latitude'] ?? null,
                'longitude' => $metadata['longitude'] ?? null,
                'file_size' => strlen($content),
                'mime_type' => $mimeType,
                'ocr_result' => null,
                'detection_notes' => null,
                'is_simulation' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $zip->close();

        // 5. Simpan Seluruh Metadata Foto ke Database dalam Satu Transaksi
        DB::beginTransaction();
        try {
            if (!empty($photosData)) {
                Photo::insert($photosData);
            }

            $extractedCount = count($photosData);
            $status = 'completed';
            $message = "Ekstraksi berhasil: Ditemukan {$totalFiles} file dan berhasil mengekstrak {$extractedCount} foto.";

            if ($extractedCount === 0) {
                $notes = "Arsip KMZ valid, namun tidak ditemukan file foto/gambar yang sesuai di dalamnya.";
                $message = $notes;
            } else {
                $notes = "Ekstraksi selesai dengan sukses. Foto siap untuk proses identifikasi pada tahap lanjutan.";
            }

            $batch->update([
                'status' => $status,
                'total_files_found' => $totalFiles,
                'total_photos_extracted' => $extractedCount,
                'kml_found' => $kmlFound,
                'kml_placemarks_count' => count($placemarks),
                'notes' => $notes,
            ]);

            DB::commit();

            return [
                'success' => true,
                'batch' => $batch->fresh(),
                'message' => $message,
                'photos_count' => $extractedCount,
            ];
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Gagal menyimpan metadata foto batch {$batch->batch_code}: " . $e->getMessage());

            $batch->update([
                'status' => 'failed',
                'error_message' => 'Gagal menyimpan metadata ke database: ' . $e->getMessage(),
            ]);

            return [
                'success' => false,
                'batch' => $batch,
                'message' => 'Gagal menyimpan metadata foto: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Membaca dan mem-parsing metadata KML sederhana (Placemark, nama, koordinat, dan referensi foto)
     */
    protected function parseKmlMetadata(string $kmlContent): array
    {
        $placemarks = [];

        try {
            // Hindari XML External Entity (XXE) injection
            $xml = @simplexml_load_string($kmlContent, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            if (!$xml) {
                return [];
            }

            // Register namespace kml jika ada
            $namespaces = $xml->getDocNamespaces();
            $ns = $namespaces[''] ?? null;
            if ($ns) {
                $xml->registerXPathNamespace('kml', $ns);
                $elements = $xml->xpath('//kml:Placemark');
            } else {
                $elements = $xml->xpath('//Placemark');
            }

            if (!is_array($elements)) {
                return [];
            }

            foreach ($elements as $elem) {
                $name = (string) ($elem->name ?? '');
                $description = (string) ($elem->description ?? '');
                $coordinatesStr = '';

                // Ambil koordinat titik jika ada
                if (isset($elem->Point->coordinates)) {
                    $coordinatesStr = (string) $elem->Point->coordinates;
                } elseif ($ns) {
                    $pt = $elem->xpath('.//kml:Point/kml:coordinates');
                    if (!empty($pt)) {
                        $coordinatesStr = (string) $pt[0];
                    }
                }

                $lat = null;
                $lng = null;
                if (!empty($coordinatesStr)) {
                    $coords = explode(',', trim($coordinatesStr));
                    if (count($coords) >= 2) {
                        $lng = is_numeric($coords[0]) ? (float) $coords[0] : null;
                        $lat = is_numeric($coords[1]) ? (float) $coords[1] : null;
                    }
                }

                // Cari referensi gambar di dalam description (e.g. <img src="...">)
                $imageRefs = [];
                if (!empty($description) && preg_match_all('/(?:src=["\']|href=["\'])([^"\']+\.(?:jpg|jpeg|png|webp|bmp))/i', $description, $matches)) {
                    $imageRefs = $matches[1];
                }

                $placemarks[] = [
                    'name' => trim($name),
                    'description' => $description,
                    'lat' => $lat,
                    'lng' => $lng,
                    'images' => $imageRefs,
                ];
            }
        } catch (Exception $e) {
            Log::warning("Gagal mem-parsing KML: " . $e->getMessage());
        }

        return $placemarks;
    }

    /**
     * Menghubungkan nama file gambar dengan placemark KML
     */
    protected function matchPlacemarkMetadata(string $fullPath, string $baseName, array $placemarks): array
    {
        foreach ($placemarks as $pm) {
            // Cek apakah ada referensi gambar yang cocok
            foreach ($pm['images'] as $imgRef) {
                if (basename($imgRef) === $baseName || str_ends_with($fullPath, $imgRef)) {
                    return [
                        'placemark_name' => $pm['name'] ?: null,
                        'latitude' => $pm['lat'],
                        'longitude' => $pm['lng'],
                    ];
                }
            }

            // Jika nama placemark memuat nama file atau sebaliknya
            if (!empty($pm['name']) && (str_contains($baseName, $pm['name']) || str_contains($pm['name'], pathinfo($baseName, PATHINFO_FILENAME)))) {
                return [
                    'placemark_name' => $pm['name'],
                    'latitude' => $pm['lat'],
                    'longitude' => $pm['lng'],
                ];
            }
        }

        return [
            'placemark_name' => null,
            'latitude' => null,
            'longitude' => null,
        ];
    }

    /**
     * Klasifikasi awal berbasis indikator nama file atau placemark jika ada.
     * Aturan: hanya boleh 'kWh', 'Tiang', atau 'Trafo'.
     * Jangan menganggap seluruh gambar adalah foto kWh.
     */
    protected function inferInitialPhotoType(string $filename, string $placemarkName): string
    {
        $haystack = strtolower($filename . ' ' . $placemarkName);

        if (str_contains($haystack, 'trafo') || str_contains($haystack, 'transformer')) {
            return 'Trafo';
        }

        if (str_contains($haystack, 'tiang') || str_contains($haystack, 'pole')) {
            return 'Tiang';
        }

        // Default ke kWh jika mengandung kata kwh/meter atau belum ditentukan
        return 'kWh';
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')
                ->constrained('kmz_batches')
                ->onDelete('cascade')
                ->comment('Relasi ke batch pemrosesan KMZ');
            $table->string('photo_name')->comment('Nama file foto asli hasil ekstraksi');
            $table->string('photo_path')->comment('Path lokasi file foto pada disk penyimpanan');
            $table->enum('photo_type', ['kWh', 'Tiang', 'Trafo'])
                ->default('kWh')
                ->index()
                ->comment('Tipe objek foto: kWh, Tiang, atau Trafo');
            $table->string('customer_id')->nullable()->index()->comment('ID Pelanggan (hanya untuk kWh meter)');
            $table->string('meter_brand')->nullable()->comment('Merk kWh meter (hanya untuk kWh meter)');
            $table->decimal('detection_confidence', 5, 2)->nullable()->comment('Tingkat keyakinan deteksi (0.00 - 100.00)');
            $table->string('status', 30)->default('active')->index()->comment('Status data foto (active, flagged, archived)');
            $table->enum('processing_status', ['unprocessed', 'pending', 'processing', 'success', 'failed'])
                ->default('unprocessed')
                ->index()
                ->comment('Status pemrosesan foto');
            
            // Metadata opsional yang diekstrak dari KML / file
            $table->string('placemark_name')->nullable()->comment('Nama placemark KML jika ada');
            $table->decimal('latitude', 10, 7)->nullable()->comment('Koordinat garis lintang');
            $table->decimal('longitude', 10, 7)->nullable()->comment('Koordinat garis bujur');
            $table->unsignedBigInteger('file_size')->nullable()->comment('Ukuran file foto dalam bytes');
            $table->string('mime_type', 50)->nullable()->comment('Tipe MIME foto');
            $table->text('ocr_result')->nullable()->comment('Hasil pembacaan angka stan meter');
            $table->text('detection_notes')->nullable()->comment('Catatan hasil deteksi/analisis');
            $table->boolean('is_simulation')->default(false)->index()->comment('Penanda jika data merupakan data simulasi pengujian');
            $table->timestamps();

            // Indeks gabungan untuk performa query pencarian dan filter
            $table->index(['batch_id', 'photo_type']);
            $table->index(['batch_id', 'processing_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('photos');
    }
};

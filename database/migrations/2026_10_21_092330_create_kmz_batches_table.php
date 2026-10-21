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
        Schema::create('kmz_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code', 60)->unique()->comment('Kode unik batch pemrosesan KMZ');
            $table->string('original_filename')->comment('Nama asli file KMZ yang diunggah');
            $table->string('file_path')->comment('Path penyimpanan arsip KMZ asli di disk');
            $table->unsignedBigInteger('file_size')->default(0)->comment('Ukuran file dalam bytes');
            $table->enum('status', ['uploaded', 'extracting', 'completed', 'failed'])
                ->default('uploaded')
                ->index()
                ->comment('Status proses ekstraksi batch KMZ');
            $table->unsignedInteger('total_files_found')->default(0)->comment('Jumlah file total dalam arsip');
            $table->unsignedInteger('total_photos_extracted')->default(0)->comment('Jumlah foto yang berhasil diekstrak');
            $table->boolean('kml_found')->default(false)->comment('Apakah file KML ditemukan dalam arsip');
            $table->unsignedInteger('kml_placemarks_count')->default(0)->comment('Jumlah placemark pada file KML');
            $table->text('error_message')->nullable()->comment('Pesan error jika ekstraksi gagal');
            $table->text('notes')->nullable()->comment('Catatan operator atau sistem');
            $table->timestamps();

            // Indeks untuk pencarian dan filter cepat
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kmz_batches');
    }
};

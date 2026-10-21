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
        Schema::table('photos', function (Blueprint $table) {
            $table->text('raw_ocr_text')->nullable()->after('ocr_result')->comment('Teks mentah hasil pembacaan OCR');
            $table->decimal('ocr_confidence', 5, 2)->nullable()->after('raw_ocr_text')->comment('Tingkat keyakinan OCR (0.00 - 100.00)');
            $table->string('ocr_status', 30)->default('skipped')->after('ocr_confidence')->index()->comment('Status OCR: success, partial, failed, needs_verification, skipped');
            $table->string('candidate_customer_id', 50)->nullable()->after('ocr_status')->comment('Kandidat ID Pelanggan hasil ekstraksi OCR');
            $table->string('candidate_meter_brand', 50)->nullable()->after('candidate_customer_id')->comment('Kandidat Merek kWh meter hasil ekstraksi OCR');
            $table->string('verified_customer_id', 50)->nullable()->after('candidate_meter_brand')->comment('ID Pelanggan hasil koreksi manual operator');
            $table->string('verified_meter_brand', 50)->nullable()->after('verified_customer_id')->comment('Merek meter hasil koreksi manual operator');
            $table->string('crop_path')->nullable()->after('verified_meter_brand')->comment('Path lokasi crop area kWh meter');
            $table->json('crop_box')->nullable()->after('crop_path')->comment('Koordinat bounding box crop [x1, y1, x2, y2]');
            $table->boolean('needs_verification')->default(false)->after('crop_box')->index()->comment('Penanda bahwa hasil OCR perlu diperiksa manual oleh operator');
            $table->boolean('is_verified')->default(false)->after('needs_verification')->index()->comment('Penanda apakah data telah diverifikasi/dikoreksi oleh operator');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn([
                'raw_ocr_text',
                'ocr_confidence',
                'ocr_status',
                'candidate_customer_id',
                'candidate_meter_brand',
                'verified_customer_id',
                'verified_meter_brand',
                'crop_path',
                'crop_box',
                'needs_verification',
                'is_verified',
            ]);
        });
    }
};

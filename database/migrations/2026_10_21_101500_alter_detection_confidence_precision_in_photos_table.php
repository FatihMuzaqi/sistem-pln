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
            // Ubah presisi detection_confidence menjadi decimal(8, 4) agar dapat menyimpan
            // nilai numerik asli rasio (0.0000 - 1.0000) maupun persentase (0.00 - 100.00)
            // tanpa kehilangan presisi angka di belakang koma.
            $table->decimal('detection_confidence', 8, 4)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->decimal('detection_confidence', 5, 2)->nullable()->change();
        });
    }
};

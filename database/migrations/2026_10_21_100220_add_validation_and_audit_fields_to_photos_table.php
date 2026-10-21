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
            $table->text('status_reason')->nullable()->after('status')->comment('Penjelasan alasan penetapan status verifikasi foto');
            $table->timestamp('verified_at')->nullable()->after('is_verified')->comment('Waktu petugas operator melakukan verifikasi/koreksi');
            $table->string('verified_by', 100)->nullable()->after('verified_at')->comment('Nama/NIP petugas yang memverifikasi');
            $table->json('validation_rules_applied')->nullable()->after('verified_by')->comment('Rincian aturan validasi yang dievaluasi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn([
                'status_reason',
                'verified_at',
                'verified_by',
                'validation_rules_applied',
            ]);
        });
    }
};

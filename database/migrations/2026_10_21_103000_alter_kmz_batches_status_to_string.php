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
        Schema::table('kmz_batches', function (Blueprint $table) {
            // Ubah kolom status menjadi string(30) agar mendukung status:
            // 'menunggu', 'berjalan', 'selesai', 'gagal' (dan kompatibel dengan status sebelumnya)
            $table->string('status', 30)->default('menunggu')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kmz_batches', function (Blueprint $table) {
            $table->enum('status', ['uploaded', 'extracting', 'completed', 'failed'])
                ->default('uploaded')
                ->change();
        });
    }
};

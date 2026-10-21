<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DetectionController;
use App\Http\Controllers\KmzController;
use App\Http\Controllers\SettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes - Sistem Deteksi Foto kWh (PLN UP3 Tegal)
|--------------------------------------------------------------------------
*/

// 1. Dashboard Utama
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

// 2. Modul Pemrosesan KMZ
Route::prefix('kmz')->name('kmz.')->group(function () {
    Route::get('/', [KmzController::class, 'index'])->name('index');
    Route::get('/upload', [KmzController::class, 'create'])->name('create');
    Route::post('/upload', [KmzController::class, 'store'])->name('store');
    Route::get('/{kmz}', [KmzController::class, 'show'])->name('show');
    Route::get('/{kmz}/download-original', [KmzController::class, 'downloadOriginal'])->name('download');
    Route::post('/{kmz}/process', [KmzController::class, 'processBatch'])->name('process');
    Route::post('/{kmz}/retry-failed', [KmzController::class, 'retryFailed'])->name('retry_failed');
    Route::get('/{kmz}/export', [KmzController::class, 'exportExcel'])->name('export');
});

// 3. Modul Hasil Deteksi & Foto
Route::prefix('detections')->name('detections.')->group(function () {
    Route::get('/', [DetectionController::class, 'index'])->name('index');
    Route::get('/export/excel', [DetectionController::class, 'exportExcel'])->name('export');
    Route::get('/{photo}', [DetectionController::class, 'show'])->name('show');
    Route::get('/{photo}/image', [DetectionController::class, 'servePhoto'])->name('image');
    Route::post('/{photo}/detect', [DetectionController::class, 'triggerAiDetection'])->name('detect');
    Route::post('/{photo}/ocr', [DetectionController::class, 'triggerOcr'])->name('ocr');
    Route::post('/{photo}/verify', [DetectionController::class, 'verifyManual'])->name('verify');
    Route::post('/{photo}/revalidate', [DetectionController::class, 'revalidate'])->name('revalidate');
    Route::post('/{photo}/retry', [DetectionController::class, 'retrySinglePhoto'])->name('retry');
});

// 4. Modul Pengaturan Aplikasi Sederhana
Route::prefix('settings')->name('settings.')->group(function () {
    Route::get('/', [SettingController::class, 'index'])->name('index');
    Route::post('/', [SettingController::class, 'update'])->name('update');
});

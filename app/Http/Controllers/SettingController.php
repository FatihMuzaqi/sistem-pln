<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Tampilkan halaman pengaturan aplikasi
     */
    public function index()
    {
        $settings = AppSetting::all()->groupBy('group');
        return view('settings.index', compact('settings'));
    }

    /**
     * Simpan perubahan pengaturan
     */
    public function update(Request $request)
    {
        $data = $request->except(['_token', '_method']);

        foreach ($data as $key => $value) {
            AppSetting::setValue($key, $value);
        }

        return redirect()->route('settings.index')
            ->with('success', 'Pengaturan aplikasi berhasil disimpan.');
    }
}

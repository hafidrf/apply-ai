<?php

/*
|--------------------------------------------------------------------------
| Pesan validasi (Bahasa Indonesia)
|--------------------------------------------------------------------------
| Hanya memuat aturan yang dipakai aplikasi ini + aturan umum.
| Key yang tidak ada di sini akan jatuh ke lang/en (APP_FALLBACK_LOCALE).
|
*/

return [

    'accepted' => ':attribute harus disetujui.',
    'array' => ':attribute harus berupa daftar.',
    'boolean' => ':attribute harus bernilai ya atau tidak.',
    'confirmed' => 'Konfirmasi :attribute tidak cocok.',
    'email' => ':attribute harus berupa alamat email yang valid.',
    'file' => ':attribute harus berupa file.',
    'in' => ':attribute yang dipilih tidak valid.',
    'integer' => ':attribute harus berupa angka bulat.',
    'numeric' => ':attribute harus berupa angka.',
    'string' => ':attribute harus berupa teks.',
    'url' => ':attribute harus berupa URL yang valid.',
    'exists' => ':attribute yang dipilih tidak ditemukan.',
    'unique' => ':attribute sudah terdaftar.',

    'min' => [
        'array' => ':attribute minimal berisi :min item.',
        'file' => ':attribute minimal :min kilobyte.',
        'numeric' => ':attribute minimal :min.',
        'string' => ':attribute minimal :min karakter.',
    ],

    'max' => [
        'array' => ':attribute maksimal berisi :max item.',
        'file' => ':attribute terlalu besar — maksimal :max kilobyte.',
        'numeric' => ':attribute maksimal :max.',
        'string' => ':attribute maksimal :max karakter.',
    ],

    'mimes' => ':attribute harus berupa file bertipe: :values.',
    'mimetypes' => ':attribute harus berupa file bertipe: :values.',

    'required' => ':attribute wajib diisi.',
    'required_if' => ':attribute wajib diisi.',
    'required_with' => ':attribute wajib diisi.',

    // Pesan ini muncul saat PHP membuang file karena melebihi batas server
    'uploaded' => ':attribute gagal diunggah. Kemungkinan ukuran file melebihi batas server — coba kompres file atau perkecil ukurannya.',

    'custom' => [
        'link' => [
            'required_if' => 'Masukkan URL profil/portfolio dulu.',
            'max' => 'URL terlalu panjang.',
        ],
        'pdfs' => [
            'required_if' => 'Pilih minimal satu file PDF dulu.',
            'array' => 'Daftar file PDF tidak valid.',
        ],
        'pdfs.*' => [
            'mimes' => 'Semua file harus berformat PDF.',
            'max' => 'Ada file PDF yang terlalu besar — maksimal 40 MB per file.',
            'file' => 'File yang diunggah tidak valid.',
            'uploaded' => 'Ada file PDF yang gagal diunggah (maksimal 40 MB per file). Kompres dulu, lalu coba lagi.',
        ],
        'password' => [
            'min' => 'Password minimal :min karakter.',
        ],
    ],

    'attributes' => [
        'name' => 'nama',
        'email' => 'email',
        'password' => 'password',
        'provider' => 'provider',
        'api_key' => 'API key',
        'base_url' => 'base URL',
        'default_model' => 'model default',
        'label' => 'label',
        'source' => 'sumber',
        'raw_text' => 'teks',
        'pdf' => 'file PDF CV',
        'pdfs' => 'file-file PDF',
        'pdfs.*' => 'file PDF',
        'link' => 'URL',
        'lang' => 'bahasa',
        'input_type' => 'jenis input',
        'channel_override' => 'kanal',
        'profile_id' => 'profil',
        'output_lang' => 'bahasa pesan',
        'llm_key_id' => 'provider AI',
    ],

];

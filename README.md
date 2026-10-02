# Asisten Apply Kerja

Aplikasi web untuk menyusun lamaran kerja dari profil kandidat dan informasi lowongan. Tempel teks, unggah PDF atau gambar, atau tambahkan link. Aplikasi memproses input dengan model AI pilihan Anda dan membantu menulis pesan yang siap disunting serta dikirim.

## Fitur

- Profil kandidat dari teks, beberapa PDF, gambar, dan link.
- Input lowongan dari teks, PDF, gambar, dan link, termasuk posting media sosial.
- BYOK: gunakan API key provider AI sendiri. Key disimpan terenkripsi oleh Laravel.
- Dukungan provider OpenAI-compatible dan Google Gemini.
- Generate pesan sesuai kanal email, LinkedIn, WhatsApp, portal, atau DM sosial.
- Revisi pesan dengan instruksi bahasa natural, misalnya "lebih ringkas dan natural".
- Riwayat pesan dikelompokkan per bulan, termasuk pesan hasil revisi dan salinan.
- Analisis kecocokan requirement serta catatan persiapan interview.
- OCR gambar di browser sebagai opsi fallback.

## Teknologi

- Backend: Laravel 13, PHP 8.3+, Sanctum, SQLite untuk pengembangan atau MySQL untuk produksi.
- Frontend: React, TypeScript, Vite, Tailwind CSS.
- Ekstraksi dokumen: smalot/pdfparser dan Symfony DomCrawler.

## Prasyarat

- PHP 8.3 atau lebih baru dengan ekstensi `curl`, `fileinfo`, `mbstring`, `openssl`, `pdo_sqlite`, dan `pdo_mysql` sesuai database yang digunakan.
- Composer.
- Node.js dan npm.

## Menjalankan secara lokal

Dari folder proyek:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File -Force database/database.sqlite
```

Atur `.env` untuk pengembangan lokal:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

Jalankan migrasi:

```powershell
php artisan migrate
```

Pasang dependency frontend dan jalankan Vite:

```powershell
cd frontend
npm ci
npm run dev
```

Di terminal kedua, dari folder proyek:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Buka `http://localhost:5173`. Vite meneruskan request `/api` ke backend di port 8000.

Di macOS atau Linux, gunakan perintah shell yang setara untuk menyalin `.env.example` ke `.env` dan membuat file SQLite.

## Konfigurasi provider AI

1. Buat akun dan masuk ke aplikasi.
2. Buka **Pengaturan**.
3. Tambahkan provider, API key, dan model.
4. Gunakan tombol **Tes** untuk memeriksa koneksi.
5. Tandai model vision bila provider dan model tersebut mendukung input gambar.

API key disimpan menggunakan encrypted cast Laravel. Simpan `APP_KEY` dengan aman dan jangan menggantinya setelah key pengguna tersimpan, karena data terenkripsi tidak dapat dibaca tanpa key yang sama.

## Pemeriksaan

Jalankan tes backend:

```powershell
php artisan test
```

Periksa tipe dan build frontend:

```powershell
cd frontend
npm run build
```

## Build produksi

Build frontend dari folder `frontend`:

```powershell
npm ci
npm run build
```

Vite menulis `index.html` dan aset hasil build ke folder `public`. Jalankan migrasi di server setelah mengatur `.env` produksi:

```bash
php artisan key:generate --force
php artisan migrate --force
php artisan config:cache
php artisan route:cache
```

Panduan hosting cPanel tersedia di [docs/DEPLOY.md](docs/DEPLOY.md).

## Keamanan dan konfigurasi

- Jangan commit `.env`, API key, token, database lokal, log, atau kredensial hosting.
- Atur `APP_DEBUG=false` di produksi.
- Arahkan document root web server ke folder `public`.
- Gunakan HTTPS di lingkungan produksi.
- Pastikan `APP_KEY` memiliki backup yang aman.

## Lisensi

Belum ditentukan. Tambahkan file lisensi sebelum mendistribusikan proyek secara publik.

# Deploy — Asisten Apply Kerja

Target: **apply.hafidrf.com** (subdomain di hosting cPanel yang sama dengan konsov.com).

## Ringkasan arsitektur produksi

| Bagian | Detail |
|---|---|
| Backend | Laravel 13 (PHP 8.3), docroot → `public/` |
| Frontend | React build di `public/` (sudah termasuk index.html + assets) |
| Database | MySQL (buat DB baru di cPanel) |
| API base | Same-origin (`/api/...`) — tidak perlu CORS di produksi |

## Langkah 1 — Siapkan di cPanel (manual, sekali saja)

1. **Buat subdomain** `apply.hafidrf.com`
   - cPanel → Domains / Subdomains
   - Document root: `/hafidrf.com/apply/public` ← **wajib** ke folder `public`
2. **Buat database MySQL**
   - cPanel → MySQL Databases
   - Buat DB + user, catat: `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
3. **Cek PHP version** subdomain → pilih **PHP 8.3** atau lebih baru
4. **Cek ekstensi** aktif: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`

## Langkah 2 — Build frontend untuk produksi

```powershell
cd C:\Cursor\apply-ai\frontend
npm run build          # → output ke ..\public\
```

## Langkah 3 — Upload

```powershell
cd C:\Cursor\apply-ai
node scripts\upload-apply-ftp.mjs
```

Script akan:
- Membungkus project jadi `apply-ai-deploy.zip` (vendor/ ikut, node_modules/.git tidak)
- Upload ke `/hafidrf.com/apply` via FTP (profil `konsov.com` dari `konsov/.vscode/sftp.json`)

## Langkah 4 — Extract & konfigurasi di cPanel

1. **File Manager** → masuk `/hafidrf.com/apply` → **Extract** `apply-ai-deploy.zip`
2. **Buat file `.env`** (copy dari `.env.example`) — nilai penting:

```ini
APP_NAME="Asisten Apply Kerja"
APP_ENV=production
APP_DEBUG=false
APP_KEY=                      # diisi di langkah 5
APP_URL=https://apply.hafidrf.com

APP_LOCALE=id
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=xxxxx_apply_ai
DB_USERNAME=xxxxx_apply
DB_PASSWORD=********

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database

FRONTEND_URL=https://apply.hafidrf.com
```

3. Hapus `apply-ai-deploy.zip` dan `.deploy-filelist.txt` dari server

## Langkah 5 — Migrasi & cache (cPanel Terminal)

```bash
cd ~/hafidrf.com/apply
php artisan key:generate --force     # isi APP_KEY
php artisan migrate --force          # buat tabel
php artisan config:cache
php artisan route:cache
```

> Kalau cPanel Terminal tidak tersedia, jalankan sejenis lewat cron sekali
> (`* * * * * cd ~/hafidrf.com/apply && php artisan migrate --force`) lalu hapus cron-nya.

## Langkah 6 — Verifikasi

1. Buka `https://apply.hafidrf.com` → harus muncul halaman Login
2. Daftar akun baru
3. Settings → tambah API key provider → klik **Tes** → harus `✅ Berhasil`
4. Profil → paste CV → Analisis → Konfirmasi
5. Lowongan → paste lowongan → Proses → Generate

## Catatan penting produksi

### Frontend routing (SPA)
`routes/web.php` sudah punya catch-all yang mengembalikan `public/index.html`.
Jika memakai Apache, pastikan `.htaccess` di `public/` mengarahkan semua request
non-file ke `index.html` (Laravel default sudah benar).

### Timeout pipeline
Pipeline generate memanggil LLM 2× berurutan (~30–90 detik total).
Kalau hosting membatasi `max_execution_time`, tambahkan di `.env`:

```ini
# Laravel tidak membaca ini otomatis — set via .htaccess atau php.ini cPanel:
# max_execution_time = 300
```

Atau di `public/.htaccess`:

```apache
<IfModule mod_php.c>
  php_value max_execution_time 300
</IfModule>
```

### OCR
OCR berjalan **di browser** (Tesseract.js), jadi **tidak butuh** Node/exec di server.
Ini penting karena shared hosting biasanya memblokir `proc_open`.

### Keamanan key
- API key user dienkripsi dengan `APP_ENCRYPTED_CAST` (Laravel `encrypted` cast) memakai `APP_KEY`.
- **Jangan pernah ganti `APP_KEY` setelah ada data** — key user tidak bisa didekripsi lagi.
- Backup `APP_KEY` di tempat aman.

### Backup
DB berisi profil & API key user. Backup rutin lewat cPanel → Backup Wizard.

## Update / redeploy

```powershell
cd C:\Cursor\apply-ai\frontend; npm run build
cd C:\Cursor\apply-ai; node scripts\upload-apply-ftp.mjs
```

Lalu di server: extract ulang, `php artisan config:cache`, `php artisan route:cache`,
`php artisan migrate --force` (kalau ada migrasi baru).

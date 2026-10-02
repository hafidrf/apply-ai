/**
 * Deploy Asisten Apply Kerja ke shared hosting (cPanel) via FTP.
 *
 * Strategi: bungkus project jadi satu ZIP (tanpa node_modules/.git),
 * upload ZIP-nya, lalu ekstrak di cPanel File Manager.
 * Ini jauh lebih cepat daripada upload ribuan file vendor/ satu per satu.
 *
 * Usage:
 *   node upload-apply-ftp.mjs              # bikin zip + upload
 *   node upload-apply-ftp.mjs --zip-only   # hanya bikin zip, tidak upload
 *
 * Prasyarat: profil FTP ada di konsov/.vscode/sftp.json
 */

import * as ftp from "basic-ftp";
import * as fs from "fs";
import * as path from "path";

const ROOT = path.resolve(import.meta.dirname, "..");          // c:\Cursor\apply-ai
const KONSOV = "C:/Cursor/konsov";
const SFTP_JSON = path.join(KONSOV, ".vscode", "sftp.json");

// Profil FTP + folder tujuan di hosting
const FTP_PROFILE = "konsov.com";
const REMOTE_DIR = "/hafidrf.com/apply";   // docroot: /hafidrf.com/apply/public

// Yang TIDAK ikut di-zip
const EXCLUDE = new Set(["node_modules", ".git", ".vscode", "storage/logs", "database/database.sqlite"]);

function walk(dir, base = ROOT, out = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    const rel = path.relative(base, full).replace(/\\/g, "/");

    if ([...EXCLUDE].some((ex) => rel === ex || rel.startsWith(ex + "/"))) continue;

    if (entry.isDirectory()) walk(full, base, out);
    else out.push({ full, rel });
  }
  return out;
}

async function main() {
  const zipOnly = process.argv.includes("--zip-only");

  // 1. Bikin ZIP pakai PowerShell Compress-Archive (tersedia di Windows)
  const files = walk(ROOT);
  console.log(`Menyiapkan ${files.length} file...`);

  const zipName = "apply-ai-deploy.zip";
  const zipPath = path.join(ROOT, zipName);
  if (fs.existsSync(zipPath)) fs.unlinkSync(zipPath);

  const { execSync } = await import("child_process");
  const listFile = path.join(ROOT, ".deploy-filelist.txt");
  fs.writeFileSync(listFile, files.map((f) => f.rel).join("\n"), "utf8");

  execSync(
    `powershell -NoProfile -Command "Compress-Archive -Path (Get-Content '${listFile}' | ForEach-Object { Join-Path '${ROOT.replace(/\//g, "\\")}' $_ }) -DestinationPath '${zipPath.replace(/\//g, "\\")}' -Force"`,
    { stdio: "inherit" },
  );
  fs.unlinkSync(listFile);

  const sizeMB = (fs.statSync(zipPath).size / 1024 / 1024).toFixed(1);
  console.log(`✓ ZIP dibuat: ${zipName} (${sizeMB} MB)`);

  if (zipOnly) {
    console.log("--zip-only: selesai tanpa upload.");
    return;
  }

  // 2. Upload ZIP via FTP
  const cfg = JSON.parse(fs.readFileSync(SFTP_JSON, "utf8"));
  const profile = cfg.profiles[FTP_PROFILE];
  if (!profile) {
    console.error(`Profil "${FTP_PROFILE}" tidak ada di sftp.json`);
    process.exit(1);
  }

  const client = new ftp.Client(60000);
  client.ftp.verbose = false;

  try {
    await client.access({
      host: profile.host,
      port: profile.port ?? 21,
      user: profile.username,
      password: profile.password,
      secure: profile.secure ?? false,
    });

    console.log(`Terhubung ke ${profile.host}. Upload ke ${REMOTE_DIR}...`);

    // Pastikan folder tujuan ada
    await client.ensureDir(REMOTE_DIR);
    await client.uploadFrom(zipPath, `${REMOTE_DIR}/${zipName}`);

    console.log("✓ ZIP terupload.");
    console.log("");
    console.log("LANGKAH SELANJUTNYA (di cPanel File Manager):");
    console.log(`  1. Masuk ke ${REMOTE_DIR}`);
    console.log(`  2. Extract ${zipName}`);
    console.log("  3. Buat file .env dari .env.example, isi kredensial MySQL + APP_KEY");
    console.log("  4. Set document root subdomain ke " + REMOTE_DIR + "/public");
    console.log("  5. Jalankan migrasi (via cPanel Terminal): php artisan migrate --force");
    console.log("  6. Hapus " + zipName + " dan .deploy-filelist.txt setelah selesai");
  } catch (err) {
    console.error("Upload gagal:", err.message);
    process.exit(1);
  } finally {
    client.close();
  }
}

main();

// Vite menulis hasil build ke ../public dengan `emptyOutDir: false`, supaya file
// penting milik server (.htaccess, index.php, robots.txt) tidak ikut terhapus.
//
// Efek sampingnya: chunk ber-hash dari build lama terus menumpuk — pernah sampai
// 43 file / 6 MB. Folder assets/ isinya 100% hasil build, jadi aman dibersihkan
// sebelum build berikutnya. Dijalankan otomatis lewat `prebuild` di package.json.
import { rmSync, existsSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, resolve } from 'node:path'

const here = dirname(fileURLToPath(import.meta.url))
const assetsDir = resolve(here, '..', '..', 'public', 'assets')

if (existsSync(assetsDir)) {
  rmSync(assetsDir, { recursive: true, force: true })
  console.log('[clean] public/assets dibersihkan sebelum build')
}

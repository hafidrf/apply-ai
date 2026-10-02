/**
 * OCR gambar screenshot lowongan → teks.
 * Jalan di browser (WebAssembly), tidak butuh server.
 *
 * tesseract.js di-import DINAMIS: library-nya besar (~1 MB), dan OCR hanya dipakai
 * sebagai fallback manual ketika model tidak mendukung gambar. Dengan import dinamis,
 * file itu baru diunduh saat benar-benar dipakai — halaman awal jadi jauh lebih ringan.
 */
export async function ocrImage(
  file: File,
  onProgress?: (ratio: number) => void,
): Promise<string> {
  const { createWorker } = await import('tesseract.js')

  const worker = await createWorker('ind', 1, {
    logger: (m: { status: string; progress: number }) => {
      if (m.status === 'recognizing text' && onProgress) {
        onProgress(m.progress)
      }
    },
  })

  try {
    const result = await worker.recognize(file)
    return result.data.text.trim()
  } finally {
    await worker.terminate()
  }
}

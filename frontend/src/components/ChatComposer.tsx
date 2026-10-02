import { useEffect, useRef, useState } from 'react'
import { Button, Spinner } from './ui'
import { ocrImage } from '../lib/ocr'
import type { LlmKey } from '../lib/api'

interface PdfAttachment {
  id: string
  file: File
  name: string
  size: number
}

interface ImageAttachment {
  id: string
  file: File
  previewUrl: string
  name: string
  size: number
}

const MAX_PDF_MB = 40
const MAX_IMAGE_MB = 20
const IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/bmp']

/**
 * Composer gaya chat (dipakai bersama oleh Lowongan & Profil):
 * paste teks, Ctrl+V gambar (tampil sebagai thumbnail), drag & drop,
 * lampirkan PDF (banyak), dan tempel link (isinya dibaca otomatis).
 *
 * Gambar dikirim APA ADANYA ke model vision (seperti ChatGPT/Claude),
 * bukan di-OCR. OCR hanya fallback kalau model tidak mendukung gambar.
 */
export default function ChatComposer({
  keys,
  placeholder,
  hint,
  submitLabel = 'Kirim',
  busyLabel = 'AI sedang memproses...',
  footerExtra,
  appendExtra,
  onSend,
  onError,
}: {
  keys: LlmKey[]
  placeholder: string
  hint?: string
  submitLabel?: string
  busyLabel?: string
  footerExtra?: React.ReactNode
  appendExtra?: (form: FormData) => void
  onSend: (form: FormData, ctx: { hasImages: boolean; usingVision: boolean }) => Promise<void>
  onError: (msg: string) => void
}) {
  const [text, setText] = useState('')
  const [images, setImages] = useState<ImageAttachment[]>([])
  const [pdfs, setPdfs] = useState<PdfAttachment[]>([])
  const [keyId, setKeyId] = useState('')
  const [busy, setBusy] = useState(false)
  const [ocrBusy, setOcrBusy] = useState<string | null>(null)
  const [dragging, setDragging] = useState(false)
  const [notes, setNotes] = useState<string[]>([])
  const [ocrText, setOcrText] = useState('')
  const fileRef = useRef<HTMLInputElement>(null)

  // Objek URL thumbnail disimpan di ref supaya bisa dibersihkan saat komponen dilepas.
  // Tanpa ini, setiap gambar yang di-paste terus tertahan di memori (blob tidak dilepas).
  const previewUrls = useRef<string[]>([])
  useEffect(() => {
    previewUrls.current = images.map((i) => i.previewUrl)
  }, [images])
  useEffect(() => () => {
    previewUrls.current.forEach((u) => URL.revokeObjectURL(u))
  }, [])

  useEffect(() => {
    if (!keyId && keys.length) {
      const def = keys.find((k) => k.is_default) ?? keys[0]
      setKeyId(String(def.id))
    }
  }, [keys, keyId])

  const activeKey = keys.find((k) => String(k.id) === keyId)
  const modelHasVision = activeKey?.can_read_images ?? false
  const visionKeys = keys.filter((k) => k.can_read_images && k.id !== activeKey?.id)

  const addImages = (files: File[]) => {
    const accepted: ImageAttachment[] = []
    const skipped: string[] = []

    for (const f of files) {
      if (f.size / 1024 / 1024 > MAX_IMAGE_MB) {
        skipped.push(`${f.name || 'gambar'} (${(f.size / 1024 / 1024).toFixed(1)} MB — maks ${MAX_IMAGE_MB} MB)`)
        continue
      }
      accepted.push({
        id: `${f.name}-${f.size}-${Date.now()}-${Math.random()}`,
        file: f,
        previewUrl: URL.createObjectURL(f),
        name: f.name || 'tempelan.png',
        size: f.size,
      })
    }

    if (skipped.length) setNotes((n) => [...n, 'Dilewati: ' + skipped.join(', ')])
    if (accepted.length) setImages((prev) => [...prev, ...accepted])
  }

  const removeImage = (id: string) => {
    setImages((prev) => {
      const target = prev.find((i) => i.id === id)
      if (target) URL.revokeObjectURL(target.previewUrl)
      return prev.filter((i) => i.id !== id)
    })
  }

  const addPdfs = (files: File[]) => {
    const accepted: PdfAttachment[] = []
    const skipped: string[] = []

    for (const f of files) {
      const isPdf = f.type === 'application/pdf' || f.name.toLowerCase().endsWith('.pdf')
      if (!isPdf) { skipped.push(`${f.name} (bukan PDF/gambar)`); continue }
      if (f.size / 1024 / 1024 > MAX_PDF_MB) {
        skipped.push(`${f.name} (${(f.size / 1024 / 1024).toFixed(1)} MB — maks ${MAX_PDF_MB} MB)`)
        continue
      }
      accepted.push({ id: `${f.name}-${f.size}-${Date.now()}-${Math.random()}`, file: f, name: f.name, size: f.size })
    }

    if (skipped.length) setNotes((n) => [...n, 'Dilewati: ' + skipped.join(', ')])
    if (accepted.length) setPdfs((prev) => [...prev, ...accepted])
  }

  const handleFiles = (files: File[]) => {
    const imgs = files.filter((f) => IMAGE_TYPES.includes(f.type))
    const rest = files.filter((f) => !IMAGE_TYPES.includes(f.type))
    if (imgs.length) addImages(imgs)
    if (rest.length) addPdfs(rest)
  }

  const onPaste = (e: React.ClipboardEvent<HTMLTextAreaElement>) => {
    const items = e.clipboardData?.items
    if (!items) return

    const files: File[] = []
    for (const item of Array.from(items)) {
      if (item.kind === 'file') {
        const f = item.getAsFile()
        if (f) files.push(f)
      }
    }
    if (files.length) {
      e.preventDefault()
      handleFiles(files)
    }
  }

  const onDrop = (e: React.DragEvent) => {
    e.preventDefault()
    setDragging(false)
    const files = Array.from(e.dataTransfer?.files ?? [])
    if (files.length) handleFiles(files)
  }

  const runOcr = async () => {
    if (images.length === 0) return
    setNotes([])
    const parts: string[] = []

    for (let i = 0; i < images.length; i++) {
      const img = images[i]
      setOcrBusy(`${i + 1}/${images.length}`)
      try {
        const t = await ocrImage(img.file)
        parts.push(`[Gambar ${i + 1}: ${img.name}]\n${t}`)
      } catch {
        setNotes((n) => [...n, `Gagal membaca gambar "${img.name}".`])
      }
    }

    setOcrBusy(null)

    if (parts.length) {
      const joined = parts.join('\n\n')
      setOcrText(joined)
      setText((prev) => (prev.trim() ? `${prev}\n\n${joined}` : joined))
      images.forEach((i) => URL.revokeObjectURL(i.previewUrl))
      setImages([])
      setNotes((n) => [...n, 'Gambar sudah diubah jadi teks. Periksa & rapikan teksnya sebelum dikirim.'])
    }
  }

  const send = async () => {
    onError('')
    const hasSomething = text.trim() || pdfs.length > 0 || images.length > 0 || ocrText.trim()
    if (!hasSomething) {
      onError('Tulis/paste sesuatu dulu — teks, gambar, link, atau PDF.')
      return
    }

    if (images.length > 0 && !modelHasVision) {
      onError(
        `Model "${activeKey?.default_model ?? activeKey?.provider_label ?? ''}" belum bisa membaca gambar. ` +
        'Pilih model vision di dropdown Provider AI, atau klik "Baca gambar jadi teks (OCR)".',
      )
      return
    }

    setBusy(true)
    try {
      const form = new FormData()
      form.append('raw_text', text)
      form.append('auto_links', '1')
      if (ocrText.trim()) form.append('ocr_text', ocrText)
      if (keyId) form.append('llm_key_id', keyId)
      pdfs.forEach((p) => form.append('pdfs[]', p.file))
      const usingVision = modelHasVision && images.length > 0
      if (usingVision) images.forEach((i) => form.append('images[]', i.file))
      appendExtra?.(form)

      await onSend(form, { hasImages: images.length > 0, usingVision })

      images.forEach((i) => URL.revokeObjectURL(i.previewUrl))
      setText(''); setImages([]); setPdfs([]); setNotes([]); setOcrText('')
    } catch (e) {
      onError(e instanceof Error ? e.message : 'Gagal memproses')
    } finally {
      setBusy(false)
    }
  }

  const totalPdfMb = pdfs.reduce((s, p) => s + p.size, 0) / 1024 / 1024
  const linkCount = (text.match(/https?:\/\//gi) ?? []).length

  return (
    <div
      onDragOver={(e) => { e.preventDefault(); setDragging(true) }}
      onDragLeave={() => setDragging(false)}
      onDrop={onDrop}
      className={`bg-white rounded-xl border-2 p-5 shadow-sm transition-colors ${
        dragging ? 'border-indigo-400 bg-indigo-50/40' : 'border-slate-200'
      }`}
    >
      {hint && (
        <div className="flex items-center justify-between mb-3">
          <span className="text-xs text-slate-400">{hint}</span>
        </div>
      )}

      <textarea
        rows={images.length || pdfs.length ? 5 : 8}
        value={text}
        onChange={(e) => setText(e.target.value)}
        onPaste={onPaste}
        placeholder={placeholder}
        className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y"
      />

      {dragging && <p className="text-sm text-indigo-600 mt-2">Lepaskan file di sini…</p>}

      {/* Thumbnail gambar — seperti chat AI */}
      {images.length > 0 && (
        <div className="mt-3">
          <div className="flex flex-wrap gap-2">
            {images.map((img) => (
              <div key={img.id} className="relative group">
                <img
                  src={img.previewUrl}
                  alt={img.name}
                  className="h-24 w-24 object-cover rounded-lg border border-slate-200"
                />
                <button
                  type="button"
                  onClick={() => removeImage(img.id)}
                  className="absolute -top-1.5 -right-1.5 w-5 h-5 rounded-full bg-red-600 text-white text-xs leading-none opacity-0 group-hover:opacity-100 transition-opacity"
                  title="Hapus gambar"
                >
                  ×
                </button>
                <p className="text-[10px] text-slate-400 mt-0.5 max-w-24 truncate">{img.name}</p>
              </div>
            ))}
          </div>

          {modelHasVision ? (
            <p className="text-xs text-emerald-700 mt-2">
              👁 {images.length} gambar akan dibaca langsung oleh {activeKey?.default_model ?? 'model'}.
            </p>
          ) : (
            <div className="mt-2 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
              <p className="text-xs text-amber-800">
                ⚠ Model <strong>{activeKey?.default_model ?? activeKey?.provider_label ?? '(belum dipilih)'}</strong> belum
                bisa membaca gambar.
              </p>

              {visionKeys.length > 0 && (
                <div className="mt-2">
                  <p className="text-xs text-amber-800 mb-1">Pindah ke model yang bisa baca gambar:</p>
                  <div className="flex flex-wrap gap-1.5">
                    {visionKeys.map((k) => (
                      <button
                        key={k.id}
                        type="button"
                        onClick={() => setKeyId(String(k.id))}
                        className="text-xs px-2.5 py-1 rounded-lg bg-white border border-emerald-300 text-emerald-800 hover:bg-emerald-50"
                      >
                        👁 {k.provider_label}{k.default_model ? ` · ${k.default_model}` : ''}
                      </button>
                    ))}
                  </div>
                </div>
              )}

              <p className="text-xs text-amber-700 mt-2">
                Atau ubah gambarnya jadi teks (kurang akurat untuk screenshot dengan banyak elemen UI):
              </p>
              <button
                type="button"
                onClick={runOcr}
                disabled={!!ocrBusy}
                className="mt-1.5 text-xs px-3 py-1 rounded-lg bg-white border border-amber-300 text-amber-800 hover:bg-amber-100 disabled:opacity-50"
              >
                {ocrBusy ? `Membaca gambar ${ocrBusy}…` : '🔤 Baca gambar jadi teks (OCR)'}
              </button>
            </div>
          )}
        </div>
      )}

      {/* PDF terlampir */}
      {pdfs.length > 0 && (
        <div className="mt-3 border border-slate-200 rounded-lg divide-y divide-slate-100">
          {pdfs.map((p) => (
            <div key={p.id} className="flex items-center justify-between gap-2 px-3 py-2">
              <div className="min-w-0">
                <p className="text-sm truncate">📄 {p.name}</p>
                <p className="text-xs text-slate-400">{(p.size / 1024 / 1024).toFixed(2)} MB</p>
              </div>
              <button
                type="button"
                onClick={() => setPdfs((prev) => prev.filter((x) => x.id !== p.id))}
                className="text-xs text-red-600 hover:underline shrink-0"
              >
                Hapus
              </button>
            </div>
          ))}
          <div className="px-3 py-1.5 text-xs text-slate-500 bg-slate-50">
            {pdfs.length} PDF · {totalPdfMb.toFixed(2)} MB
          </div>
        </div>
      )}

      {notes.length > 0 && (
        <div className="mt-2 bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-lg px-3 py-2 space-y-0.5">
          {notes.map((n, i) => <p key={i}>⚠ {n}</p>)}
        </div>
      )}

      {ocrBusy && (
        <div className="mt-2">
          <Spinner label={`OCR gambar ${ocrBusy}…`} />
        </div>
      )}

      <div className="mt-3 flex flex-wrap gap-3 items-end">
        <button
          type="button"
          onClick={() => fileRef.current?.click()}
          className="px-3 py-2 rounded-lg border border-slate-300 text-sm hover:bg-slate-50"
          title="Lampirkan gambar atau PDF"
        >
          📎 Lampirkan
        </button>
        <input
          ref={fileRef}
          type="file"
          multiple
          accept="image/*,.pdf,application/pdf"
          className="hidden"
          onChange={(e) => { handleFiles(Array.from(e.target.files ?? [])); e.target.value = '' }}
        />

        <div className="w-56">
          <label className="text-xs text-slate-500 mb-1 block">Provider AI</label>
          <select
            value={keyId}
            onChange={(e) => setKeyId(e.target.value)}
            className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            {keys.length === 0 && <option value="">— belum ada key —</option>}
            {keys.map((k) => (
              <option key={k.id} value={k.id}>
                {k.provider_label}{k.default_model ? ` · ${k.default_model}` : ''}
                {k.can_read_images ? ' 👁' : ''}{k.is_default ? ' (default)' : ''}
              </option>
            ))}
          </select>
        </div>

        {footerExtra}

        <Button onClick={send} disabled={busy || !!ocrBusy}>
          {busy ? 'Memproses...' : submitLabel}
        </Button>

        {busy && <Spinner label={busyLabel} />}
      </div>

      {linkCount > 0 && (
        <p className="text-xs text-indigo-600 mt-2">
          🔗 {linkCount} link terdeteksi — isinya akan dicoba dibaca otomatis saat dikirim.
        </p>
      )}
    </div>
  )
}

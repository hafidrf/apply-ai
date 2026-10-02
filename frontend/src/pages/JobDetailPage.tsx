import { useEffect, useState } from 'react'
import { useParams } from 'react-router-dom'
import { api, type Job, type LlmKey } from '../lib/api'
import { Button, Card, Select, ErrorMsg, Badge, Spinner } from '../components/ui'

export default function JobDetailPage() {
  const { id } = useParams<{ id: string }>()
  const [job, setJob] = useState<Job | null>(null)
  const [keys, setKeys] = useState<LlmKey[]>([])
  const [keyId, setKeyId] = useState('')
  const [outputLang, setOutputLang] = useState('id')
  const [channelOverride, setChannelOverride] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [varBusy, setVarBusy] = useState(false)
  const [revisionBusy, setRevisionBusy] = useState(false)
  const [revisionOpen, setRevisionOpen] = useState(false)
  const [revisionKind, setRevisionKind] = useState<'utama' | 'varian'>('utama')
  const [revisionInstruction, setRevisionInstruction] = useState('')
  const [copied, setCopied] = useState('')
  const [appendText, setAppendText] = useState('')
  const [appendFiles, setAppendFiles] = useState<File[]>([])
  const [appendBusy, setAppendBusy] = useState(false)

  const load = async () => {
    if (!id) return
    try {
      const [j, ks] = await Promise.all([
        api.get<Job>(`/jobs/${id}`),
        api.get<LlmKey[]>('/llm-keys').catch(() => []),
      ])
      setJob(j)
      setKeys(ks)
      const def = ks.find((k) => k.is_default)
      if (def && !keyId) setKeyId(String(def.id))
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Gagal memuat')
    }
  }

  useEffect(() => { load() }, [id])

  const generate = async () => {
    setError('')
    if (!id) {
      setError('Lowongan tidak dikenali. Buka lagi dari daftar Lowongan.')
      return
    }
    setBusy(true)
    try {
      const body: Record<string, unknown> = { output_lang: outputLang }
      if (channelOverride) body.channel_override = channelOverride
      if (keyId) body.llm_key_id = Number(keyId)
      const r = await api.post<Job>(`/jobs/${id}/generate`, body)
      setJob(r)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Gagal generate')
    } finally {
      setBusy(false)
    }
  }

  const makeVariant = async () => {
    setError('')
    if (!id) {
      setError('Lowongan tidak dikenali. Buka lagi dari daftar Lowongan.')
      return
    }
    setVarBusy(true)
    try {
      const r = await api.post<Job>(`/jobs/${id}/variant`, { output_lang: outputLang })
      setJob(r)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Gagal membuat varian')
    } finally {
      setVarBusy(false)
    }
  }

  const openRevision = (kind: 'utama' | 'varian') => {
    setRevisionKind(kind)
    setRevisionInstruction('')
    setRevisionOpen(true)
  }

  const revise = async () => {
    setError('')
    if (!id) {
      setError('Lowongan tidak dikenali. Buka lagi dari daftar Lowongan.')
      return
    }
    if (revisionInstruction.trim().length < 3) {
      setError('Tulis arahan singkat, misalnya: "lebih natural dan ringkas".')
      return
    }

    setRevisionBusy(true)
    try {
      const body: Record<string, unknown> = {
        instruction: revisionInstruction.trim(),
        kind: revisionKind,
        output_lang: outputLang,
      }
      if (keyId) body.llm_key_id = Number(keyId)
      const updated = await api.post<Job>(`/jobs/${id}/revise`, body)
      setJob(updated)
      setRevisionInstruction('')
      setRevisionOpen(false)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Gagal merevisi pesan')
    } finally {
      setRevisionBusy(false)
    }
  }

  const appendPart = async () => {
    setError('')
    if (!id) {
      setError('Lowongan tidak dikenali. Buka lagi dari daftar Lowongan.')
      return
    }
    if (!appendText.trim() && appendFiles.length === 0) {
      setError('Paste teks lanjutan atau lampirkan screenshot/PDF bagian berikutnya.')
      return
    }
    setAppendBusy(true)
    try {
      const form = new FormData()
      if (appendText.trim()) form.append('raw_text', appendText)
      appendFiles.forEach((f) => {
        if (f.type === 'application/pdf' || f.name.toLowerCase().endsWith('.pdf')) form.append('pdfs[]', f)
        else form.append('images[]', f)
      })
      const r = await api.postForm<Job>(`/jobs/${id}/append`, form)
      setJob(r)
      setAppendText('')
      setAppendFiles([])
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Gagal menambah bagian')
    } finally {
      setAppendBusy(false)
    }
  }

  const copy = async (text: string, tag: string, kind: 'utama' | 'varian' = 'utama') => {
    if (!navigator.clipboard) {
      setError('Browser ini tidak mengizinkan akses clipboard. Copy manual dari kotak pesan di bawah.')
      return
    }
    try {
      await navigator.clipboard.writeText(text)
    } catch {
      setError('Browser menolak akses clipboard. Copy manual dari kotak pesan di bawah.')
      return
    }
    setCopied(tag)
    setTimeout(() => setCopied(''), 2000)

    // Catat ke Riwayat: pesan ini akan/sudah dikirim
    if (!id) return
    try {
      await api.post(`/jobs/${id}/copied`, { kind })
      setCopied(`${tag}-logged`)
      setTimeout(() => setCopied(''), 2000)
    } catch {
      // gagal mencatat riwayat bukan alasan menggagalkan copy
    }
  }

  if (!job) {
    return <div>{error ? <ErrorMsg>{error}</ErrorMsg> : <Spinner label="Memuat..." />}</div>
  }

  const draft = job.drafts[0]
  const parsed = job.parsed as Record<string, unknown> | null

  return (
    <div className="space-y-6">
      <div className="flex items-start justify-between flex-wrap gap-2">
        <div>
          <h1 className="text-2xl font-bold">{job.position ?? 'Posisi tidak terdeteksi'}</h1>
          {job.company && <p className="text-sm text-slate-500">{job.company}</p>}
        </div>
        <div className="flex items-center gap-2">
          {job.channel && <Badge color="indigo">kanal: {CHANNEL_LABELS[job.channel] ?? job.channel}</Badge>}
          {typeof parsed?.gaya_posting === 'string' && parsed.gaya_posting && (
            <Badge color="slate">gaya: {parsed.gaya_posting}</Badge>
          )}
          <Badge color={job.status === 'composed' ? 'green' : job.status === 'failed' ? 'red' : 'slate'}>
            {job.status}
          </Badge>
        </div>
      </div>

      {/* Lowongan bentuk thread / info terpisah */}
      {parsed?.lengkap === false && (
        <Card className="border-indigo-300 bg-indigo-50/40">
          <p className="text-sm text-indigo-900">
            📎 <strong>Info lowongan ini sepertinya baru sebagian</strong> (bentuk thread / beberapa bagian).
            Ada bagian lanjutannya? Tempel di bawah — sifatnya opsional, tidak menghalangi pembuatan pesan.
          </p>
        </Card>
      )}

      <ErrorMsg>{error || job.error}</ErrorMsg>

      {/* Sumber input yang dibaca */}
      {(job.input_meta?.sources?.length ?? 0) > 0 && (
        <Card>
          <h2 className="font-semibold mb-2">Sumber Input</h2>
          <div className="space-y-1 text-xs">
            {job.input_meta!.sources!.map((s, i) => (
              <div key={i} className="flex items-start gap-2">
                <span className="shrink-0">{s.type === 'link' ? '🔗' : s.type === 'pdf' ? '📄' : '📝'}</span>
                <span className={s.error ? 'text-amber-700' : 'text-slate-600'}>
                  {s.url ?? s.name ?? 'teks yang ditempel'}
                  {s.error
                    ? ` — ${s.error}`
                    : ` · ${s.chars.toLocaleString('id-ID')} karakter${s.title ? ` · "${s.title}"` : ''}`}
                </span>
              </div>
            ))}
            <p className="text-slate-400 pt-1">
              Total {Number(job.input_meta?.total_chars ?? 0).toLocaleString('id-ID')} karakter
            </p>
          </div>
        </Card>
      )}

      {/* Info lowongan */}
      {parsed && (
        <Card>
          <h2 className="font-semibold mb-3">Info Lowongan</h2>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2 text-sm">
            <Info label="Lokasi" value={str(parsed.lokasi)} />
            <Info label="Tipe kerja" value={str(parsed.tipe_kerja)} />
            <Info label="Recruiter" value={str(parsed.nama_recruiter)} />
            <Info label="Cara melamar" value={str(parsed.cara_melamar)} />
          </div>
          {Array.isArray(parsed.requirement_wajib) && (parsed.requirement_wajib as string[]).length > 0 && (
            <div className="mt-4">
              <p className="text-xs font-semibold text-slate-500 uppercase mb-1">Requirement Wajib</p>
              <ul className="text-sm list-disc list-inside space-y-0.5">
                {(parsed.requirement_wajib as string[]).map((r, i) => <li key={i}>{r}</li>)}
              </ul>
            </div>
          )}
        </Card>
      )}

      {/* Tambah bagian (untuk lowongan bentuk thread) */}
      <Card>
        <details>
          <summary className="cursor-pointer text-sm font-medium text-slate-700">
            ➕ Tambah bagian lain dari lowongan ini (thread 1/2, 2/2, screenshot lanjutan)
          </summary>
          <div className="mt-3 space-y-2">
            <p className="text-xs text-slate-500">
              Paste teks lanjutan atau lampirkan screenshot/PDF bagian berikutnya — sistem akan
              menggabungkannya dengan info yang sudah ada, lalu memparse ulang.
            </p>
            <textarea
              rows={4}
              value={appendText}
              onChange={(e) => setAppendText(e.target.value)}
              placeholder="Paste bagian lanjutan di sini (boleh juga Ctrl+V gambar)..."
              className="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500"
              onPaste={(e) => {
                const files: File[] = []
                for (const item of Array.from(e.clipboardData.items)) {
                  if (item.kind === 'file') {
                    const f = item.getAsFile()
                    if (f) files.push(f)
                  }
                }
                if (files.length) {
                  e.preventDefault()
                  setAppendFiles((prev) => [...prev, ...files])
                }
              }}
            />
            <div className="flex items-center gap-3 flex-wrap">
              <input
                type="file"
                multiple
                accept="image/*,.pdf,application/pdf"
                onChange={(e) => {
                  setAppendFiles((prev) => [...prev, ...Array.from(e.target.files ?? [])])
                  e.target.value = ''
                }}
                className="text-xs"
              />
              {appendFiles.length > 0 && (
                <span className="text-xs text-slate-500">{appendFiles.length} file siap dikirim</span>
              )}
              <Button onClick={appendPart} disabled={appendBusy}>
                {appendBusy ? 'Menggabungkan...' : 'Tambah & Parse Ulang'}
              </Button>
              {appendBusy && <Spinner label="Menggabungkan bagian baru..." />}
            </div>
          </div>
        </details>
      </Card>

      {/* Susun pesan */}
      {job.status !== 'composed' && (
        <Card>
          <h2 className="font-semibold mb-4">Susun Pesan Lamaran</h2>
          <div className="flex flex-wrap gap-3 items-end">
            <div className="w-48">
              <label className="text-xs text-slate-500 mb-1 block">Provider AI</label>
              <Select value={keyId} onChange={(e) => setKeyId(e.target.value)}>
                {keys.length === 0 && <option value="">— belum ada key —</option>}
                {keys.map((k) => (
                  <option key={k.id} value={k.id}>
                    {k.provider_label}{k.default_model ? ` · ${k.default_model}` : ''}{k.is_default ? ' (default)' : ''}
                  </option>
                ))}
              </Select>
            </div>
            <div className="w-40">
              <label className="text-xs text-slate-500 mb-1 block">Bahasa pesan</label>
              <Select value={outputLang} onChange={(e) => setOutputLang(e.target.value)}>
                <option value="id">Indonesia</option>
                <option value="en">English</option>
              </Select>
            </div>
            <div className="w-48">
              <label className="text-xs text-slate-500 mb-1 block">Kanal (override)</label>
              <Select value={channelOverride} onChange={(e) => setChannelOverride(e.target.value)}>
                <option value="">sesuai deteksi ({job.channel ?? 'belum ada'})</option>
                <option value="email">Email</option>
                <option value="linkedin">LinkedIn DM</option>
                <option value="whatsapp">WhatsApp DM</option>
                <option value="dm">DM Sosial (Threads/IG/X)</option>
                <option value="portal">Portal / Cover letter</option>
              </Select>
            </div>
            <Button onClick={generate} disabled={busy}>
              {busy ? 'Menyusun...' : '🚀 Generate Pesan'}
            </Button>
          </div>
          {busy && (
            <div className="mt-3">
              <Spinner label="Pipeline berjalan: matching → reframing → penulisan. Bisa makan 1-3 menit..." />
            </div>
          )}
        </Card>
      )}

      {/* Hasil */}
      {draft && (
        <>
          <Card className="border-emerald-200">
            <div className="flex items-center justify-between mb-3">
              <h2 className="font-semibold">✉️ Pesan Siap Kirim ({draft.channel})</h2>
              <div className="flex gap-2 flex-wrap">
                <Button variant="secondary" onClick={() => openRevision('utama')}>
                  ✨ Edit dengan AI
                </Button>
                <Button onClick={() => copy(draft.message_text, 'main', 'utama')}>
                  {copied === 'main'
                    ? '✓ Tersalin!'
                    : copied === 'main-logged'
                      ? '✓ Tersalin & dicatat'
                      : '📋 Copy'}
                </Button>
              </div>
            </div>
            <pre className="whitespace-pre-wrap text-sm bg-slate-50 border border-slate-200 rounded-lg p-4 font-sans">
              {draft.message_text}
            </pre>
            {draft.notes?.jumlah_kata != null && (
              <p className="text-xs text-slate-400 mt-2">{draft.notes.jumlah_kata} kata</p>
            )}
          </Card>

          {draft.variant_text ? (
            <Card>
              <div className="flex items-center justify-between mb-3">
                <h2 className="font-semibold">🎨 Varian Nada Alternatif</h2>
                <div className="flex gap-2 flex-wrap">
                  <Button variant="secondary" onClick={() => openRevision('varian')}>
                    ✨ Edit dengan AI
                  </Button>
                  <Button variant="secondary" onClick={() => copy(draft.variant_text!, 'variant', 'varian')}>
                    {copied === 'variant'
                      ? '✓ Tersalin!'
                      : copied === 'variant-logged'
                        ? '✓ Tersalin & dicatat'
                        : '📋 Copy'}
                  </Button>
                </div>
              </div>
              <pre className="whitespace-pre-wrap text-sm bg-slate-50 border border-slate-200 rounded-lg p-4 font-sans">
                {draft.variant_text}
              </pre>
            </Card>
          ) : (
            <Card>
              <div className="flex items-center justify-between flex-wrap gap-3">
                <div>
                  <h2 className="font-semibold">🎨 Mau versi nada lain?</h2>
                  <p className="text-xs text-slate-500 mt-0.5">
                    Buat 1 varian alternatif dari pesan di atas (fakta & requirement tetap sama).
                  </p>
                </div>
                <Button variant="secondary" onClick={makeVariant} disabled={varBusy}>
                  {varBusy ? 'Membuat...' : 'Buat Varian Nada'}
                </Button>
              </div>
            </Card>
          )}

          {revisionOpen && (
            <Card className="border-indigo-200">
              <div className="flex items-start justify-between gap-3 flex-wrap">
                <div>
                  <h2 className="font-semibold">✨ Edit pesan dengan AI</h2>
                  <p className="text-xs text-slate-500 mt-1">
                    Beri arahan seperti ke asisten. Fakta profil dan lowongan tetap dijaga.
                  </p>
                </div>
                <Badge color="indigo">{revisionKind === 'varian' ? 'varian' : 'pesan utama'}</Badge>
              </div>

              <div className="flex flex-wrap gap-2 mt-3">
                {[
                  'Lebih natural dan hangat',
                  'Ringkas, langsung ke inti',
                  'Lebih profesional, tetap human',
                  'Perkuat kecocokan dengan lowongan',
                ].map((suggestion) => (
                  <button
                    key={suggestion}
                    type="button"
                    onClick={() => setRevisionInstruction(suggestion)}
                    className="px-2.5 py-1 rounded-full border border-slate-200 bg-white text-xs text-slate-600 hover:border-indigo-300 hover:text-indigo-700"
                  >
                    {suggestion}
                  </button>
                ))}
              </div>

              <textarea
                rows={3}
                maxLength={1000}
                value={revisionInstruction}
                onChange={(e) => setRevisionInstruction(e.target.value)}
                onKeyDown={(e) => {
                  if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') revise()
                }}
                placeholder="Contoh: pembuka terlalu kaku. Buat lebih personal, pertahankan angka pencapaian, dan jangan lebih dari 130 kata."
                className="w-full mt-3 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y"
              />
              <div className="flex items-center justify-between gap-3 mt-2 flex-wrap">
                <p className="text-xs text-slate-400">Ctrl+Enter untuk revisi · {revisionInstruction.length}/1000</p>
                <div className="flex gap-2">
                  <Button variant="secondary" onClick={() => setRevisionOpen(false)} disabled={revisionBusy}>
                    Batal
                  </Button>
                  <Button onClick={revise} disabled={revisionBusy || revisionInstruction.trim().length < 3}>
                    {revisionBusy ? 'Merevisi...' : '✨ Terapkan revisi'}
                  </Button>
                </div>
              </div>
              {revisionBusy && <div className="mt-3"><Spinner label="AI sedang memperbaiki pesan..." /></div>}
            </Card>
          )}

          {/* Catatan privat */}
          {draft.notes && (
            <Card className="border-amber-200 bg-amber-50/30">
              <h2 className="font-semibold mb-3">🔒 Catatan Privat (untuk persiapan interview)</h2>
              <p className="text-xs text-slate-500 mb-3">
                Bagian ini TIDAK ikut dikirim. Ini pengingat requirement yang direframe.
              </p>
              {(draft.notes.reframes?.length ?? 0) > 0 ? (
                <div className="space-y-2">
                  {draft.notes.reframes!.map((r, i) => (
                    <div key={i} className="border border-amber-200 bg-white rounded-lg p-3 text-sm">
                      <div className="flex items-center gap-2 mb-1 flex-wrap">
                        <Badge color="yellow">{r.teknik ?? '-'}</Badge>
                        <span className="font-medium">{r.requirement}</span>
                      </div>
                      <p className="text-slate-600">{r.persiapan_interview}</p>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-sm text-slate-500">Semua requirement wajib match — tidak ada yang direframe.</p>
              )}
              {(draft.notes.kata_kunci_ats?.length ?? 0) > 0 && (
                <div className="mt-4">
                  <p className="text-xs font-semibold text-slate-500 uppercase mb-1">Kata kunci ATS yang tersisip</p>
                  <div className="flex gap-1 flex-wrap">
                    {draft.notes.kata_kunci_ats!.map((k, i) => (
                      <Badge key={i} color="indigo">{k}</Badge>
                    ))}
                  </div>
                </div>
              )}
            </Card>
          )}
        </>
      )}
    </div>
  )
}

function str(v: unknown): string | null {
  return typeof v === 'string' && v ? v : null
}

const CHANNEL_LABELS: Record<string, string> = {
  email: 'Email',
  linkedin: 'LinkedIn DM',
  whatsapp: 'WhatsApp DM',
  dm: 'DM Sosial (Threads/IG/X)',
  portal: 'Portal / Cover letter',
}

function Info({ label, value }: { label: string; value: string | null }) {
  if (!value) return null
  return (
    <div>
      <span className="text-xs text-slate-400">{label}:</span>{' '}
      <span className="font-medium">{value}</span>
    </div>
  )
}

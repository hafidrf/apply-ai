import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { api, type HistoryEvent, type HistoryMonth, type HistoryResponse } from '../lib/api'
import { Button, Card, ErrorMsg, Badge, Spinner } from '../components/ui'

const EVENT_ICON: Record<string, string> = {
  generated: '🚀',
  variant: '🎨',
  revised: '✨',
  copied: '📋',
}

const EVENT_LABEL: Record<string, string> = {
  generated: 'Pesan dibuat',
  variant: 'Varian dibuat',
  revised: 'Direvisi dengan AI',
  copied: 'Disalin — siap dikirim',
}

const EVENT_COLOR: Record<string, string> = {
  generated: 'indigo',
  variant: 'slate',
  revised: 'indigo',
  copied: 'green',
}

const CHANNEL_LABEL: Record<string, string> = {
  email: 'Email',
  linkedin: 'LinkedIn DM',
  whatsapp: 'WhatsApp',
  portal: 'Portal',
  dm: 'DM Sosial',
}

type Filter = 'semua' | 'copied' | 'generated' | 'variant' | 'revised'

const FILTERS: Array<{ key: Filter; label: string }> = [
  { key: 'semua', label: 'Semua' },
  { key: 'copied', label: '📋 Disalin' },
  { key: 'generated', label: '🚀 Dibuat' },
  { key: 'variant', label: '🎨 Varian' },
  { key: 'revised', label: '✨ Direvisi' },
]

function monthLabel(key: string) {
  return new Date(`${key}-01T00:00:00`).toLocaleDateString('id-ID', {
    month: 'long',
    year: 'numeric',
  })
}

function stamp(iso: string | null) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function EventRow({ e }: { e: HistoryEvent }) {
  const [openMsg, setOpenMsg] = useState(false)
  const [copied, setCopied] = useState(false)

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(e.message_text)
      setCopied(true)
      setTimeout(() => setCopied(false), 1500)
    } catch {
      // clipboard diblokir → tampilkan pesannya supaya bisa copy manual
      setOpenMsg(true)
    }
  }

  return (
    <div className="border border-slate-200 rounded-lg p-3">
      <div className="flex items-start justify-between gap-3 flex-wrap">
        <div className="min-w-0">
          <div className="flex items-center gap-2 flex-wrap">
            <span className="text-sm">{EVENT_ICON[e.event] ?? '•'}</span>
            <span className="font-medium text-sm truncate">
              {e.position ?? '(posisi tidak terdeteksi)'}
            </span>
            {e.company && <span className="text-xs text-slate-500">di {e.company}</span>}
            <Badge color={EVENT_COLOR[e.event] ?? 'slate'}>{EVENT_LABEL[e.event] ?? e.event}</Badge>
            {e.kind === 'varian' && <Badge color="slate">varian</Badge>}
            {e.channel && <Badge color="indigo">{CHANNEL_LABEL[e.channel] ?? e.channel}</Badge>}
          </div>
          <p className="text-xs text-slate-400 mt-0.5">
            {stamp(e.created_at)}
            {e.word_count ? ` · ${e.word_count} kata` : ''}
          </p>
        </div>
        <div className="flex gap-2 shrink-0">
          <Button variant="secondary" onClick={() => setOpenMsg((v) => !v)}>
            {openMsg ? 'Sembunyikan' : 'Lihat pesan'}
          </Button>
          <Button variant="secondary" onClick={copy}>
            {copied ? '✓ Tersalin' : '📋 Copy'}
          </Button>
          {e.job_id && (
            <Link to={`/jobs/${e.job_id}`}>
              <Button variant="secondary">Lowongan →</Button>
            </Link>
          )}
        </div>
      </div>

      {openMsg && (
        <div className="mt-3 space-y-2">
          {e.instruction && (
            <p className="text-xs text-indigo-700 bg-indigo-50 border border-indigo-100 rounded-md px-3 py-2">
              Instruksi: {e.instruction}
            </p>
          )}
          <pre className="whitespace-pre-wrap text-sm bg-slate-50 border border-slate-200 rounded-lg p-3 font-sans">
            {e.message_text}
          </pre>
        </div>
      )}
    </div>
  )
}

export default function HistoryPage() {
  const [data, setData] = useState<HistoryResponse | null>(null)
  const [error, setError] = useState('')
  const [filter, setFilter] = useState<Filter>('semua')
  /** true = bulan itu ditutup user; default: hanya bulan terbaru yang terbuka */
  const [closed, setClosed] = useState<Record<string, boolean>>({})

  const load = async () => {
    try {
      setData(await api.get<HistoryResponse>('/history'))
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Gagal memuat riwayat')
    }
  }

  useEffect(() => {
    load()
  }, [])

  const months: HistoryMonth[] = useMemo(() => {
    const raw = data?.months ?? []
    if (filter === 'semua') return raw
    return raw
      .map((m) => ({ ...m, events: m.events.filter((e) => e.event === filter) }))
      .map((m) => ({ ...m, count: m.events.length }))
      .filter((m) => m.events.length > 0)
  }, [data, filter])

  const shown = months.reduce((sum, m) => sum + m.count, 0)
  const totalCopied = data?.months.reduce(
    (sum, m) => sum + m.events.filter((e) => e.event === 'copied').length,
    0,
  ) ?? 0

  const isOpen = (key: string, index: number) =>
    key in closed ? !closed[key] : index === 0

  const toggle = (key: string, index: number) =>
    setClosed((c) => ({ ...c, [key]: isOpen(key, index) }))

  const toggleAll = (open: boolean) => {
    const next: Record<string, boolean> = {}
    months.forEach((m) => {
      next[m.key] = !open
    })
    setClosed(next)
  }

  return (
    <div className="space-y-6">
      <div className="flex items-start justify-between flex-wrap gap-3">
        <div>
          <h1 className="text-2xl font-bold">Riwayat Lamaran</h1>
          <p className="text-sm text-slate-500 mt-1">
            Catatan otomatis setiap kali pesan dibuat atau disalin — jadi Anda tahu pesan apa yang
            pernah dikirim ke lowongan mana.
          </p>
        </div>
        {months.length > 0 && (
          <div className="flex gap-2">
            <Button variant="secondary" onClick={() => toggleAll(true)}>Buka semua</Button>
            <Button variant="secondary" onClick={() => toggleAll(false)}>Tutup semua</Button>
          </div>
        )}
      </div>

      <ErrorMsg>{error}</ErrorMsg>

      {!data && !error && <Spinner label="Memuat riwayat..." />}

      {data && data.total === 0 && (
        <Card>
          <p className="text-sm text-slate-500">
            Belum ada riwayat. Buka sebuah lowongan, tekan <strong>Generate Pesan</strong>, lalu{' '}
            <strong>📋 Copy</strong> — keduanya otomatis tercatat di sini.
          </p>
        </Card>
      )}

      {data && data.total > 0 && (
        <>
          <div className="flex items-center gap-2 flex-wrap">
            {FILTERS.map((f) => (
              <button
                key={f.key}
                type="button"
                onClick={() => setFilter(f.key)}
                className={`px-3 py-1.5 rounded-full text-xs font-medium border transition-colors ${
                  filter === f.key
                    ? 'bg-indigo-600 text-white border-indigo-600'
                    : 'bg-white text-slate-600 border-slate-300 hover:bg-slate-50'
                }`}
              >
                {f.label}
              </button>
            ))}
            <span className="text-xs text-slate-400 ml-1">
              {shown} dari {data.total} catatan
              {totalCopied > 0 ? ` · ${totalCopied} kali disalin` : ''}
            </span>
          </div>

          {months.length === 0 ? (
            <Card>
              <p className="text-sm text-slate-500">Tidak ada catatan jenis ini.</p>
            </Card>
          ) : (
            <div className="space-y-4">
              {months.map((m, index) => {
                const open = isOpen(m.key, index)
                return (
                  <div key={m.key}>
                    <button
                      type="button"
                      onClick={() => toggle(m.key, index)}
                      className="w-full flex items-center justify-between gap-3 px-3 py-2 rounded-lg bg-white border border-slate-200 hover:bg-slate-50 transition-colors"
                    >
                      <span className="flex items-center gap-2 min-w-0">
                        <span
                          className={`text-slate-400 text-xs transition-transform ${open ? 'rotate-90' : ''}`}
                        >
                          ▶
                        </span>
                        <span className="font-semibold text-sm capitalize">{monthLabel(m.key)}</span>
                        <Badge color="slate">{m.count} catatan</Badge>
                      </span>
                      <span className="text-xs text-slate-400">{open ? 'tutup' : 'buka'}</span>
                    </button>

                    {open && (
                      <div className="mt-2 space-y-2 pl-1">
                        {m.events.map((e) => (
                          <EventRow key={e.id} e={e} />
                        ))}
                      </div>
                    )}
                  </div>
                )
              })}
            </div>
          )}
        </>
      )}
    </div>
  )
}

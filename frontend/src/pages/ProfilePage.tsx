import { useEffect, useState, type ReactNode } from 'react'
import { api, type Profile, type ProfileData, type LlmKey } from '../lib/api'
import ProfileComposer from '../components/ProfileComposer'
import { Button, Card, Textarea, ErrorMsg, Badge } from '../components/ui'

type Exp = {
  posisi?: string
  perusahaan?: string | null
  periode?: string | null
  lokasi?: string | null
  ringkasan?: string | null
  pencapaian?: string[]
  teknologi?: string[]
}
type Edu = { institusi?: string; jurusan?: string | null; tahun?: string | null; detail?: string | null }
type Cert = { nama?: string; penerbit?: string | null; tahun?: string | null }
type Lang = { bahasa?: string; level?: string | null }

type ChipTone = 'slate' | 'indigo' | 'emerald'

const TONES: Record<ChipTone, string> = {
  slate: 'bg-slate-100 text-slate-700',
  indigo: 'bg-indigo-50 text-indigo-700 border border-indigo-200',
  emerald: 'bg-emerald-50 text-emerald-700 border border-emerald-200',
}

function Chip({ children, tone = 'slate' }: { children: ReactNode; tone?: ChipTone }) {
  return <span className={`text-xs px-2 py-0.5 rounded-full ${TONES[tone]}`}>{children}</span>
}

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <div className="mt-5">
      <h3 className="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-2">{title}</h3>
      {children}
    </div>
  )
}

function SourceList({ meta }: { meta: NonNullable<Profile['input_meta']> }) {
  const sources = meta.sources ?? []
  if (sources.length === 0) return null
  return (
    <div className="bg-slate-50 border border-slate-200 rounded-lg p-3">
      <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1.5">Sumber yang dibaca</p>
      <ul className="space-y-0.5 text-xs text-slate-600">
        {sources.map((s, i) => (
          <li key={i} className={`flex items-start gap-2 ${s.error ? 'text-amber-700' : ''}`}>
            <span className="shrink-0">
              {s.type === 'link' ? '🔗' : s.type === 'pdf' ? '📄' : s.type === 'image' ? '🖼' : '📝'}
            </span>
            <span>
              {s.url ?? s.name ?? 'teks yang ditempel'}
              {s.error
                ? ` — ${s.error}`
                : s.type === 'image'
                  ? ' · dibaca langsung sebagai gambar'
                  : ` · ${s.chars.toLocaleString('id-ID')} karakter`}
            </span>
          </li>
        ))}
      </ul>
      {typeof meta.total_chars === 'number' && meta.total_chars > 0 && (
        <p className="mt-1.5 text-[11px] text-slate-400">
          Total {meta.total_chars.toLocaleString('id-ID')} karakter
        </p>
      )}
      {(meta.notes ?? []).length > 0 && (
        <ul className="mt-1.5 text-[11px] text-amber-600 list-disc list-inside">
          {(meta.notes ?? []).map((n, i) => (
            <li key={i}>{n}</li>
          ))}
        </ul>
      )}
    </div>
  )
}

/** Tampilan profil yang sudah jadi — dibaca manusia, bukan JSON. */
function ProfilePreview({ profile }: { profile: Profile }) {
  const d = profile.data
  const skills = d.skills ?? {}
  const pengalaman = (d.pengalaman ?? []) as Exp[]
  const pendidikan = (d.pendidikan ?? []) as Edu[]
  const sertifikasi = (d.sertifikasi ?? []) as Cert[]
  const bahasa = (d.bahasa ?? []) as Lang[]
  const kontak = d.kontak ?? {}
  const preferensi = d.preferensi ?? {}

  const kontakItems = [
    ['Email', kontak.email],
    ['Telepon', kontak.telepon],
    ['LinkedIn', kontak.linkedin],
    ['Portfolio', kontak.portfolio],
    ['GitHub', kontak.github],
    ['Lokasi', kontak.lokasi],
  ].filter(([, v]) => v) as Array<[string, string]>

  const prefItems = [
    ['Tipe kerja', preferensi.tipe_kerja],
    ['Lokasi', preferensi.lokasi],
    ['Remote', preferensi.remote],
  ].filter(([, v]) => v) as Array<[string, string]>

  return (
    <div>
      <div className="flex items-start justify-between gap-3 flex-wrap">
        <div className="min-w-0">
          <h2 className="text-lg font-bold">{d.nama ?? '(tanpa nama)'}</h2>
          {(d.headline || d.judul_profesi) && (
            <p className="text-sm text-indigo-700 font-medium mt-0.5">{d.headline ?? d.judul_profesi}</p>
          )}
        </div>
        <div className="flex items-center gap-2">
          <Badge color="green">siap dipakai</Badge>
          <span className="text-xs text-slate-400">via {profile.source}</span>
        </div>
      </div>

      {d.ringkasan && (
        <Section title="Ringkasan">
          <p className="text-sm text-slate-700 leading-relaxed whitespace-pre-line">{d.ringkasan}</p>
        </Section>
      )}

      {(d.keunggulan ?? []).length > 0 && (
        <Section title="Keunggulan">
          <ul className="space-y-1">
            {(d.keunggulan ?? []).map((k, i) => (
              <li key={i} className="text-sm text-slate-700 flex gap-2">
                <span className="text-emerald-600 shrink-0">✓</span>
                <span>{k}</span>
              </li>
            ))}
          </ul>
        </Section>
      )}

      {(d.kata_kunci_ats ?? []).length > 0 && (
        <Section title={`Kata kunci ATS (${(d.kata_kunci_ats ?? []).length})`}>
          <div className="flex flex-wrap gap-1.5">
            {(d.kata_kunci_ats ?? []).map((k, i) => (
              <Chip key={i} tone="indigo">{k}</Chip>
            ))}
          </div>
        </Section>
      )}

      <Section title="Keahlian">
        <div className="space-y-2">
          {(['teknis', 'tools', 'soft'] as const).map((k) =>
            (skills[k] ?? []).length > 0 ? (
              <div key={k} className="flex flex-wrap gap-1.5 items-center">
                <span className="text-[11px] uppercase tracking-wider text-slate-400 w-12">{k}</span>
                {(skills[k] ?? []).map((s, i) => (
                  <Chip key={i}>{s}</Chip>
                ))}
              </div>
            ) : null,
          )}
        </div>
      </Section>

      {pengalaman.length > 0 && (
        <Section title={`Pengalaman (${pengalaman.length})`}>
          <div className="space-y-3">
            {pengalaman.map((e, i) => (
              <div key={i} className="border-l-2 border-slate-200 pl-3">
                <p className="text-sm font-semibold text-slate-800">
                  {e.posisi ?? '—'}
                  {e.perusahaan ? <span className="font-normal text-slate-600"> · {e.perusahaan}</span> : null}
                </p>
                {(e.periode || e.lokasi) && (
                  <p className="text-xs text-slate-400">
                    {[e.periode, e.lokasi].filter(Boolean).join(' · ')}
                  </p>
                )}
                {e.ringkasan && <p className="text-sm text-slate-600 mt-1">{e.ringkasan}</p>}
                {(e.pencapaian ?? []).length > 0 && (
                  <ul className="mt-1 space-y-0.5">
                    {(e.pencapaian ?? []).map((p, j) => (
                      <li key={j} className="text-sm text-slate-600 flex gap-2">
                        <span className="text-slate-300 shrink-0">–</span>
                        <span>{p}</span>
                      </li>
                    ))}
                  </ul>
                )}
                {(e.teknologi ?? []).length > 0 && (
                  <div className="flex flex-wrap gap-1.5 mt-1.5">
                    {(e.teknologi ?? []).map((t, j) => (
                      <Chip key={j}>{t}</Chip>
                    ))}
                  </div>
                )}
              </div>
            ))}
          </div>
        </Section>
      )}

      {pendidikan.length > 0 && (
        <Section title="Pendidikan">
          <div className="space-y-2">
            {pendidikan.map((e, i) => (
              <div key={i}>
                <p className="text-sm font-semibold text-slate-800">{e.institusi ?? '—'}</p>
                <p className="text-xs text-slate-500">
                  {[e.jurusan, e.tahun].filter(Boolean).join(' · ')}
                </p>
                {e.detail && <p className="text-sm text-slate-600 mt-0.5">{e.detail}</p>}
              </div>
            ))}
          </div>
        </Section>
      )}

      {sertifikasi.length > 0 && (
        <Section title="Sertifikasi">
          <ul className="space-y-1">
            {sertifikasi.map((c, i) => (
              <li key={i} className="text-sm text-slate-700">
                {c.nama ?? '—'}
                {(c.penerbit || c.tahun) && (
                  <span className="text-slate-400"> — {[c.penerbit, c.tahun].filter(Boolean).join(', ')}</span>
                )}
              </li>
            ))}
          </ul>
        </Section>
      )}

      {bahasa.length > 0 && (
        <Section title="Bahasa">
          <div className="flex flex-wrap gap-1.5">
            {bahasa.map((b, i) => (
              <Chip key={i}>{[b.bahasa, b.level].filter(Boolean).join(' · ')}</Chip>
            ))}
          </div>
        </Section>
      )}

      {kontakItems.length > 0 && (
        <Section title="Kontak">
          <div className="grid sm:grid-cols-2 gap-x-4 gap-y-1">
            {kontakItems.map(([label, value]) => (
              <p key={label} className="text-sm text-slate-700 truncate">
                <span className="text-slate-400">{label}: </span>
                {value}
              </p>
            ))}
          </div>
        </Section>
      )}

      {prefItems.length > 0 && (
        <Section title="Preferensi">
          <div className="flex flex-wrap gap-1.5">
            {prefItems.map(([label, value]) => (
              <Chip key={label}>{label}: {value}</Chip>
            ))}
          </div>
        </Section>
      )}

      {profile.input_meta && (
        <div className="mt-5">
          <SourceList meta={profile.input_meta} />
        </div>
      )}
    </div>
  )
}

export default function ProfilePage() {
  const [profiles, setProfiles] = useState<Profile[]>([])
  const [keys, setKeys] = useState<LlmKey[]>([])
  const [viewing, setViewing] = useState<Profile | null>(null)
  const [editing, setEditing] = useState(false)
  const [editJson, setEditJson] = useState('')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')

  const load = async () => {
    try {
      const [p, k] = await Promise.all([
        api.get<Profile[]>('/profiles'),
        api.get<LlmKey[]>('/llm-keys').catch(() => [] as LlmKey[]),
      ])
      setProfiles(p)
      setKeys(k)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Gagal memuat')
    }
  }

  useEffect(() => { load() }, [])

  const openProfile = (p: Profile) => {
    setViewing(p)
    setEditing(false)
    setEditJson(JSON.stringify(p.data, null, 2))
  }

  const saveEdits = async () => {
    if (!viewing) return
    let data: ProfileData
    try {
      data = JSON.parse(editJson) as ProfileData
    } catch (e) {
      setError('JSON tidak valid: ' + (e instanceof Error ? e.message : ''))
      return
    }
    setSaving(true)
    setError('')
    try {
      const updated = await api.post<Profile>(`/profiles/${viewing.id}/confirm`, { data })
      setViewing({ ...viewing, ...updated })
      setEditing(false)
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Gagal menyimpan')
    } finally {
      setSaving(false)
    }
  }

  const activate = async (p: Profile) => {
    try {
      const updated = await api.post<Profile>(`/profiles/${p.id}/confirm`, {})
      if (viewing?.id === p.id) setViewing({ ...viewing, ...updated })
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Gagal mengaktifkan')
    }
  }

  const deleteProfile = async (id: number) => {
    if (!confirm('Hapus profil ini?')) return
    try {
      await api.delete(`/profiles/${id}`)
      if (viewing?.id === id) setViewing(null)
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Gagal menghapus')
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Profil Saya</h1>
        <p className="text-sm text-slate-500 mt-1">
          Sekali tempel, profil langsung jadi &amp; dipakai otomatis untuk semua lowongan.
        </p>
      </div>

      <ErrorMsg>{error}</ErrorMsg>

      <ProfileComposer
        keys={keys}
        onError={setError}
        onCreated={(p) => {
          openProfile(p as Profile)
          load()
        }}
      />

      {/* Hasil profil */}
      {viewing && (
        <Card className="border-indigo-200">
          <ProfilePreview profile={viewing} />

          <div className="mt-5 border-t border-slate-200 pt-3">
            {!editing ? (
              <div className="flex gap-2 flex-wrap">
                <Button variant="secondary" onClick={() => setEditing(true)}>Edit data</Button>
                <Button
                  variant="secondary"
                  onClick={() => {
                    navigator.clipboard?.writeText(JSON.stringify(viewing.data, null, 2))
                  }}
                >
                  Copy JSON
                </Button>
                <Button variant="secondary" onClick={() => setViewing(null)}>Tutup</Button>
              </div>
            ) : (
              <>
                <p className="text-xs text-slate-500 mb-2">
                  Ubah langsung di JSON kalau ada yang perlu diperbaiki, lalu simpan.
                </p>
                <Textarea
                  rows={18}
                  className="font-mono text-xs"
                  value={editJson}
                  onChange={(e) => setEditJson(e.target.value)}
                />
                <div className="flex gap-2 mt-3">
                  <Button onClick={saveEdits} disabled={saving}>
                    {saving ? 'Menyimpan…' : 'Simpan perubahan'}
                  </Button>
                  <Button variant="secondary" onClick={() => setEditing(false)}>Batal</Button>
                </div>
              </>
            )}
          </div>
        </Card>
      )}

      {/* Daftar profil */}
      <Card>
        <h2 className="font-semibold mb-4">Riwayat Profil</h2>
        {profiles.length === 0 ? (
          <p className="text-sm text-slate-400">Belum ada profil.</p>
        ) : (
          <div className="space-y-3">
            {profiles.map((p) => (
              <div
                key={p.id}
                className="border border-slate-200 rounded-lg p-3 flex items-center justify-between flex-wrap gap-2"
              >
                <div className="min-w-0">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="font-medium text-sm">{p.data.nama ?? '(tanpa nama)'}</span>
                    {p.confirmed
                      ? <Badge color="green">siap dipakai</Badge>
                      : <Badge color="yellow">draft</Badge>}
                    <span className="text-xs text-slate-400">via {p.source}</span>
                  </div>
                  <p className="text-xs text-slate-500 mt-0.5 truncate">
                    {p.data.headline ?? p.data.judul_profesi ?? p.data.skills?.teknis?.slice(0, 6).join(', ') ?? '—'}
                  </p>
                </div>
                <div className="flex gap-2">
                  <Button variant="secondary" onClick={() => openProfile(p)}>Lihat</Button>
                  {!p.confirmed && <Button onClick={() => activate(p)}>Aktifkan</Button>}
                  <Button variant="danger" onClick={() => deleteProfile(p.id)}>Hapus</Button>
                </div>
              </div>
            ))}
          </div>
        )}
      </Card>
    </div>
  )
}

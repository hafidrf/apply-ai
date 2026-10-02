import { useEffect, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api, type Profile, type Job, type LlmKey } from '../lib/api'
import JobComposer from '../components/JobComposer'
import { Button, Card, ErrorMsg, Badge } from '../components/ui'

export default function JobsPage() {
  const navigate = useNavigate()
  const [jobs, setJobs] = useState<Job[]>([])
  const [profiles, setProfiles] = useState<Profile[]>([])
  const [keys, setKeys] = useState<LlmKey[]>([])
  const [error, setError] = useState('')
  const [page] = useState(1)

  const load = async () => {
    try {
      const [jr, p, k] = await Promise.all([
        api.get<{ data: Job[]; current_page: number; last_page: number }>(`/jobs?page=${page}`),
        api.get<Profile[]>('/profiles'),
        api.get<LlmKey[]>('/llm-keys').catch(() => [] as LlmKey[]),
      ])
      setJobs(jr.data ?? [])
      setProfiles(p)
      setKeys(k)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Gagal memuat')
    }
  }

  useEffect(() => { load() }, [page])

  const deleteJob = async (id: number) => {
    if (!confirm('Hapus lowongan ini?')) return
    setError('')
    try {
      await api.delete(`/jobs/${id}`)
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Gagal menghapus lowongan')
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Lowongan</h1>
        <p className="text-sm text-slate-500 mt-1">
          Tempel teks, screenshot (Ctrl+V), link, atau PDF lowongan — sistem mendeteksi kanal & menyusun pesan.
        </p>
      </div>

      <ErrorMsg>{error}</ErrorMsg>

      <JobComposer
        profiles={profiles}
        keys={keys}
        onError={setError}
        onCreated={(job) => navigate(`/jobs/${job.id}`)}
      />

      {/* Daftar lowongan */}
      <Card>
        <h2 className="font-semibold mb-4">Daftar Lowongan</h2>
        {jobs.length === 0 ? (
          <p className="text-sm text-slate-400">Belum ada lowongan. Kirim di atas.</p>
        ) : (
          <div className="space-y-2">
            {jobs.map((j) => (
              <div key={j.id} className="border border-slate-200 rounded-lg p-3 flex items-center justify-between flex-wrap gap-2">
                <Link to={`/jobs/${j.id}`} className="min-w-0 flex-1 hover:bg-slate-50 rounded-lg -m-1 p-1 transition-colors">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="font-medium text-sm truncate">
                      {j.position ?? '(posisi tidak terdeteksi)'}
                    </span>
                    {j.company && <span className="text-xs text-slate-500">di {j.company}</span>}
                    {j.channel && <Badge color="indigo">{j.channel}</Badge>}
                    <StatusBadge status={j.status} />
                  </div>
                </Link>
                <div className="flex gap-2">
                  <Link to={`/jobs/${j.id}`}>
                    <Button variant="secondary">Buka</Button>
                  </Link>
                  <Button variant="danger" onClick={() => deleteJob(j.id)}>Hapus</Button>
                </div>
              </div>
            ))}
          </div>
        )}
      </Card>
    </div>
  )
}

function StatusBadge({ status }: { status: string }) {
  const map: Record<string, [string, string]> = {
    pending: ['pending', 'slate'],
    parsed: ['terparse', 'indigo'],
    matched: ['matched', 'indigo'],
    composed: ['✓ pesan siap', 'green'],
    failed: ['gagal', 'red'],
  }
  const [label, color] = map[status] ?? [status, 'slate']
  return <Badge color={color}>{label}</Badge>
}

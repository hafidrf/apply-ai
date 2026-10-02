import { useEffect, useState } from 'react'
import { api, type LlmKey, type ProviderInfo } from '../lib/api'
import { Button, Card, Input, Select, ErrorMsg, Badge } from '../components/ui'

export default function SettingsPage() {
  const [providers, setProviders] = useState<ProviderInfo[]>([])
  const [keys, setKeys] = useState<LlmKey[]>([])
  const [provider, setProvider] = useState('felidaeai')
  const [label, setLabel] = useState('')
  const [apiKey, setApiKey] = useState('')
  const [baseUrl, setBaseUrl] = useState('')
  const [model, setModel] = useState('')
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [testResult, setTestResult] = useState<Record<number, string>>({})

  const load = async () => {
    try {
      const [cat, ks] = await Promise.all([
        api.get<{ providers: ProviderInfo[] }>('/llm/catalog'),
        api.get<LlmKey[]>('/llm-keys'),
      ])
      setProviders(cat.providers)
      setKeys(ks)
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Gagal memuat')
    }
  }

  useEffect(() => { load() }, [])

  const selectedProvider = providers.find((p) => p.id === provider)

  const addKey = async () => {
    setError('')
    setBusy(true)
    try {
      const body: Record<string, unknown> = { provider, is_default: keys.length === 0 }
      if (label) body.label = label
      if (apiKey) body.api_key = apiKey
      if (baseUrl) body.base_url = baseUrl
      if (model) body.default_model = model
      await api.post('/llm-keys', body)
      setApiKey(''); setLabel(''); setBaseUrl(''); setModel('')
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Gagal menyimpan')
    } finally {
      setBusy(false)
    }
  }

  const testKey = async (id: number) => {
    setTestResult((prev) => ({ ...prev, [id]: '⏳ Menguji...' }))
    try {
      const r = await api.post<{ ok: boolean; reply?: string; error?: string }>(`/llm-keys/${id}/test`)
      setTestResult((prev) => ({
        ...prev,
        [id]: r.ok ? `✅ Berhasil — balasan: ${r.reply}` : `❌ ${r.error}`,
      }))
    } catch (e) {
      setTestResult((prev) => ({ ...prev, [id]: `❌ ${e instanceof Error ? e.message : 'gagal'}` }))
    }
  }

  const deleteKey = async (id: number) => {
    if (!confirm('Hapus key ini?')) return
    setError('')
    try {
      await api.delete(`/llm-keys/${id}`)
      await load()
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Gagal menghapus key')
    }
  }

  const setDefault = async (id: number) => {
    setError('')
    try {
      await api.put(`/llm-keys/${id}`, { is_default: true })
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Gagal menjadikan default')
    }
    await load()
  }

  const setVision = async (id: number, value: boolean | null) => {
    setError('')
    try {
      await api.put(`/llm-keys/${id}`, { supports_vision: value })
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Gagal mengubah kemampuan gambar')
    }
    await load()
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Pengaturan Provider AI</h1>
        <p className="text-sm text-slate-500 mt-1">
          Bawa key sendiri (BYOK). Key disimpan terenkripsi di server dan tidak pernah ditampilkan lagi.
        </p>
      </div>

      <ErrorMsg>{error}</ErrorMsg>

      {/* Tambah key */}
      <Card>
        <h2 className="font-semibold mb-4">Tambah API Key</h2>
        <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div>
            <label className="text-xs text-slate-500 mb-1 block">Provider</label>
            <Select value={provider} onChange={(e) => { setProvider(e.target.value); setModel(''); setBaseUrl('') }}>
              {providers.map((p) => (
                <option key={p.id} value={p.id}>{p.label}</option>
              ))}
            </Select>
          </div>
          <div>
            <label className="text-xs text-slate-500 mb-1 block">Label (opsional)</label>
            <Input placeholder="mis. Key utama" value={label} onChange={(e) => setLabel(e.target.value)} />
          </div>
          <div>
            <label className="text-xs text-slate-500 mb-1 block">
              API Key {selectedProvider ? `(${selectedProvider.key_hint})` : ''}
            </label>
            <Input type="password" placeholder="sk-..." value={apiKey} onChange={(e) => setApiKey(e.target.value)} />
          </div>
          <div>
            <label className="text-xs text-slate-500 mb-1 block">Model default</label>
            <Input
              list="model-list"
              placeholder="pilih atau ketik"
              value={model}
              onChange={(e) => setModel(e.target.value)}
            />
            <datalist id="model-list">
              {selectedProvider?.models.map((m) => (
                <option key={m} value={m} />
              ))}
            </datalist>
          </div>
          {provider === 'custom' && (
            <div className="md:col-span-2">
              <label className="text-xs text-slate-500 mb-1 block">Base URL (wajib untuk custom)</label>
              <Input placeholder="https://endpoint-anda.com/v1" value={baseUrl} onChange={(e) => setBaseUrl(e.target.value)} />
            </div>
          )}
        </div>
        <div className="mt-4">
          <Button onClick={addKey} disabled={busy}>{busy ? 'Menyimpan...' : 'Tambah Key'}</Button>
        </div>
      </Card>

      {/* Daftar key */}
      <Card>
        <h2 className="font-semibold mb-4">Key Tersimpan</h2>
        {keys.length === 0 ? (
          <p className="text-sm text-slate-400">Belum ada key. Tambahkan di atas untuk mulai.</p>
        ) : (
          <div className="space-y-3">
            {keys.map((k) => (
              <div key={k.id} className="border border-slate-200 rounded-lg p-3">
                <div className="flex items-center justify-between gap-2 flex-wrap">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="font-medium text-sm">{k.provider_label}</span>
                    {k.label && <span className="text-xs text-slate-400">"{k.label}"</span>}
                    <code className="text-xs bg-slate-100 px-1.5 py-0.5 rounded">{k.masked_key}</code>
                    {k.default_model && <Badge color="indigo">{k.default_model}</Badge>}
                    <Badge color={k.can_read_images ? 'green' : 'slate'}>
                      {k.can_read_images ? '👁 bisa gambar' : 'teks saja'}
                    </Badge>
                    {k.is_default && <Badge color="green">default</Badge>}
                  </div>
                  <div className="flex gap-2 flex-wrap">
                    <Button
                      variant="secondary"
                      onClick={() => setVision(k.id, k.can_read_images ? false : true)}
                      title="Tandai manual apakah model ini bisa membaca gambar"
                    >
                      {k.can_read_images ? 'Tandai: teks saja' : 'Tandai: bisa gambar'}
                    </Button>
                    {!k.is_default && (
                      <Button variant="secondary" onClick={() => setDefault(k.id)}>Jadikan default</Button>
                    )}
                    <Button variant="secondary" onClick={() => testKey(k.id)}>Tes</Button>
                    <Button variant="danger" onClick={() => deleteKey(k.id)}>Hapus</Button>
                  </div>
                </div>
                {testResult[k.id] && (
                  <p className="text-xs mt-2 text-slate-600">{testResult[k.id]}</p>
                )}
              </div>
            ))}
          </div>
        )}
      </Card>
    </div>
  )
}

import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api, setToken, type User } from '../lib/api'
import { Button, Input, Card, ErrorMsg } from '../components/ui'

export default function RegisterPage() {
  const navigate = useNavigate()
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    if (password.length < 8) {
      setError('Password minimal 8 karakter.')
      return
    }
    setLoading(true)
    try {
      const r = await api.post<{ user: User; token: string }>('/auth/register', { name, email, password })
      setToken(r.token)
      navigate('/settings')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Pendaftaran gagal')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-slate-50 px-4">
      <Card className="w-full max-w-sm">
        <h1 className="text-xl font-bold text-center mb-1">Daftar Akun</h1>
        <p className="text-sm text-slate-500 text-center mb-6">Bawa API key provider AI Anda sendiri (BYOK)</p>
        <form onSubmit={submit} className="space-y-3">
          <Input placeholder="Nama" value={name} onChange={(e) => setName(e.target.value)} required />
          <Input type="email" placeholder="Email" value={email} onChange={(e) => setEmail(e.target.value)} required />
          <Input type="password" placeholder="Password (min. 8 karakter)" value={password} onChange={(e) => setPassword(e.target.value)} required />
          <ErrorMsg>{error}</ErrorMsg>
          <Button type="submit" disabled={loading} className="w-full">
            {loading ? 'Memproses...' : 'Daftar'}
          </Button>
        </form>
        <p className="text-sm text-center mt-4 text-slate-500">
          Sudah punya akun? <Link to="/login" className="text-indigo-600 hover:underline">Masuk</Link>
        </p>
      </Card>
    </div>
  )
}

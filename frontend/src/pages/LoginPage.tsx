import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api, setToken, type User } from '../lib/api'
import { Button, Input, Card, ErrorMsg } from '../components/ui'

export default function LoginPage() {
  const navigate = useNavigate()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  const submit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      const r = await api.post<{ user: User; token: string }>('/auth/login', { email, password })
      setToken(r.token)
      navigate('/jobs')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Login gagal')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="min-h-screen flex items-center justify-center bg-slate-50 px-4">
      <Card className="w-full max-w-sm">
        <h1 className="text-xl font-bold text-center mb-1">💼 Asisten Apply</h1>
        <p className="text-sm text-slate-500 text-center mb-6">Masuk untuk menyusun lamaran kerja</p>
        <form onSubmit={submit} className="space-y-3">
          <Input type="email" placeholder="Email" value={email} onChange={(e) => setEmail(e.target.value)} required />
          <Input type="password" placeholder="Password" value={password} onChange={(e) => setPassword(e.target.value)} required />
          <ErrorMsg>{error}</ErrorMsg>
          <Button type="submit" disabled={loading} className="w-full">
            {loading ? 'Memproses...' : 'Masuk'}
          </Button>
        </form>
        <p className="text-sm text-center mt-4 text-slate-500">
          Belum punya akun? <Link to="/register" className="text-indigo-600 hover:underline">Daftar</Link>
        </p>
      </Card>
    </div>
  )
}

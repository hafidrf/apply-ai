import { NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { api, setToken, getToken } from '../lib/api'
import ErrorBoundary from './ErrorBoundary'

const navItems = [
  { to: '/profile', label: 'Profil' },
  { to: '/jobs', label: 'Lowongan' },
  { to: '/history', label: 'Riwayat' },
  { to: '/settings', label: 'Pengaturan' },
]

export default function Layout() {
  const navigate = useNavigate()
  const { pathname } = useLocation()

  const logout = async () => {
    try {
      await api.post('/auth/logout')
    } catch {
      // abaikan error logout
    }
    setToken(null)
    navigate('/login')
  }

  return (
    <div className="min-h-screen bg-slate-50">
      <header className="bg-white border-b border-slate-200 sticky top-0 z-10">
        <div className="max-w-5xl mx-auto px-4 h-14 flex items-center justify-between">
          <div className="flex items-center gap-6">
            <span className="font-bold text-lg text-indigo-700">💼 Asisten Apply</span>
            <nav className="flex gap-1">
              {navItems.map((item) => (
                <NavLink
                  key={item.to}
                  to={item.to}
                  className={({ isActive }) =>
                    `px-3 py-1.5 rounded-md text-sm font-medium transition-colors ${
                      isActive
                        ? 'bg-indigo-50 text-indigo-700'
                        : 'text-slate-600 hover:bg-slate-100'
                    }`
                  }
                >
                  {item.label}
                </NavLink>
              ))}
            </nav>
          </div>
          {getToken() && (
            <button
              onClick={logout}
              className="text-sm text-slate-500 hover:text-slate-800 px-3 py-1.5 rounded-md hover:bg-slate-100"
            >
              Keluar
            </button>
          )}
        </div>
      </header>
      <main className="max-w-5xl mx-auto px-4 py-6">
        {/* key per halaman: error di satu halaman tidak ikut terbawa saat pindah menu */}
        <ErrorBoundary key={pathname}>
          <Outlet />
        </ErrorBoundary>
      </main>
    </div>
  )
}

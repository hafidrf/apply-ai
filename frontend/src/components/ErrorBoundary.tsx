import { Component, type ErrorInfo, type ReactNode } from 'react'

interface State {
  error: Error | null
  componentStack: string
}

/**
 * Jaring pengaman anti "layar putih".
 *
 * Tanpa ini, satu error di satu komponen membuat SELURUH aplikasi kosong tanpa
 * penjelasan. Dengan ini, halaman yang error diganti kartu yang bisa dimuat ulang
 * dan menampilkan detail teknis untuk dilaporkan.
 *
 * Khusus error "gagal memuat modul" (chunk lama setelah deploy baru), halaman
 * dimuat ulang otomatis SEKALI — cara paling ampuh mengatasinya.
 */
export default class ErrorBoundary extends Component<{ children: ReactNode }, State> {
  state: State = { error: null, componentStack: '' }

  private static readonly RELOAD_KEY = 'applyai_chunk_reload'

  static getDerivedStateFromError(error: Error): Partial<State> {
    return { error }
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    this.setState({ componentStack: (info.componentStack ?? '').split('\n').slice(0, 8).join('\n') })
    console.error('[ErrorBoundary]', error, info)

    // Chunk JS lama setelah deploy baru → reload sekali biasanya langsung beres.
    const isChunkError = /dynamically imported module|Importing a module script failed|Loading chunk/i.test(
      String(error?.message ?? ''),
    )
    if (isChunkError && !sessionStorage.getItem(ErrorBoundary.RELOAD_KEY)) {
      sessionStorage.setItem(ErrorBoundary.RELOAD_KEY, '1')
      window.location.reload()
    }
  }

  private reset = () => {
    sessionStorage.removeItem(ErrorBoundary.RELOAD_KEY)
    this.setState({ error: null, componentStack: '' })
  }

  render() {
    const { error, componentStack } = this.state
    if (!error) return this.props.children

    return (
      <div className="min-h-[60vh] flex items-center justify-center p-4">
        <div className="max-w-xl w-full bg-white border border-red-200 rounded-xl shadow-sm p-6">
          <h1 className="text-lg font-bold text-red-700">Ada yang error di tampilan ini</h1>
          <p className="text-sm text-slate-600 mt-2">
            Aplikasi dan datanya tidak rusak. Coba tampilkan ulang; kalau muncul lagi, salin
            detail teknis di bawah saat melapor.
          </p>

          <div className="flex gap-2 mt-4 flex-wrap">
            <button
              type="button"
              onClick={this.reset}
              className="px-4 py-2 rounded-lg text-sm font-medium bg-indigo-600 text-white hover:bg-indigo-700"
            >
              Coba tampilkan lagi
            </button>
            <button
              type="button"
              onClick={() => {
                sessionStorage.removeItem(ErrorBoundary.RELOAD_KEY)
                window.location.reload()
              }}
              className="px-4 py-2 rounded-lg text-sm font-medium bg-white border border-slate-300 text-slate-700 hover:bg-slate-50"
            >
              Muat ulang halaman
            </button>
          </div>

          <details className="mt-4">
            <summary className="text-xs text-slate-500 cursor-pointer select-none">Detail teknis</summary>
            <pre className="mt-2 text-[11px] leading-relaxed bg-slate-50 border border-slate-200 rounded-lg p-3 overflow-auto max-h-60 whitespace-pre-wrap font-mono">
              {String(error.stack ?? error.message ?? error)}
              {componentStack ? `\n\nKomponen:\n${componentStack}` : ''}
            </pre>
          </details>
        </div>
      </div>
    )
  }
}

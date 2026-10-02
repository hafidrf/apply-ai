import ChatComposer from './ChatComposer'
import type { LlmKey, Profile } from '../lib/api'

/**
 * Composer profil — memakai ChatComposer bersama.
 * Terima teks, gambar (CV/sertifikat/screenshot profil), PDF (banyak), dan link.
 */
export default function ProfileComposer({
  keys,
  onCreated,
  onError,
}: {
  keys: LlmKey[]
  onCreated: (profile: Profile & { input_meta?: Record<string, unknown> }) => void
  onError: (msg: string) => void
}) {
  return (
    <ChatComposer
      keys={keys}
      hint="paste teks CV · Ctrl+V gambar · drag & drop · PDF · link LinkedIn/portfolio"
      submitLabel="Bangun Profil"
      busyLabel="AI sedang membangun profil Anda..."
      placeholder={`Tempel apa saja tentang diri Anda:

• Teks CV / isi profil LinkedIn (copy dari mana pun)
• Screenshot CV atau sertifikat — paste dengan Ctrl+V (boleh banyak)
• Beberapa PDF sekaligus (CV + portfolio + sertifikat)
• Link portfolio / LinkedIn (isinya dibaca otomatis kalau bisa diakses)

Semuanya digabung jadi satu profil.`}
      onError={onError}
      onSend={async (form, ctx) => {
        form.append('source', ctx.hasImages ? 'mixed' : 'text')

        const resp = await fetch('/api/profiles', {
          method: 'POST',
          headers: { Authorization: `Bearer ${localStorage.getItem('applyai_token')}` },
          body: form,
        })
        const json = await resp.json()
        if (!resp.ok) throw new Error(json.message ?? `Gagal (${resp.status})`)
        onCreated(json)
      }}
    />
  )
}

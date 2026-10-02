// API client — Asisten Apply Kerja
// Semua request lewat proxy Vite /api → Laravel (dev), atau same-origin (prod)

const TOKEN_KEY = 'applyai_token';

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string | null) {
  if (token) localStorage.setItem(TOKEN_KEY, token);
  else localStorage.removeItem(TOKEN_KEY);
}

export class ApiError extends Error {
  status: number
  constructor(status: number, message: string) {
    super(message)
    this.status = status
  }
}

/**
 * Ubah respons error jadi satu pesan yang enak dibaca.
 * Laravel mengirim `message` + `errors` (per-field) untuk error validasi — ambil
 * yang paling informatif supaya user tahu apa yang salah, bukan "Request gagal (422)".
 */
function extractMessage(json: unknown, status: number): string {
  const j = (json ?? {}) as Record<string, unknown>

  const errors = j.errors as Record<string, unknown> | undefined
  if (errors && typeof errors === 'object') {
    const flat = Object.values(errors)
      .flatMap((v) => (Array.isArray(v) ? v : [v]))
      .filter((v): v is string => typeof v === 'string' && v.trim() !== '')
    if (flat.length > 0) return flat.join(' ')
  }

  if (typeof j.message === 'string' && j.message.trim() !== '') return j.message

  if (status === 401) return 'Sesi Anda sudah berakhir. Silakan masuk lagi.'
  if (status === 403) return 'Anda tidak punya akses ke data ini.'
  if (status === 404) return 'Data tidak ditemukan.'
  if (status === 413) return 'File yang dikirim terlalu besar. Kirim satu per satu.'
  if (status === 419) return 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.'
  if (status === 429) return 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.'
  if (status >= 500) return `Terjadi masalah di server (${status}). Coba lagi sebentar lagi.`
  return `Request gagal (${status})`
}

async function request<T>(method: string, path: string, body?: unknown, isForm = false): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json' };
  const token = getToken();
  if (token) headers['Authorization'] = `Bearer ${token}`;
  if (!isForm && body !== undefined) headers['Content-Type'] = 'application/json';

  let resp: Response
  try {
    resp = await fetch(`/api${path}`, {
      method,
      headers,
      body: isForm ? (body as FormData) : body !== undefined ? JSON.stringify(body) : undefined,
    });
  } catch {
    // fetch hanya gagal di level jaringan (server mati, koneksi putus)
    throw new ApiError(
      0,
      'Tidak bisa terhubung ke server. Pastikan jendela "Asisten Apply Kerja" masih terbuka.',
    )
  }

  // 401 dari Sanctum = token hilang/kedaluwarsa → bersihkan sesi supaya tidak
  // menampilkan error di setiap halaman. Endpoint /auth/* dikecualikan karena
  // login yang salah memang divalidasi di sana (422), bukan 401.
  if (resp.status === 401 && !path.startsWith('/auth/')) {
    setToken(null)
    if (!window.location.pathname.startsWith('/login')) window.location.replace('/login')
    throw new ApiError(401, 'Sesi Anda sudah berakhir. Silakan masuk lagi.')
  }

  // Baca sebagai teks dulu: server bisa balas HTML (500 error page) atau body kosong
  const text = await resp.text()
  let json: unknown = {}
  if (text) {
    try {
      json = JSON.parse(text)
    } catch {
      json = {}
    }
  }

  if (!resp.ok) throw new ApiError(resp.status, extractMessage(json, resp.status))

  return json as T
}

export const api = {
  get: <T>(path: string) => request<T>('GET', path),
  post: <T>(path: string, body?: unknown) => request<T>('POST', path, body),
  put: <T>(path: string, body?: unknown) => request<T>('PUT', path, body),
  postForm: <T>(path: string, form: FormData) => request<T>('POST', path, form, true),
  delete: <T>(path: string) => request<T>('DELETE', path),
};

// ---------------- Types ----------------

export interface User {
  id: number;
  name: string;
  email: string;
}

export interface ProviderInfo {
  id: string;
  label: string;
  client: string;
  base_url: string | null;
  models: string[];
  vision_models: string[];
  key_hint: string;
}

export interface LlmKey {
  id: number;
  provider: string;
  provider_label: string;
  label: string | null;
  masked_key: string;
  base_url: string | null;
  default_model: string | null;
  supports_vision: boolean | null;
  can_read_images: boolean;
  vision_models: string[];
  is_default: boolean;
  created_at: string | null;
}

export interface ProfileData {
  nama?: string | null;
  headline?: string | null;
  judul_profesi?: string | null;
  kontak?: Record<string, string | null>;
  ringkasan?: string | null;
  keunggulan?: string[];
  kata_kunci_ats?: string[];
  pendidikan?: Array<Record<string, string | null>>;
  pengalaman?: Array<Record<string, unknown>>;
  skills?: { teknis?: string[]; tools?: string[]; soft?: string[] };
  proyek?: Array<Record<string, unknown>>;
  sertifikasi?: Array<Record<string, string | null>>;
  bahasa?: Array<Record<string, string | null>>;
  preferensi?: Record<string, string | null>;
  /** hanya ada di profil lama (skema crosscheck sudah dihapus) */
  perlu_konfirmasi?: Array<{ pertanyaan: string; konteks: string }>;
}

export interface Profile {
  id: number;
  session_id: string;
  source: string;
  data: ProfileData;
  input_meta?: {
    sources?: Array<{
      type: string;
      name?: string;
      url?: string;
      title?: string | null;
      chars: number;
      error: string | null;
    }>;
    notes?: string[];
    total_chars?: number;
    used_vision?: boolean;
  } | null;
  confirmed: boolean;
  is_active: boolean;
  created_at: string | null;
}

export interface Draft {
  id: number;
  channel: string;
  message_text: string;
  notes: {
    kanal?: string;
    jumlah_kata?: number;
    kata_kunci_ats?: string[];
    match_ringkas?: Array<{ requirement: string; status: string }>;
    reframes?: Array<{
      requirement: string;
      teknik: string | null;
      reframe: string;
      dasar_fakta: string | null;
      persiapan_interview: string;
    }>;
  } | null;
  variant_text: string | null;
  created_at: string | null;
}

export interface Job {
  id: number;
  profile_id: number | null;
  raw_input_type: string;
  channel: string | null;
  position: string | null;
  company: string | null;
  parsed: Record<string, unknown> | null;
  input_meta?: {
    sources?: Array<{
      type: string;
      name?: string;
      url?: string;
      title?: string | null;
      chars: number;
      error: string | null;
    }>;
    notes?: string[];
    total_chars?: number;
  } | null;
  status: 'pending' | 'parsed' | 'matched' | 'composed' | 'failed';
  error: string | null;
  drafts: Draft[];
  created_at: string | null;
}

// ---------------- Riwayat pesan lamaran ----------------

export interface HistoryEvent {
  id: number;
  job_id: number | null;
  event: 'generated' | 'variant' | 'revised' | 'copied';
  kind: 'utama' | 'varian';
  channel: string | null;
  position: string | null;
  company: string | null;
  message_text: string;
  instruction: string | null;
  word_count: number | null;
  created_at: string | null;
}

export interface HistoryMonth {
  /** format YYYY-MM */
  key: string;
  count: number;
  events: HistoryEvent[];
}

export interface HistoryResponse {
  total: number;
  months: HistoryMonth[];
}

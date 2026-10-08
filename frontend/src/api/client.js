import axios from 'axios'
import { useUiStore } from '@/stores/ui'

export const TOKEN_KEY = 'bengkel.token'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://127.0.0.1:8000/api/v1',
  headers: { Accept: 'application/json' },
  timeout: 60000,
})

api.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY)
  if (token) config.headers.Authorization = `Bearer ${token}`
  // Jangan biarkan axios menetapkan JSON content-type pada FormData (boundary rusak).
  if (config.data instanceof FormData) delete config.headers['Content-Type']
  return config
})

export function pesanGalat(error) {
  if (!error.response) return 'Tidak dapat terhubung ke server. Periksa koneksi atau server API.'
  const status = error.response.status
  const data = error.response.data
  if (data?.errors) {
    const kunci = Object.keys(data.errors)
    const daftar = kunci.map((k) => data.errors[k]).flat()
    if (daftar.length) return daftar.join(' ')
  }
  if (data?.message) return data.message
  if (status === 401) return 'Sesi berakhir, silakan masuk kembali.'
  if (status === 403) return 'Anda tidak berwenang melakukan aksi ini.'
  if (status === 404) return 'Data tidak ditemukan.'
  if (status === 422) return 'Data yang dikirim tidak valid.'
  if (status === 429) return 'Terlalu banyak percobaan. Coba beberapa saat lagi.'
  return 'Terjadi kesalahan pada server.'
}

/** Ambil pesan validasi per-field (untuk ditempel di form). */
export function galatValidasi(error) {
  const hasil = {}
  const errors = error?.response?.data?.errors
  if (errors) {
    Object.keys(errors).forEach((k) => {
      hasil[k] = Array.isArray(errors[k]) ? errors[k][0] : String(errors[k])
    })
  }
  return hasil
}

api.interceptors.response.use(
  (res) => res,
  (error) => {
    const status = error.response?.status
    if (status === 401) {
      localStorage.removeItem(TOKEN_KEY)
      try {
        useUiStore().gagal('Sesi berakhir, silakan masuk kembali.')
      } catch {
        /* pinia belum aktif */
      }
      if (!window.location.pathname.startsWith('/login')) {
        window.location.replace('/login')
      }
    } else if (status !== 422) {
      try {
        useUiStore().gagal(pesanGalat(error))
      } catch {
        /* pinia belum aktif */
      }
    }
    return Promise.reject(error)
  },
)

export default api

import { defineStore } from 'pinia'
import { authApi } from '@/api'
import { TOKEN_KEY } from '@/api/client'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem(TOKEN_KEY),
    user: null,
    roles: [],
    permissions: [],
    siap: false,
    memuat: false,
  }),
  getters: {
    sudahMasuk: (s) => !!s.token,
    nama: (s) => s.user?.name || '',
    peranUtama: (s) => s.roles[0] || null,
    isMember: (s) => s.roles.includes('member'),
    isOwner: (s) => s.roles.includes('owner'),
    isAdmin: (s) => s.roles.includes('owner') || s.roles.includes('admin'),
    wajibGantiPassword: (s) => !!s.user?.must_change_password,
    bisaGantiPassword: (s) => !!s.user && !s.wajibGantiPassword,
  },
  actions: {
    async masuk(login, password) {
      const res = await authApi.masuk({ login, password })
      const data = res.data
      this.token = data.token
      localStorage.setItem(TOKEN_KEY, data.token)
      this.terapkan(data)
      return data
    },
    async ambilProfil() {
      this.memuat = true
      try {
        const res = await authApi.saya()
        this.terapkan(res.data)
      } catch (e) {
        // 401 = token memang sudah tidak berlaku → bersihkan sesi.
        // Galat jaringan (server mati / koneksi putus) JANGAN menghapus token:
        // tanpa pembedaan ini, gangguan jaringan sesaat melempar pengguna ke halaman masuk.
        if (e?.response?.status === 401) this.bersihkan()
        throw e
      } finally {
        this.memuat = false
        this.siap = true
      }
    },
    terapkan(data) {
      this.user = data.user || null
      this.roles = data.roles || []
      this.permissions = data.permissions || []
    },
    async keluar() {
      try {
        await authApi.keluar()
      } catch {
        /* token mungkin sudah tidak valid */
      }
      this.bersihkan()
    },
    bersihkan() {
      this.token = null
      this.user = null
      this.roles = []
      this.permissions = []
      this.siap = true
      localStorage.removeItem(TOKEN_KEY)
    },
    /** Cek izin: `bisa('customer.manage')` — owner selalu boleh. */
    bisa(izin) {
      if (this.roles.includes('owner')) return true
      if (!izin) return true
      const daftar = Array.isArray(izin) ? izin : [izin]
      return daftar.some((i) => this.permissions.includes(i))
    },
    punyaPeran(...peran) {
      return peran.flat().some((p) => this.roles.includes(p))
    },
  },
})

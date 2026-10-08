import { defineStore } from 'pinia'

let nomorToast = 0

export const useUiStore = defineStore('ui', {
  state: () => ({
    toasts: [],
    konfirmasi: null,
    sidebarTerbuka: false,
  }),
  actions: {
    dorong(jenis, pesan, opsi = {}) {
      const id = ++nomorToast
      this.toasts.push({ id, jenis, pesan, judul: opsi.judul || null, waktu: Date.now() })
      const durasi = opsi.durasi ?? (jenis === 'gagal' ? 6000 : 3500)
      setTimeout(() => this.tutupToast(id), durasi)
      return id
    },
    sukses(pesan, opsi) {
      return this.dorong('sukses', pesan, opsi)
    },
    gagal(pesan, opsi) {
      return this.dorong('gagal', pesan, opsi)
    },
    info(pesan, opsi) {
      return this.dorong('info', pesan, opsi)
    },
    tutupToast(id) {
      this.toasts = this.toasts.filter((t) => t.id !== id)
    },
    /** Buka dialog konfirmasi; mengembalikan Promise<boolean>. */
    tanya({ judul = 'Konfirmasi', pesan = '', teksOk = 'Lanjutkan', teksBatal = 'Batal', jenis = 'bahaya' } = {}) {
      return new Promise((selesai) => {
        this.konfirmasi = {
          judul,
          pesan,
          teksOk,
          teksBatal,
          jenis,
          jawab: (nilai) => {
            this.konfirmasi = null
            selesai(nilai)
          },
        }
      })
    },
    bukaSidebar() {
      this.sidebarTerbuka = true
    },
    tutupSidebar() {
      this.sidebarTerbuka = false
    },
  },
})

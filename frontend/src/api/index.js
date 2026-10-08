import api from './client'

/* ------------------------------ helper dasar ------------------------------ */
export const ambil = (url, params) => api.get(url, { params }).then((r) => r.data)
export const kirim = (url, body) => api.post(url, body).then((r) => r.data)
export const ubah = (url, body) => api.put(url, body).then((r) => r.data)
export const patch = (url, body) => api.patch(url, body).then((r) => r.data)
export const hapus = (url) => api.delete(url).then((r) => r.data)

/**
 * Buka berkas ber-token (PDF) di TAB BARU — bukan langsung terunduh.
 * Pengguna dapat melihat nota/laporan dulu, lalu mencetaknya dari penampil PDF.
 * Bila popup diblokir peramban, jatuh ke unduhan biasa agar tidak gagal diam-diam.
 */
export async function pratinjau(url, params, namaCadangan = 'pratinjau') {
  // PENTING: tab dibuka SINKRON di dalam gestur klik. `window.open()` yang
  // dipanggil setelah `await fetch` sudah keluar dari gestur pengguna sehingga
  // diblokir popup blocker — akibatnya pratinjau berubah menjadi unduhan,
  // padahal justru unduhan yang ingin dihindari.
  const tab = window.open('', '_blank')

  if (tab) {
    try {
      tab.document.write(
        '<!doctype html><title>Menyiapkan dokumen…</title>' +
          '<body style="font-family:system-ui;padding:2rem;color:#475569">Menyiapkan dokumen…</body>',
      )
    } catch {
      /* tab sudah dinavigasi peramban — abaikan */
    }
  }

  try {
    const res = await api.get(url, { params, responseType: 'blob' })
    const nama = namaDariHeader(res.headers['content-disposition']) || namaCadangan
    const bola = new Blob([res.data], { type: res.headers['content-type'] || 'application/pdf' })
    const blobUrl = URL.createObjectURL(bola)

    if (tab) {
      tab.location.href = blobUrl
      tab.focus()
      // Objek URL ditahan cukup lama; mencabutnya terlalu cepat membuat tab kosong.
      setTimeout(() => URL.revokeObjectURL(blobUrl), 120000)
      return nama
    }

    // Popup benar-benar diblokir → terpaksa unduh, jangan gagal diam-diam.
    const a = document.createElement('a')
    a.href = blobUrl
    a.download = nama
    document.body.appendChild(a)
    a.click()
    a.remove()
    setTimeout(() => URL.revokeObjectURL(blobUrl), 10000)
    return nama
  } catch (e) {
    if (tab) tab.close()
    throw e
  }
}

/** Unduh berkas ber-token (Excel/CSV) → memicu unduhan di peramban. */
export async function unduh(url, params, namaCadangan = 'unduhan') {
  const res = await api.get(url, { params, responseType: 'blob' })
  const nama = namaDariHeader(res.headers['content-disposition']) || namaCadangan
  const blobUrl = URL.createObjectURL(res.data)
  const a = document.createElement('a')
  a.href = blobUrl
  a.download = nama
  document.body.appendChild(a)
  a.click()
  a.remove()
  setTimeout(() => URL.revokeObjectURL(blobUrl), 4000)
  return nama
}

function namaDariHeader(header) {
  if (!header) return null
  const bintang = /filename\*=UTF-8''([^;]+)/i.exec(header)
  if (bintang) return decodeURIComponent(bintang[1])
  const biasa = /filename="?([^";]+)"?/i.exec(header)
  if (biasa) return biasa[1]
  return null
}

/* ---------------------------------- auth --------------------------------- */
export const authApi = {
  masuk: (body) => kirim('/auth/login', body),
  keluar: () => kirim('/auth/logout'),
  saya: () => ambil('/auth/me'),
  gantiPassword: (body) => kirim('/auth/change-password', body),
}

/* -------------------------------- dashboard ------------------------------- */
export const dashboardApi = {
  ringkasan: () => ambil('/dashboard/summary'),
}

/* --------------------------------- setting -------------------------------- */
export const settingApi = {
  daftar: () => ambil('/settings'),
  simpan: (settings) => ubah('/settings', { settings }),
}

export const penggunaApi = {
  daftar: (params) => ambil('/users', params),
  simpan: (body) => kirim('/users', body),
  ubah: (id, body) => ubah(`/users/${id}`, body),
  hapus: (id) => hapus(`/users/${id}`),
}

/* ------------------------------- pelanggan -------------------------------- */
export const pelangganApi = {
  daftar: (params) => ambil('/customers', params),
  detail: (id) => ambil(`/customers/${id}`),
  simpan: (body) => kirim('/customers', body),
  ubah: (id, body) => ubah(`/customers/${id}`, body),
  hapus: (id) => hapus(`/customers/${id}`),
  tambahKendaraan: (id, body) => kirim(`/customers/${id}/vehicles`, body),
  ubahKendaraan: (id, body) => ubah(`/vehicles/${id}`, body),
  hapusKendaraan: (id) => hapus(`/vehicles/${id}`),
  jadikanMember: (id) => kirim(`/customers/${id}/membership`),
  perpanjangMember: (id) => kirim(`/customers/${id}/membership/renew`),
  poin: (id, params) => ambil(`/customers/${id}/points`, params),
  tukarPoin: (id, body) => kirim(`/customers/${id}/redeem`, body),
  riwayat: (id) => ambil(`/customers/${id}/history`),
}

/* --------------------------------- master --------------------------------- */
const crud = (jalur) => ({
  daftar: (params) => ambil(jalur, params),
  detail: (id) => ambil(`${jalur}/${id}`),
  simpan: (body) => kirim(jalur, body),
  ubah: (id, body) => ubah(`${jalur}/${id}`, body),
  hapus: (id) => hapus(`${jalur}/${id}`),
  /** Pencarian ringan untuk dropdown (tanpa paginasi). */
  pilihan: (params) => ambil(`${jalur}/pilihan`, params),
})

export const mekanikApi = crud('/mechanics')
export const jasaApi = crud('/services')
export const sparepartApi = crud('/spareparts')
export const rewardApi = crud('/rewards')

/* ------------------------------ stok & keuangan ---------------------------- */
export const stokApi = {
  pembelian: (params) => ambil('/part-purchases', params),
  detailPembelian: (id) => ambil(`/part-purchases/${id}`),
  simpanPembelian: (body) => kirim('/part-purchases', body),
  penyesuaian: (body) => kirim('/stock-adjustments', body),
  kartuStok: (params) => ambil('/stock-movements', params),
  laporan: (params) => ambil('/reports/stock', params),
  pengeluaran: (body) => kirim('/expenses', body),
  daftarPengeluaran: (params) => ambil('/expenses', params),
}

/* -------------------------------- check up -------------------------------- */
export const checkupApi = {
  daftar: (params) => ambil('/checkups', params),
  detail: (id) => ambil(`/checkups/${id}`),
  simpan: (body) => kirim('/checkups', body),
  ubah: (id, body) => ubah(`/checkups/${id}`, body),
  hapus: (id) => hapus(`/checkups/${id}`),
  selesai: (id, body) => kirim(`/checkups/${id}/finish`, body),
}

export const templateApi = {
  daftar: (params) => ambil('/checkup-templates', params),
  detail: (id) => ambil(`/checkup-templates/${id}`),
  simpan: (body) => kirim('/checkup-templates', body),
  ubah: (id, body) => ubah(`/checkup-templates/${id}`, body),
  hapus: (id) => hapus(`/checkup-templates/${id}`),
  duplikat: (id) => kirim(`/checkup-templates/${id}/duplikat`, {}),
  tambahItem: (id, body) => kirim(`/checkup-templates/${id}/items`, body),
  ubahItem: (id, itemId, body) => ubah(`/checkup-templates/${id}/items/${itemId}`, body),
  hapusItem: (id, itemId) => hapus(`/checkup-templates/${id}/items/${itemId}`),
  urutItem: (id, body) => kirim(`/checkup-templates/${id}/items/reorder`, body),
}

/* ------------------------------ service order ------------------------------ */
export const saApi = {
  daftar: (params) => ambil('/service-orders', params),
  detail: (id) => ambil(`/service-orders/${id}`),
  simpan: (body) => kirim('/service-orders', body),
  ubah: (id, body) => ubah(`/service-orders/${id}`, body),
  hapus: (id) => hapus(`/service-orders/${id}`),
  mulai: (id) => kirim(`/service-orders/${id}/start`),
  selesai: (id) => kirim(`/service-orders/${id}/finish`),
  bayar: (id, body) => kirim(`/service-orders/${id}/pay`, body),
  batal: (id, body) => kirim(`/service-orders/${id}/cancel`, body),
}

export const unitEntryApi = {
  daftar: (params) => ambil('/unit-entries', params),
  hapus: (id) => hapus(`/unit-entries/${id}`),
}

/* ---------------------------------- kasir --------------------------------- */
// Modul kasir terpisah dari Form SA: hanya menangani tagihan yang sudah "selesai".
export const kasirApi = {
  tagihan: (params) => ambil('/kasir/tagihan', params),
  ringkasan: () => ambil('/kasir/ringkasan'),
  transaksi: (params) => ambil('/kasir/transaksi', params),
}

/* --------------------------------- laporan -------------------------------- */
export const laporanApi = {
  keuangan: (params) => ambil('/reports/finance', params),
  keuanganPdf: (params) => pratinjau('/reports/finance/export', { ...params, format: 'pdf' }, 'laporan-keuangan.pdf'),
  keuanganExcel: (params) => unduh('/reports/finance/export', { ...params, format: 'excel' }, 'laporan-keuangan.xlsx'),
  stok: (params) => ambil('/reports/stock', params),
  stokPdf: (params) => pratinjau('/reports/stock/pdf', params, 'laporan-stok.pdf'),
  unitEntry: (params) => ambil('/reports/unit-entries', params),
  unitEntryExcel: (params) => unduh('/reports/unit-entries/export', { ...params, format: 'excel' }, 'laporan-unit-entry.xlsx'),
}

export const cetakApi = {
  notaSa: (id) => pratinjau(`/service-orders/${id}/print`, {}, `FormSA-${id}.pdf`),
}

/* --------------------------------- portal --------------------------------- */
export const portalApi = {
  profil: () => ambil('/portal/profile'),
  riwayat: () => ambil('/portal/history'),
  detail: (tipe, id) => ambil(`/portal/history/${tipe}/${id}`),
  kendaraan: () => ambil('/portal/vehicles'),
}
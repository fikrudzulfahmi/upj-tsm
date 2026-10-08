export const STATUS_SA = {
  draft: { label: 'Draft', varian: 'netral' },
  in_progress: { label: 'Dikerjakan', varian: 'peringatan' },
  finished: { label: 'Selesai', varian: 'info' },
  paid: { label: 'Dibayar', varian: 'sukses' },
  cancelled: { label: 'Dibatalkan', varian: 'bahaya' },
}

export function statusSa(kode) {
  return STATUS_SA[kode] || { label: kode || '-', varian: 'netral' }
}

export const STATUS_MEMBER = {
  active: { label: 'Aktif', varian: 'sukses' },
  expired: { label: 'Hangus', varian: 'bahaya' },
}

export function statusMember(kode) {
  return STATUS_MEMBER[kode] || { label: kode || '-', varian: 'netral' }
}

export const JENIS_UNIT = {
  checkup_only: { label: 'Check Up', varian: 'info' },
  service: { label: 'Service', varian: 'brand' },
}

export function jenisUnit(kode) {
  return JENIS_UNIT[kode] || { label: kode || '-', varian: 'netral' }
}

export const TIPE_KENDARAAN = [
  { value: 'motor', label: 'Motor' },
  { value: 'mobil', label: 'Mobil' },
]

export const METODE_BAYAR = [
  { value: 'cash', label: 'Tunai' },
  { value: 'transfer', label: 'Transfer' },
  { value: 'qris', label: 'QRIS' },
]

export function labelMetode(kode) {
  return METODE_BAYAR.find((m) => m.value === kode)?.label || '-'
}

export const KATEGORI_TRANSAKSI = {
  pendapatan_jasa: 'Pendapatan Jasa',
  penjualan_sparepart: 'Penjualan Sparepart',
  pembelian_sparepart: 'Pembelian Sparepart',
  operasional: 'Operasional',
  promosi_poin: 'Promosi Poin',
  lainnya: 'Lainnya',
}

/**
 * Aksi membership yang perlu ditampilkan untuk sebuah pelanggan.
 * Dipakai bersama oleh halaman detail dan daftar pelanggan agar seragam.
 *
 * - belum punya membership  -> "Jadikan Member"
 * - membership hangus       -> "Aktifkan Kembali" (backend memperpanjang dari hari ini)
 * - masih aktif             -> tidak ada aksi (cukup Perpanjang/Tukar Poin di detail)
 */
export function aksiMember(membership) {
  if (!membership) return { tampil: true, label: 'Jadikan Member', hangus: false }
  if (membership.status !== 'active') return { tampil: true, label: 'Aktifkan Kembali', hangus: true }
  return { tampil: false, label: '', hangus: false }
}

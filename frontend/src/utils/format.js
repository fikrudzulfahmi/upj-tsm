/**
 * Utilitas format Indonesia.
 * PENTING: tanggal-saja ('YYYY-MM-DD') jangan dilewatkan `new Date()` — string itu
 * dianggap UTC dan harinya bisa bergeser. Selalu ambil komponennya lewat regex.
 */

const NAMA_BULAN = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]
const NAMA_HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']

/** Pisahkan 'YYYY-MM-DD' (atau ISO dengan jam) menjadi komponen angka. */
export function komponenTanggal(value) {
  if (!value) return null
  const cocok = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value))
  if (!cocok) return null
  return { tahun: Number(cocok[1]), bulan: Number(cocok[2]), hari: Number(cocok[3]) }
}

/** 'YYYY-MM-DD' → '6 Juli 2026' */
export function formatTanggal(value) {
  const k = komponenTanggal(value)
  if (!k) return '-'
  return `${k.hari} ${NAMA_BULAN[k.bulan - 1]} ${k.tahun}`
}

/** 'YYYY-MM-DD' → '06-07-2026' */
export function formatTanggalAngka(value) {
  const k = komponenTanggal(value)
  if (!k) return '-'
  const d = String(k.hari).padStart(2, '0')
  const b = String(k.bulan).padStart(2, '0')
  return `${d}-${b}-${k.tahun}`
}

/** 'YYYY-MM-DD' → 'Sen, 06 Jul 2026' */
export function formatTanggalSingkat(value) {
  const k = komponenTanggal(value)
  if (!k) return '-'
  const hari = NAMA_HARI[new Date(k.tahun, k.bulan - 1, k.hari).getDay()]
  return `${hari}, ${String(k.hari).padStart(2, '0')} ${NAMA_BULAN[k.bulan - 1].slice(0, 3)} ${k.tahun}`
}

/** ISO timestamp → '06 Jul 2026 14:30' */
export function formatTanggalJam(value) {
  if (!value) return '-'
  const iso = /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/.exec(String(value))
  if (iso) {
    const [, t, b, h, j, m] = iso
    return `${h} ${NAMA_BULAN[Number(b) - 1].slice(0, 3)} ${t} ${j}:${m}`
  }
  return formatTanggal(value)
}

/** Tanggal hari ini sebagai 'YYYY-MM-DD' pada waktu LOKAL (bukan UTC). */
export function tanggalHariIni() {
  const d = new Date()
  const b = String(d.getMonth() + 1).padStart(2, '0')
  const h = String(d.getDate()).padStart(2, '0')
  return `${d.getFullYear()}-${b}-${h}`
}

/** Jam lokal 'HH:mm'. */
export function jamSekarang() {
  const d = new Date()
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}

/** 1250000 → 'Rp 1.250.000' */
export function formatRupiah(value) {
  const angka = Number(value ?? 0)
  if (Number.isNaN(angka)) return 'Rp 0'
  return 'Rp ' + Math.round(angka).toLocaleString('id-ID')
}

/** 1250000 → '1.250.000' (tanpa prefix) */
export function formatAngka(value) {
  const angka = Number(value ?? 0)
  if (Number.isNaN(angka)) return '0'
  return Math.round(angka).toLocaleString('id-ID')
}

/** 'Rp 1.250.000' / '1250000' / '1.250.000' → 1250000 (integer Rupiah) */
export function parseRupiah(value) {
  if (typeof value === 'number') return Math.round(value)
  const bersih = String(value ?? '').replace(/[^\d-]/g, '')
  if (bersih === '' || bersih === '-') return 0
  return parseInt(bersih, 10) || 0
}

/** Selisih hari dari hari ini ke tanggal target (boleh negatif). */
export function selisihHari(value) {
  const k = komponenTanggal(value)
  if (!k) return null
  const target = new Date(k.tahun, k.bulan - 1, k.hari)
  const hari_ini = new Date()
  hari_ini.setHours(0, 0, 0, 0)
  return Math.round((target - hari_ini) / 86400000)
}

/** Label bulan untuk filter laporan: 'YYYY-MM' → 'Juli 2026' */
export function formatBulan(value) {
  const cocok = /^(\d{4})-(\d{2})/.exec(String(value || ''))
  if (!cocok) return '-'
  return `${NAMA_BULAN[Number(cocok[2]) - 1]} ${cocok[1]}`
}

export const daftarBulan = NAMA_BULAN

/**
 * Encoder ESC/POS untuk printer struk thermal (58mm / 80mm).
 *
 * Sengaja dibuat MURNI: tanpa dependensi, tanpa akses DOM, tanpa alias impor,
 * sehingga bisa diuji langsung dengan Node (`node tools/uji-escpos.mjs`).
 *
 * Perintah yang dipakai (lihat referensi ESC/POS):
 *   ESC @        inisialisasi
 *   ESC a n      perataan (0 kiri, 1 tengah, 2 kanan)
 *   ESC E n      huruf tebal
 *   GS  ! n      ukuran huruf (0x00 normal, 0x11 lebar & tinggi 2x)
 *   ESC d n      umpan n baris
 *   GS V 66 0    umpan lalu potong sebagian
 */

export const LEBAR_58 = 32 // 58mm, font A (12x24) = 32 karakter
export const LEBAR_80 = 48 // 80mm, font A = 48 karakter

const ESC = 0x1b
const GS = 0x1d
const LF = 0x0a

/**
 * Transliterasi teks ke ASCII agar aman pada halaman kode bawaan printer (CP437).
 * Contoh nyata di aplikasi: "—" (em dash), "×", "·", "“ ”" semuanya harus diganti,
 * sebab kalau tidak akan tercetak sebagai karakter sampah.
 */
export function keAscii(teks = '') {
  return String(teks)
    .replace(/[\u2014\u2013]/g, '-')
    .replace(/[\u201c\u201d]/g, '"')
    .replace(/[\u2018\u2019]/g, "'")
    .replace(/[\u00d7\u2715]/g, 'x')
    .replace(/[\u00b7\u2022]/g, '.')
    .replace(/\u2026/g, '...')
    .replace(/\u00b0/g, ' derajat')
    .replace(/[\u00a0\u202f]/g, ' ')
    .replace(/[^\x20-\x7E]/g, '')
}

/** Angka gaya Indonesia tanpa "Rp": 1060000 -> "1.060.000" (deterministik, tanpa ICU). */
export function angka(nilai) {
  const nbulat = Math.round(Math.abs(Number(nilai) || 0))
  const teks = String(nbulat).replace(/\B(?=(\d{3})+(?!\d))/g, '.')
  return Number(nilai) < 0 ? '-' + teks : teks
}

/** Pecah teks menjadi baris maksimal `lebar` karakter tanpa memotong kata. */
export function bungkus(teks, lebar = LEBAR_58) {
  const baris = []
  let kini = ''

  for (const kata of keAscii(teks).split(/\s+/).filter(Boolean)) {
    if (!kini) {
      kini = kata
    } else if (kini.length + 1 + kata.length <= lebar) {
      kini += ' ' + kata
    } else {
      baris.push(kini)
      kini = kata
    }

    // Kata tunggal yang lebih panjang dari kertas: potong paksa.
    while (kini.length > lebar) {
      baris.push(kini.slice(0, lebar))
      kini = kini.slice(lebar)
    }
  }

  if (kini) baris.push(kini)
  return baris.length ? baris : ['']
}

/** Gabung dua kolom dalam satu baris selebar `lebar` (kiri dipotong bila perlu). */
export function duaKolom(kiri, kanan, lebar = LEBAR_58) {
  const kananTeks = keAscii(kanan ?? '')
  const ruang = Math.max(0, lebar - kananTeks.length - 1)
  let kiriTeks = keAscii(kiri ?? '')

  if (kiriTeks.length > ruang) kiriTeks = kiriTeks.slice(0, ruang)

  const jeda = Math.max(1, lebar - kiriTeks.length - kananTeks.length)
  return kiriTeks + ' '.repeat(jeda) + kananTeks
}

/**
 * Cetak baris label-nilai yang aman untuk kertas sempit.
 *
 * `duaKolom` polos memotong kolom kiri bila nilai di kanan panjang. Pada nota
 * nyata itu berbahaya: label penting ikut terpotong ("Pelanggan" -> "Pelan") dan
 * nomor SA terpangkas ("SA-202610-0007" -> "SA-202610-000"). Fungsi ini menjaga
 * label selalu utuh: bila tidak cukup satu baris, label dicetak lebih dulu lalu
 * nilainya dibungkus ke baris berikutnya.
 */
export function kolomNota(n, label, nilai, { inden = true } = {}) {
  const kiri = keAscii(label)
  const kanan = keAscii(nilai ?? '')

  if (kanan === '') return n
  if (kiri.length + 1 + kanan.length <= n.lebar) return n.duaKolom(kiri, kanan)

  // Label dicetak lebih dulu (dan utuh) supaya tidak pernah terpotong.
  n.baris(kiri)

  const awalan = inden ? '  ' : ''
  for (const b of bungkus(kanan, Math.max(8, n.lebar - awalan.length))) {
    n.baris(awalan + b)
  }

  return n
}

function byteDari(teks) {
  const hasil = []
  for (const huruf of keAscii(teks)) hasil.push(huruf.charCodeAt(0))
  return hasil
}

/**
 * Pembangun perintah ESC/POS dengan gaya berantai (chainable).
 * Semua baris otomatis diakhiri LF.
 */
export function buatNota(lebar = LEBAR_58) {
  const byte = []
  const tulis = (...nilai) => byte.push(...nilai)

  const api = {
    lebar,

    init() {
      tulis(ESC, 0x40)
      return api
    },

    baris(teks = '') {
      tulis(...byteDari(teks), LF)
      return api
    },

    /** Baris panjang otomatis dibungkus ke lebar kertas. */
    paragraf(teks = '') {
      for (const b of bungkus(teks, lebar)) api.baris(b)
      return api
    },

    kiri() {
      tulis(ESC, 0x61, 0)
      return api
    },

    tengah() {
      tulis(ESC, 0x61, 1)
      return api
    },

    kanan() {
      tulis(ESC, 0x61, 2)
      return api
    },

    tebal(aktif = true) {
      tulis(ESC, 0x45, aktif ? 1 : 0)
      return api
    },

    /** Huruf 2x lebar & tinggi (dipakai untuk nama bengkel & TOTAL). */
    ganda(aktif = true) {
      tulis(GS, 0x21, aktif ? 0x11 : 0x00)
      return api
    },

    garis(karakter = '-') {
      return api.baris(String(karakter).repeat(lebar))
    },

    duaKolom(kiri, kanan) {
      return api.baris(duaKolom(kiri, kanan, lebar))
    },

    /** Versi aman: label tidak pernah dipotong (kebijakan ada di notaThermal). */
    kolomNota(label, nilai, opsi) {
      return kolomNota(api, label, nilai, opsi)
    },

    umpan(n = 1) {
      tulis(ESC, 0x64, Math.max(0, Math.min(255, Math.round(n))))
      return api
    },

    /** GS V 66 0 — umpan sedikit lalu potong sebagian (aman untuk printer mini). */
    potong() {
      tulis(GS, 0x56, 0x42, 0x00)
      return api
    },

    hasil() {
      return new Uint8Array(byte)
    },
  }

  return api
}
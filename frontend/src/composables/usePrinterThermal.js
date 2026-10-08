/**
 * Manajer printer thermal Bluetooth (Web Bluetooth / BLE) — kirim ESC/POS langsung.
 *
 * Alur yang dipakai kasir:
 *   1. "Pilih Printer"  -> chooser peramban -> pilih HP-M200 (sekali saja)
 *   2. "Cetak Nota"     -> byte ESC/POS dikirim ke printer, tanpa dialog cetak
 *
 * Catatan penting yang sudah diuji:
 *  - Endpoint `requestDevice` WAJIB dipanggil langsung dari gestur klik. Karena itu
 *    ia tidak boleh didahului `await`, kalau tidak Chrome akan menolak ("user gesture
 *    required") dan pratinjau/penoctakan gagal tanpa sebab yang jelas.
 *  - BLE memakai paket kecil, jadi byte dikirim bertahap (chunk) dengan jeda.
 *  - `writeValue` (dengan respons) biasanya terbatas ~20 byte; kalau karakteristik
 *    mendukung `writeWithoutResponse` potongan bisa lebih besar.
 */
import { computed, ref } from 'vue'
import { buatNota, LEBAR_58 } from '@/utils/escpos'
import { susunNota } from '@/utils/notaThermal'

const KUNCI_SIMPAN = 'bengkel.printer.thermal'
const KUNCI_UUID = 'bengkel.printer.uuidTambahan'

/**
 * Profil GATT yang umum dipakai printer struk Bluetooth murah.
 * `requestDevice` hanya mengizinkan akses ke service yang DIDAFTARKAN di sini,
 * jadi daftar ini yang menentukan printer bisa ditemukan atau tidak.
 */
export const PROFIL_PRINTER = [
  { service: '000018f0-0000-1000-8000-00805f9b34fb', karakter: '00002af1-0000-1000-8000-00805f9b34fb', nama: '18F0/2AF1 (ESC-POS umum)' },
  { service: '0000ff00-0000-1000-8000-00805f9b34fb', karakter: '0000ff02-0000-1000-8000-00805f9b34fb', nama: 'FF00/FF02' },
  { service: '0000ffe0-0000-1000-8000-00805f9b34fb', karakter: '0000ffe1-0000-1000-8000-00805f9b34fb', nama: 'FFE0/FFE1 (modul serial HM-10)' },
  { service: '49535343-fe7d-4ae5-8fa9-9fafd205e455', karakter: '49535343-8841-43f4-a8d4-ecbe34729bb3', nama: 'ISSC transparan UART' },
  { service: 'e7810a71-73ae-499d-8c15-faa9aef0c3f2', karakter: 'bef8d6c9-9c21-4c9e-b632-bd58c1009f9f', nama: 'profil printer (Goojprt/Catiga)' },
  { service: '0000fff0-0000-1000-8000-00805f9b34fb', karakter: '0000fff2-0000-1000-8000-00805f9b34fb', nama: 'FFF0/FFF2' },
  { service: '0000ae30-0000-1000-8000-00805f9b34fb', karakter: '0000ae01-0000-1000-8000-00805f9b34fb', nama: 'AE30/AE01' },
]

export const LEBAR_BAWAAN = 32 // 58mm

/* ------------------------------------------------------------------ keadaan */
// Ditaruh di lingkup modul supaya seluruh komponen berbagi satu koneksi printer.
const perangkat = ref(null)
const karakteristik = ref(null)
const namaPrinter = ref('')
const tersambung = ref(false)
const menyambung = ref(false)
const pesan = ref('')
const galat = ref('')
const profilTerpakai = ref('')
const diagnostik = ref([])
const idTersimpan = ref('')
const uuidTambahan = ref([])

const didukung = computed(() => typeof navigator !== 'undefined' && 'bluetooth' in navigator)
const aman = computed(() => typeof window === 'undefined' || window.isSecureContext)

function jeda(ms) {
  return new Promise((r) => setTimeout(r, ms))
}

/* --------------------------------------------------------------- pengaturan */
function muatTersimpan() {
  try {
    const simpan = JSON.parse(localStorage.getItem(KUNCI_SIMPAN) || 'null')
    if (simpan) {
      idTersimpan.value = simpan.id || ''
      namaPrinter.value = simpan.nama || ''
    }
    uuidTambahan.value = JSON.parse(localStorage.getItem(KUNCI_UUID) || '[]') || []
  } catch {
    idTersimpan.value = ''
    namaPrinter.value = ''
    uuidTambahan.value = []
  }
}
muatTersimpan()

function simpanUuidTambahan(daftar) {
  uuidTambahan.value = (daftar || []).map((s) => String(s).trim()).filter(Boolean)
  localStorage.setItem(KUNCI_UUID, JSON.stringify(uuidTambahan.value))
  pesan.value = 'Daftar UUID kustom disimpan.'
}

const semuaProfil = computed(() => [
  ...PROFIL_PRINTER,
  ...uuidTambahan.value.map((u) => ({ service: u, karakter: '', nama: 'kustom' })),
])

/* ------------------------------------------------------------------ koneksi */
/** Bungkus tiap service jadi daftar karakteristik yang bisa ditulis. */
async function telusuriKarakteristik(server) {
  diagnostik.value = []
  let kandidat = null

  for (const layanan of await server.getPrimaryServices()) {
    for (const kar of await layanan.getCharacteristics()) {
      const bisaTulis = kar.properties.write || kar.properties.writeWithoutResponse
      diagnostik.value.push({
        service: layanan.uuid,
        karakter: kar.uuid,
        sifat: [
          kar.properties.write ? 'write' : '',
          kar.properties.writeWithoutResponse ? 'tanpa-respons' : '',
          kar.properties.notify ? 'notify' : '',
        ].filter(Boolean).join('+') || 'baca saja',
        bisaTulis,
      })

      if (bisaTulis && !kandidat) {
        kandidat = kar
        const cocok = PROFIL_PRINTER.find(
          (p) => layanan.uuid.toLowerCase().includes(p.service.slice(0, 8)),
        )
        profilTerpakai.value = cocok ? cocok.nama : layanan.uuid.slice(0, 8) + ' (dari perangkat)'
      }
    }
  }

  return kandidat
}

async function sambungkan(device) {
  menyambung.value = true
  galat.value = ''

  try {
    perangkat.value = device
    const server = await device.gatt.connect()
    const kar = await telusuriKarakteristik(server)

    if (!kar) {
      throw new Error(
        'Printer tersambung tetapi tidak ditemukan karakteristik yang bisa ditulis. ' +
          'Salin daftar "Diagnostik GATT" di bawah dan kirimkan ke pengembang.',
      )
    }

    karakteristik.value = kar
    namaPrinter.value = device.name || 'Printer Bluetooth'
    tersambung.value = true
    pesan.value = `Tersambung ke ${namaPrinter.value}.`

    localStorage.setItem(KUNCI_SIMPAN, JSON.stringify({ id: device.id, nama: namaPrinter.value }))
    idTersimpan.value = device.id

    device.removeEventListener('gattserverdisconnected', saatTerputus)
    device.addEventListener('gattserverdisconnected', saatTerputus)
  } catch (e) {
    tersambung.value = false
    karakteristik.value = null
    galat.value = e?.message || 'Gagal menyambung ke printer.'
  } finally {
    menyambung.value = false
  }
}

function saatTerputus() {
  tersambung.value = false
  karakteristik.value = null
  pesan.value = 'Printer terputus. Klik "Sambungkan Ulang".'
}

/** HARUS dipanggil langsung dari klik (tanpa await di depannya). */
async function pilihPrinter() {
  if (!didukung.value) {
    galat.value = 'Peramban ini tidak mendukung Web Bluetooth. Gunakan Chrome/Edge di Android (atau desktop).'
    return
  }

  galat.value = ''
  pesan.value = 'Memilih printer…'

  try {
    const device = await navigator.bluetooth.requestDevice({
      acceptAllDevices: true, // daftar printer ditampilkan semua; printer tanpa profil dikenal pun bisa dipilih
      optionalServices: semuaProfil.value.map((p) => p.service),
    })
    await sambungkan(device)
  } catch (e) {
    if (e?.name === 'NotFoundError') {
      galat.value = 'Tidak ada printer yang dipilih (dibatalkan).'
    } else {
      galat.value = e?.message || 'Gagal membuka daftar printer.'
    }
    pesan.value = ''
  }
}

/** Coba sambung tanpa chooser memakai izin yang sudah pernah diberikan. */
async function sambungkanTersimpan() {
  if (!didukung.value) return false

  try {
    if (typeof navigator.bluetooth.getDevices !== 'function') return false

    const daftar = await navigator.bluetooth.getDevices()
    const device =
      daftar.find((d) => d.id === idTersimpan.value) ||
      daftar.find((d) => (d.name || '') && (d.name || '') === namaPrinter.value)

    if (!device) return false

    await sambungkan(device)
    return tersambung.value
  } catch {
    return false
  }
}

function putuskan() {
  try {
    perangkat.value?.gatt?.disconnect()
  } catch {
    /* sudah terputus */
  }
  tersambung.value = false
  karakteristik.value = null
  pesan.value = 'Printer diputuskan.'
}

/* --------------------------------------------------------------- pencetakan */
async function kirimByte(bytes) {
  if (!tersambung.value || !karakteristik.value) {
    throw new Error('Printer thermal belum tersambung.')
  }

  const kar = karakteristik.value
  const tanpaRespons = !!kar.properties?.writeWithoutResponse
  const potongan = tanpaRespons ? 100 : 20 // paket BLE kecil; `write` terbatas ~20 byte

  for (let i = 0; i < bytes.length; i += potongan) {
    const bagian = bytes.slice(i, i + potongan)
    if (tanpaRespons) await kar.writeValueWithoutResponse(bagian)
    else await kar.writeValue(bagian)
    await jeda(tanpaRespons ? 25 : 12)
  }

  pesan.value = `${bytes.length} byte terkirim ke ${namaPrinter.value}.`
  return bytes.length
}

/** Cetak nota Form SA. */
async function cetakNota(sa, bengkel, opsi = {}) {
  const bytes = susunNota(sa, bengkel, { lebar: opsi.lebar || LEBAR_BAWAAN, ...opsi })
  await kirimByte(bytes)
  return bytes.length
}

/** Cetak slip uji: membuktikan koneksi, lebar 32 kolom, dan potong kertas bekerja. */
async function cetakUji(bengkel = {}) {
  const n = buatNota(LEBAR_BAWAAN)
  const waktu = new Date().toLocaleString('id-ID')

  n.init()
    .tengah()
    .ganda(true)
    .tebal(true)
    .paragraf(bengkel.namaBengkel || 'UJI PRINTER')
    .ganda(false)
    .tebal(false)
    .garis('=')
    .tebal(true)
    .baris('UJI PRINTER THERMAL')
    .tebal(false)
    .baris(waktu)
    .kiri()
    .garis('-')
    .baris('Lebar kertas : 58 mm (32 kolom)')
    .baris('Perintah     : ESC/POS')
    .baris('Potong       : otomatis')
    .garis('-')
    .tengah()
    .paragraf('Jika teks di atas tercetak rapi, printer siap dipakai.')
    .baris('')
    .umpan(3)
    .potong()

  await kirimByte(n.hasil())
  return true
}

export function usePrinterThermal() {
  return {
    // keadaan
    didukung,
    aman,
    tersambung,
    menyambung,
    namaPrinter,
    pesan,
    galat,
    profilTerpakai,
    diagnostik,
    uuidTambahan,
    // aksi
    pilihPrinter,
    sambungkanTersimpan,
    putuskan,
    kirimByte,
    cetakNota,
    cetakUji,
    simpanUuidTambahan,
  }
}
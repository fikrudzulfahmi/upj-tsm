/**
 * Uji encoder ESC/POS tanpa perlu printer fisik.
 *
 * Skrip ini membaca ulang byte hasil encoder dengan "penafsir mini" yang paham
 * perintah yang kita pakai, lalu memeriksa hal-hal yang menentukan nota tercetak
 * benar di kertas 58mm:
 *   1. setiap baris <= 32 karakter (kalau lebih, printer akan membungkusnya sendiri
 *      dan nota jadi berantakan),
 *   2. tidak ada byte non-ASCII (halaman kode CP437 akan mencetaknya sebagai sampah),
 *   3. perintah potong kertas & ukuran huruf benar-benar ada,
 *   4. transliterasi karakter Indonesia ("—", "×", "·") bekerja.
 *
 * Jalankan: node tools/uji-escpos.mjs
 */
import { LEBAR_58, angka, buatNota, bungkus, duaKolom, keAscii } from '../src/utils/escpos.js'
import { susunNota } from '../src/utils/notaThermal.js'

let lulus = 0
let gagal = 0

function cek(nama, benar, detail = '') {
  if (benar) {
    lulus++
    console.log(`  OK   ${nama}`)
  } else {
    gagal++
    console.log(`  GAGAL ${nama} ${detail}`)
  }
}

/** Penafsir mini ESC/POS: memisahkan perintah dari teks. */
function baca(byte) {
  const baris = []
  const perintah = { init: 0, tebal: 0, ganda: 0, potong: 0, perataan: [], umpan: 0 }
  let kini = ''
  let byteTertinggi = 0

  for (let i = 0; i < byte.length; i++) {
    const b = byte[i]

    if (b === 0x1b) {
      const c = byte[i + 1]
      if (c === 0x40) { perintah.init++; i += 1; continue }
      if (c === 0x61) { perintah.perataan.push(byte[i + 2]); i += 2; continue }
      if (c === 0x45) { if (byte[i + 2]) perintah.tebal++; i += 2; continue }
      if (c === 0x64) { perintah.umpan = byte[i + 2]; i += 2; continue }
      i += 1
      continue
    }

    if (b === 0x1d) {
      const c = byte[i + 1]
      if (c === 0x21) { if (byte[i + 2]) perintah.ganda++; i += 2; continue }
      if (c === 0x56) { perintah.potong++; i += 3; continue }
      i += 1
      continue
    }

    if (b === 0x0a) { baris.push(kini); kini = ''; continue }

    byteTertinggi = Math.max(byteTertinggi, b)
    kini += String.fromCharCode(b)
  }

  if (kini) baris.push(kini)
  return { baris, perintah, byteTertinggi }
}

console.log('\n=== 1. Perintah dasar ESC/POS ===')
{
  const nota = buatNota(LEBAR_58)
  nota.init().tebal(true).tengah().baris('HALO').tebal(false).kiri().umpan(3).potong()
  const byte = nota.hasil()
  const { baris, perintah } = baca(byte)

  cek('inisialisasi ESC @ dikirim', perintah.init === 1)
  cek('huruf tebal aktif & dimatikan', perintah.tebal === 1)
  cek('perataan tengah dikirim', perintah.perataan.includes(1))
  cek('perataan kiri dikirim', perintah.perataan.includes(0))
  cek('potong kertas GS V 66 0 dikirim', perintah.potong === 1)
  cek('umpan 3 baris', perintah.umpan === 3)
  cek('teks utuh', baris.includes('HALO'))
  cek('byte mentah sesuai harapan', Array.from(byte.slice(0, 2)).join(',') === '27,64', `dapat ${Array.from(byte.slice(0, 2)).join(',')}`)
}

console.log('\n=== 2. Utilitas teks ===')
{
  cek('angka ribuan Indonesia', angka(1060000) === '1.060.000', angka(1060000))
  cek('angka negatif', angka(-5000) === '-5.000', angka(-5000))
  cek('angka nol', angka(0) === '0')
  cek('angka desimal dibulatkan', angka(65000.4) === '65.000')

  const terjemah = keAscii('Rp 65.000 — Oli × 2 · “promo”')
  cek('transliterasi karakter Indonesia', /^[\x20-\x7E]+$/.test(terjemah), JSON.stringify(terjemah))
  cek('em dash jadi tanda hubung', terjemah.includes('-'), terjemah)

  const kolom = duaKolom('Oli Mesin 10W-40', '65.000', LEBAR_58)
  cek('dua kolom selebar kertas', kolom.length === LEBAR_58, `panjang ${kolom.length}`)
  cek('dua kolom rata kanan', kolom.endsWith('65.000'), JSON.stringify(kolom))

  const kolomPanjang = duaKolom('Nama pekerjaan yang sangat panjang sekali sampai melebihi kertas', '125.000', LEBAR_58)
  cek('kolom kiri dipotong bila kepanjangan', kolomPanjang.length === LEBAR_58, `panjang ${kolomPanjang.length}`)

  const panjang = 'Ganti oli mesin sekaligus filter dan pemeriksaan rem depan belakang menyeluruh'
  const bungkusan = bungkus(panjang, LEBAR_58)
  cek('pembungkusan kata', bungkusan.every((b) => b.length <= LEBAR_58), JSON.stringify(bungkusan))
  cek('pembungkusan mempertahankan seluruh teks', bungkusan.join(' ') === panjang, JSON.stringify(bungkusan.join(' ')))
  cek('pembungkusan jadi lebih dari satu baris', bungkusan.length > 1, `${bungkusan.length} baris`)
}

console.log('\n=== 3. Nota lengkap (SA member, ada diskon & sparepart) ===')
{
  const sa = {
    sa_no: 'SA-202610-0007',
    status: 'paid',
    created_at: '2026-10-07T07:26:59.000000Z',
    customer_name: 'Budi Hartono Wijaya Kusuma',
    plate_number: 'AB9012GH',
    vehicle_name: 'Honda Vario 125 — 2021',
    odometer: 18700,
    is_member_at_entry: true,
    member_discount_percent: 10,
    subtotal_services: 750000,
    discount_services: 75000,
    total_parts: 130000,
    grand_total: 805000,
    paid_amount: 850000,
    payment_method: 'cash',
    mechanic: { name: 'Budi Santoso' },
    services: [
      { name: 'Servis Besar (Turun Mesin) + Pembersihan Kerak Ruang Bakar', qty: 1, price: 750000, subtotal: 675000, discount_amount: 75000, discount_percent: 10 },
    ],
    parts: [
      { name: 'Oli Mesin 10W-40 (1 Liter)', qty: 1, sell_price: 65000, subtotal: 65000 },
      { name: 'Oli Gratis Hadiah Poin', qty: 1, sell_price: 65000, subtotal: 65000, is_free_reward: true },
    ],
  }
  const bengkel = { namaBengkel: 'Bengkel Motor Sejahtera', alamatBengkel: 'Jl. Raya Contoh No. 93, Kota Contoh 12345', teleponBengkel: 'Telp 0812-1111-2222' }

  const byte = susunNota(sa, bengkel, { lebar: LEBAR_58, tanggalTeks: '07/10/26 14:26' })
  const { baris, perintah, byteTertinggi } = baca(byte)

  const terpanjang = baris.reduce((maks, b) => Math.max(maks, b.length), 0)
  cek(`tidak ada baris melebihi ${LEBAR_58} karakter`, terpanjang <= LEBAR_58, `terpanjang ${terpanjang}`)
  cek('semua byte ASCII yang aman dicetak', byteTertinggi <= 126, `byte tertinggi ${byteTertinggi}`)
  cek('potong kertas dikirim', perintah.potong === 1)
  cek('ada huruf tebal (TOTAL & judul)', perintah.tebal >= 4, `tebal ${perintah.tebal}`)
  cek('huruf ganda dipakai untuk kop & TOTAL', perintah.ganda === 2, `ganda ${perintah.ganda}`)
  cek('rupiah ikut tercetak pada TOTAL', baris.some((b) => b.includes('Rp 805.000')), baris.filter((b) => b.includes('805')).join(' / '))
  cek('diskon member tercetak', baris.some((b) => b.includes('-75.000')))
  cek('sparepart hadiah poin jadi GRATIS', baris.some((b) => b.includes('GRATIS')))
  cek('kembalian dihitung dari jumlah bayar', baris.some((b) => b.includes('Kembalian') && b.includes('45.000')), baris.filter((b) => b.includes('Kembalian')).join(' / '))
  cek('penanda LUNAS ada', baris.some((b) => b.includes('LUNAS')))
  cek('ukuran byte wajar (< 2 KB)', byte.length < 2048, `${byte.length} byte`)

  // Dua cacat nyata yang ditemukan saat pratinjau pertama:
  // nomor SA terpangkas & label "Pelanggan" terpotong jadi "Pelan".
  cek('nomor SA tidak terpotong', baris.some((b) => b.includes('SA-202610-0007')), baris.find((b) => b.includes('SA-2026')) || '(tidak ada)')
  cek('label Pelanggan utuh', baris.some((b) => b.includes('Pelanggan')))
  cek('nama pelanggan panjang tercetak utuh', baris.some((b) => b.includes('Budi Hartono Wijaya Kusuma')), baris.filter((b) => b.includes('Budi')).join(' / '))
  cek('label Kendaraan utuh', baris.some((b) => b.includes('Kendaraan')))
  const labelTerpangkas = baris.filter((b) => /^(Pelan|No\. Pol|Kendaraa|Mekani|Odomete)\s/.test(b))
  cek('tidak ada label yang terpangkas', labelTerpangkas.length === 0, JSON.stringify(labelTerpangkas))

  console.log('\n----- pratinjau nota (seperti di kertas 58mm / 32 kolom) -----')
  console.log('+' + '-'.repeat(LEBAR_58) + '+')
  for (const b of baris) {
    console.log('|' + b.padEnd(LEBAR_58, ' ').slice(0, LEBAR_58) + '|')
  }
  console.log('+' + '-'.repeat(LEBAR_58) + '+')
}

console.log('\n=== 4. Nota belum dibayar ===')
{
  const byte = susunNota({ sa_no: 'SA-1', status: 'finished', customer_name: 'Tamu', plate_number: 'B 1 CD', services: [], parts: [], subtotal_services: 0, discount_services: 0, total_parts: 0, grand_total: 0 }, {}, { lebar: LEBAR_58 })
  const { baris } = baca(byte)
  cek('menandai BELUM DIBAYAR', baris.some((b) => b.includes('BELUM DIBAYAR')))
  cek('tetap memotong kertas', baca(byte).perintah.potong === 1)
}

console.log(`\nHASIL: ${lulus} lulus, ${gagal} gagal\n`)
process.exit(gagal === 0 ? 0 : 1)
/**
 * Menyusun nota/invoice Form SA menjadi perintah ESC/POS untuk printer thermal.
 * Murni (tanpa alias impor & tanpa DOM) agar dapat diuji dengan Node.
 */
import { LEBAR_58, angka, buatNota, kolomNota } from './escpos.js'

const METODE = { cash: 'Tunai', transfer: 'Transfer', qris: 'QRIS' }

/**
 * dd/mm/yy hh:mm — diambil dengan regex, bukan `new Date()`, agar hari/tanggal
 * tidak bergeser karena perbedaan zona waktu (jebakan yang sudah pernah terjadi).
 */
function tanggalSingkat(iso) {
  const m = String(iso || '').match(/(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})/)
  if (!m) return ''
  return `${m[3]}/${m[2]}/${m[1].slice(2)} ${m[4]}:${m[5]}`
}

export function susunNota(sa, bengkel = {}, opsi = {}) {
  const lebar = opsi.lebar || LEBAR_58
  const n = buatNota(lebar)
  const sudahBayar = sa.status === 'paid'

  // ---------- kop ----------
  n.init()
  n.tengah().ganda(true).tebal(true).paragraf((bengkel.namaBengkel || 'BENGKEL').toUpperCase())
  n.ganda(false).tebal(false)

  if (bengkel.alamatBengkel) n.paragraf(bengkel.alamatBengkel)
  if (bengkel.teleponBengkel) n.baris(bengkel.teleponBengkel)

  n.kiri().garis('=')

  // ---------- identitas ----------
  kolomNota(n, 'No. ' + (sa.sa_no || '-'), opsi.tanggalTeks || tanggalSingkat(sa.created_at), { inden: false })
  kolomNota(n, 'Pelanggan', sa.customer_name || '-')
  kolomNota(n, 'No. Polisi', sa.plate_number || '-')
  if (sa.vehicle_name) kolomNota(n, 'Kendaraan', sa.vehicle_name)
  if (sa.mechanic?.name) kolomNota(n, 'Mekanik', sa.mechanic.name)
  if (sa.odometer) kolomNota(n, 'Odometer', angka(sa.odometer) + ' km')

  if (sa.is_member_at_entry) {
    const diskon = Number(sa.member_discount_percent) || 0
    n.baris(`* Member${diskon ? ` (diskon ${diskon}%)` : ''}`)
  }

  // ---------- pekerjaan ----------
  if (sa.services?.length) {
    n.garis('-').tebal(true).baris('PEKERJAAN').tebal(false)
    for (const j of sa.services) {
      n.paragraf(j.name || '')
      n.duaKolom(`  ${j.qty} x ${angka(j.price)}`, angka(j.subtotal))
      if (Number(j.discount_amount) > 0) n.duaKolom('  diskon member', '-' + angka(j.discount_amount))
    }
  }

  // ---------- sparepart ----------
  if (sa.parts?.length) {
    n.garis('-').tebal(true).baris('SPAREPART').tebal(false)
    for (const p of sa.parts) {
      n.paragraf(p.name || '')
      n.duaKolom(`  ${p.qty} x ${angka(p.sell_price)}`, p.is_free_reward ? 'GRATIS' : angka(p.subtotal))
    }
  }

  // ---------- total ----------
  n.garis('-')
  kolomNota(n, 'Subtotal jasa', angka(sa.subtotal_services))
  if (Number(sa.discount_services) > 0) {
    kolomNota(n, `Diskon member ${sa.member_discount_percent || 0}%`, '-' + angka(sa.discount_services))
  }
  kolomNota(n, 'Total sparepart', angka(sa.total_parts))
  n.garis('-')
  n.tebal(true).ganda(true).kolomNota('TOTAL', 'Rp ' + angka(sa.grand_total))
  n.ganda(false).tebal(false)

  // ---------- status bayar ----------
  if (sudahBayar) {
    kolomNota(n, 'Bayar (' + (METODE[sa.payment_method] || sa.payment_method || '-') + ')', angka(sa.paid_amount ?? sa.grand_total))
    const kembali = Number(sa.paid_amount || 0) - Number(sa.grand_total || 0)
    if (kembali > 0) kolomNota(n, 'Kembalian', angka(kembali))
    n.tengah().tebal(true).baris('*** LUNAS ***').tebal(false)
  } else {
    n.tengah().baris('** BELUM DIBAYAR **')
    n.paragraf('Pembayaran dilakukan di kasir.')
  }

  // ---------- kaki ----------
  n.kiri().garis('=')
  n.tengah()
  n.paragraf('Terima kasih atas kepercayaan Anda')
  n.paragraf('Barang yang sudah dibeli tidak dapat ditukar')
  n.baris('')
  n.umpan(3).potong()

  return n.hasil()
}
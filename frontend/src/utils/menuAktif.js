/**
 * Menentukan apakah sebuah menu sidebar sedang aktif.
 *
 * Kenapa tidak memakai perbandingan teks path?
 *  - Pencocokan awalan membuat `/kasir` ikut aktif saat berada di `/kasir/transaksi`
 *    (dua menu menyala bersamaan).
 *  - Dua menu yang menunjuk rute sama dengan query berbeda (`/spareparts` vs
 *    `/spareparts?menipis=1`) akan sama-sama aktif karena query diabaikan.
 *
 * Karena itu menu diidentifikasi lewat NAMA RUTE (`nama`), bukan path. Halaman
 * turunan (detail/ubah) tetap bisa menyalakan menu induknya dengan menuliskan
 * daftar nama rute.
 *
 * @param {{nama: string|string[], query?: object, tanpaQuery?: string[]}} item
 * @param {import('vue-router').RouteLocationNormalizedLoaded} route
 */
export function menuAktif(item, route) {
  const daftarNama = Array.isArray(item.nama) ? item.nama : [item.nama]

  if (!item.nama || !daftarNama.includes(route.name)) return false

  // Pembedanya bila dua menu memakai rute yang sama (mis. "Stok Menipis").
  if (item.query) {
    return Object.entries(item.query).every(
      ([kunci, nilai]) => String(route.query[kunci] ?? '') === String(nilai),
    )
  }

  if (item.tanpaQuery) {
    return item.tanpaQuery.every((kunci) => route.query[kunci] === undefined)
  }

  return true
}

import { computed, reactive, ref, watch } from 'vue'
import { useUiStore } from '@/stores/ui'

/**
 * Pembungkus daftar berhalaman: ambil data, cari, ganti halaman.
 * `pengambil(filter)` harus mengembalikan amplop { data, meta }.
 */
export function useDaftar(pengambil, opsi = {}) {
  const ui = useUiStore()
  const baris = ref([])
  const meta = reactive({ halaman: 1, per_halaman: opsi.perHalaman || 15, total: 0, total_halaman: 1 })
  const memuat = ref(false)
  const galat = ref(null)
  const filter = reactive({ search: '', halaman: 1, ...(opsi.filter || {}) })

  let timer = null

  async function muat() {
    memuat.value = true
    galat.value = null
    try {
      const res = await pengambil({ ...filter, per_page: meta.per_halaman })
      baris.value = res.data || []
      // Seluruh meta disalin apa adanya: endpoint kasir/laporan menitipkan
      // ringkasan & rekap di sini, sehingga tidak perlu permintaan kedua.
      Object.assign(meta, res.meta || {}, {
        halaman: res.meta?.halaman || 1,
        per_halaman: res.meta?.per_halaman || meta.per_halaman,
        total: res.meta?.total || 0,
        total_halaman: res.meta?.total_halaman || 1,
      })
    } catch (e) {
      galat.value = 'Gagal memuat data.'
      baris.value = []
    } finally {
      memuat.value = false
    }
  }

  /** Ketik pencarian: tunda sedikit agar tidak membanjiri server. */
  function cari() {
    clearTimeout(timer)
    timer = setTimeout(() => {
      filter.halaman = 1
      muat()
    }, 300)
  }

  function keHalaman(h) {
    filter.halaman = h
    muat()
  }

  function saring(kunci, nilai) {
    filter[kunci] = nilai
    filter.halaman = 1
    muat()
  }

  watch(
    () => filter.halaman,
    () => {},
  )

  return { baris, meta, memuat, galat, filter, muat, cari, keHalaman, saring }
}
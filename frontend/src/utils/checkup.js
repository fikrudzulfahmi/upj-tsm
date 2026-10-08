export const STATUS_ITEM = [
  { nilai: 'ok', label: 'OK', singkat: 'OK' },
  { nilai: 'perlu_perhatian', label: 'Perlu Perhatian', singkat: 'PP' },
  { nilai: 'rusak', label: 'Rusak', singkat: 'R' },
  { nilai: 'tidak_diperiksa', label: 'Tidak Diperiksa', singkat: 'TD' },
]

export function labelStatusItem(kode) {
  return STATUS_ITEM.find((s) => s.nilai === kode)?.label || kode || '-'
}

/** Bangun baris hasil pemeriksaan dari item template (SNAPSHOT nama & kategori). */
export function buatBarisDariTemplate(items, hasilLama = []) {
  const peta = new Map()
  ;(hasilLama || []).forEach((h) => peta.set(`${h.category}||${h.item_name}`, h))
  return (items || []).map((it, i) => {
    const lama = peta.get(`${it.category}||${it.name}`)
    return {
      category: it.category,
      item_name: it.name,
      // Default "Tidak diperiksa": item baru TIDAK dianggap OK sebelum petugas
      // benar-benar memeriksanya (permintaan user).
      status: lama?.status || 'tidak_diperiksa',
      note: lama?.note || '',
      sort_order: it.sort_order ?? i + 1,
    }
  })
}

export function kelompokkanPerKategori(baris) {
  const grup = new Map()
  ;(baris || []).forEach((b) => {
    if (!grup.has(b.category)) grup.set(b.category, [])
    grup.get(b.category).push(b)
  })
  return [...grup.entries()].map(([kategori, items]) => ({ kategori, items }))
}

export function ringkasHasil(baris) {
  const r = { total: baris?.length || 0, ok: 0, perlu_perhatian: 0, rusak: 0, tidak_diperiksa: 0, bermasalah: 0 }
  ;(baris || []).forEach((b) => {
    if (r[b.status] !== undefined) r[b.status] += 1
  })
  r.bermasalah = r.perlu_perhatian + r.rusak
  return r
}

/**
 * Saring tujuan pengalihan pasca-masuk agar tidak menjadi open redirect.
 * Hanya jalur internal (diawali satu '/') yang diterima.
 */
export function jalurAman(nilai, cadangan = '/') {
  if (typeof nilai !== 'string' || nilai === '') return cadangan
  if (!nilai.startsWith('/')) return cadangan
  if (nilai.startsWith('//') || nilai.startsWith('/\\')) return cadangan
  if (nilai.includes('://')) return cadangan
  return nilai
}

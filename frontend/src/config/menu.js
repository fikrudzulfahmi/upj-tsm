/**
 * Definisi menu sidebar.
 *
 * `nama` = NAMA RUTE yang menyalakan menu ini (boleh daftar, untuk halaman turunan
 * seperti detail/ubah agar menu induknya tetap menyala). JANGAN memakai awalan path:
 * `/kasir` akan ikut menyala di `/kasir/transaksi`.
 * `query`/`tanpaQuery` dipakai bila dua menu menunjuk rute yang sama dengan query
 * berbeda (mis. "Stok Menipis" = /spareparts?menipis=1).
 *
 * `izin` mengacu ke permission backend (bagian 5 blueprint). Menu disaring di frontend
 * untuk kenyamanan; backend tetap penentu utama.
 */
export const MENU = [
  { grup: null, item: [{ label: 'Dashboard', to: '/', ikon: 'dashboard', nama: 'dashboard' }] },
  {
    grup: 'Bengkel',
    item: [
      {
        label: 'General Check Up',
        to: '/checkups',
        ikon: 'clipboardCheck',
        izin: 'checkup.manage',
        nama: ['checkup', 'checkup-baru', 'checkup-detail', 'checkup-ubah'],
      },
      {
        label: 'Form SA (Pekerjaan)',
        to: '/service-orders',
        ikon: 'clipboardPen',
        izin: 'service-order.manage',
        nama: ['sa', 'sa-baru', 'sa-detail', 'sa-ubah'],
      },
      { label: 'Unit Entry', to: '/unit-entries', ikon: 'listOrdered', izin: 'service-order.manage', nama: 'unit-entry' },
    ],
  },
  {
    grup: 'Kasir',
    item: [
      { label: 'Tagihan Siap Bayar', to: '/kasir', ikon: 'banknote', izin: 'kasir.manage', nama: 'kasir' },
      { label: 'Transaksi Kasir', to: '/kasir/transaksi', ikon: 'handCoins', izin: 'kasir.manage', nama: 'kasir-transaksi' },
    ],
  },
  {
    grup: 'Pelanggan',
    item: [
      {
        label: 'Data Pelanggan',
        to: '/customers',
        ikon: 'users',
        izin: 'customer.manage',
        nama: ['pelanggan', 'pelanggan-detail'],
      },
      { label: 'Reward Poin', to: '/rewards', ikon: 'gift', izin: 'customer.manage', nama: 'reward' },
    ],
  },
  {
    grup: 'Sparepart & Stok',
    item: [
      {
        label: 'Master Sparepart',
        to: '/spareparts',
        ikon: 'package',
        izin: 'sparepart.manage',
        nama: 'sparepart',
        tanpaQuery: ['menipis'], // supaya tidak menyala bersamaan dengan "Stok Menipis"
      },
      {
        label: 'Input Sparepart',
        to: '/part-purchases',
        ikon: 'packagePlus',
        izin: 'stock.input',
        nama: ['pembelian', 'pembelian-baru'],
      },
      {
        label: 'Stok Menipis',
        to: '/spareparts?menipis=1',
        ikon: 'alert',
        izin: 'sparepart.manage',
        nama: 'sparepart',
        query: { menipis: '1' },
      },
    ],
  },
  {
    grup: 'Master',
    item: [
      { label: 'Pekerjaan / Jasa', to: '/services', ikon: 'wrench', izin: 'service.manage', nama: 'jasa' },
      { label: 'Mekanik', to: '/mechanics', ikon: 'userCog', izin: 'mechanic.manage', nama: 'mekanik' },
    ],
  },
  {
    grup: 'Laporan',
    item: [
      { label: 'Keuangan', to: '/reports/finance', ikon: 'chart', izin: 'report.finance', nama: 'laporan-keuangan' },
      { label: 'Pengeluaran Lain', to: '/expenses', ikon: 'money', izin: 'report.finance', nama: 'pengeluaran' },
      { label: 'Stok', to: '/reports/stock', ikon: 'boxes', izin: 'report.stock', nama: 'laporan-stok' },
      { label: 'Unit Entry', to: '/reports/unit-entries', ikon: 'table', izin: 'report.stock', nama: 'laporan-unit' },
    ],
  },
  {
    grup: 'Sistem',
    item: [
      { label: 'Pengaturan', to: '/settings', ikon: 'settings', izin: 'setting.manage', nama: 'pengaturan' },
      { label: 'Pengguna', to: '/users', ikon: 'userCog', izin: 'user.manage', nama: 'pengguna' },
    ],
  },
]

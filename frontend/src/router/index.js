import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { jalurAman } from '@/utils/jalurAman'

const AdminLayout = () => import('@/layouts/AdminLayout.vue')
const PortalLayout = () => import('@/layouts/PortalLayout.vue')

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/views/auth/Login.vue'),
    meta: { publik: true, judul: 'Masuk' },
  },
  {
    path: '/ganti-password',
    name: 'ganti-password',
    component: () => import('@/views/auth/GantiPassword.vue'),
    meta: { butuhAuth: true, lewatiWajibGanti: true, judul: 'Ganti Password' },
  },

  /* ------------------------------------------------------------ admin/kasir */
  {
    path: '/',
    component: AdminLayout,
    meta: { butuhAuth: true },
    children: [
      { path: '', name: 'dashboard', component: () => import('@/views/dashboard/Dashboard.vue'), meta: { judul: 'Dashboard' } },

      { path: 'customers', name: 'pelanggan', component: () => import('@/views/customers/Daftar.vue'), meta: { judul: 'Data Pelanggan', izin: 'customer.manage' } },
      { path: 'customers/:id', name: 'pelanggan-detail', component: () => import('@/views/customers/Detail.vue'), meta: { judul: 'Detail Pelanggan', izin: 'customer.manage' } },

      { path: 'checkups', name: 'checkup', component: () => import('@/views/checkups/Daftar.vue'), meta: { judul: 'General Check Up', izin: 'checkup.manage' } },
      { path: 'checkups/create', name: 'checkup-baru', component: () => import('@/views/checkups/Form.vue'), meta: { judul: 'Check Up Baru', izin: 'checkup.manage' } },
      { path: 'checkups/:id', name: 'checkup-detail', component: () => import('@/views/checkups/Detail.vue'), meta: { judul: 'Detail Check Up', izin: 'checkup.manage' } },
      { path: 'checkups/:id/edit', name: 'checkup-ubah', component: () => import('@/views/checkups/Form.vue'), meta: { judul: 'Ubah Check Up', izin: 'checkup.manage' } },

      { path: 'service-orders', name: 'sa', component: () => import('@/views/service-orders/Daftar.vue'), meta: { judul: 'Form SA (Service)', izin: 'service-order.manage' } },
      { path: 'service-orders/create', name: 'sa-baru', component: () => import('@/views/service-orders/Form.vue'), meta: { judul: 'Form SA Baru', izin: 'service-order.manage' } },
      { path: 'service-orders/:id', name: 'sa-detail', component: () => import('@/views/service-orders/Detail.vue'), meta: { judul: 'Detail Form SA', izin: 'service-order.manage' } },
      { path: 'service-orders/:id/edit', name: 'sa-ubah', component: () => import('@/views/service-orders/Form.vue'), meta: { judul: 'Ubah Form SA', izin: 'service-order.manage' } },

      { path: 'unit-entries', name: 'unit-entry', component: () => import('@/views/unit-entries/Daftar.vue'), meta: { judul: 'Rekap Unit Entry', izin: 'service-order.manage' } },

      // Kasir dipisah dari Form SA: hanya menangani tagihan yang sudah "selesai".
      { path: 'kasir', name: 'kasir', component: () => import('@/views/kasir/Tagihan.vue'), meta: { judul: 'Kasir — Tagihan Siap Bayar', keterangan: 'Pembayaran, nota, dan cetak invoice', izin: 'kasir.manage' } },
      { path: 'kasir/transaksi', name: 'kasir-transaksi', component: () => import('@/views/kasir/Transaksi.vue'), meta: { judul: 'Transaksi Kasir', keterangan: 'Riwayat penerimaan uang & rekap metode bayar', izin: 'kasir.manage' } },

      { path: 'spareparts', name: 'sparepart', component: () => import('@/views/spareparts/Daftar.vue'), meta: { judul: 'Master Sparepart', izin: 'sparepart.manage' } },
      { path: 'part-purchases', name: 'pembelian', component: () => import('@/views/part-purchases/Daftar.vue'), meta: { judul: 'Input Sparepart', izin: 'stock.input' } },
      { path: 'part-purchases/create', name: 'pembelian-baru', component: () => import('@/views/part-purchases/Form.vue'), meta: { judul: 'Input Sparepart Baru', izin: 'stock.input' } },

      { path: 'services', name: 'jasa', component: () => import('@/views/services/Daftar.vue'), meta: { judul: 'Master Pekerjaan', izin: 'service.manage' } },
      { path: 'mechanics', name: 'mekanik', component: () => import('@/views/mechanics/Daftar.vue'), meta: { judul: 'Data Mekanik', izin: 'mechanic.manage' } },
      { path: 'rewards', name: 'reward', component: () => import('@/views/rewards/Daftar.vue'), meta: { judul: 'Reward Tukar Poin', izin: 'customer.manage' } },

      { path: 'reports/finance', name: 'laporan-keuangan', component: () => import('@/views/reports/Keuangan.vue'), meta: { judul: 'Laporan Keuangan', izin: 'report.finance' } },
      { path: 'reports/stock', name: 'laporan-stok', component: () => import('@/views/reports/Stok.vue'), meta: { judul: 'Laporan Stok', izin: 'report.stock' } },
      { path: 'reports/unit-entries', name: 'laporan-unit', component: () => import('@/views/reports/UnitEntry.vue'), meta: { judul: 'Laporan Unit Entry', izin: 'report.stock' } },
      { path: 'expenses', name: 'pengeluaran', component: () => import('@/views/expenses/Pengeluaran.vue'), meta: { judul: 'Pengeluaran Lain', izin: 'report.finance' } },

      { path: 'settings', name: 'pengaturan', component: () => import('@/views/settings/Pengaturan.vue'), meta: { judul: 'Pengaturan', izin: 'setting.manage' } },
      { path: 'users', name: 'pengguna', component: () => import('@/views/users/Pengguna.vue'), meta: { judul: 'Pengguna & Peran', izin: 'user.manage' } },
    ],
  },

  /* ------------------------------------------------------------ portal member */
  {
    path: '/portal',
    component: PortalLayout,
    meta: { butuhAuth: true, peran: 'member' },
    children: [
      { path: '', name: 'portal', component: () => import('@/views/portal/Beranda.vue'), meta: { judul: 'Portal Member' } },
      { path: 'riwayat', name: 'portal-riwayat', component: () => import('@/views/portal/Riwayat.vue'), meta: { judul: 'Riwayat Servis' } },
      { path: 'riwayat/:tipe/:id', name: 'portal-detail', component: () => import('@/views/portal/Detail.vue'), meta: { judul: 'Detail Riwayat' } },
      { path: 'kendaraan', name: 'portal-kendaraan', component: () => import('@/views/portal/Kendaraan.vue'), meta: { judul: 'Kendaraan Saya' } },
      { path: 'akun', name: 'portal-akun', component: () => import('@/views/portal/Akun.vue'), meta: { judul: 'Akun Saya' } },
    ],
  },

  /* ------------------------------------------------- halaman cetak (tanpa layout) */
  {
    path: '/cetak/nota/:id',
    name: 'cetak-nota',
    component: () => import('@/views/cetak/Nota.vue'),
    meta: { butuhAuth: true, izin: 'service-order.manage', judul: 'Cetak Nota / Invoice' },
  },
  {
    path: '/cetak/nota-thermal/:id',
    name: 'cetak-nota-thermal',
    component: () => import('@/views/cetak/NotaThermal.vue'),
    meta: { butuhAuth: true, izin: 'service-order.manage', judul: 'Cetak Nota 58mm' },
  },
  {
    path: '/cetak/laporan-stok',
    name: 'cetak-laporan-stok',
    component: () => import('@/views/cetak/LaporanStok.vue'),
    meta: { butuhAuth: true, izin: 'report.stock', judul: 'Cetak Laporan Stok' },
  },
  {
    path: '/cetak/laporan-keuangan',
    name: 'cetak-laporan-keuangan',
    component: () => import('@/views/cetak/LaporanKeuangan.vue'),
    meta: { butuhAuth: true, izin: 'report.finance', judul: 'Cetak Laporan Keuangan' },
  },

  { path: '/:pathMatch(.*)*', name: 'tidak-ditemukan', component: () => import('@/views/NotFound.vue'), meta: { publik: true } },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (auth.token && !auth.siap) {
    try {
      await auth.ambilProfil()
    } catch (e) {
      // Hanya 401 yang menghapus sesi; galat jaringan membiarkan token utuh
      // agar halaman tetap dapat dimuat dan pengguna tinggal mencoba lagi.
      if (e?.response?.status === 401) auth.bersihkan()
    }
  }

  if (to.meta.publik) {
    if (auth.sudahMasuk && to.name === 'login') {
      return { name: auth.isMember ? 'portal' : 'dashboard' }
    }
    return true
  }

  if (!auth.sudahMasuk) {
    return { name: 'login', query: { dari: jalurAman(to.fullPath, '') } }
  }

  if (auth.wajibGantiPassword && !to.meta.lewatiWajibGanti) {
    return { name: 'ganti-password' }
  }

  if (!auth.wajibGantiPassword && to.meta.lewatiWajibGanti) {
    return { name: auth.isMember ? 'portal' : 'dashboard' }
  }

  // Halaman ganti-password harus tetap dapat diakses member (kalau tidak,
  // aturan "member selalu ke portal" akan berputar tanpa henti dengan halaman ini).
  if (auth.isMember && !to.path.startsWith('/portal') && to.name !== 'ganti-password') {
    return { name: 'portal' }
  }

  if (!auth.isMember && to.path.startsWith('/portal')) {
    return { name: 'dashboard' }
  }

  if (to.meta.izin && !auth.bisa(to.meta.izin)) {
    return { name: 'dashboard' }
  }

  return true
})

export default router
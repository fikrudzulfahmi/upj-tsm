<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { dashboardApi } from '@/api'
import { useAuthStore } from '@/stores/auth'
import { usePengaturanStore } from '@/stores/pengaturan'
import { formatRupiah, formatTanggal, selisihHari } from '@/utils/format'
import { statusSa } from '@/utils/status'
import StatCard from '@/components/ui/StatCard.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import LoadingBlock from '@/components/ui/LoadingBlock.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import {
  BarElement, CategoryScale, Chart as ChartJS, Legend, LinearScale, Tooltip,
} from 'chart.js'
import { Bar } from 'vue-chartjs'

ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip, Legend)

const auth = useAuthStore()
const pengaturan = usePengaturanStore()
const data = ref(null)
const memuat = ref(true)

onMounted(async () => {
  try {
    const res = await dashboardApi.ringkasan()
    data.value = res.data
  } finally {
    memuat.value = false
  }
})

const grafik = computed(() => ({
  labels: (data.value?.grafik_7_hari || []).map((d) => d.label),
  datasets: [
    {
      label: 'Pemasukan',
      data: (data.value?.grafik_7_hari || []).map((d) => d.pemasukan),
      backgroundColor: '#dc2626',
      borderRadius: 4,
    },
    {
      label: 'Pengeluaran',
      data: (data.value?.grafik_7_hari || []).map((d) => d.pengeluaran),
      backgroundColor: '#cbd5e1',
      borderRadius: 4,
    },
  ],
}))

const opsiGrafik = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
  scales: {
    y: { beginAtZero: true, ticks: { callback: (v) => (v >= 1000 ? `${v / 1000}rb` : v) }, grid: { color: '#f1f5f9' } },
    x: { grid: { display: false } },
  },
}
</script>

<template>
  <div class="space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 class="text-lg font-semibold text-slate-800">Selamat datang, {{ auth.nama }}</h2>
        <p class="text-sm text-slate-500">
          Ringkasan operasional {{ pengaturan.namaBengkel }} —
          {{ data?.tanggal ? formatTanggal(data.tanggal) : '…' }}
        </p>
      </div>
      <div class="flex flex-wrap gap-2 text-xs">
        <RouterLink v-if="auth.bisa('checkup.manage')" to="/checkups/create" class="rounded-lg bg-brand-600 px-3 py-2 font-medium text-white hover:bg-brand-700">
          + Check Up
        </RouterLink>
        <RouterLink v-if="auth.bisa('service-order.manage')" to="/service-orders/create" class="rounded-lg border border-slate-300 bg-white px-3 py-2 font-medium text-slate-700 hover:border-brand-400">
          + Form SA
        </RouterLink>
      </div>
    </div>

    <LoadingBlock v-if="memuat" />

    <template v-else-if="data">
      <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard label="Unit masuk hari ini" :nilai="data.unit_hari_ini" ikon="bike" warna="brand" :keterangan="`${data.checkup_hari_ini} check up`" />
        <StatCard label="SA berjalan" :nilai="data.sa_berjalan" ikon="clipboardPen" warna="amber" keterangan="Draft & dikerjakan" />
        <RouterLink v-if="auth.bisa('kasir.manage')" to="/kasir" class="block transition hover:-translate-y-0.5">
          <StatCard label="Menunggu pembayaran" :nilai="data.sa_menunggu_bayar" ikon="banknote" warna="sky" keterangan="Klik untuk membuka Kasir" />
        </RouterLink>
        <StatCard v-else label="Menunggu pembayaran" :nilai="data.sa_menunggu_bayar" ikon="banknote" warna="sky" keterangan="Sudah selesai, belum dibayar" />
        <StatCard label="Omzet hari ini" :nilai="formatRupiah(data.omzet_hari_ini)" ikon="trendingUp" warna="emerald" :keterangan="`Bulan ini ${formatRupiah(data.omzet_bulan_ini)}`" />
      </div>

      <div class="grid gap-4 lg:grid-cols-3">
        <div class="card lg:col-span-2">
          <div class="card-header">
            <h3 class="text-sm font-semibold text-slate-700">Pemasukan &amp; pengeluaran 7 hari terakhir</h3>
            <span class="text-xs text-slate-400">Semua nilai Rupiah</span>
          </div>
          <div class="h-64 p-3">
            <Bar :data="grafik" :options="opsiGrafik" />
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h3 class="text-sm font-semibold text-slate-700">Stok menipis</h3>
            <RouterLink v-if="auth.bisa('sparepart.manage')" to="/spareparts?menipis=1" class="text-xs font-medium text-brand-600 hover:underline">
              Lihat semua
            </RouterLink>
          </div>
          <div v-if="!data.stok_menipis.length" class="px-4 py-6 text-center text-xs text-slate-400">
            Semua stok aman.
          </div>
          <ul v-else class="divide-y divide-slate-100">
            <li v-for="p in data.stok_menipis" :key="p.id" class="flex items-center justify-between px-4 py-2.5">
              <div class="min-w-0">
                <p class="truncate text-sm text-slate-700">{{ p.name }}</p>
                <p class="text-xs text-slate-400">Minimum {{ p.min_stock }} {{ p.unit }}</p>
              </div>
              <BaseBadge varian="bahaya">{{ p.stock }} {{ p.unit }}</BaseBadge>
            </li>
          </ul>
        </div>
      </div>

      <div class="grid gap-4 lg:grid-cols-3">
        <div class="card lg:col-span-2">
          <div class="card-header">
            <h3 class="text-sm font-semibold text-slate-700">Form SA terakhir</h3>
            <RouterLink to="/service-orders" class="text-xs font-medium text-brand-600 hover:underline">Semua SA</RouterLink>
          </div>
          <div v-if="!data.unit_hari_ini_daftar.length" class="p-4">
            <EmptyState ikon="clipboardPen" judul="Belum ada Form SA" pesan="SA yang dibuat akan tampil di sini." />
          </div>
          <ul v-else class="divide-y divide-slate-100">
            <li v-for="sa in data.unit_hari_ini_daftar" :key="sa.id" class="flex items-center justify-between gap-3 px-4 py-2.5">
              <div class="min-w-0">
                <p class="truncate text-sm font-medium text-slate-700">{{ sa.sa_no }} · {{ sa.customer_name }}</p>
                <p class="text-xs text-slate-400">{{ sa.plate_number }}</p>
              </div>
              <div class="flex items-center gap-2">
                <span class="tabular text-sm text-slate-600">{{ formatRupiah(sa.grand_total) }}</span>
                <BaseBadge :varian="statusSa(sa.status).varian">{{ statusSa(sa.status).label }}</BaseBadge>
              </div>
            </li>
          </ul>
        </div>

        <div class="card">
          <div class="card-header">
            <h3 class="text-sm font-semibold text-slate-700">Member akan hangus (≤ 14 hari)</h3>
            <span class="text-xs text-slate-400">{{ data.total_member_aktif }} member aktif</span>
          </div>
          <div v-if="!data.member_akan_hangus.length" class="px-4 py-6 text-center text-xs text-slate-400">
            Tidak ada member yang mendekati hangus.
          </div>
          <ul v-else class="divide-y divide-slate-100">
            <li v-for="m in data.member_akan_hangus" :key="m.customer_id" class="flex items-center justify-between gap-2 px-4 py-2.5">
              <div class="min-w-0">
                <RouterLink :to="`/customers/${m.customer_id}`" class="truncate text-sm text-slate-700 hover:text-brand-600">
                  {{ m.name }}
                </RouterLink>
                <p class="text-xs text-slate-400">Berakhir {{ formatTanggal(m.expires_at) }}</p>
              </div>
              <BaseBadge :varian="selisihHari(m.expires_at) <= 7 ? 'bahaya' : 'peringatan'">
                {{ m.sisa_hari }} hari
              </BaseBadge>
            </li>
          </ul>
        </div>
      </div>
    </template>
  </div>
</template>
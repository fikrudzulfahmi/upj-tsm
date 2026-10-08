<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { portalApi } from '@/api'
import { useAuthStore } from '@/stores/auth'
import { formatRupiah, formatTanggal } from '@/utils/format'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import LoadingBlock from '@/components/ui/LoadingBlock.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const auth = useAuthStore()
const profil = ref(null)
const riwayat = ref({ checkups: [], service_orders: [] })
const memuat = ref(true)

onMounted(async () => {
  try {
    const [p, r] = await Promise.all([portalApi.profil(), portalApi.riwayat()])
    profil.value = p.data
    riwayat.value = r.data
  } finally {
    memuat.value = false
  }
})

const member = computed(() => profil.value?.membership || null)
const aktif = computed(() => member.value?.aktif === true)
const totalKunjungan = computed(() => (riwayat.value.checkups?.length || 0) + (riwayat.value.service_orders?.length || 0))
const progresReward = computed(() => {
  if (!member.value) return 0
  return Math.min(100, Math.round((member.value.points_balance / 100) * 100))
})
</script>

<template>
  <div class="space-y-4">
    <LoadingBlock v-if="memuat" />

    <template v-else-if="profil">
      <div v-if="!aktif" class="flex items-start gap-2 rounded-xl border border-brand-200 bg-brand-50 p-3 text-sm text-brand-700">
        <AppIcon nama="warning" :ukuran="18" class="mt-0.5 shrink-0" />
        <div>
          <p class="font-semibold">Membership berakhir</p>
          <p class="text-xs">
            Masa keanggotaan Anda sudah berakhir. Riwayat servis tetap dapat dilihat.
            Silakan hubungi bengkel untuk memperpanjang.
          </p>
        </div>
      </div>

      <!-- Kartu member -->
      <div class="rounded-2xl bg-brand-600 p-5 text-white shadow-sm">
        <div class="flex items-start justify-between">
          <div>
            <p class="text-xs uppercase tracking-widest text-white/70">Kartu Member</p>
            <p class="mt-1 text-xl font-bold">{{ profil.customer.name }}</p>
            <p class="text-sm text-white/80">{{ profil.customer.phone }}</p>
          </div>
          <div class="rounded-xl bg-white/15 p-2">
            <AppIcon nama="award" :ukuran="24" />
          </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="text-xs text-white/70">No. Member</p>
            <p class="font-semibold tracking-wide">{{ member?.member_no || '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-white/70">Berlaku sampai</p>
            <p class="font-semibold">{{ member ? formatTanggal(member.expires_at) : '—' }}</p>
          </div>
          <div>
            <p class="text-xs text-white/70">Sisa hari</p>
            <p class="font-semibold">{{ member?.sisa_hari ?? '—' }} hari</p>
          </div>
          <div class="rounded-xl bg-white/15 px-4 py-2 text-center">
            <p class="text-xs text-white/80">Saldo Poin</p>
            <p class="text-2xl font-bold">{{ member?.points_balance ?? 0 }}</p>
          </div>
        </div>
      </div>

      <!-- Progres poin -->
      <div class="card p-4">
        <div class="mb-2 flex items-center justify-between text-sm">
          <span class="font-medium text-slate-700">Progres menuju reward Oli Gratis (100 poin)</span>
          <span class="text-slate-500">{{ member?.points_balance ?? 0 }} / 100</span>
        </div>
        <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100">
          <div class="h-full rounded-full bg-brand-600 transition-all" :style="{ width: `${progresReward}%` }" />
        </div>
        <p class="mt-2 text-xs text-slate-400">
          Poin bertambah setiap servis berbayar (1 poin per Rp 10.000 biaya jasa).
        </p>
      </div>

      <div class="grid grid-cols-3 gap-3">
        <div class="card p-3 text-center">
          <p class="text-xs uppercase text-slate-500">Kunjungan</p>
          <p class="text-xl font-bold text-slate-800">{{ totalKunjungan }}</p>
        </div>
        <div class="card p-3 text-center">
          <p class="text-xs uppercase text-slate-500">Kendaraan</p>
          <p class="text-xl font-bold text-slate-800">{{ profil.jumlah_kendaraan }}</p>
        </div>
        <div class="card p-3 text-center">
          <p class="text-xs uppercase text-slate-500">Reward</p>
          <p class="text-xl font-bold text-slate-800">{{ Math.floor((member?.points_balance || 0) / 100) }}</p>
        </div>
      </div>

      <!-- Riwayat terakhir -->
      <div class="card">
        <div class="card-header">
          <h3 class="text-sm font-semibold text-slate-700">Servis Terakhir</h3>
          <RouterLink to="/portal/riwayat" class="text-xs font-medium text-brand-600 hover:underline">Lihat semua</RouterLink>
        </div>
        <ul v-if="riwayat.service_orders?.length" class="divide-y divide-slate-100">
          <li v-for="sa in riwayat.service_orders.slice(0, 3)" :key="sa.id" class="flex items-center justify-between gap-3 px-4 py-3">
            <div>
              <p class="text-sm font-medium text-slate-700">{{ sa.sa_no }}</p>
              <p class="text-xs text-slate-400">{{ formatTanggal(sa.tanggal) }} · {{ sa.plate_number }} · {{ sa.mekanik || '-' }}</p>
            </div>
            <div class="text-right">
              <p v-if="sa.total" class="tabular text-sm font-medium">{{ formatRupiah(sa.total) }}</p>
              <RouterLink :to="`/portal/riwayat/service/${sa.id}`" class="text-xs text-brand-600 hover:underline">Detail</RouterLink>
            </div>
          </li>
        </ul>
        <p v-else class="px-4 py-6 text-center text-xs text-slate-400">Belum ada riwayat servis.</p>
      </div>
    </template>
  </div>
</template>

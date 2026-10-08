<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { portalApi } from '@/api'
import { formatRupiah, formatTanggal } from '@/utils/format'
import { labelStatusItem, ringkasHasil } from '@/utils/checkup'
import { statusSa } from '@/utils/status'
import FuelGauge from '@/components/FuelGauge.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import LoadingBlock from '@/components/ui/LoadingBlock.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const route = useRoute()
const router = useRouter()
const data = ref(null)
const memuat = ref(true)

onMounted(async () => {
  try {
    const res = await portalApi.detail(route.params.tipe, route.params.id)
    data.value = res.data
  } catch {
    router.replace('/portal/riwayat')
  } finally {
    memuat.value = false
  }
})

const ringkas = computed(() => ringkasHasil(data.value?.hasil || []))
const grup = computed(() => {
  const g = new Map()
  ;(data.value?.hasil || []).forEach((h) => {
    if (!g.has(h.category)) g.set(h.category, [])
    g.get(h.category).push(h)
  })
  return [...g.entries()].map(([kategori, items]) => ({ kategori, items }))
})
</script>

<template>
  <div class="space-y-4">
    <LoadingBlock v-if="memuat" />

    <template v-else-if="data">
      <div class="flex items-center gap-2">
        <BaseButton varian="garis" ukuran="sm" ikon="arrowLeft" @click="router.back()">Kembali</BaseButton>
        <BaseBadge varian="netral" ukuran="sm">{{ data.tipe === 'service' ? 'Servis' : 'Check Up' }}</BaseBadge>
      </div>

      <div class="card p-4">
        <h2 class="text-lg font-semibold text-slate-800">{{ data.sa_no || data.checkup_no }}</h2>
        <p class="mt-1 text-sm text-slate-500">
          {{ formatTanggal(data.tanggal || data.checkup_date) }} · {{ data.plate_number }} · {{ data.kendaraan }}
        </p>
        <div v-if="data.tipe === 'service'" class="mt-2 flex flex-wrap items-center gap-2 text-xs">
          <BaseBadge :varian="statusSa(data.status).varian" ukuran="sm">{{ data.status_label }}</BaseBadge>
          <span v-if="data.mekanik" class="text-slate-500">Mekanik: {{ data.mekanik }}</span>
          <span v-if="data.odometer" class="text-slate-500">Odometer: {{ data.odometer.toLocaleString('id-ID') }} km</span>
        </div>
        <div v-if="data.complaint" class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
          <p class="text-xs uppercase tracking-wide text-slate-500">Keluhan</p>
          <p class="mt-1">{{ data.complaint }}</p>
        </div>
        <div v-if="data.general_notes" class="mt-2 rounded-lg bg-slate-50 p-3 text-sm text-slate-700">
          <p class="text-xs uppercase tracking-wide text-slate-500">Catatan</p>
          <p class="mt-1">{{ data.general_notes }}</p>
        </div>
      </div>

      <!-- CHECK UP -->
      <template v-if="data.tipe === 'checkup'">
        <div class="grid grid-cols-4 gap-2 text-center">
          <div class="card p-2"><p class="text-[11px] uppercase text-slate-500">Item</p><p class="text-lg font-bold">{{ ringkas.total }}</p></div>
          <div class="card p-2"><p class="text-[11px] uppercase text-slate-500">OK</p><p class="text-lg font-bold text-emerald-600">{{ ringkas.ok }}</p></div>
          <div class="card p-2"><p class="text-[11px] uppercase text-slate-500">Perhatian</p><p class="text-lg font-bold text-amber-600">{{ ringkas.perlu_perhatian }}</p></div>
          <div class="card p-2"><p class="text-[11px] uppercase text-slate-500">Rusak</p><p class="text-lg font-bold text-brand-600">{{ ringkas.rusak }}</p></div>
        </div>

        <div v-for="g in grup" :key="g.kategori" class="card">
          <div class="card-header"><h3 class="text-sm font-semibold text-slate-700">{{ g.kategori }}</h3></div>
          <ul class="divide-y divide-slate-100">
            <li v-for="(h, i) in g.items" :key="i" class="flex items-center justify-between gap-2 px-4 py-2.5">
              <div>
                <p class="text-sm text-slate-700">{{ h.item_name }}</p>
                <p v-if="h.note" class="text-xs text-slate-400">{{ h.note }}</p>
              </div>
              <span class="rounded px-2 py-0.5 text-[11px] font-medium" :class="`status-${h.status}`">
                {{ labelStatusItem(h.status) }}
              </span>
            </li>
          </ul>
        </div>
      </template>

      <!-- SERVICE -->
      <template v-else>
        <div class="card p-4">
          <FuelGauge :model-value="data.fuel_level" :bisa-edit="false" />
        </div>

        <div class="card">
          <div class="card-header"><h3 class="text-sm font-semibold text-slate-700">Pekerjaan</h3></div>
          <ul class="divide-y divide-slate-100">
            <li v-for="(p, i) in data.pekerjaan || []" :key="i" class="flex items-center justify-between px-4 py-2.5">
              <div>
                <p class="text-sm text-slate-700">{{ p.nama }}</p>
                <p class="text-xs text-slate-400">{{ p.qty }}× {{ formatRupiah(p.harga) }}</p>
              </div>
              <span class="tabular text-sm">{{ formatRupiah(p.subtotal) }}</span>
            </li>
            <li v-if="!(data.pekerjaan || []).length" class="px-4 py-4 text-center text-xs text-slate-400">Tidak ada pekerjaan.</li>
          </ul>
        </div>

        <div class="card">
          <div class="card-header"><h3 class="text-sm font-semibold text-slate-700">Sparepart Diganti</h3></div>
          <ul class="divide-y divide-slate-100">
            <li v-for="(p, i) in data.sparepart || []" :key="i" class="flex items-center justify-between px-4 py-2.5">
              <div>
                <p class="text-sm text-slate-700">
                  {{ p.nama }}
                  <BaseBadge v-if="p.gratis" varian="brand" ukuran="sm">hadiah poin</BaseBadge>
                </p>
                <p class="text-xs text-slate-400">{{ p.qty }}× {{ formatRupiah(p.harga) }}</p>
              </div>
              <span class="tabular text-sm">{{ formatRupiah(p.subtotal) }}</span>
            </li>
            <li v-if="!(data.sparepart || []).length" class="px-4 py-4 text-center text-xs text-slate-400">Tidak ada sparepart.</li>
          </ul>
        </div>

        <div v-if="(data.kondisi || []).length" class="card">
          <div class="card-header"><h3 class="text-sm font-semibold text-slate-700">Kondisi Kendaraan</h3></div>
          <ul class="divide-y divide-slate-100">
            <li v-for="(k, i) in data.kondisi" :key="i" class="flex items-center justify-between px-4 py-2.5">
              <div>
                <p class="text-sm text-slate-700">{{ k.item_name }}</p>
                <p class="text-xs text-slate-400">{{ k.category }}{{ k.note ? ` · ${k.note}` : '' }}</p>
              </div>
              <span class="rounded px-2 py-0.5 text-[11px] font-medium" :class="`status-${k.status}`">
                {{ labelStatusItem(k.status) }}
              </span>
            </li>
          </ul>
        </div>

        <div class="card p-4">
          <dl class="space-y-1.5 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">Total jasa</dt><dd class="tabular">{{ formatRupiah(data.total_jasa) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Total sparepart</dt><dd class="tabular">{{ formatRupiah(data.total_part) }}</dd></div>
            <div class="flex justify-between border-t border-slate-100 pt-2">
              <dt class="font-semibold text-slate-700">Total</dt>
              <dd class="tabular text-lg font-bold text-brand-700">{{ formatRupiah(data.total) }}</dd>
            </div>
          </dl>
        </div>
      </template>

      <p class="flex items-center justify-center gap-1 text-[11px] text-slate-400">
        <AppIcon nama="shield" :ukuran="12" /> Data ini hanya dapat dilihat oleh Anda.
      </p>
    </template>
  </div>
</template>

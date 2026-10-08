<script setup>
import AppIcon from './AppIcon.vue'

const props = defineProps({
  label: { type: String, required: true },
  nilai: { type: [String, Number], default: '—' },
  ikon: { type: String, default: 'chart' },
  warna: { type: String, default: 'brand' }, // brand|emerald|amber|sky|slate|violet
  keterangan: { type: String, default: null },
})
const WARNA = {
  brand: 'bg-brand-50 text-brand-600',
  emerald: 'bg-emerald-50 text-emerald-600',
  amber: 'bg-amber-50 text-amber-600',
  sky: 'bg-sky-50 text-sky-600',
  slate: 'bg-slate-100 text-slate-600',
  violet: 'bg-violet-50 text-violet-600',
}
</script>

<template>
  <div class="card flex items-start gap-3 p-4">
    <div class="rounded-lg p-2.5" :class="WARNA[warna] || WARNA.brand">
      <AppIcon :nama="ikon" :ukuran="20" />
    </div>
    <div class="min-w-0">
      <p class="truncate text-xs font-medium uppercase tracking-wide text-slate-500">{{ label }}</p>
      <p class="tabular mt-0.5 truncate text-lg font-semibold text-slate-800">{{ nilai }}</p>
      <p v-if="keterangan" class="mt-0.5 text-xs text-slate-400">{{ keterangan }}</p>
    </div>
  </div>
</template>

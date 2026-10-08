<script setup>
const props = defineProps({
  varian: { type: String, default: 'netral' }, // netral|sukses|peringatan|bahaya|info|brand
  teks: { type: String, default: null },
  ukuran: { type: String, default: 'md' }, // sm|md
})
const KELAS = {
  netral: 'bg-slate-100 text-slate-600 ring-slate-200',
  sukses: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
  peringatan: 'bg-amber-100 text-amber-700 ring-amber-200',
  bahaya: 'bg-brand-100 text-brand-700 ring-brand-200',
  info: 'bg-sky-100 text-sky-700 ring-sky-200',
  brand: 'bg-brand-600 text-white ring-brand-600',
}
</script>

<template>
  <span
    class="inline-flex items-center gap-1 rounded-full font-medium ring-1 ring-inset"
    :class="[KELAS[varian] || KELAS.netral, ukuran === 'sm' ? 'px-2 py-0.5 text-[11px]' : 'px-2.5 py-1 text-xs']"
  >
    <slot>{{ teks }}</slot>
  </span>
</template>

<script setup>
import { computed } from 'vue'
import AppIcon from './AppIcon.vue'

const props = defineProps({
  varian: { type: String, default: 'utama' }, // utama | sekunder | garis | halus | bahaya | sukses
  ukuran: { type: String, default: 'md' }, // sm | md | lg | ikon
  tipe: { type: String, default: 'button' },
  ikon: { type: String, default: null },
  memuat: { type: Boolean, default: false },
  nonaktif: { type: Boolean, default: false },
  blok: { type: Boolean, default: false },
})

const KELAS = {
  utama:
    'bg-brand-600 text-white hover:bg-brand-700 focus-visible:outline-brand-600 shadow-sm disabled:hover:bg-brand-600',
  sekunder: 'bg-slate-800 text-white hover:bg-slate-900 focus-visible:outline-slate-800 shadow-sm',
  garis:
    'border border-slate-300 bg-white text-slate-700 hover:border-brand-400 hover:text-brand-700 focus-visible:outline-brand-500',
  halus: 'bg-brand-50 text-brand-700 hover:bg-brand-100 focus-visible:outline-brand-500',
  bahaya: 'bg-white border border-brand-200 text-brand-700 hover:bg-brand-50 focus-visible:outline-brand-500',
  sukses: 'bg-emerald-600 text-white hover:bg-emerald-700 focus-visible:outline-emerald-600',
}

const UKURAN = {
  sm: 'px-2.5 py-1.5 text-xs gap-1.5',
  md: 'px-3.5 py-2 text-sm gap-2',
  lg: 'px-5 py-2.5 text-base gap-2',
  ikon: 'p-2 text-sm',
}

const kelas = computed(() => [
  'inline-flex items-center justify-center rounded-lg font-medium transition',
  'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2',
  'disabled:cursor-not-allowed disabled:opacity-60',
  KELAS[props.varian] || KELAS.utama,
  UKURAN[props.ukuran] || UKURAN.md,
  props.blok ? 'w-full' : '',
])
</script>

<template>
  <button :type="tipe" :class="kelas" :disabled="nonaktif || memuat">
    <AppIcon v-if="memuat" nama="loader" :ukuran="ukuran === 'sm' ? 13 : 16" class="animate-spin" />
    <AppIcon v-else-if="ikon" :nama="ikon" :ukuran="ukuran === 'sm' ? 13 : 16" />
    <slot />
  </button>
</template>

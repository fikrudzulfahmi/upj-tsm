<script setup>
import { computed } from 'vue'
import AppIcon from './AppIcon.vue'

const props = defineProps({
  halaman: { type: Number, default: 1 },
  perHalaman: { type: Number, default: 15 },
  total: { type: Number, default: 0 },
})
const emit = defineEmits(['ubah'])

const totalHalaman = computed(() => Math.max(1, Math.ceil(props.total / Math.max(1, props.perHalaman))))
const dari = computed(() => (props.total === 0 ? 0 : (props.halaman - 1) * props.perHalaman + 1))
const sampai = computed(() => Math.min(props.total, props.halaman * props.perHalaman))
const halamanTampil = computed(() => {
  const semua = []
  const mulai = Math.max(1, props.halaman - 2)
  const akhir = Math.min(totalHalaman.value, mulai + 4)
  for (let i = Math.max(1, Math.min(mulai, akhir - 4)); i <= akhir; i++) semua.push(i)
  return semua
})
function pindah(h) {
  if (h < 1 || h > totalHalaman.value || h === props.halaman) return
  emit('ubah', h)
}
</script>

<template>
  <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-sm">
    <p class="text-xs text-slate-500">
      Menampilkan <span class="font-medium text-slate-700">{{ dari }}–{{ sampai }}</span> dari
      <span class="font-medium text-slate-700">{{ total }}</span> data
    </p>
    <div class="flex items-center gap-1">
      <button
        class="rounded-md border border-slate-200 p-1.5 text-slate-500 hover:border-brand-300 hover:text-brand-700 disabled:opacity-40"
        :disabled="halaman <= 1"
        @click="pindah(halaman - 1)"
      >
        <AppIcon nama="chevronLeft" :ukuran="15" />
      </button>
      <button
        v-for="h in halamanTampil"
        :key="h"
        class="min-w-8 rounded-md border px-2 py-1 text-xs font-medium"
        :class="h === halaman ? 'border-brand-600 bg-brand-600 text-white' : 'border-slate-200 text-slate-600 hover:border-brand-300'"
        @click="pindah(h)"
      >
        {{ h }}
      </button>
      <button
        class="rounded-md border border-slate-200 p-1.5 text-slate-500 hover:border-brand-300 hover:text-brand-700 disabled:opacity-40"
        :disabled="halaman >= totalHalaman"
        @click="pindah(halaman + 1)"
      >
        <AppIcon nama="chevronRight" :ukuran="15" />
      </button>
    </div>
  </div>
</template>

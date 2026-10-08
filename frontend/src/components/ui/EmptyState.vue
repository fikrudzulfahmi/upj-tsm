<script setup>
import AppIcon from './AppIcon.vue'
defineProps({
  ikon: { type: String, default: 'inbox' },
  judul: { type: String, default: 'Belum ada data' },
  pesan: { type: String, default: '' },
})
</script>

<template>
  <div class="flex flex-col items-center justify-center gap-2 px-4 py-12 text-center">
    <div class="rounded-full bg-slate-100 p-3 text-slate-400">
      <AppIcon :nama="ikon" :ukuran="24" />
    </div>
    <h4 class="text-sm font-semibold text-slate-700">{{ judul }}</h4>
    <p v-if="pesan" class="max-w-sm text-xs text-slate-500">{{ pesan }}</p>
    <div class="mt-2"><slot /></div>
  </div>
</template>

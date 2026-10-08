<script setup>
import AppIcon from './AppIcon.vue'
defineProps({ teks: { type: String, default: 'Memuat data…' } })
</script>

<template>
  <div class="flex flex-col items-center justify-center gap-2 py-12 text-slate-400">
    <AppIcon nama="loader" :ukuran="24" class="animate-spin" />
    <p class="text-sm">{{ teks }}</p>
  </div>
</template>

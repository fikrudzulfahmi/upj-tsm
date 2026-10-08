<script setup>
import { RouterLink } from 'vue-router'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
</script>

<template>
  <div class="flex min-h-screen flex-col items-center justify-center gap-3 bg-white px-6 text-center">
    <div class="rounded-full bg-brand-50 p-4 text-brand-600">
      <AppIcon nama="search" :ukuran="30" />
    </div>
    <h1 class="text-xl font-bold text-slate-800">Halaman tidak ditemukan</h1>
    <p class="max-w-sm text-sm text-slate-500">
      Alamat yang Anda buka tidak tersedia atau sudah dipindahkan.
    </p>
    <RouterLink to="/"><BaseButton ikon="home">Kembali ke Dashboard</BaseButton></RouterLink>
  </div>
</template>

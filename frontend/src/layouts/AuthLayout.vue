<script setup>
import AppIcon from '@/components/ui/AppIcon.vue'
import { usePengaturanStore } from '@/stores/pengaturan'
import { onMounted } from 'vue'

const pengaturan = usePengaturanStore()
onMounted(() => pengaturan.muat())
</script>

<template>
  <div class="flex min-h-screen flex-col bg-white lg:flex-row">
    <!-- Panel brand (kiri) -->
    <div
      class="relative flex flex-col justify-between overflow-hidden bg-brand-600 px-8 py-10 text-white lg:w-[42%] lg:px-12 lg:py-14"
    >
      <div class="flex items-center gap-3">
        <div class="rounded-xl bg-white/15 p-2">
          <AppIcon nama="wrench" :ukuran="24" />
        </div>
        <div>
          <p class="text-sm font-semibold uppercase tracking-widest text-white/80">Aplikasi Bengkel</p>
          <p class="text-lg font-bold">{{ pengaturan.namaBengkel }}</p>
        </div>
      </div>
      <div class="mt-10 hidden lg:block">
        <h1 class="text-3xl font-bold leading-tight">
          Kelola service, stok, dan pelanggan<br />dalam satu tempat.
        </h1>
        <ul class="mt-6 space-y-2 text-sm text-white/85">
          <li class="flex items-center gap-2"><AppIcon nama="check" :ukuran="16" /> General Check Up &amp; Form SA</li>
          <li class="flex items-center gap-2"><AppIcon nama="check" :ukuran="16" /> Stok sparepart &amp; laporan keuangan</li>
          <li class="flex items-center gap-2"><AppIcon nama="check" :ukuran="16" /> Membership, poin, dan portal pelanggan</li>
        </ul>
      </div>
      <p class="mt-10 text-xs text-white/70">
        {{ pengaturan.alamatBengkel }} <span v-if="pengaturan.teleponBengkel">· {{ pengaturan.teleponBengkel }}</span>
      </p>
    </div>

    <!-- Panel form (kanan) -->
    <div class="flex flex-1 items-center justify-center px-5 py-10 sm:px-8">
      <div class="w-full max-w-sm">
        <slot />
      </div>
    </div>
  </div>
</template>

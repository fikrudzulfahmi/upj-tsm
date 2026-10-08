<script setup>
import { useUiStore } from '@/stores/ui'
import AppIcon from './AppIcon.vue'

const ui = useUiStore()
const GAYA = {
  sukses: { kelas: 'border-emerald-200 bg-emerald-50 text-emerald-800', ikon: 'checkCircle' },
  gagal: { kelas: 'border-brand-200 bg-brand-50 text-brand-800', ikon: 'xCircle' },
  info: { kelas: 'border-sky-200 bg-sky-50 text-sky-800', ikon: 'info' },
}
</script>

<template>
  <div class="no-print pointer-events-none fixed right-4 top-4 z-[60] flex w-[min(22rem,calc(100vw-2rem))] flex-col gap-2">
    <TransitionGroup
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-y-[-6px] opacity-0"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="opacity-0"
    >
      <div
        v-for="t in ui.toasts"
        :key="t.id"
        class="pointer-events-auto flex items-start gap-2 rounded-lg border px-3.5 py-2.5 shadow-sm"
        :class="(GAYA[t.jenis] || GAYA.info).kelas"
      >
        <AppIcon :nama="(GAYA[t.jenis] || GAYA.info).ikon" :ukuran="17" class="mt-0.5 shrink-0" />
        <div class="min-w-0 flex-1">
          <p v-if="t.judul" class="text-xs font-semibold">{{ t.judul }}</p>
          <p class="text-sm leading-snug">{{ t.pesan }}</p>
        </div>
        <button class="mt-0.5 shrink-0 opacity-60 hover:opacity-100" @click="ui.tutupToast(t.id)">
          <AppIcon nama="x" :ukuran="15" />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

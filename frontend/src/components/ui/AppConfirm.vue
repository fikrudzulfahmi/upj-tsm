<script setup>
import { useUiStore } from '@/stores/ui'
import BaseModal from './BaseModal.vue'
import BaseButton from './BaseButton.vue'
import AppIcon from './AppIcon.vue'

const ui = useUiStore()
function jawab(nilai) {
  ui.konfirmasi?.jawab(nilai)
}
</script>

<template>
  <BaseModal
    :model-value="!!ui.konfirmasi"
    :judul="ui.konfirmasi?.judul || 'Konfirmasi'"
    lebar="sm"
    :tutup-klik-latar="false"
    @update:model-value="jawab(false)"
  >
    <div class="flex gap-3">
      <div
        class="shrink-0 rounded-full p-2"
        :class="ui.konfirmasi?.jenis === 'bahaya' ? 'bg-brand-50 text-brand-600' : 'bg-sky-50 text-sky-600'"
      >
        <AppIcon :nama="ui.konfirmasi?.jenis === 'bahaya' ? 'warning' : 'info'" :ukuran="20" />
      </div>
      <p class="pt-1 text-sm leading-relaxed text-slate-600">{{ ui.konfirmasi?.pesan }}</p>
    </div>
    <template #footer>
      <BaseButton varian="garis" @click="jawab(false)">{{ ui.konfirmasi?.teksBatal || 'Batal' }}</BaseButton>
      <BaseButton
        :varian="ui.konfirmasi?.jenis === 'bahaya' ? 'utama' : 'utama'"
        @click="jawab(true)"
      >
        {{ ui.konfirmasi?.teksOk || 'Lanjutkan' }}
      </BaseButton>
    </template>
  </BaseModal>
</template>

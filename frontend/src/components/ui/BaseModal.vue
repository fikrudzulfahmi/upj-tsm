<script setup>
import { computed, onBeforeUnmount, watch } from 'vue'
import BaseButton from './BaseButton.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  judul: { type: String, default: '' },
  lebar: { type: String, default: 'md' }, // sm|md|lg|xl|2xl
  tutupKlikLatar: { type: Boolean, default: true },
})
const emit = defineEmits(['update:modelValue'])

const LEBAR = { sm: 'max-w-sm', md: 'max-w-lg', lg: 'max-w-2xl', xl: 'max-w-4xl', '2xl': 'max-w-6xl' }
const kelasLebar = computed(() => LEBAR[props.lebar] || LEBAR.md)

function tutup() {
  emit('update:modelValue', false)
}
function tekan(e) {
  if (e.key === 'Escape' && props.modelValue) tutup()
}
watch(
  () => props.modelValue,
  (buka) => {
    if (buka) document.addEventListener('keydown', tekan)
    else document.removeEventListener('keydown', tekan)
    document.body.style.overflow = buka ? 'hidden' : ''
  },
)
onBeforeUnmount(() => {
  document.removeEventListener('keydown', tekan)
  document.body.style.overflow = ''
})
</script>

<template>
  <Teleport to="body">
    <div v-if="modelValue" class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:p-6">
      <div class="fixed inset-0 bg-slate-900/50" @click="tutupKlikLatar && tutup()" />
      <div
        class="relative z-10 my-4 w-full rounded-xl border border-slate-200 bg-white shadow-xl"
        :class="kelasLebar"
      >
        <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-5 py-3.5">
          <div>
            <h3 class="text-base font-semibold text-slate-800">{{ judul }}</h3>
            <p v-if="$slots.subjudul" class="mt-0.5 text-xs text-slate-500">
              <slot name="subjudul" />
            </p>
          </div>
          <BaseButton varian="halus" ukuran="ikon" ikon="x" @click="tutup" />
        </div>
        <div class="px-5 py-4">
          <slot />
        </div>
        <div v-if="$slots.footer" class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 px-5 py-3.5">
          <slot name="footer" />
        </div>
      </div>
    </div>
  </Teleport>
</template>

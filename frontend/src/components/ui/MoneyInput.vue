<script setup>
import { computed } from 'vue'
import { formatAngka, parseRupiah } from '@/utils/format'

const props = defineProps({
  modelValue: { type: [Number, String], default: 0 },
  label: { type: String, default: null },
  galat: { type: String, default: null },
  petunjuk: { type: String, default: null },
  placeholder: { type: String, default: '0' },
  nonaktif: { type: Boolean, default: false },
  diperlukan: { type: Boolean, default: false },
  masukanKelas: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue'])
const tampil = computed(() => (props.modelValue === '' || props.modelValue === null ? '' : formatAngka(props.modelValue)))
function saatInput(e) {
  emit('update:modelValue', parseRupiah(e.target.value))
}
</script>

<template>
  <div>
    <label v-if="label" class="label-form">
      {{ label }} <span v-if="diperlukan" class="text-brand-600">*</span>
    </label>
    <div class="relative">
      <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-medium text-slate-400">Rp</span>
      <input
        :value="tampil"
        :disabled="nonaktif"
        inputmode="numeric"
        class="input-form tabular pl-9 text-right"
        :class="[galat ? 'border-brand-400' : '', masukanKelas]"
        :placeholder="placeholder"
        @input="saatInput"
      />
    </div>
    <p v-if="galat" class="mt-1 text-xs text-brand-600">{{ galat }}</p>
    <p v-else-if="petunjuk" class="mt-1 text-xs text-slate-500">{{ petunjuk }}</p>
  </div>
</template>

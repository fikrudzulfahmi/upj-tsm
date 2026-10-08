<script setup>
import { computed } from 'vue'
import AppIcon from './AppIcon.vue'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  label: { type: String, default: null },
  tipe: { type: String, default: 'text' },
  placeholder: { type: String, default: '' },
  galat: { type: String, default: null },
  petunjuk: { type: String, default: null },
  ikon: { type: String, default: null },
  nonaktif: { type: Boolean, default: false },
  diperlukan: { type: Boolean, default: false },
  autofokus: { type: Boolean, default: false },
  min: { type: [String, Number], default: undefined },
  max: { type: [String, Number], default: undefined },
  step: { type: [String, Number], default: undefined },
  maksPanjang: { type: [String, Number], default: undefined },
  masukanKelas: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue', 'enter', 'blur'])
const kelasInput = computed(() => [props.galat ? 'border-brand-400 focus:ring-brand-100' : '', props.masukanKelas])
</script>

<template>
  <div>
    <label v-if="label" class="label-form">
      {{ label }}
      <span v-if="diperlukan" class="text-brand-600">*</span>
    </label>
    <div class="relative">
      <span v-if="ikon" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
        <AppIcon :nama="ikon" :ukuran="16" />
      </span>
      <input
        :type="tipe"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="nonaktif"
        :min="min"
        :max="max"
        :step="step"
        :maxlength="maksPanjang"
        :autofocus="autofokus"
        class="input-form"
        :class="[kelasInput, ikon ? 'pl-9' : '']"
        @input="
          emit('update:modelValue', $event.target.value);
          emit('input', $event.target.value)
        "
        @keyup.enter="emit('enter')"
        @blur="emit('blur')"
      />
    </div>
    <p v-if="galat" class="mt-1 text-xs text-brand-600">{{ galat }}</p>
    <p v-else-if="petunjuk" class="mt-1 text-xs text-slate-500">{{ petunjuk }}</p>
  </div>
</template>
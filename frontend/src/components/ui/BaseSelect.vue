<script setup>
const props = defineProps({
  modelValue: { type: [String, Number, null], default: '' },
  label: { type: String, default: null },
  opsi: { type: Array, default: () => [] }, // [{ value, label }] atau string[]
  placeholder: { type: String, default: '— Pilih —' },
  galat: { type: String, default: null },
  petunjuk: { type: String, default: null },
  nonaktif: { type: Boolean, default: false },
  diperlukan: { type: Boolean, default: false },
  bolehKosong: { type: Boolean, default: true },
})
const emit = defineEmits(['update:modelValue'])
const nilaiOpsi = (o) => (typeof o === 'object' && o !== null ? o.value : o)
const labelOpsi = (o) => (typeof o === 'object' && o !== null ? o.label : o)
</script>

<template>
  <div>
    <label v-if="label" class="label-form">
      {{ label }} <span v-if="diperlukan" class="text-brand-600">*</span>
    </label>
    <select
      :value="modelValue"
      :disabled="nonaktif"
      class="input-form"
      @change="emit('update:modelValue', $event.target.value)"
    >
      <option v-if="bolehKosong" value="">{{ placeholder }}</option>
      <option v-for="o in opsi" :key="nilaiOpsi(o)" :value="nilaiOpsi(o)">{{ labelOpsi(o) }}</option>
    </select>
    <p v-if="galat" class="mt-1 text-xs text-brand-600">{{ galat }}</p>
    <p v-else-if="petunjuk" class="mt-1 text-xs text-slate-500">{{ petunjuk }}</p>
  </div>
</template>

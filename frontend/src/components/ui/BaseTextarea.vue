<script setup>
const props = defineProps({
  modelValue: { type: String, default: '' },
  label: { type: String, default: null },
  baris: { type: Number, default: 3 },
  placeholder: { type: String, default: '' },
  galat: { type: String, default: null },
  petunjuk: { type: String, default: null },
  nonaktif: { type: Boolean, default: false },
  diperlukan: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <div>
    <label v-if="label" class="label-form">
      {{ label }} <span v-if="diperlukan" class="text-brand-600">*</span>
    </label>
    <textarea
      :value="modelValue"
      :rows="baris"
      :placeholder="placeholder"
      :disabled="nonaktif"
      class="input-form resize-y"
      @input="emit('update:modelValue', $event.target.value)"
    />
    <p v-if="galat" class="mt-1 text-xs text-brand-600">{{ galat }}</p>
    <p v-else-if="petunjuk" class="mt-1 text-xs text-slate-500">{{ petunjuk }}</p>
  </div>
</template>

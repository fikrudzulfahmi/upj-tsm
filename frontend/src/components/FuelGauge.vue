<script setup>
/**
 * Level bensin 0..maks (default 8) yang bisa diklik per bar.
 */
import { computed } from 'vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  modelValue: { type: [Number, String], default: 0 },
  maks: { type: Number, default: 8 },
  bisaEdit: { type: Boolean, default: true },
  label: { type: String, default: 'Level Bensin' },
})
const emit = defineEmits(['update:modelValue'])
const level = computed(() => Number(props.modelValue) || 0)
const bar = computed(() => Array.from({ length: props.maks }, (_, i) => i + 1))
/** Bar bersifat dekoratif (aria-hidden) — pilihan sesungguhnya lewat tombol angka di atasnya. */
function setLevel(n) {
  if (!props.bisaEdit) return
  emit('update:modelValue', level.value === n ? 0 : n)
}
</script>

<template>
  <div>
    <div class="mb-1 flex items-center justify-between">
      <label class="label-form mb-0">{{ label }}</label>
      <span class="text-xs font-medium text-slate-500">
        <AppIcon nama="fuel" :ukuran="14" class="mr-1 inline" />{{ level }} / {{ maks }}
      </span>
    </div>
    <div class="flex items-center gap-1.5">
      <div class="flex gap-1" role="group" :aria-label="label">
        <button
          v-for="n in bar"
          :key="n"
          type="button"
          :disabled="!bisaEdit"
          :aria-label="`Set level ${n}`"
          class="h-7 w-7 rounded-sm border transition"
          :class="
            n <= level
              ? 'border-brand-600 bg-brand-500'
              : 'border-slate-300 bg-slate-100 hover:border-brand-400'
          "
          @click="setLevel(n)"
        />
      </div>
      <button
        v-if="bisaEdit && level > 0"
        type="button"
        class="ml-1 text-[11px] text-slate-400 underline hover:text-brand-600"
        @click="emit('update:modelValue', 0)"
      >
        kosongkan
      </button>
    </div>
  </div>
</template>

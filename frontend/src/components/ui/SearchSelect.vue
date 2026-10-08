<script setup>
/**
 * Pencarian + pilih satu data (master atau hasil pencarian server).
 * - `opsi`  : daftar lokal (disaring di klien)
 * - `pencari`: fungsi async (kata) => array (disaring di server, ada debounce)
 * Pilih lewat slot `#item="{ item }"`; label terpilih lewat `#terpilih="{ item }"`.
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { pesanGalat } from '@/api/client'
import { useUiStore } from '@/stores/ui'
import AppIcon from './AppIcon.vue'

const props = defineProps({
  label: { type: String, default: null },
  modelValue: { type: [Number, String, null], default: null },
  opsi: { type: Array, default: null },
  pencari: { type: Function, default: null },
  kunciNilai: { type: String, default: 'id' },
  kunciLabel: { type: String, default: 'name' },
  placeholder: { type: String, default: 'Cari…' },
  galat: { type: String, default: null },
  nonaktif: { type: Boolean, default: false },
  diperlukan: { type: Boolean, default: false },
  kosongTeks: { type: String, default: 'Tidak ada hasil.' },
})
const emit = defineEmits(['update:modelValue', 'pilih'])

const akar = ref(null)
const kata = ref('')
const terbuka = ref(false)
const hasil = ref([])
const memuat = ref(false)
const galatCari = ref(null)
const terpilih = ref(null)
let timer = null

const daftarTampil = computed(() => {
  if (props.pencari) return hasil.value
  const sumber = props.opsi || []
  const q = kata.value.trim().toLowerCase()
  if (!q) return sumber.slice(0, 30)
  return sumber.filter((o) => String(o[props.kunciLabel] ?? '').toLowerCase().includes(q)).slice(0, 30)
})

watch(
  () => props.modelValue,
  (v) => {
    if (v === null || v === '' || v === undefined) terpilih.value = null
    else if (props.opsi) {
      const ketemu = props.opsi.find((o) => o[props.kunciNilai] === v)
      if (ketemu) terpilih.value = ketemu
    }
  },
  { immediate: true },
)

function saatKetik() {
  terbuka.value = true
  if (!props.pencari) return
  clearTimeout(timer)
  memuat.value = true
  timer = setTimeout(async () => {
    try {
      hasil.value = (await props.pencari(kata.value.trim())) || []
      galatCari.value = null
    } catch (e) {
      // Jangan telan galat: pencarian yang gagal harus terlihat, bukan tampak "tidak ada hasil".
      hasil.value = []
      galatCari.value = pesanGalat(e)
      useUiStore().gagal(`Pencarian gagal: ${galatCari.value}`)
    } finally {
      memuat.value = false
    }
  }, 280)
}

function pilih(item) {
  terpilih.value = item
  kata.value = ''
  terbuka.value = false
  emit('update:modelValue', item[props.kunciNilai])
  emit('pilih', item)
}

function bersihkan() {
  terpilih.value = null
  kata.value = ''
  emit('update:modelValue', null)
  emit('pilih', null)
}

/** Dipakai induk untuk menetapkan pilihan dari luar (mis. setelah simpan cepat). */
function setTerpilih(item, diam = true) {
  terpilih.value = item
  if (!diam) pilih(item)
}

function saatKlikLuar(e) {
  if (akar.value && !akar.value.contains(e.target)) terbuka.value = false
}
onMounted(() => document.addEventListener('mousedown', saatKlikLuar))
onBeforeUnmount(() => document.removeEventListener('mousedown', saatKlikLuar))

defineExpose({ setTerpilih, bersihkan })
</script>

<template>
  <div ref="akar" class="relative">
    <label v-if="label" class="label-form">
      {{ label }} <span v-if="diperlukan" class="text-brand-600">*</span>
    </label>

    <div
      v-if="terpilih"
      class="flex items-center justify-between gap-2 rounded-lg border border-slate-300 bg-slate-50 px-3 py-2"
    >
      <div class="min-w-0 text-sm">
        <slot name="terpilih" :item="terpilih">{{ terpilih[kunciLabel] }}</slot>
      </div>
      <button v-if="!nonaktif" class="text-slate-400 hover:text-brand-600" @click="bersihkan">
        <AppIcon nama="x" :ukuran="16" />
      </button>
    </div>

    <div v-else>
      <div class="relative">
        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
          <AppIcon nama="search" :ukuran="16" />
        </span>
        <input
          v-model="kata"
          :placeholder="placeholder"
          :disabled="nonaktif"
          class="input-form pl-9"
          :class="galat ? 'border-brand-400' : ''"
          @focus="terbuka = true"
          @input="saatKetik"
        />
        <span v-if="memuat" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">
          <AppIcon nama="loader" :ukuran="15" class="animate-spin" />
        </span>
      </div>

      <div
        v-if="terbuka"
        class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
      >
        <p v-if="!daftarTampil.length" class="px-3 py-2 text-xs" :class="galatCari ? 'text-brand-600' : 'text-slate-400'">
          {{ galatCari ? `Pencarian gagal: ${galatCari}` : kosongTeks }}
        </p>
        <button
          v-for="o in daftarTampil"
          :key="o[kunciNilai]"
          type="button"
          class="block w-full px-3 py-2 text-left text-sm hover:bg-brand-50"
          @click="pilih(o)"
        >
          <slot name="item" :item="o">{{ o[kunciLabel] }}</slot>
        </button>
      </div>
    </div>

    <p v-if="galat" class="mt-1 text-xs text-brand-600">{{ galat }}</p>
  </div>
</template>

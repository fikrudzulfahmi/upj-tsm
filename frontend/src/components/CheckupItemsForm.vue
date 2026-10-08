<script setup>
/**
 * Form/daftar hasil pemeriksaan check up — dipakai di form Check Up (bisaEdit)
 * dan ditampilkan ulang di Form SA (read-only).
 */
import { computed } from 'vue'
import { kelompokkanPerKategori, ringkasHasil, STATUS_ITEM } from '@/utils/checkup'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  baris: { type: Array, default: () => [] },
  bisaEdit: { type: Boolean, default: true },
  judul: { type: String, default: 'Hasil Pemeriksaan' },
})
const emit = defineEmits(['update:baris'])

const grup = computed(() => kelompokkanPerKategori(props.baris))
const ringkas = computed(() => ringkasHasil(props.baris))

function ubahStatus(item, status) {
  emit(
    'update:baris',
    props.baris.map((b) => (b === item ? { ...b, status } : b)),
  )
}
function ubahCatatan(item, note) {
  emit(
    'update:baris',
    props.baris.map((b) => (b === item ? { ...b, note } : b)),
  )
}
const kelasStatus = (kode) => `status-${kode}`
</script>

<template>
  <div class="card">
    <div class="card-header">
      <div class="flex items-center gap-2">
        <AppIcon nama="clipboardCheck" :ukuran="17" class="text-brand-600" />
        <h3 class="text-sm font-semibold text-slate-700">{{ judul }}</h3>
        <span class="text-xs text-slate-400">({{ ringkas.total }} item)</span>
      </div>
      <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
        <span class="rounded-full bg-emerald-100 px-2 py-0.5 font-medium text-emerald-700">OK {{ ringkas.ok }}</span>
        <span class="rounded-full bg-amber-100 px-2 py-0.5 font-medium text-amber-700">Perlu Perhatian {{ ringkas.perlu_perhatian }}</span>
        <span class="rounded-full bg-brand-100 px-2 py-0.5 font-medium text-brand-700">Rusak {{ ringkas.rusak }}</span>
        <span class="rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-600">Tidak Diperiksa {{ ringkas.tidak_diperiksa }}</span>
      </div>
    </div>

    <div v-if="!baris.length" class="px-4 py-8 text-center text-sm text-slate-400">
      Tidak ada item pemeriksaan.
    </div>

    <div v-else class="divide-y divide-slate-100">
      <div v-for="g in grup" :key="g.kategori" class="px-4 py-3">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-brand-700">{{ g.kategori }}</p>
        <div class="space-y-2">
          <div v-for="item in g.items" :key="item.item_name" class="rounded-lg border border-slate-100 bg-slate-50/60 p-2.5">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <p class="text-sm font-medium text-slate-700">{{ item.item_name }}</p>
              <div v-if="bisaEdit" class="flex flex-wrap gap-1">
                <button
                  v-for="s in STATUS_ITEM"
                  :key="s.nilai"
                  type="button"
                  class="rounded-md px-2 py-1 text-[11px] font-medium transition"
                  :class="[
                    kelasStatus(s.nilai),
                    item.status === s.nilai ? 'ring-2 ring-offset-1 ring-slate-400' : 'opacity-60 hover:opacity-100',
                  ]"
                  @click="ubahStatus(item, s.nilai)"
                >
                  {{ s.label }}
                </button>
              </div>
              <span
                v-else
                class="rounded-md px-2 py-1 text-[11px] font-medium"
                :class="kelasStatus(item.status)"
              >
                {{ STATUS_ITEM.find((s) => s.nilai === item.status)?.label || '-' }}
              </span>
            </div>
            <input
              v-if="bisaEdit || item.note"
              :value="item.note"
              :readonly="!bisaEdit"
              placeholder="Catatan (opsional)"
              class="mt-2 w-full rounded-md border border-slate-200 bg-white px-2 py-1.5 text-xs text-slate-700 outline-none focus:border-brand-400"
              @input="ubahCatatan(item, $event.target.value)"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

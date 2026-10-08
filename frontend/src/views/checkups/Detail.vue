<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { checkupApi } from '@/api'
import { useUiStore } from '@/stores/ui'
import { ringkasHasil } from '@/utils/checkup'
import { formatTanggal } from '@/utils/format'
import CheckupItemsForm from '@/components/CheckupItemsForm.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import LoadingBlock from '@/components/ui/LoadingBlock.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const route = useRoute()
const router = useRouter()
const ui = useUiStore()

const data = ref(null)
const memuat = ref(true)
const modalSelesai = ref(false)
const proses = ref(false)

const ringkas = computed(() => ringkasHasil(data.value?.results || []))
const sudahSelesai = computed(() => data.value?.status === 'completed')

onMounted(async () => {
  try {
    const res = await checkupApi.detail(route.params.id)
    data.value = res.data
  } finally {
    memuat.value = false
  }
})

async function selesaikan(hasil) {
  proses.value = true
  try {
    const res = await checkupApi.selesai(data.value.id, { result: hasil })
    modalSelesai.value = false

    if (hasil === 'continue_service' && res.data.service_order_id) {
      ui.sukses('Form SA dibuat otomatis.')
      router.push(`/service-orders/${res.data.service_order_id}/edit`)
    } else {
      ui.sukses('Check up selesai.')
      data.value = res.data.checkup
    }
  } finally {
    proses.value = false
  }
}
</script>

<template>
  <div class="space-y-4">
    <LoadingBlock v-if="memuat" />

    <template v-else-if="data">
      <div class="card p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h2 class="text-lg font-semibold text-slate-800">{{ data.checkup_no }}</h2>
              <BaseBadge :varian="sudahSelesai ? 'sukses' : 'netral'">
                {{ sudahSelesai ? 'Selesai' : 'Draft' }}
              </BaseBadge>
              <BaseBadge v-if="data.result" varian="brand">
                {{ data.result === 'continue_service' ? 'Lanjut Service' : 'Hanya Check Up' }}
              </BaseBadge>
            </div>
            <p class="mt-1 text-sm text-slate-500">
              {{ formatTanggal(data.checkup_date) }} ·
              {{ data.customer?.name }} · {{ data.vehicle?.plate_number }}
              <span v-if="data.odometer"> · {{ data.odometer.toLocaleString('id-ID') }} km</span>
            </p>
            <p class="text-xs text-slate-400">
              Template: {{ data.template?.name || '-' }} · Unit entry: {{ data.unit_entry?.entry_no || '-' }}
            </p>
          </div>
          <div class="flex flex-wrap gap-2">
            <RouterLink v-if="data.unit_entry?.service_order_id" :to="`/service-orders/${data.unit_entry.service_order_id}`">
              <BaseButton varian="halus" ikon="clipboardPen">Lihat Form SA</BaseButton>
            </RouterLink>
            <RouterLink v-if="!sudahSelesai" :to="`/checkups/${data.id}/edit`">
              <BaseButton varian="garis" ikon="pencil">Lanjutkan Isi</BaseButton>
            </RouterLink>
            <BaseButton v-if="!sudahSelesai" ikon="checkCircle" @click="modalSelesai = true">Selesaikan</BaseButton>
            <BaseButton varian="garis" ikon="arrowLeft" @click="router.push('/checkups')">Kembali</BaseButton>
          </div>
        </div>

        <div class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
          <div class="rounded-lg bg-slate-50 p-3">
            <p class="text-xs uppercase tracking-wide text-slate-500">Keluhan Konsumen</p>
            <p class="mt-1 text-slate-700">{{ data.complaint || '-' }}</p>
          </div>
          <div class="rounded-lg bg-slate-50 p-3">
            <p class="text-xs uppercase tracking-wide text-slate-500">Catatan Umum</p>
            <p class="mt-1 text-slate-700">{{ data.general_notes || '-' }}</p>
          </div>
        </div>
      </div>

      <div class="grid gap-3 sm:grid-cols-4">
        <div class="card p-3 text-center">
          <p class="text-xs uppercase text-slate-500">Total item</p>
          <p class="text-xl font-bold text-slate-800">{{ ringkas.total }}</p>
        </div>
        <div class="card p-3 text-center">
          <p class="text-xs uppercase text-slate-500">OK</p>
          <p class="text-xl font-bold text-emerald-600">{{ ringkas.ok }}</p>
        </div>
        <div class="card p-3 text-center">
          <p class="text-xs uppercase text-slate-500">Perlu perhatian</p>
          <p class="text-xl font-bold text-amber-600">{{ ringkas.perlu_perhatian }}</p>
        </div>
        <div class="card p-3 text-center">
          <p class="text-xs uppercase text-slate-500">Rusak</p>
          <p class="text-xl font-bold text-brand-600">{{ ringkas.rusak }}</p>
        </div>
      </div>

      <CheckupItemsForm v-model:baris="data.results" :bisa-edit="false" judul="Hasil Pemeriksaan" />
    </template>

    <BaseModal v-model="modalSelesai" judul="Selesaikan Check Up" lebar="sm">
      <div class="grid gap-2">
        <button class="rounded-lg border border-slate-200 p-3 text-left hover:border-brand-400 hover:bg-brand-50" @click="selesaikan('checkup_only')">
          <span class="block text-sm font-semibold text-slate-700">Hanya Check Up</span>
          <span class="block text-xs text-slate-500">Selesai tanpa servis.</span>
        </button>
        <button class="rounded-lg border border-brand-200 bg-brand-50/50 p-3 text-left hover:border-brand-500" @click="selesaikan('continue_service')">
          <span class="block text-sm font-semibold text-brand-700">Lanjut Service (Form SA)</span>
          <span class="block text-xs text-slate-500">SA dibuat otomatis dengan kondisi kendaraan tersalin.</span>
        </button>
      </div>
      <p v-if="proses" class="mt-3 flex items-center gap-2 text-xs text-slate-500">
        <AppIcon nama="loader" :ukuran="14" class="animate-spin" /> Memproses…
      </p>
    </BaseModal>
  </div>
</template>

<script setup>
/**
 * Komponen reusable: cari pelanggan (nama / HP / nopol) → pilih kendaraan.
 * Dipakai di Form Check Up dan Form SA. Bila pelanggan belum ada, tombol
 * "Pelanggan baru" membuka CustomerQuickForm dan hasilnya langsung terpilih.
 */
import { computed, ref, watch } from 'vue'
import { pelangganApi } from '@/api'
import { useUiStore } from '@/stores/ui'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import CustomerQuickForm from '@/components/CustomerQuickForm.vue'

const props = defineProps({
  pelangganId: { type: [Number, String, null], default: null },
  kendaraanId: { type: [Number, String, null], default: null },
  labelPelanggan: { type: String, default: 'Pelanggan' },
  labelKendaraan: { type: String, default: 'Kendaraan' },
  galatPelanggan: { type: String, default: null },
  galatKendaraan: { type: String, default: null },
  nonaktif: { type: Boolean, default: false },
  tampilkanKendaraan: { type: Boolean, default: true },
})
const emit = defineEmits(['update:pelangganId', 'update:kendaraanId', 'terpilih'])

const ui = useUiStore()
const kata = ref('')
const hasil = ref([])
const terbuka = ref(false)
const memuat = ref(false)
const pelanggan = ref(null)
const modalBaru = ref(false)
let timer = null

const daftarKendaraan = computed(() => pelanggan.value?.vehicles || [])
const kendaraanTerpilih = computed(() => daftarKendaraan.value.find((k) => k.id === Number(props.kendaraanId)) || null)
const opsiKendaraan = computed(() =>
  daftarKendaraan.value.map((k) => ({
    value: k.id,
    label: `${k.plate_number} — ${[k.brand, k.model].filter(Boolean).join(' ') || k.type}`,
  })),
)

function cari() {
  clearTimeout(timer)
  memuat.value = true
  timer = setTimeout(async () => {
    try {
      const res = await pelangganApi.daftar({ search: kata.value.trim() || undefined, per_page: 8 })
      hasil.value = res.data || []
    } catch {
      hasil.value = []
    } finally {
      memuat.value = false
    }
  }, 280)
}

function pilihPelanggan(item) {
  pasangPelanggan(item, kata.value.trim())
  kata.value = ''
  terbuka.value = false
}

/** Tentukan kendaraan awal: cocokkan nopol dengan kata pencarian, jika tidak ambil yang pertama. */
function pasangPelanggan(item, kataPencarian = '') {
  pelanggan.value = item
  emit('update:pelangganId', item.id)
  const kendaraan = item.vehicles || []
  let target = null
  if (kendaraan.length) {
    const q = (kataPencarian || '').toUpperCase().replace(/\s+/g, '')
    target = kendaraan.find((k) => k.plate_number.replace(/\s+/g, '').includes(q) && q.length > 2) || kendaraan[0]
  }
  emit('update:kendaraanId', target?.id ?? null)
  emit('terpilih', { customer: item, vehicle: target })
}

function gantiKendaraan(id) {
  emit('update:kendaraanId', id)
  emit('terpilih', { customer: pelanggan.value, vehicle: daftarKendaraan.value.find((k) => k.id === Number(id)) || null })
}

function bersihkan() {
  pelanggan.value = null
  kata.value = ''
  emit('update:pelangganId', null)
  emit('update:kendaraanId', null)
  emit('terpilih', { customer: null, vehicle: null })
}

function setelahSimpanCepat(pelangganBaru) {
  pasangPelanggan(pelangganBaru)
  ui.info('Pelanggan baru langsung dipilih pada form.')
}

/** Sinkronkan bila induk menetapkan pelanggan dari luar (mis. lanjutan check up). */
watch(
  () => props.pelangganId,
  async (id) => {
    if (!id) {
      if (pelanggan.value) pelanggan.value = null
      return
    }
    if (pelanggan.value?.id === Number(id)) return
    try {
      const res = await pelangganApi.detail(id)
      const data = res.data
      pelanggan.value = data
      if (!props.kendaraanId && data.vehicles?.length) {
        emit('update:kendaraanId', data.vehicles[0].id)
      }
    } catch {
      /* diabaikan: pesan galat sudah ditampilkan interceptor */
    }
  },
  { immediate: true },
)

defineExpose({ bersihkan, pasangPelanggan })
</script>

<template>
  <div class="space-y-3">
    <!-- Pencarian / kartu pelanggan terpilih -->
    <div>
      <label class="label-form">
        {{ labelPelanggan }} <span class="text-brand-600">*</span>
      </label>

      <div v-if="pelanggan" class="rounded-lg border border-slate-200 bg-white p-3">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <p class="text-sm font-semibold text-slate-800">{{ pelanggan.name }}</p>
              <BaseBadge v-if="pelanggan.membership?.status === 'active'" varian="sukses" ukuran="sm">
                <AppIcon nama="award" :ukuran="11" /> MEMBER
              </BaseBadge>
              <BaseBadge v-else-if="pelanggan.membership" varian="bahaya" ukuran="sm">Hangus</BaseBadge>
            </div>
            <p class="mt-0.5 text-xs text-slate-500">
              <span v-if="pelanggan.phone"><AppIcon nama="phone" :ukuran="12" class="mr-1 inline" />{{ pelanggan.phone }}</span>
              <span v-else class="text-slate-400">Tanpa no. HP</span>
              <span class="mx-1.5 text-slate-300">·</span>{{ pelanggan.code }}
            </p>
            <p v-if="pelanggan.address" class="mt-0.5 truncate text-xs text-slate-400">{{ pelanggan.address }}</p>
          </div>
          <button v-if="!nonaktif" class="shrink-0 text-slate-400 hover:text-brand-600" title="Ganti pelanggan" @click="bersihkan">
            <AppIcon nama="x" :ukuran="16" />
          </button>
        </div>
      </div>

      <div v-else class="relative">
        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
          <AppIcon nama="search" :ukuran="16" />
        </span>
        <input
          v-model="kata"
          :disabled="nonaktif"
          placeholder="Cari nama / no. HP / nomor polisi…"
          class="input-form pl-9"
          :class="galatPelanggan ? 'border-brand-400' : ''"
          @focus="terbuka = true"
          @input="cari"
        />
        <span v-if="memuat" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">
          <AppIcon nama="loader" :ukuran="15" class="animate-spin" />
        </span>

        <div
          v-if="terbuka"
          class="absolute z-30 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
        >
          <p v-if="!hasil.length" class="px-3 py-2 text-xs text-slate-400">
            {{ kata ? 'Pelanggan tidak ditemukan. Gunakan "Pelanggan baru".' : 'Ketik untuk mencari pelanggan.' }}
          </p>
          <button
            v-for="c in hasil"
            :key="c.id"
            type="button"
            class="block w-full px-3 py-2 text-left hover:bg-brand-50"
            @click="pilihPelanggan(c)"
          >
            <div class="flex items-center justify-between gap-2">
              <span class="text-sm font-medium text-slate-700">{{ c.name }}</span>
              <BaseBadge v-if="c.membership?.status === 'active'" varian="sukses" ukuran="sm">MEMBER</BaseBadge>
            </div>
            <p class="text-xs text-slate-400">
              {{ c.phone || 'tanpa HP' }} ·
              {{ (c.vehicles || []).map((v) => v.plate_number).join(', ') || 'belum ada kendaraan' }}
            </p>
          </button>
          <div class="border-t border-slate-100 p-2">
            <BaseButton varian="halus" ukuran="sm" ikon="userPlus" blok @click="modalBaru = true; terbuka = false">
              Pelanggan baru
            </BaseButton>
          </div>
        </div>
      </div>

      <p v-if="galatPelanggan" class="mt-1 text-xs text-brand-600">{{ galatPelanggan }}</p>
      <button
        v-if="!pelanggan"
        type="button"
        class="mt-1.5 text-xs font-medium text-brand-600 underline hover:text-brand-700"
        @click="modalBaru = true"
      >
        + Tambah pelanggan baru
      </button>
    </div>

    <!-- Kendaraan -->
    <div v-if="tampilkanKendaraan && pelanggan">
      <BaseSelect
        v-if="daftarKendaraan.length"
        :model-value="kendaraanId ?? ''"
        :label="labelKendaraan"
        :opsi="opsiKendaraan"
        :galat="galatKendaraan"
        :nonaktif="nonaktif"
        :placeholder="'— Pilih kendaraan —'"
        @update:model-value="gantiKendaraan"
      />
      <div v-else class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
        Pelanggan ini belum punya kendaraan. Tambahkan lewat halaman detail pelanggan.
      </div>
      <p v-if="kendaraanTerpilih" class="mt-1 text-xs text-slate-500">
        {{ kendaraanTerpilih.type === 'mobil' ? 'Mobil' : 'Motor' }}
        <span v-if="kendaraanTerpilih.year">· {{ kendaraanTerpilih.year }}</span>
        <span v-if="kendaraanTerpilih.color">· {{ kendaraanTerpilih.color }}</span>
      </p>
    </div>

    <CustomerQuickForm v-model="modalBaru" @tersimpan="setelahSimpanCepat" />
  </div>
</template>

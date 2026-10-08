<script setup>
import { computed, onMounted, ref } from 'vue'
import { rewardApi, sparepartApi } from '@/api'
import MasterCrud from '@/components/MasterCrud.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const spareparts = ref([])

onMounted(async () => {
  const res = await sparepartApi.pilihan({ limit: 100 })
  spareparts.value = res.data || []
})

const kolom = [
  { kunci: 'name', label: 'Reward' },
  { kunci: 'points_required', label: 'Poin', align: 'kanan' },
  { kunci: 'hadiah', label: 'Hadiah' },
  { kunci: 'status', label: 'Status' },
  { kunci: 'aksi', label: '', kelas: 'w-24 text-right' },
]

const bidang = computed(() => [
  { kunci: 'name', label: 'Nama Reward', wajib: true, placeholder: 'mis. Oli Gratis' },
  { kunci: 'points_required', label: 'Poin Dibutuhkan', tipe: 'number', wajib: true },
  {
    kunci: 'sparepart_id',
    label: 'Sparepart Hadiah',
    tipe: 'select',
    opsi: spareparts.value.map((s) => ({ value: s.id, label: `${s.name} (stok ${s.stock} ${s.unit})` })),
    petunjuk: 'Stok part ini otomatis berkurang saat poin ditukar',
  },
  { kunci: 'qty', label: 'Jumlah Diberikan', tipe: 'number' },
  { kunci: 'is_active', label: 'Aktif', tipe: 'checkbox' },
])
</script>

<template>
  <MasterCrud
    judul="Reward Tukar Poin"
    teks-tombol="Reward Baru"
    cari-placeholder="Cari nama reward…"
    kosong-teks="Belum ada reward."
    :kolom="kolom"
    :bidang="bidang"
    :nilai-awal="{ name: '', points_required: 100, sparepart_id: '', qty: 1, is_active: true }"
    :ambil="(f) => rewardApi.daftar(f)"
    :simpan="(p) => rewardApi.simpan(p)"
    :ubah="(id, p) => rewardApi.ubah(id, p)"
    :hapus="rewardApi.hapus"
    :pesan-hapus="(b) => `Hapus reward '${b.name}'?`"
  >
    <template #sel-points_required="{ baris }">
      <span class="tabular text-sm font-medium">{{ baris.points_required }} poin</span>
    </template>

    <template #sel-hadiah="{ baris }">
      <div v-if="baris.sparepart" class="text-sm text-slate-600">
        <AppIcon nama="package" :ukuran="13" class="mr-1 inline text-slate-400" />
        {{ baris.qty }}× {{ baris.sparepart.name }}
        <p class="text-xs text-slate-400">stok saat ini {{ baris.sparepart.stock }} {{ baris.sparepart.unit }}</p>
      </div>
      <span v-else class="text-xs text-slate-400">Tanpa sparepart</span>
    </template>

    <template #sel-status="{ baris }">
      <BaseBadge :varian="baris.is_active ? 'sukses' : 'netral'">{{ baris.is_active ? 'Aktif' : 'Nonaktif' }}</BaseBadge>
    </template>
  </MasterCrud>
</template>

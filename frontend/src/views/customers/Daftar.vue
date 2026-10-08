<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { pelangganApi } from '@/api'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { useDaftar } from '@/composables/useDaftar'
import { formatTanggal } from '@/utils/format'
import { aksiMember } from '@/utils/status'
import { pesanGalat } from '@/api/client'
import BaseTable from '@/components/ui/BaseTable.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import Pagination from '@/components/ui/Pagination.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import PelangganFormModal from '@/components/PelangganFormModal.vue'
import ModalAkunMember from '@/components/ModalAkunMember.vue'

const auth = useAuthStore()
const ui = useUiStore()

const kolom = [
  { kunci: 'name', label: 'Pelanggan' },
  { kunci: 'kontak', label: 'Kontak' },
  { kunci: 'kendaraan', label: 'Kendaraan' },
  { kunci: 'member', label: 'Membership' },
  { kunci: 'kunjungan', label: 'Kunjungan', align: 'kanan' },
  { kunci: 'aksi', label: '', kelas: 'w-32 text-right' },
]

const daftar = useDaftar((f) => pelangganApi.daftar(f))

/* ------------------------- jadikan member dari daftar ------------------------- */
// Pelanggan yang belum member bisa langsung dijadikan member dari baris daftar,
// tanpa harus membuka detail lalu mencari tab "Membership & Poin".
const modalAkun = ref(false)
const hasilAkun = ref(null)
const prosesMember = ref(0) // menyimpan id pelanggan yang sedang diproses

function aksi(baris) {
  return aksiMember(baris.membership)
}

async function jadikanMember(baris) {
  const info = aksi(baris)
  const ya = await ui.tanya({
    judul: info.hangus ? 'Aktifkan kembali membership' : 'Jadikan member',
    pesan: info.hangus
      ? `Aktifkan kembali membership ${baris.name}? Masa berlaku dihitung dari hari ini.`
      : `Jadikan ${baris.name} sebagai member? Akun portal akan dibuat dengan login No. HP pelanggan.`,
    teksOk: info.label,
    jenis: 'info',
  })
  if (!ya) return

  prosesMember.value = baris.id
  try {
    const res = await pelangganApi.jadikanMember(baris.id)
    hasilAkun.value = res.data
    modalAkun.value = true
    await daftar.muat()
  } catch (e) {
    ui.gagal(pesanGalat(e))
  } finally {
    prosesMember.value = 0
  }
}
const modalTerbuka = ref(false)
const sedangUbah = ref(null)

onMounted(daftar.muat)

function bukaTambah() {
  sedangUbah.value = null
  modalTerbuka.value = true
}
function bukaUbah(baris) {
  sedangUbah.value = baris
  modalTerbuka.value = true
}

async function hapus(baris) {
  const ya = await ui.tanya({
    judul: 'Hapus pelanggan',
    pesan: `Hapus pelanggan "${baris.name}"? Data riwayat servisnya tetap tersimpan.`,
    teksOk: 'Hapus',
  })
  if (!ya) return
  await pelangganApi.hapus(baris.id)
  ui.sukses('Pelanggan dihapus.')
  daftar.muat()
}

function setelahSimpan() {
  daftar.muat()
}
</script>

<template>
  <div class="space-y-4">
    <div class="card">
      <div class="card-header">
        <div class="flex flex-wrap items-center gap-2">
          <BaseInput v-model="daftar.filter.search" ikon="search" placeholder="Cari nama / HP / nopol…" class="w-72" @input="daftar.cari" />
          <BaseSelect
            :model-value="daftar.filter.member || ''"
            :opsi="[{ value: 'aktif', label: 'Member aktif' }, { value: 'hangus', label: 'Member hangus' }]"
            placeholder="Semua pelanggan"
            class="w-48"
            @update:model-value="daftar.saring('member', $event || undefined)"
          />
        </div>
        <BaseButton v-if="auth.bisa('customer.manage')" ikon="plus" @click="bukaTambah">Pelanggan Baru</BaseButton>
      </div>

      <BaseTable :kolom="kolom" :baris="daftar.baris.value" :muat="daftar.memuat.value" kosong-teks="Belum ada pelanggan.">
        <template #sel-name="{ baris }">
          <RouterLink :to="`/customers/${baris.id}`" class="font-medium text-slate-700 hover:text-brand-600">
            {{ baris.name }}
          </RouterLink>
          <p class="text-xs text-slate-400">{{ baris.code }}{{ baris.gender ? ` · ${baris.gender === 'L' ? 'Laki-laki' : 'Perempuan'}` : '' }}</p>
        </template>

        <template #sel-kontak="{ baris }">
          <p class="text-sm text-slate-600">{{ baris.phone || '-' }}</p>
          <p class="truncate text-xs text-slate-400">{{ baris.address || 'Alamat belum diisi' }}</p>
        </template>

        <template #sel-kendaraan="{ baris }">
          <div v-if="baris.vehicles?.length" class="flex flex-wrap gap-1">
            <span v-for="k in baris.vehicles" :key="k.id" class="rounded-md bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-600">
              {{ k.plate_number }}
            </span>
          </div>
          <span v-else class="text-xs text-slate-400">Belum ada</span>
        </template>

        <template #sel-member="{ baris }">
          <BaseBadge v-if="baris.membership?.status === 'active'" varian="sukses" ukuran="sm">
            <AppIcon nama="award" :ukuran="11" /> {{ baris.membership.points_balance }} poin
          </BaseBadge>
          <BaseBadge v-else-if="baris.membership" varian="bahaya" ukuran="sm">Hangus</BaseBadge>
          <span v-else class="text-xs text-slate-400">Bukan member</span>
          <p v-if="baris.membership?.status === 'active'" class="mt-0.5 text-[11px] text-slate-400">
            s/d {{ formatTanggal(baris.membership.expires_at) }}
          </p>
        </template>

        <template #sel-kunjungan="{ baris }">
          <span class="tabular text-sm">{{ baris.jumlah_unit ?? 0 }}×</span>
          <p class="text-xs text-slate-400">{{ baris.jumlah_sa ?? 0 }} SA</p>
        </template>

        <template #sel-aksi="{ baris }">
          <div class="flex justify-end gap-1">
            <BaseButton
              v-if="aksi(baris).tampil"
              ukuran="ikon"
              varian="halus"
              ikon="award"
              :title="aksi(baris).label"
              :memuat="prosesMember === baris.id"
              @click="jadikanMember(baris)"
            />
            <BaseButton ukuran="ikon" varian="halus" ikon="pencil" title="Ubah" @click="bukaUbah(baris)" />
            <BaseButton v-if="auth.isAdmin" ukuran="ikon" varian="bahaya" ikon="trash" title="Hapus" @click="hapus(baris)" />
          </div>
        </template>
      </BaseTable>

      <Pagination
        :halaman="daftar.meta.halaman"
        :per-halaman="daftar.meta.per_halaman"
        :total="daftar.meta.total"
        @ubah="daftar.keHalaman"
      />
    </div>

    <PelangganFormModal v-model="modalTerbuka" :pelanggan="sedangUbah" @tersimpan="setelahSimpan" />
  </div>

    <!-- Kredensial akun portal member (komponen bersama dengan halaman detail) -->
    <ModalAkunMember v-model="modalAkun" :hasil="hasilAkun" />
</template>
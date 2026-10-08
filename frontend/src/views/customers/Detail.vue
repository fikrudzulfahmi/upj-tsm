<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { pelangganApi, rewardApi } from '@/api'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { formatRupiah, formatTanggal } from '@/utils/format'
import { pesanGalat } from '@/api/client'
import { aksiMember, statusSa } from '@/utils/status'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import LoadingBlock from '@/components/ui/LoadingBlock.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import PelangganFormModal from '@/components/PelangganFormModal.vue'
import ModalAkunMember from '@/components/ModalAkunMember.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const ui = useUiStore()

const id = route.params.id
const pelanggan = ref(null)
const poin = ref({ daftar: [], saldo: 0 })
const riwayat = ref({ checkups: [], service_orders: [] })
const rewards = ref([])
const tab = ref('info')
const memuat = ref(true)
const modalUbah = ref(false)
const modalPassword = ref(null)
const modalPasswordTerbuka = ref(false)
const modalTukar = ref(false)
const rewardDipilih = ref('')
const proses = ref(false)

const TAB = [
  { kunci: 'info', label: 'Info & Kendaraan', ikon: 'user' },
  { kunci: 'member', label: 'Membership & Poin', ikon: 'award' },
  { kunci: 'riwayat', label: 'Riwayat Servis', ikon: 'history' },
]

const member = computed(() => pelanggan.value?.membership || null)

/** Tombol "Jadikan Member" juga tersedia di kepala halaman supaya tidak tersembunyi di dalam tab. */
const aksiMbr = computed(() => aksiMember(member.value))

async function muat() {
  memuat.value = true
  try {
    const res = await pelangganApi.detail(id)
    pelanggan.value = res.data
  } finally {
    memuat.value = false
  }
}

async function muatPoin() {
  const res = await pelangganApi.poin(id, { per_page: 20 })
  poin.value = { daftar: res.data || [], saldo: res.meta?.saldo || 0 }
}

async function muatRiwayat() {
  const res = await pelangganApi.riwayat(id)
  riwayat.value = res.data || { checkups: [], service_orders: [] }
}

onMounted(async () => {
  await muat()
  await Promise.all([muatPoin(), muatRiwayat()])
  if (auth.bisa('customer.manage')) {
    const r = await rewardApi.daftar({ aktif: 1 })
    rewards.value = r.data || []
  }
})

async function jadikanMember() {
  const ya = await ui.tanya({
    judul: 'Jadikan member',
    pesan: `Jadikan ${pelanggan.value.name} sebagai member? Akun portal akan dibuat dengan login No. HP pelanggan.`,
    teksOk: 'Jadikan Member',
    jenis: 'info',
  })
  if (!ya) return

  proses.value = true
  try {
    const res = await pelangganApi.jadikanMember(id)
    modalPassword.value = res.data
    modalPasswordTerbuka.value = true
    await muat()
    await muatPoin()
  } catch (e) {
    // Sebelumnya galat (mis. No. HP kosong / sudah member aktif) tidak pernah tampil.
    ui.gagal(pesanGalat(e))
  } finally {
    proses.value = false
  }
}

async function perpanjang() {
  const ya = await ui.tanya({
    judul: 'Perpanjang membership',
    pesan: 'Masa berlaku akan ditambah sesuai durasi pada Pengaturan (dihitung dari tanggal berakhir yang masih berlaku).',
    teksOk: 'Perpanjang',
    jenis: 'info',
  })
  if (!ya) return
  proses.value = true
  try {
    await pelangganApi.perpanjangMember(id)
    ui.sukses('Masa membership diperpanjang.')
    await muat()
  } finally {
    proses.value = false
  }
}

async function tukarPoin() {
  if (!rewardDipilih.value) {
    ui.gagal('Pilih reward terlebih dahulu.')
    return
  }
  proses.value = true
  try {
    const res = await pelangganApi.tukarPoin(id, { reward_id: Number(rewardDipilih.value) })
    ui.sukses(`Poin ditukar. ${res.data.redemption.reward} telah diberikan.`)
    modalTukar.value = false
    rewardDipilih.value = ''
    await Promise.all([muat(), muatPoin()])
  } finally {
    proses.value = false
  }
}

function salinPassword() {
  navigator.clipboard?.writeText(modalPassword.value.password_awal)
  ui.sukses('Password disalin.')
}

const opsiReward = computed(() =>
  rewards.value
    .filter((r) => r.points_required <= (member.value?.points_balance || 0))
    .map((r) => ({ value: r.id, label: `${r.name} (${r.points_required} poin)` })),
)
</script>

<template>
  <div class="space-y-4">
    <LoadingBlock v-if="memuat" />

    <template v-else-if="pelanggan">
      <div class="card p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div class="flex items-start gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white">
              {{ pelanggan.name.slice(0, 2).toUpperCase() }}
            </div>
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg font-semibold text-slate-800">{{ pelanggan.name }}</h2>
                <BaseBadge v-if="member?.status === 'active'" varian="sukses" ukuran="sm">
                  <AppIcon nama="award" :ukuran="11" /> MEMBER
                </BaseBadge>
                <BaseBadge v-else-if="member" varian="bahaya" ukuran="sm">HANGUS</BaseBadge>
              </div>
              <p class="mt-0.5 text-sm text-slate-500">
                {{ pelanggan.code }} · {{ pelanggan.phone || 'tanpa no. HP' }}
              </p>
              <p v-if="pelanggan.address" class="text-xs text-slate-400">{{ pelanggan.address }}</p>
            </div>
          </div>
          <div class="flex flex-wrap gap-2">
            <BaseButton
              v-if="aksiMbr.tampil"
              :varian="aksiMbr.hangus ? 'garis' : 'utama'"
              ikon="award"
              :memuat="proses"
              @click="jadikanMember"
            >
              {{ aksiMbr.label }}
            </BaseButton>
            <BaseButton varian="garis" ikon="pencil" @click="modalUbah = true">Ubah</BaseButton>
            <BaseButton varian="garis" ikon="arrowLeft" @click="router.push('/customers')">Kembali</BaseButton>
          </div>
        </div>
      </div>

      <div class="flex flex-wrap gap-1 border-b border-slate-200">
        <button
          v-for="t in TAB"
          :key="t.kunci"
          class="flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium transition"
          :class="tab === t.kunci ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
          @click="tab = t.kunci"
        >
          <AppIcon :nama="t.ikon" :ukuran="15" /> {{ t.label }}
        </button>
      </div>

      <!-- INFO & KENDARAAN -->
      <div v-if="tab === 'info'" class="grid gap-4 lg:grid-cols-2">
        <div class="card">
          <div class="card-header"><h3 class="text-sm font-semibold text-slate-700">Data Pelanggan</h3></div>
          <dl class="divide-y divide-slate-100 text-sm">
            <div class="flex justify-between px-4 py-2.5"><dt class="text-slate-500">Kode</dt><dd>{{ pelanggan.code }}</dd></div>
            <div class="flex justify-between px-4 py-2.5"><dt class="text-slate-500">Jenis kelamin</dt><dd>{{ pelanggan.gender === 'P' ? 'Perempuan' : 'Laki-laki' }}</dd></div>
            <div class="flex justify-between px-4 py-2.5"><dt class="text-slate-500">No. HP</dt><dd>{{ pelanggan.phone || '-' }}</dd></div>
            <div class="flex justify-between px-4 py-2.5"><dt class="text-slate-500">Alamat</dt><dd class="text-right">{{ pelanggan.address || '-' }}</dd></div>
            <div class="flex justify-between px-4 py-2.5"><dt class="text-slate-500">Catatan</dt><dd class="text-right">{{ pelanggan.notes || '-' }}</dd></div>
            <div class="flex justify-between px-4 py-2.5"><dt class="text-slate-500">Kunjungan</dt><dd>{{ pelanggan.jumlah_unit || 0 }} unit · {{ pelanggan.jumlah_sa || 0 }} SA</dd></div>
          </dl>
        </div>

        <div class="card">
          <div class="card-header">
            <h3 class="text-sm font-semibold text-slate-700">Kendaraan</h3>
            <span class="text-xs text-slate-400">Kelola lewat tombol Ubah</span>
          </div>
          <div v-if="!pelanggan.vehicles?.length" class="p-4">
            <EmptyState ikon="bike" judul="Belum ada kendaraan" pesan="Tambahkan kendaraan lewat tombol Ubah." />
          </div>
          <ul v-else class="divide-y divide-slate-100">
            <li v-for="k in pelanggan.vehicles" :key="k.id" class="flex items-center justify-between gap-3 px-4 py-3">
              <div>
                <p class="text-sm font-medium text-slate-700">{{ k.plate_number }}</p>
                <p class="text-xs text-slate-400">
                  {{ k.type === 'mobil' ? 'Mobil' : 'Motor' }} · {{ [k.brand, k.model].filter(Boolean).join(' ') || '-' }}
                  <span v-if="k.year"> · {{ k.year }}</span>
                  <span v-if="k.color"> · {{ k.color }}</span>
                </p>
              </div>
              <span v-if="k.last_odometer" class="tabular text-xs text-slate-500">{{ k.last_odometer.toLocaleString('id-ID') }} km</span>
            </li>
          </ul>
        </div>
      </div>

      <!-- MEMBERSHIP & POIN -->
      <div v-else-if="tab === 'member'" class="grid gap-4 lg:grid-cols-3">
        <div class="card lg:col-span-1">
          <div class="card-header"><h3 class="text-sm font-semibold text-slate-700">Status Membership</h3></div>
          <div v-if="!member" class="space-y-3 p-4">
            <p class="text-sm text-slate-500">Pelanggan ini belum terdaftar sebagai member.</p>
            <BaseButton blok ikon="award" :memuat="proses" @click="jadikanMember">Jadikan Member</BaseButton>
            <p class="text-xs text-slate-400">
              Akun portal dibuat otomatis memakai No. HP pelanggan. Password awal ditampilkan satu kali.
            </p>
          </div>
          <div v-else class="space-y-3 p-4">
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-500">Status</span>
              <BaseBadge :varian="member.status === 'active' ? 'sukses' : 'bahaya'">
                {{ member.status === 'active' ? 'Aktif' : 'Hangus' }}
              </BaseBadge>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-500">No. Member</span><span class="text-sm font-medium">{{ member.member_no }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-500">Mulai</span><span class="text-sm">{{ formatTanggal(member.started_at) }}</span>
            </div>
            <div class="flex items-center justify-between">
              <span class="text-sm text-slate-500">Berakhir</span>
              <span class="text-sm">
                {{ formatTanggal(member.expires_at) }}
                <span class="text-xs text-slate-400">({{ member.sisa_hari }} hari)</span>
              </span>
            </div>
            <div class="rounded-lg bg-brand-50 p-3 text-center">
              <p class="text-xs uppercase tracking-wide text-brand-700">Saldo Poin</p>
              <p class="text-2xl font-bold text-brand-700">{{ member.points_balance }}</p>
            </div>
            <div class="flex flex-col gap-2">
              <BaseButton blok varian="garis" ikon="refresh" :memuat="proses" @click="perpanjang">Perpanjang {{ member.status === 'active' ? 'Masa Berakhir' : 'Membership' }}</BaseButton>
              <BaseButton blok ikon="gift" :nonaktif="!member.points_balance" @click="modalTukar = true">Tukar Poin</BaseButton>
            </div>
          </div>
        </div>

        <div class="card lg:col-span-2">
          <div class="card-header">
            <h3 class="text-sm font-semibold text-slate-700">Riwayat Poin</h3>
            <span class="text-xs text-slate-400">Ledger tidak dapat diubah langsung</span>
          </div>
          <div v-if="!poin.daftar.length" class="p-4">
            <EmptyState ikon="coins" judul="Belum ada mutasi poin" pesan="Poin bertambah setiap servis berbayar." />
          </div>
          <ul v-else class="divide-y divide-slate-100">
            <li v-for="t in poin.daftar" :key="t.id" class="flex items-center justify-between gap-3 px-4 py-2.5">
              <div>
                <p class="text-sm text-slate-700">{{ t.description || t.label }}</p>
                <p class="text-xs text-slate-400">{{ formatTanggal(t.created_at) }} · {{ t.label }}</p>
              </div>
              <div class="text-right">
                <p class="tabular text-sm font-semibold" :class="t.points >= 0 ? 'text-emerald-600' : 'text-brand-600'">
                  {{ t.points >= 0 ? '+' : '' }}{{ t.points }}
                </p>
                <p class="text-xs text-slate-400">saldo {{ t.balance_after }}</p>
              </div>
            </li>
          </ul>
        </div>
      </div>

      <!-- RIWAYAT -->
      <div v-else class="grid gap-4 lg:grid-cols-2">
        <div class="card">
          <div class="card-header"><h3 class="text-sm font-semibold text-slate-700">Riwayat Check Up</h3></div>
          <div v-if="!riwayat.checkups.length" class="p-4">
            <EmptyState ikon="clipboardCheck" judul="Belum ada check up" />
          </div>
          <ul v-else class="divide-y divide-slate-100">
            <li v-for="c in riwayat.checkups" :key="c.id" class="flex items-center justify-between gap-3 px-4 py-2.5">
              <div>
                <p class="text-sm font-medium text-slate-700">{{ c.checkup_no }}</p>
                <p class="text-xs text-slate-400">
                  {{ formatTanggal(c.checkup_date) }} · {{ c.vehicle?.plate_number }}
                </p>
              </div>
              <BaseBadge :varian="c.result === 'continue_service' ? 'brand' : 'netral'" ukuran="sm">
                {{ c.result === 'continue_service' ? 'Lanjut Service' : 'Check Up Saja' }}
              </BaseBadge>
            </li>
          </ul>
        </div>

        <div class="card">
          <div class="card-header"><h3 class="text-sm font-semibold text-slate-700">Riwayat Service (SA)</h3></div>
          <div v-if="!riwayat.service_orders.length" class="p-4">
            <EmptyState ikon="clipboardPen" judul="Belum ada Form SA" />
          </div>
          <ul v-else class="divide-y divide-slate-100">
            <li v-for="sa in riwayat.service_orders" :key="sa.id" class="flex items-center justify-between gap-3 px-4 py-2.5">
              <div>
                <p class="text-sm font-medium text-slate-700">{{ sa.sa_no }}</p>
                <p class="text-xs text-slate-400">
                  {{ formatTanggal(sa.created_at) }} · {{ sa.plate_number }} · {{ sa.mechanic?.name || 'tanpa mekanik' }}
                </p>
              </div>
              <div class="text-right">
                <p class="tabular text-sm">{{ formatRupiah(sa.grand_total) }}</p>
                <BaseBadge :varian="statusSa(sa.status).varian" ukuran="sm">{{ statusSa(sa.status).label }}</BaseBadge>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </template>

    <PelangganFormModal v-model="modalUbah" :pelanggan="pelanggan" @tersimpan="muat" />

    <!-- Kredensial akun portal member (komponen bersama dengan halaman daftar) -->
    <ModalAkunMember v-model="modalPasswordTerbuka" :hasil="modalPassword" />

    <!-- Tukar poin -->
    <BaseModal v-model="modalTukar" judul="Tukar Poin" lebar="sm">
      <div class="space-y-3">
        <p class="text-sm text-slate-600">Saldo poin: <strong>{{ member?.points_balance || 0 }}</strong></p>
        <BaseSelect v-model="rewardDipilih" label="Reward" :opsi="opsiReward" placeholder="— Pilih reward —" :boleh-kosong="false" />
        <p v-if="!opsiReward.length" class="text-xs text-amber-600">Saldo poin belum cukup untuk reward mana pun.</p>
      </div>
      <template #footer>
        <BaseButton varian="garis" @click="modalTukar = false">Batal</BaseButton>
        <BaseButton ikon="gift" :memuat="proses" :nonaktif="!opsiReward.length" @click="tukarPoin">Tukar</BaseButton>
      </template>
    </BaseModal>
  </div>
</template>
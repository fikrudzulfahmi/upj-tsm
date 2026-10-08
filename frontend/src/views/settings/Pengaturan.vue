<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import PrinterThermalPanel from '@/components/PrinterThermalPanel.vue'
import { settingApi } from '@/api'
import { pesanGalat } from '@/api/client'
import { useUiStore } from '@/stores/ui'
import { usePengaturanStore } from '@/stores/pengaturan'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import LoadingBlock from '@/components/ui/LoadingBlock.vue'
import AppIcon from '@/components/ui/AppIcon.vue'
import TemplateCheckupPanel from '@/components/TemplateCheckupPanel.vue'

const ui = useUiStore()
const pengaturan = usePengaturanStore()

const memuat = ref(true)
const menyimpan = ref(false)
const tab = ref('identitas')
const form = reactive({})

const TAB = [
  { kunci: 'identitas', label: 'Identitas Bengkel', ikon: 'store' },
  { kunci: 'membership', label: 'Membership & Poin', ikon: 'award' },
  { kunci: 'operasional', label: 'Operasional', ikon: 'cog' },
  { kunci: 'template', label: 'Template Check Up', ikon: 'listChecks' },
  { kunci: 'printer', label: 'Printer Thermal', ikon: 'bluetooth' },
]

onMounted(async () => {
  try {
    const res = await settingApi.daftar()
    Object.assign(form, res.data || {})
    pengaturan.nilai = { ...pengaturan.nilai, ...(res.data || {}) }
  } finally {
    memuat.value = false
  }
})

async function simpan() {
  menyimpan.value = true
  try {
    const kirim = {
      shop_name: form.shop_name,
      shop_address: form.shop_address,
      shop_phone: form.shop_phone,
      membership_duration_months: Number(form.membership_duration_months) || 6,
      member_discount_percent: Number(form.member_discount_percent) || 0,
      points_per_amount: Number(form.points_per_amount) || 10000,
      member_points_expire_with_membership: !!form.member_points_expire_with_membership,
      checkup_extends_membership: !!form.checkup_extends_membership,
      auto_member_after_first_service: !!form.auto_member_after_first_service,
      record_redeem_cost: !!form.record_redeem_cost,
      fuel_bar_count: Number(form.fuel_bar_count) || 8,
      update_buy_price_on_purchase: !!form.update_buy_price_on_purchase,
      service_reminder_days: Number(form.service_reminder_days) || 90,
    }
    await settingApi.simpan(kirim)
    ui.sukses('Pengaturan disimpan.')
    await pengaturan.muat(true)
  } catch (e) {
    ui.gagal(pesanGalat(e))
  } finally {
    menyimpan.value = false
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-wrap gap-1 border-b border-slate-200">
      <button
        v-for="t in TAB"
        :key="t.kunci"
        class="flex items-center gap-1.5 border-b-2 px-3 py-2 text-sm font-medium"
        :class="tab === t.kunci ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
        @click="tab = t.kunci"
      >
        <AppIcon :nama="t.ikon" :ukuran="15" /> {{ t.label }}
      </button>
    </div>

    <LoadingBlock v-if="memuat" />

    <template v-else-if="tab !== 'template' && tab !== 'printer'">
      <div class="card p-4">
        <!-- IDENTITAS -->
        <div v-if="tab === 'identitas'" class="grid gap-3 sm:grid-cols-2">
          <BaseInput v-model="form.shop_name" label="Nama Bengkel" diperlukan petunjuk="Tampil pada header aplikasi, nota, dan laporan" />
          <BaseInput v-model="form.shop_phone" label="Telepon Bengkel" />
          <div class="sm:col-span-2">
            <BaseTextarea v-model="form.shop_address" label="Alamat Bengkel" :baris="2" petunjuk="Dipakai pada kop nota dan laporan PDF" />
          </div>
        </div>

        <!-- MEMBERSHIP -->
        <div v-else-if="tab === 'membership'" class="grid gap-3 sm:grid-cols-2">
          <BaseInput v-model="form.membership_duration_months" label="Masa Berlaku Member (bulan)" tipe="number" petunjuk="Setiap servis selesai, masa berlaku diperpanjang selama ini" />
          <BaseInput v-model="form.member_discount_percent" label="Diskon Member Global (%)" tipe="number" petunjuk="Hanya berlaku untuk jasa, bukan sparepart" />
          <BaseInput v-model="form.points_per_amount" label="Nominal per 1 Poin (Rp)" tipe="number" petunjuk="mis. 10000 = 1 poin setiap Rp 10.000 biaya jasa" />
          <label class="flex items-center gap-2 pt-6 text-sm text-slate-600">
            <input v-model="form.member_points_expire_with_membership" type="checkbox" />
            Poin ikut hangus saat membership kedaluwarsa
          </label>
          <label class="flex items-center gap-2 text-sm text-slate-600">
            <input v-model="form.checkup_extends_membership" type="checkbox" />
            Check up (gratis) memperpanjang masa membership
          </label>
          <label class="flex items-center gap-2 text-sm text-slate-600">
            <input v-model="form.auto_member_after_first_service" type="checkbox" />
            Otomatis jadi member setelah servis pertama
          </label>
          <label class="flex items-center gap-2 text-sm text-slate-600">
            <input v-model="form.record_redeem_cost" type="checkbox" />
            Catat biaya promosi saat poin ditukar (sebesar HPP part)
          </label>
        </div>

        <!-- OPERASIONAL -->
        <div v-else class="grid gap-3 sm:grid-cols-2">
          <BaseInput v-model="form.fuel_bar_count" label="Jumlah Bar Level Bensin" tipe="number" petunjuk="Default 8 bar pada Form SA" />
          <BaseInput v-model="form.service_reminder_days" label="Interval Pengingat Servis (hari)" tipe="number" petunjuk="Dipakai untuk saran pengingat servis berikutnya" />
          <label class="flex items-center gap-2 text-sm text-slate-600">
            <input v-model="form.update_buy_price_on_purchase" type="checkbox" />
            Perbarui harga beli master dari pembelian terakhir
          </label>
        </div>

        <div class="mt-4 flex justify-end border-t border-slate-100 pt-3">
          <BaseButton ikon="save" :memuat="menyimpan" @click="simpan">Simpan Pengaturan</BaseButton>
        </div>
      </div>
    </template>

    <PrinterThermalPanel v-else-if="tab === 'printer'" />
    <TemplateCheckupPanel v-else />
  </div>
</template>
<script setup>
/**
 * Tombol "Cetak Thermal" yang dipakai bersama oleh Kasir & Form SA.
 *
 * Kalau printer belum tersambung, tombol ini TIDAK mencoba membuka chooser
 * Bluetooth sendiri: `requestDevice` wajib dipanggil dari gestur klik yang segar,
 * jadi pengguna diberi modal dengan tombol "Pilih Printer" (satu klik = satu gestur).
 */
import { ref } from 'vue'
import { useUiStore } from '@/stores/ui'
import { usePengaturanStore } from '@/stores/pengaturan'
import { usePrinterThermal } from '@/composables/usePrinterThermal'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  sa: { type: Object, required: true },
  ikon: { type: String, default: 'bluetooth' },
  teks: { type: String, default: '' },
  ukuran: { type: String, default: 'ikon' },
  varian: { type: String, default: 'garis' },
})

const ui = useUiStore()
const pengaturan = usePengaturanStore()
const printer = usePrinterThermal()

const modal = ref(false)
const mencetak = ref(false)

async function cetak() {
  if (!printer.tersambung.value) {
    modal.value = true
    return
  }

  mencetak.value = true
  try {
    await pengaturan.muat()
    const jumlah = await printer.cetakNota(props.sa, {
      namaBengkel: pengaturan.namaBengkel,
      alamatBengkel: pengaturan.alamatBengkel,
      teleponBengkel: pengaturan.teleponBengkel,
    })
    ui.sukses(`Nota ${props.sa.sa_no} dikirim ke printer thermal (${jumlah} byte).`)
  } catch (e) {
    ui.gagal(e?.message || 'Gagal mencetak ke printer thermal.')
  } finally {
    mencetak.value = false
  }
}

/** Dipanggil langsung dari klik → gestur masih valid untuk requestDevice. */
function pilih() {
  printer.pilihPrinter()
}
</script>

<template>
  <BaseButton
    :ikon="ikon"
    :ukuran="ukuran"
    :varian="varian"
    :memuat="mencetak"
    :title="teks ? undefined : 'Cetak thermal (Bluetooth)'"
    @click="cetak"
  >
    {{ teks }}
  </BaseButton>

  <BaseModal v-model="modal" judul="Printer Thermal Belum Tersambung" lebar="sm">
    <div class="space-y-3 text-sm text-slate-600">
      <p>
        Nota bisa dicetak langsung ke printer thermal Bluetooth. Pilih printer sekali,
        setelah itu tombol cetak bekerja sekali klik tanpa dialog apa pun.
      </p>

      <div v-if="!printer.aman.value" class="rounded-lg bg-amber-50 p-3 text-amber-800">
        Halaman ini tidak diakses lewat HTTPS. Web Bluetooth hanya bekerja pada HTTPS
        (atau localhost).
      </div>

      <div v-else-if="!printer.didukung.value" class="rounded-lg bg-amber-50 p-3 text-amber-800">
        Peramban ini tidak mendukung Web Bluetooth. Gunakan <strong>Chrome/Edge</strong>
        — di Android maupun desktop. Safari/Firefox tidak bisa.
      </div>

      <p v-if="printer.galat.value" class="rounded-lg bg-red-50 p-3 text-brand-700">
        {{ printer.galat.value }}
      </p>
    </div>

    <template #footer>
      <BaseButton varian="garis" @click="modal = false">Tutup</BaseButton>
      <router-link to="/settings">
        <BaseButton varian="garis" ikon="settings2">Pengaturan Printer</BaseButton>
      </router-link>
      <BaseButton ikon="bluetoothCari" :disabled="!printer.didukung.value" @click="pilih">
        Pilih Printer
      </BaseButton>
    </template>
  </BaseModal>
</template>

<script setup>
/**
 * Panel pengaturan printer thermal Bluetooth (Web Bluetooth / ESC/POS).
 * Ditempatkan di halaman Pengaturan sebagai tab "Printer Thermal".
 */
import { ref } from 'vue'
import { useUiStore } from '@/stores/ui'
import { usePengaturanStore } from '@/stores/pengaturan'
import { PROFIL_PRINTER, usePrinterThermal } from '@/composables/usePrinterThermal'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import BaseTextarea from '@/components/ui/BaseTextarea.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const ui = useUiStore()
const pengaturan = usePengaturanStore()
const printer = usePrinterThermal()

const teksUuid = ref((printer.uuidTambahan.value || []).join('\n'))
const mencetakUji = ref(false)
const menyalin = ref(false)

function pilih() {
  printer.pilihPrinter()
}

async function sambungUlang() {
  const berhasil = await printer.sambungkanTersimpan()
  if (berhasil) ui.sukses(`Tersambung ulang ke ${printer.namaPrinter.value}.`)
  else ui.gagal('Tidak bisa menyambung ulang otomatis. Klik "Pilih Printer" untuk memilih lagi.')
}

async function cetakUji() {
  mencetakUji.value = true
  try {
    await pengaturan.muat()
    await printer.cetakUji({ namaBengkel: pengaturan.namaBengkel })
    ui.sukses('Slip uji dikirim ke printer.')
  } catch (e) {
    ui.gagal(e?.message || 'Gagal mencetak slip uji.')
  } finally {
    mencetakUji.value = false
  }
}

function simpanUuid() {
  printer.simpanUuidTambahan(teksUuid.value.split('\n'))
  ui.sukses('Daftar UUID kustom disimpan.')
}

async function salinDiagnostik() {
  const teks = printer.diagnostik.value
    .map((d) => `${d.service} | ${d.karakter} | ${d.sifat}${d.bisaTulis ? ' | BISA DITULIS' : ''}`)
    .join('\n')
  try {
    await navigator.clipboard.writeText(teks)
    ui.sukses('Diagnostik GATT disalin ke papan klip.')
  } catch {
    ui.gagal('Tidak bisa menyalin otomatis; sorot manual lalu salin.')
  }
}
</script>

<template>
  <div class="space-y-4">
    <!-- status -->
    <div class="card p-4">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex items-start gap-3">
          <div
            class="rounded-xl p-2"
            :class="printer.tersambung.value ? 'bg-emerald-50 text-emerald-600' : 'bg-slate-100 text-slate-400'"
          >
            <AppIcon :nama="printer.tersambung.value ? 'bluetoothTersambung' : 'bluetooth'" :ukuran="22" />
          </div>
          <div>
            <p class="text-sm font-semibold text-slate-700">
              {{ printer.tersambung.value ? printer.namaPrinter.value : 'Printer thermal belum tersambung' }}
            </p>
            <p class="text-xs text-slate-500">
              <template v-if="printer.tersambung.value">
                Profil: {{ printer.profilTerpakai.value || 'terdeteksi otomatis' }} · siap mencetak nota 58mm
              </template>
              <template v-else>
                Cetak nota langsung dari aplikasi tanpa dialog cetak peramban
              </template>
            </p>
          </div>
        </div>

        <div class="flex flex-wrap gap-2">
          <BaseButton v-if="!printer.tersambung.value" varian="garis" ikon="refresh" @click="sambungUlang">
            Sambungkan Ulang
          </BaseButton>
          <BaseButton varian="garis" ikon="xCircle" :disabled="!printer.tersambung.value" @click="printer.putuskan()">
            Putuskan
          </BaseButton>
          <BaseButton ikon="bluetoothCari" @click="pilih">
            {{ printer.tersambung.value ? 'Ganti Printer' : 'Pilih Printer' }}
          </BaseButton>
          <BaseButton
            varian="garis"
            ikon="printer"
            :memuat="mencetakUji"
            :disabled="!printer.tersambung.value"
            @click="cetakUji"
          >
            Cetak Uji
          </BaseButton>
        </div>
      </div>

      <p v-if="printer.pesan.value" class="mt-3 text-xs text-slate-500">{{ printer.pesan.value }}</p>
      <p v-if="printer.galat.value" class="mt-3 rounded-lg bg-red-50 p-2 text-xs text-brand-700">
        {{ printer.galat.value }}
      </p>

      <div v-if="!printer.aman.value" class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-800">
        Halaman ini tidak diakses via HTTPS. Web Bluetooth hanya berjalan pada HTTPS atau localhost.
      </div>
      <div v-else-if="!printer.didukung.value" class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-800">
        Peramban ini tidak mendukung Web Bluetooth. Gunakan <strong>Chrome/Edge</strong> (Android atau desktop);
        Safari &amp; Firefox tidak bisa.
      </div>
    </div>

    <!-- syarat & cara pakai -->
    <div class="card p-4">
      <h3 class="mb-2 text-sm font-semibold text-slate-700">Cara pakai (sekali saja)</h3>
      <ol class="ml-4 list-decimal space-y-1 text-sm text-slate-600">
        <li>Nyalakan printer dan aktifkan mode Bluetooth-nya.</li>
        <li>Tekan <strong>Pilih Printer</strong> di atas, lalu pilih <strong>HP-M200</strong> pada daftar yang muncul.</li>
        <li>Tekan <strong>Cetak Uji</strong>. Kalau teks tercetak rapi dan kertas terpotong, printer siap.</li>
      </ol>
      <p class="mt-3 text-xs text-slate-500">
        Syarat: peramban <strong>Chrome/Edge</strong> (Android atau desktop), aplikasi diakses lewat
        <strong>HTTPS</strong>, dan printer mendukung <strong>BLE</strong>. Kertas 58mm = 32 kolom.
      </p>
    </div>

    <!-- diagnostik -->
    <div v-if="printer.diagnostik.value.length" class="card p-4">
      <div class="card-header">
        <h3 class="text-sm font-semibold text-slate-700">Diagnostik GATT</h3>
        <BaseButton varian="garis" ikon="fileText" @click="salinDiagnostik">Salin</BaseButton>
      </div>
      <p class="mb-2 text-xs text-slate-500">
        Kirimkan daftar ini ke pengembang bila nota tidak terbaca — dari sini bisa dilihat
        karakteristik mana yang dipakai printer Anda.
      </p>
      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead>
            <tr class="border-b border-slate-200 text-left text-slate-500">
              <th class="py-1 pr-2">Service</th>
              <th class="py-1 pr-2">Karakteristik</th>
              <th class="py-1 pr-2">Sifat</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="d in printer.diagnostik.value" :key="d.karakter" class="border-b border-slate-100">
              <td class="py-1 pr-2 font-mono text-[11px]">{{ d.service }}</td>
              <td class="py-1 pr-2 font-mono text-[11px]">{{ d.karakter }}</td>
              <td class="py-1 pr-2">
                {{ d.sifat }}
                <BaseBadge v-if="d.bisaTulis" varian="sukses" ukuran="sm">dipakai</BaseBadge>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- lanjutan -->
    <div class="card p-4">
      <h3 class="mb-2 text-sm font-semibold text-slate-700">Lanjutan: UUID service kustom</h3>
      <p class="mb-2 text-xs text-slate-500">
        Dipakai hanya bila printer Anda tidak terdeteksi. Isi satu UUID service per baris
        (biasanya ada di manual printer), lalu tekan Pilih Printer lagi.
      </p>
      <BaseTextarea v-model="teksUuid" :baris="3" placeholder="0000ff00-0000-1000-8000-00805f9b34fb" />
      <div class="mt-2 flex items-center justify-between">
        <p class="text-xs text-slate-400">
          Profil bawaan yang dicoba: {{ PROFIL_PRINTER.length }} buah
        </p>
        <BaseButton ukuran="sm" varian="garis" ikon="save" @click="simpanUuid">Simpan UUID</BaseButton>
      </div>
    </div>
  </div>
</template>

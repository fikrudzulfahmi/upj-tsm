<script setup>
/**
 * Menampilkan kredensial akun portal member setelah pelanggan dijadikan member.
 *
 * Tiga kemungkinan hasil dari backend:
 *  1. password_awal ada           -> akun baru dibuat (password hanya tampil SEKALI)
 *  2. password_awal null + login  -> akun sudah pernah ada (aktivasi ulang member hangus)
 *  3. login null                  -> pelanggan belum punya No. HP, akun portal belum dibuat
 */
import { computed } from 'vue'
import { useUiStore } from '@/stores/ui'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  hasil: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue'])

const ui = useUiStore()

const terbuka = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

const akunBaru = computed(() => !!props.hasil?.password_awal)
const akunLama = computed(() => !props.hasil?.password_awal && !!props.hasil?.login)
const tanpaHp = computed(() => !!props.hasil && !props.hasil.login)

async function salin() {
  const teks = `Login (No. HP): ${props.hasil.login}
Password awal: ${props.hasil.password_awal}`
  try {
    await navigator.clipboard.writeText(teks)
    ui.sukses('Kredensial disalin ke papan klip.')
  } catch {
    ui.gagal('Tidak bisa menyalin otomatis; sorot manual lalu salin.')
  }
}
</script>

<template>
  <BaseModal v-model="terbuka" judul="Akun Portal Member" lebar="sm" :tutup-klik-latar="false">
    <div v-if="hasil" class="space-y-3 text-sm text-slate-600">
      <div v-if="akunBaru">
        <p>
          Berikan kredensial berikut kepada pelanggan. Password hanya ditampilkan
          <strong>satu kali</strong> dan wajib diganti saat pertama masuk.
        </p>
        <div class="mt-3 rounded-lg border border-brand-200 bg-brand-50 p-3">
          <p><span class="text-slate-500">Login (No. HP):</span> <strong>{{ hasil.login }}</strong></p>
          <p>
            <span class="text-slate-500">Password awal:</span>
            <strong class="text-lg tracking-wider">{{ hasil.password_awal }}</strong>
          </p>
        </div>
      </div>

      <div v-else-if="akunLama" class="flex items-start gap-2 rounded-lg bg-slate-50 p-3">
        <AppIcon nama="info" :ukuran="16" class="mt-0.5 text-slate-400" />
        <p>
          Member sudah diaktifkan kembali. Akun portal pelanggan <strong>sudah pernah dibuat</strong>,
          jadi password lamanya tetap berlaku — pelanggan bisa menggantinya di menu
          <strong>Akun</strong> pada portal member.
        </p>
      </div>

      <div v-else-if="tanpaHp" class="flex items-start gap-2 rounded-lg bg-amber-50 p-3 text-amber-700">
        <AppIcon nama="alert" :ukuran="16" class="mt-0.5" />
        <p>
          Pelanggan belum punya No. HP sehingga akun portal belum dibuat. Lengkapi No. HP
          pelanggan, lalu aktifkan membership sekali lagi.
        </p>
      </div>
    </div>

    <template #footer>
      <BaseButton v-if="akunBaru" varian="garis" ikon="fileText" @click="salin">Salin Kredensial</BaseButton>
      <BaseButton @click="terbuka = false">Sudah Dicatat</BaseButton>
    </template>
  </BaseModal>
</template>

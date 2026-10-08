<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { authApi } from '@/api'
import { pesanGalat } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import AuthLayout from '@/layouts/AuthLayout.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const auth = useAuthStore()
const ui = useUiStore()
const router = useRouter()

const form = ref({ password_lama: '', password: '', password_confirmation: '' })
const memuat = ref(false)
const galat = ref('')

async function simpan() {
  galat.value = ''
  if (form.value.password.length < 8) {
    galat.value = 'Password baru minimal 8 karakter.'
    return
  }
  if (form.value.password !== form.value.password_confirmation) {
    galat.value = 'Konfirmasi password tidak sama.'
    return
  }

  memuat.value = true
  try {
    await authApi.gantiPassword({ ...form.value })
    auth.user = { ...auth.user, must_change_password: false }
    ui.sukses('Password berhasil diperbarui.')
    router.replace({ name: auth.isMember ? 'portal' : 'dashboard' })
  } catch (e) {
    galat.value = pesanGalat(e)
  } finally {
    memuat.value = false
  }
}

async function keluar() {
  await auth.keluar()
  router.replace({ name: 'login' })
}
</script>

<template>
  <AuthLayout>
    <div class="mb-6">
      <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1 text-xs font-medium text-brand-700">
        <AppIcon nama="shield" :ukuran="14" /> Wajib ganti password
      </div>
      <h2 class="text-xl font-bold text-slate-800">Ganti password</h2>
      <p class="mt-1 text-sm text-slate-500">
        Demi keamanan, password awal harus diganti sebelum memakai aplikasi.
      </p>
    </div>

    <form class="space-y-4" @submit.prevent="simpan">
      <BaseInput v-if="!auth.wajibGantiPassword" v-model="form.password_lama" label="Password lama" tipe="password" ikon="key" />
      <BaseInput v-model="form.password" label="Password baru" tipe="password" ikon="key" petunjuk="Minimal 8 karakter" />
      <BaseInput v-model="form.password_confirmation" label="Ulangi password baru" tipe="password" ikon="key" />

      <div v-if="galat" class="rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-700">
        {{ galat }}
      </div>

      <BaseButton blok ukuran="lg" :memuat="memuat" ikon="save" @click="simpan">Simpan Password</BaseButton>
      <BaseButton blok varian="garis" @click="keluar">Keluar</BaseButton>
    </form>
  </AuthLayout>
</template>

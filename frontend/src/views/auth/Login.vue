<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { pesanGalat } from '@/api/client'
import { jalurAman } from '@/utils/jalurAman'
import AuthLayout from '@/layouts/AuthLayout.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const form = ref({ login: '', password: '' })
const memuat = ref(false)
const galat = ref('')
const lihatPassword = ref(false)
const tujuan = jalurAman(route.query.dari, '')

async function masuk() {
  galat.value = ''
  if (!form.value.login || !form.value.password) {
    galat.value = 'Email/No. HP dan password wajib diisi.'
    return
  }

  memuat.value = true
  try {
    await auth.masuk(form.value.login, form.value.password)

    if (auth.wajibGantiPassword) {
      router.replace({ name: 'ganti-password' })
      return
    }

    if (tujuan) {
      router.replace(tujuan)
      return
    }

    router.replace({ name: auth.isMember ? 'portal' : 'dashboard' })
  } catch (e) {
    galat.value = pesanGalat(e)
  } finally {
    memuat.value = false
  }
}
</script>

<template>
  <AuthLayout>
    <div class="mb-6">
      <h2 class="text-xl font-bold text-slate-800">Masuk ke aplikasi</h2>
      <p class="mt-1 text-sm text-slate-500">Gunakan email atau nomor HP terdaftar.</p>
    </div>

    <form class="space-y-4" @submit.prevent="masuk">
      <BaseInput
        v-model="form.login"
        label="Email atau No. HP"
        ikon="user"
        placeholder="owner@bengkel.test / 08xxxxxxxxxx"
        autofokus
      />

      <div class="relative">
        <BaseInput
          v-model="form.password"
          label="Password"
          ikon="key"
          :tipe="lihatPassword ? 'text' : 'password'"
          placeholder="••••••••"
        />
        <button
          type="button"
          class="absolute right-3 top-[34px] text-slate-400 hover:text-brand-600"
          @click="lihatPassword = !lihatPassword"
        >
          <AppIcon nama="eye" :ukuran="16" />
        </button>
      </div>

      <div v-if="galat" class="flex items-start gap-2 rounded-lg border border-brand-200 bg-brand-50 px-3 py-2 text-sm text-brand-700">
        <AppIcon nama="warning" :ukuran="16" class="mt-0.5 shrink-0" />
        <span>{{ galat }}</span>
      </div>

      <BaseButton blok ukuran="lg" :memuat="memuat" ikon="logout" @click="masuk">Masuk</BaseButton>
    </form>

    <p v-if="$route.query.dari" class="mt-4 text-xs text-slate-400">
      Anda diarahkan ke halaman ini karena perlu masuk terlebih dahulu.
    </p>

    <div v-if="$route.meta" class="mt-6 rounded-lg bg-slate-50 p-3 text-xs text-slate-500">
      <p class="font-medium text-slate-600">Akun demo (data contoh lokal):</p>
      <p>Admin: owner@bengkel.test / owner12345</p>
      <p>Member: 081211110001 (password tampil saat seeder dijalankan)</p>
    </div>
  </AuthLayout>
</template>

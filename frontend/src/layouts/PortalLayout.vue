<script setup>
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { usePengaturanStore } from '@/stores/pengaturan'
import AppIcon from '@/components/ui/AppIcon.vue'
import { menuAktif } from '@/utils/menuAktif'
import { onMounted } from 'vue'

const auth = useAuthStore()
const pengaturan = usePengaturanStore()
const route = useRoute()
const router = useRouter()

onMounted(() => pengaturan.muat())

// `nama` = nama rute; halaman detail riwayat tetap menyalakan menu "Riwayat".
const MENU = [
  { label: 'Beranda', to: '/portal', ikon: 'home', nama: 'portal' },
  { label: 'Riwayat', to: '/portal/riwayat', ikon: 'history', nama: ['portal-riwayat', 'portal-detail'] },
  { label: 'Kendaraan', to: '/portal/kendaraan', ikon: 'bike', nama: 'portal-kendaraan' },
  { label: 'Akun', to: '/portal/akun', ikon: 'user', nama: 'portal-akun' },
]

async function keluar() {
  await auth.keluar()
  router.replace({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 pb-20">
    <header class="sticky top-0 z-20 flex items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3">
      <div class="flex items-center gap-2.5">
        <div class="rounded-lg bg-brand-600 p-1.5 text-white">
          <AppIcon nama="wrench" :ukuran="18" />
        </div>
        <div>
          <p class="text-sm font-bold text-slate-800">{{ pengaturan.namaBengkel }}</p>
          <p class="text-[11px] text-slate-400">Portal Member</p>
        </div>
      </div>
      <button class="rounded-md p-2 text-slate-400 hover:bg-brand-50 hover:text-brand-600" title="Keluar" @click="keluar">
        <AppIcon nama="logout" :ukuran="18" />
      </button>
    </header>

    <main class="mx-auto w-full max-w-2xl px-4 py-4">
      <RouterView />
    </main>

    <nav class="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white" aria-label="Navigasi portal">
      <div class="mx-auto flex max-w-2xl">
        <RouterLink
          v-for="m in MENU"
          :key="m.to"
          :to="m.to"
          class="flex flex-1 flex-col items-center gap-0.5 py-2.5 text-[11px] font-medium transition"
          :class="menuAktif(m, route) ? 'text-brand-600' : 'text-slate-500'"
        >
          <AppIcon :nama="m.ikon" :ukuran="19" />
          {{ m.label }}
        </RouterLink>
      </div>
    </nav>
  </div>
</template>
<script setup>
import { computed, onMounted } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { MENU } from '@/config/menu'
import { menuAktif } from '@/utils/menuAktif'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { usePengaturanStore } from '@/stores/pengaturan'
import AppIcon from '@/components/ui/AppIcon.vue'
import BaseButton from '@/components/ui/BaseButton.vue'

const auth = useAuthStore()
const ui = useUiStore()
const pengaturan = usePengaturanStore()
const route = useRoute()
const router = useRouter()

onMounted(() => {
  pengaturan.muat()
})

const menuTampil = computed(() =>
  MENU.map((g) => ({ ...g, item: g.item.filter((m) => auth.bisa(m.izin)) })).filter((g) => g.item.length),
)

const judulHalaman = computed(() => route.meta?.judul || 'Dashboard')

/** Aktif hanya bila NAMA RUTE cocok (lihat utils/menuAktif.js) — bukan awalan path,
 *  supaya menu tidak menyala bersamaan. */
function aktif(m) {
  return menuAktif(m, route)
}

async function keluar() {
  const ya = await ui.tanya({
    judul: 'Keluar dari aplikasi',
    pesan: 'Anda akan keluar dari sesi ini. Lanjutkan?',
    teksOk: 'Keluar',
  })
  if (!ya) return
  await auth.keluar()
  router.replace({ name: 'login' })
}
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <!-- Sidebar -->
    <aside
      class="no-print fixed inset-y-0 left-0 z-40 w-64 -translate-x-full border-r border-slate-200 bg-white transition-transform lg:translate-x-0"
      :class="ui.sidebarTerbuka ? 'translate-x-0' : ''"
    >
      <div class="flex h-16 items-center gap-2.5 border-b border-slate-100 px-4">
        <div class="rounded-lg bg-brand-600 p-1.5 text-white">
          <AppIcon nama="wrench" :ukuran="18" />
        </div>
        <div class="min-w-0">
          <p class="truncate text-sm font-bold text-slate-800">{{ pengaturan.namaBengkel }}</p>
          <p class="text-[11px] uppercase tracking-wide text-slate-400">Manajemen Service</p>
        </div>
      </div>

      <nav class="h-[calc(100vh-8rem)] overflow-y-auto px-3 py-3">
        <div v-for="(g, gi) in menuTampil" :key="gi" class="mb-3">
          <p v-if="g.grup" class="px-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-400">
            {{ g.grup }}
          </p>
          <RouterLink
            v-for="m in g.item"
            :key="m.to"
            :to="m.to"
            class="mb-0.5 flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition"
            :class="aktif(m) ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-50'"
            @click="ui.tutupSidebar()"
          >
            <AppIcon :nama="m.ikon" :ukuran="17" :class="aktif(m) ? 'text-brand-600' : 'text-slate-400'" />
            {{ m.label }}
          </RouterLink>
        </div>
      </nav>

      <div class="absolute inset-x-0 bottom-0 border-t border-slate-100 p-3">
        <div class="flex items-center gap-2">
          <div class="flex h-9 w-9 items-center justify-center rounded-full bg-brand-600 text-xs font-bold text-white">
            {{ (auth.nama || '?').slice(0, 2).toUpperCase() }}
          </div>
          <div class="min-w-0 flex-1">
            <p class="truncate text-xs font-semibold text-slate-700">{{ auth.nama }}</p>
            <p class="truncate text-[11px] capitalize text-slate-400">{{ auth.peranUtama }}</p>
          </div>
          <button class="rounded-md p-1.5 text-slate-400 hover:bg-brand-50 hover:text-brand-600" title="Keluar" @click="keluar">
            <AppIcon nama="logout" :ukuran="16" />
          </button>
        </div>
      </div>
    </aside>

    <div
      v-if="ui.sidebarTerbuka"
      class="fixed inset-0 z-30 bg-slate-900/40 lg:hidden"
      @click="ui.tutupSidebar()"
    />

    <!-- Konten -->
    <div class="lg:pl-64">
      <header
        class="no-print sticky top-0 z-20 flex h-16 items-center justify-between gap-3 border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-6"
      >
        <div class="flex min-w-0 items-center gap-3">
          <button class="rounded-md p-2 text-slate-500 hover:bg-slate-100 lg:hidden" @click="ui.bukaSidebar()">
            <AppIcon nama="menu" :ukuran="20" />
          </button>
          <div class="min-w-0">
            <h1 class="truncate text-base font-semibold text-slate-800">{{ judulHalaman }}</h1>
            <p class="truncate text-xs text-slate-400">{{ route.meta?.keterangan || 'Aplikasi Manajemen Service Bengkel' }}</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <RouterLink v-if="auth.bisa('checkup.manage')" to="/checkups/create">
            <BaseButton ukuran="sm" ikon="plus">Check Up</BaseButton>
          </RouterLink>
          <RouterLink v-if="auth.bisa('service-order.manage')" to="/service-orders/create">
            <BaseButton ukuran="sm" varian="garis" ikon="clipboardPen">Form SA</BaseButton>
          </RouterLink>
          <RouterLink v-if="auth.bisa('kasir.manage')" to="/kasir">
            <BaseButton ukuran="sm" varian="garis" ikon="banknote">Kasir</BaseButton>
          </RouterLink>
        </div>
      </header>

      <main class="px-4 py-5 sm:px-6 sm:py-6">
        <RouterView />
      </main>
    </div>
  </div>
</template>
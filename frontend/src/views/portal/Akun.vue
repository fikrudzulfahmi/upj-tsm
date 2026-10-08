<script setup>
import { onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { portalApi } from '@/api'
import { useAuthStore } from '@/stores/auth'
import { useUiStore } from '@/stores/ui'
import { formatTanggal } from '@/utils/format'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseBadge from '@/components/ui/BaseBadge.vue'
import LoadingBlock from '@/components/ui/LoadingBlock.vue'
import AppIcon from '@/components/ui/AppIcon.vue'

const auth = useAuthStore()
const ui = useUiStore()
const router = useRouter()
const profil = ref(null)
const memuat = ref(true)

onMounted(async () => {
  try {
    const res = await portalApi.profil()
    profil.value = res.data
  } finally {
    memuat.value = false
  }
})

async function keluar() {
  const ya = await ui.tanya({ judul: 'Keluar', pesan: 'Keluar dari portal member?', teksOk: 'Keluar' })
  if (!ya) return
  await auth.keluar()
  router.replace({ name: 'login' })
}
</script>

<template>
  <div class="space-y-4">
    <LoadingBlock v-if="memuat" />

    <template v-else-if="profil">
      <div class="card p-4">
        <div class="flex items-center gap-3">
          <div class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white">
            {{ profil.customer.name.slice(0, 2).toUpperCase() }}
          </div>
          <div>
            <p class="text-base font-semibold text-slate-800">{{ profil.customer.name }}</p>
            <p class="text-xs text-slate-500">{{ profil.customer.code }} · {{ profil.customer.phone }}</p>
          </div>
        </div>

        <dl class="mt-4 divide-y divide-slate-100 text-sm">
          <div class="flex justify-between py-2.5"><dt class="text-slate-500">Status member</dt>
            <dd>
              <BaseBadge :varian="profil.membership?.aktif ? 'sukses' : 'bahaya'" ukuran="sm">
                {{ profil.membership?.aktif ? 'Aktif' : 'Hangus' }}
              </BaseBadge>
            </dd>
          </div>
          <div class="flex justify-between py-2.5"><dt class="text-slate-500">No. Member</dt><dd>{{ profil.membership?.member_no || '-' }}</dd></div>
          <div class="flex justify-between py-2.5"><dt class="text-slate-500">Berlaku sampai</dt>
            <dd>{{ profil.membership ? formatTanggal(profil.membership.expires_at) : '-' }}</dd>
          </div>
          <div class="flex justify-between py-2.5"><dt class="text-slate-500">Saldo poin</dt><dd>{{ profil.membership?.points_balance ?? 0 }} poin</dd></div>
          <div class="flex justify-between py-2.5"><dt class="text-slate-500">Alamat</dt><dd class="text-right">{{ profil.customer.address || '-' }}</dd></div>
        </dl>
      </div>

      <div class="space-y-2">
        <RouterLink to="/ganti-password" class="block">
          <BaseButton blok varian="garis" ikon="key">Ganti Password</BaseButton>
        </RouterLink>
        <BaseButton blok varian="bahaya" ikon="logout" @click="keluar">Keluar</BaseButton>
      </div>

      <p class="flex items-center justify-center gap-1 text-[11px] text-slate-400">
        <AppIcon nama="info" :ukuran="12" />
        Perubahan data pribadi dapat dilakukan di bengkel.
      </p>
    </template>
  </div>
</template>

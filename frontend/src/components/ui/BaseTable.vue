<script setup>
import AppIcon from './AppIcon.vue'

const props = defineProps({
  /** [{ kunci, label, kelas, align }] */
  kolom: { type: Array, required: true },
  baris: { type: Array, default: () => [] },
  muat: { type: Boolean, default: false },
  kosongTeks: { type: String, default: 'Belum ada data.' },
  kunciBaris: { type: String, default: 'id' },
  klikBaris: { type: Boolean, default: false },
})
const emit = defineEmits(['pilihBaris'])
</script>

<template>
  <div class="overflow-x-auto">
    <table class="table-app">
      <thead>
        <tr>
          <th v-for="k in kolom" :key="k.kunci" :class="[k.kelas, k.align === 'kanan' ? 'text-right' : '']">
            {{ k.label }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="muat">
          <td :colspan="kolom.length" class="py-10 text-center text-slate-400">
            <AppIcon nama="loader" :ukuran="22" class="mx-auto animate-spin" />
            <p class="mt-2 text-xs">Memuat data…</p>
          </td>
        </tr>
        <tr v-else-if="!baris.length">
          <td :colspan="kolom.length" class="py-10 text-center text-slate-400">
            <slot name="kosong">
              <AppIcon nama="inbox" :ukuran="26" class="mx-auto text-slate-300" />
              <p class="mt-2 text-sm">{{ kosongTeks }}</p>
            </slot>
          </td>
        </tr>
        <tr
          v-for="(b, i) in baris"
          v-else
          :key="b[kunciBaris] ?? i"
          :class="klikBaris ? 'cursor-pointer' : ''"
          @click="klikBaris && emit('pilihBaris', b)"
        >
          <td v-for="k in kolom" :key="k.kunci" :class="[k.kelas, k.align === 'kanan' ? 'text-right' : '']">
            <slot :name="`sel-${k.kunci}`" :baris="b" :nilai="b[k.kunci]" :index="i">{{ b[k.kunci] }}</slot>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

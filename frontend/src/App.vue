<script setup>
import { RouterView } from 'vue-router'
import AppToast from '@/components/ui/AppToast.vue'
import AppConfirm from '@/components/ui/AppConfirm.vue'
</script>

<template>
  <RouterView />
  <AppToast />
  <AppConfirm />
</template>

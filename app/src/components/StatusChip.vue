<template>
  <q-chip dense :color="statusColor(status)" text-color="white">
    {{ statusLabel(status) }}
  </q-chip>
</template>

<script setup>
import { statusColor, statusLabel } from '@/utils/format'

defineProps({
  status: {
    type: String,
    default: '',
  },
})
</script>

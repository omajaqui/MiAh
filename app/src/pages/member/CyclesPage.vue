<template>
  <q-page class="q-pa-md">
    <div class="text-h5 text-weight-bold q-mb-md">Mis ciclos</div>
    <q-table
      flat
      bordered
      :rows="rows"
      :columns="columns"
      row-key="id"
      :loading="loading"
      hide-pagination
      :rows-per-page-options="[0]"
    >
      <template #body-cell-status="props">
        <q-td :props="props"><StatusChip :status="props.row.status" /></q-td>
      </template>
      <template #body-cell-weekly_quota="props">
        <q-td :props="props">{{ formatMoney(props.row.weekly_quota) }}</q-td>
      </template>
      <template #body-cell-expected_gross_amount="props">
        <q-td :props="props">{{ formatMoney(props.row.expected_gross_amount) }}</q-td>
      </template>
      <template #body-cell-actions="props">
        <q-td :props="props">
          <q-btn flat dense color="primary" icon="visibility" :to="`/socio/ciclos/${props.row.id}`" />
        </q-td>
      </template>
    </q-table>
  </q-page>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { Notify } from 'quasar'
import StatusChip from '@/components/StatusChip.vue'
import { getMemberCycles } from '@/services/member'
import { errorMessage, formatDate, formatMoney } from '@/utils/format'

const loading = ref(false)
const rows = ref([])
const columns = [
  { name: 'name', label: 'Ciclo', field: 'name', align: 'left' },
  { name: 'status', label: 'Estado', field: 'status', align: 'left' },
  { name: 'start_date', label: 'Inicio', field: (row) => formatDate(row.start_date), align: 'left' },
  { name: 'weekly_quota', label: 'Cuota', field: 'weekly_quota', align: 'left' },
  { name: 'delivery_position', label: 'Posición', field: 'delivery_position', align: 'left' },
  { name: 'expected_disbursement_date', label: 'Su entrega', field: (row) => formatDate(row.expected_disbursement_date), align: 'left' },
  { name: 'expected_gross_amount', label: 'Monto bruto', field: 'expected_gross_amount', align: 'left' },
  { name: 'actions', label: '', field: 'actions', align: 'right' },
]

onMounted(async () => {
  loading.value = true
  try {
    rows.value = await getMemberCycles()
  } catch (error) {
    Notify.create({ type: 'negative', message: errorMessage(error) })
  } finally {
    loading.value = false
  }
})
</script>

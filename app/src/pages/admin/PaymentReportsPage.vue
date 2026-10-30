<template>
  <q-page class="q-pa-md">
    <div class="text-h5 text-weight-bold q-mb-md">Pagos reportados</div>

    <div class="row q-col-gutter-md q-mb-md">
      <div class="col-12 col-md-6">
        <q-input v-model="filters.search" outlined dense debounce="400" placeholder="Buscar por socio" @update:model-value="load(1)">
          <template #prepend>
            <q-icon name="search" />
          </template>
        </q-input>
      </div>
      <div class="col-12 col-md-3">
        <q-select v-model="filters.status" outlined dense emit-value map-options :options="statusOptions" label="Estado" @update:model-value="load(1)" />
      </div>
    </div>

    <q-table
      flat
      bordered
      :rows="rows"
      :columns="columns"
      row-key="id"
      :loading="loading"
      v-model:pagination="pagination"
      binary-state-sort
      @request="onRequest"
    >
      <template #body-cell-status="props">
        <q-td :props="props"><StatusChip :status="props.row.status" /></q-td>
      </template>
      <template #body-cell-amount="props">
        <q-td :props="props">{{ formatMoney(props.row.amount, props.row.cycle?.currency) }}</q-td>
      </template>
      <template #body-cell-actions="props">
        <q-td :props="props">
          <q-btn
            unelevated
            dense
            color="primary"
            :label="props.row.status === 'pendiente_validar' ? 'Revisar' : 'Ver'"
            @click="openReport(props.row)"
          />
        </q-td>
      </template>
    </q-table>

    <PaymentReportDialog v-model="dialog" :report="currentReport" can-review @reviewed="load()" />
  </q-page>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { Notify } from 'quasar'
import PaymentReportDialog from '@/components/PaymentReportDialog.vue'
import StatusChip from '@/components/StatusChip.vue'
import { listPaymentReports } from '@/services/paymentReports'
import { errorMessage, formatDate, formatMoney } from '@/utils/format'

const loading = ref(false)
const rows = ref([])
const dialog = ref(false)
const currentReport = ref(null)
const pagination = ref({ page: 1, rowsPerPage: 10, rowsNumber: 0 })
const filters = reactive({ search: '', status: 'pendiente_validar' })
const statusOptions = [
  { label: 'Pendiente de validar', value: 'pendiente_validar' },
  { label: 'Pago correcto', value: 'pago_correcto' },
  { label: 'Pago rechazado', value: 'pago_rechazado' },
  { label: 'Todos', value: null },
]
const columns = [
  { name: 'created_at', label: 'Fecha', field: (row) => formatDate(row.created_at), align: 'left' },
  { name: 'person', label: 'Socio', field: (row) => row.person?.full_name, align: 'left' },
  { name: 'cycle', label: 'Ciclo', field: (row) => row.cycle?.name, align: 'left' },
  { name: 'amount', label: 'Monto', field: 'amount', align: 'left' },
  { name: 'status', label: 'Estado', field: 'status', align: 'left' },
  { name: 'actions', label: '', field: 'actions', align: 'right' },
]

async function load(page = pagination.value.page) {
  loading.value = true
  try {
    const result = await listPaymentReports({
      search: filters.search || undefined,
      status: filters.status || undefined,
      page,
      per_page: pagination.value.rowsPerPage,
    })
    rows.value = result.items
    pagination.value.page = result.meta.current_page || page
    pagination.value.rowsNumber = result.meta.total || result.items.length
  } catch (error) {
    Notify.create({ type: 'negative', message: errorMessage(error) })
  } finally {
    loading.value = false
  }
}

function onRequest(props) {
  pagination.value.page = props.pagination.page
  pagination.value.rowsPerPage = props.pagination.rowsPerPage
  load(props.pagination.page)
}

function openReport(report) {
  currentReport.value = report
  dialog.value = true
}

onMounted(() => load(1))
</script>

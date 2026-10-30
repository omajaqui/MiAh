<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-mb-md">
      <div class="text-h5 text-weight-bold col">Ciclos de ahorro</div>
      <q-btn unelevated color="primary" icon="add" label="Crear ciclo" @click="openForm()" />
    </div>

    <div class="row q-col-gutter-md q-mb-md">
      <div class="col-12 col-md-6">
        <q-input v-model="filters.search" outlined dense debounce="400" placeholder="Buscar ciclo" @update:model-value="load(1)" />
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
      @request="onRequest"
    >
      <template #body-cell-status="props">
        <q-td :props="props"><StatusChip :status="props.row.status" /></q-td>
      </template>
      <template #body-cell-weekly_quota="props">
        <q-td :props="props">{{ formatMoney(props.row.weekly_quota, props.row.currency) }}</q-td>
      </template>
      <template #body-cell-actions="props">
        <q-td :props="props">
          <q-btn flat dense color="primary" icon="visibility" :to="`/admin/ciclos/${props.row.id}`" />
        </q-td>
      </template>
    </q-table>

    <q-dialog v-model="dialog">
      <q-card class="cycle-form-card">
        <q-form class="cycle-form" @submit.prevent="save">
          <q-card-section class="q-pb-sm">
            <div class="text-h6">Crear ciclo</div>
          </q-card-section>

          <q-card-section class="q-pt-sm">
            <div class="cycle-form-grid">
              <q-input v-model="form.name" class="span-2" outlined dense label="Nombre" :rules="[required]" />
              <q-input v-model="form.description" class="span-2" outlined dense type="textarea" autogrow label="Descripción (opcional)" />
              <q-input v-model="form.start_date" outlined dense type="date" label="Fecha de inicio" :rules="[required]" />
              <q-select v-model="form.contribution_weekday" outlined dense emit-value map-options :options="weekdays" label="Día de aporte" />
              <q-input v-model="form.first_contribution_date" outlined dense type="date" label="Fecha del primer aporte" :rules="[required]" />
              <q-input v-model="form.first_disbursement_date" outlined dense type="date" label="Fecha de la primera entrega" :rules="[required]" />
              <q-input
                v-model="quotaDisplay"
                outlined
                dense
                input-class="text-right"
                label="Cuota semanal"
                placeholder="$ 20.000"
                :rules="[required, validQuota]"
                @focus="onQuotaFocus"
                @blur="onQuotaBlur"
              />
              <q-input model-value="COP" outlined dense label="Moneda" readonly />
              <q-input v-model="form.observations" class="span-2" outlined dense type="textarea" autogrow label="Observaciones" />
            </div>
          </q-card-section>

          <q-card-actions align="right" class="q-px-md q-pb-md">
            <q-btn flat label="Cancelar" v-close-popup />
            <q-btn unelevated color="primary" :loading="saving" label="Crear" type="submit" />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { Notify } from 'quasar'
import { useRouter } from 'vue-router'
import StatusChip from '@/components/StatusChip.vue'
import { createCycle, listCycles } from '@/services/cycles'
import { errorMessage, formatDate, formatMoney, formatMoneyInput, parseMoneyInput } from '@/utils/format'

const router = useRouter()
const loading = ref(false)
const saving = ref(false)
const dialog = ref(false)
const quotaDisplay = ref('')
const rows = ref([])
const pagination = ref({ page: 1, rowsPerPage: 10, rowsNumber: 0 })
const filters = reactive({ search: '', status: null })
const form = reactive(emptyForm())
const required = (val) => !!val || 'Campo obligatorio'
const validQuota = (val) => {
  const amount = parseMoneyInput(val)
  return (amount !== null && amount > 0) || 'Ingrese una cuota semanal válida'
}
const statusOptions = [
  { label: 'Todos', value: null },
  { label: 'Abierto', value: 'abierto' },
  { label: 'En proceso', value: 'en_proceso' },
  { label: 'Cerrado', value: 'cerrado' },
  { label: 'Finalizado', value: 'finalizado' },
]
const weekdays = [
  { label: 'Lunes', value: 1 },
  { label: 'Martes', value: 2 },
  { label: 'Miércoles', value: 3 },
  { label: 'Jueves', value: 4 },
  { label: 'Viernes', value: 5 },
  { label: 'Sábado', value: 6 },
  { label: 'Domingo', value: 7 },
]
const columns = [
  { name: 'name', label: 'Ciclo', field: 'name', align: 'left' },
  { name: 'status', label: 'Estado', field: 'status', align: 'left' },
  { name: 'start_date', label: 'Inicio', field: (row) => formatDate(row.start_date), align: 'left' },
  { name: 'weekly_quota', label: 'Cuota', field: 'weekly_quota', align: 'left' },
  { name: 'members_count', label: 'Socios', field: 'members_count', align: 'left' },
  { name: 'actions', label: '', field: 'actions', align: 'right' },
]

function emptyForm() {
  return {
    name: '',
    description: '',
    start_date: '',
    contribution_weekday: 1,
    first_contribution_date: '',
    first_disbursement_date: '',
    weekly_quota: 50000,
    currency: 'COP',
    observations: '',
  }
}

function onQuotaFocus() {
  const amount = parseMoneyInput(quotaDisplay.value)
  quotaDisplay.value = amount ? String(amount) : ''
}

function onQuotaBlur() {
  const amount = parseMoneyInput(quotaDisplay.value)
  form.weekly_quota = amount
  quotaDisplay.value = amount ? formatMoneyInput(amount) : ''
}

async function load(page = pagination.value.page) {
  loading.value = true
  try {
    const result = await listCycles({
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

function openForm() {
  Object.assign(form, emptyForm())
  quotaDisplay.value = formatMoneyInput(form.weekly_quota)
  dialog.value = true
}

async function save() {
  const amount = parseMoneyInput(quotaDisplay.value)

  if (!amount || amount <= 0) {
    Notify.create({ type: 'negative', message: 'Ingrese una cuota semanal válida.' })
    return
  }

  saving.value = true
  try {
    const cycle = await createCycle({
      ...form,
      weekly_quota: amount,
    })
    Notify.create({ type: 'positive', message: 'Ciclo creado.' })
    dialog.value = false
    await router.push(`/admin/ciclos/${cycle.id}`)
  } catch (error) {
    Notify.create({ type: 'negative', message: errorMessage(error) })
  } finally {
    saving.value = false
  }
}

onMounted(() => load(1))
</script>

<style scoped>
.cycle-form-card {
  width: min(640px, 96vw);
}

.cycle-form-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
}

.cycle-form-grid :deep(.q-field) {
  width: 100%;
}

@media (min-width: 600px) {
  .cycle-form-grid {
    grid-template-columns: 1fr 1fr;
  }

  .cycle-form-grid .span-2 {
    grid-column: 1 / -1;
  }
}
</style>

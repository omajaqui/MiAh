<template>
  <q-page class="q-pa-md" v-if="cycle">
    <div class="row items-center q-mb-md q-col-gutter-sm">
      <div class="col">
        <q-btn flat color="primary" icon="arrow_back" label="Volver" to="/admin/ciclos" class="q-ml-n-sm" />
        <div class="text-h5 text-weight-bold">{{ cycle.name }}</div>
        <div class="row items-center q-gutter-sm">
          <StatusChip :status="cycle.status" />
          <span class="text-grey-7">{{ cycle.members_count }} socios · cuota {{ formatMoney(cycle.weekly_quota, cycle.currency) }}</span>
        </div>
      </div>
      <div class="col-auto row q-gutter-sm">
        <q-btn v-if="cycle.status === 'abierto'" unelevated color="primary" label="Iniciar ciclo" @click="confirmStart" />
        <q-btn v-if="['abierto', 'en_proceso'].includes(cycle.status)" outline color="orange-9" label="Cerrar" @click="confirmClose" />
        <q-btn v-if="cycle.status === 'en_proceso'" unelevated color="secondary" label="Finalizar" @click="confirmFinalize" />
      </div>
    </div>

    <q-tabs v-model="tab" dense class="text-grey-8" active-color="primary" indicator-color="primary" align="left" narrow-indicator>
      <q-tab name="info" label="Configuración" />
      <q-tab name="members" label="Socios" />
      <q-tab name="calendar" label="Calendario" />
      <q-tab name="contributions" label="Aportes" />
      <q-tab name="reports" label="Pagos reportados" />
      <q-tab name="disbursements" label="Entregas" />
      <q-tab name="summary" label="Resumen" />
    </q-tabs>

    <q-separator class="q-mb-md" />

    <q-tab-panels v-model="tab" animated>
      <q-tab-panel name="info" class="q-pa-none">
        <q-card flat bordered>
          <q-card-section>
            <q-form class="q-gutter-md" @submit.prevent="saveConfig">
              <div class="row q-col-gutter-md">
                <div class="col-12 col-md-6"><q-input v-model="form.name" outlined dense label="Nombre" :disable="!canEdit" /></div>
                <div class="col-12 col-md-6">
                  <q-input
                    v-model="quotaDisplay"
                    outlined
                    dense
                    input-class="text-right"
                    label="Cuota semanal"
                    placeholder="$ 20.000"
                    :disable="!canEdit"
                    :rules="canEdit ? [validQuota] : []"
                    @focus="onQuotaFocus"
                    @blur="onQuotaBlur"
                  />
                </div>
                <div class="col-12"><q-input v-model="form.description" outlined dense type="textarea" autogrow label="Descripción" :disable="!canEdit" /></div>
                <div class="col-12 col-sm-6"><q-input v-model="form.start_date" outlined dense type="date" label="Fecha de inicio" :disable="!canEdit" /></div>
                <div class="col-12 col-sm-6">
                  <q-select v-model="form.contribution_weekday" outlined dense emit-value map-options :options="weekdays" label="Día de aporte" :disable="!canEdit" />
                </div>
                <div class="col-12 col-sm-6"><q-input v-model="form.first_contribution_date" outlined dense type="date" label="Primer aporte" :disable="!canEdit" /></div>
                <div class="col-12 col-sm-6"><q-input v-model="form.first_disbursement_date" outlined dense type="date" label="Primera entrega" :disable="!canEdit" /></div>
                <div class="col-12"><q-input v-model="form.observations" outlined dense type="textarea" autogrow label="Observaciones" :disable="!canEdit" /></div>
              </div>
              <q-btn v-if="canEdit" unelevated color="primary" label="Guardar configuración" :loading="saving" type="submit" />
            </q-form>
          </q-card-section>
        </q-card>
      </q-tab-panel>

      <q-tab-panel name="members" class="q-pa-none">
        <q-card flat bordered>
          <q-card-section class="row items-center">
            <div class="text-subtitle1 text-weight-bold col">Orden de entrega</div>
            <q-btn v-if="canEdit" unelevated color="primary" icon="person_add" label="Asociar socio" @click="memberDialog = true" />
          </q-card-section>
          <q-list separator>
            <q-item v-for="(member, index) in cycle.members" :key="member.id">
              <q-item-section avatar>
                <q-avatar color="primary" text-color="white">{{ member.delivery_position }}</q-avatar>
              </q-item-section>
              <q-item-section>
                <q-item-label>{{ member.person?.full_name }}</q-item-label>
                <q-item-label caption>{{ member.person?.email }} · {{ formatMoney(member.weekly_quota, cycle.currency) }}</q-item-label>
              </q-item-section>
              <q-item-section side v-if="canEdit">
                <div class="row q-gutter-xs">
                  <q-btn flat dense icon="arrow_upward" :disable="index === 0" @click="moveMember(index, -1)" />
                  <q-btn flat dense icon="arrow_downward" :disable="index === cycle.members.length - 1" @click="moveMember(index, 1)" />
                  <q-btn flat dense icon="delete" color="negative" @click="remove(member)" />
                </div>
              </q-item-section>
            </q-item>
          </q-list>
          <q-card-section v-if="!cycle.members?.length" class="text-grey-7">Aún no hay socios asociados.</q-card-section>
        </q-card>
      </q-tab-panel>

      <q-tab-panel name="calendar" class="q-pa-none">
        <q-card flat bordered>
          <q-table
            flat
            :rows="cycle.turns || []"
            :columns="turnColumns"
            row-key="id"
            hide-pagination
            :rows-per-page-options="[0]"
          >
            <template #body-cell-status="props">
              <q-td :props="props"><StatusChip :status="props.row.status" /></q-td>
            </template>
            <template #body-cell-expected_gross_amount="props">
              <q-td :props="props">{{ formatMoney(props.row.expected_gross_amount, cycle.currency) }}</q-td>
            </template>
            <template #body-cell-collected_amount="props">
              <q-td :props="props">{{ formatMoney(props.row.collected_amount, cycle.currency) }}</q-td>
            </template>
          </q-table>
        </q-card>
      </q-tab-panel>

      <q-tab-panel name="contributions" class="q-pa-none">
        <q-card flat bordered>
          <q-table
            flat
            :rows="contributions"
            :columns="contributionColumns"
            row-key="id"
            hide-pagination
            :rows-per-page-options="[0]"
          >
            <template #body-cell-status="props">
              <q-td :props="props"><StatusChip :status="props.row.status" /></q-td>
            </template>
            <template #body-cell-expected_amount="props">
              <q-td :props="props">{{ formatMoney(props.row.expected_amount, cycle.currency) }}</q-td>
            </template>
            <template #body-cell-received_amount="props">
              <q-td :props="props">{{ formatMoney(props.row.received_amount, cycle.currency) }}</q-td>
            </template>
            <template #body-cell-actions="props">
              <q-td :props="props">
                <q-btn
                  v-if="cycle.status === 'en_proceso' && ['pendiente', 'parcial'].includes(props.row.status)"
                  unelevated
                  dense
                  color="primary"
                  label="Registrar"
                  @click="openPayment(props.row)"
                />
              </q-td>
            </template>
          </q-table>
        </q-card>
      </q-tab-panel>

      <q-tab-panel name="reports" class="q-pa-none">
        <q-card flat bordered>
          <q-table
            flat
            :rows="paymentReports"
            :columns="reportColumns"
            row-key="id"
            hide-pagination
            :rows-per-page-options="[0]"
            no-data-label="No hay pagos reportados en este ciclo."
          >
            <template #body-cell-status="props">
              <q-td :props="props"><StatusChip :status="props.row.status" /></q-td>
            </template>
            <template #body-cell-amount="props">
              <q-td :props="props">{{ formatMoney(props.row.amount, cycle.currency) }}</q-td>
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
        </q-card>
      </q-tab-panel>

      <q-tab-panel name="disbursements" class="q-pa-none">
        <div class="row q-col-gutter-md">
          <div v-for="turn in cycle.turns" :key="turn.id" class="col-12 col-md-6">
            <q-card flat bordered>
              <q-card-section>
                <div class="row items-center">
                  <div class="text-subtitle1 text-weight-bold col">Turno {{ turn.turn_number }} · {{ turn.beneficiary?.full_name }}</div>
                  <StatusChip :status="turn.status" />
                </div>
                <div class="q-mt-sm text-grey-7">Entrega prevista: {{ formatDate(turn.expected_disbursement_date) }}</div>
                <div>Previsto: {{ formatMoney(turn.expected_gross_amount, cycle.currency) }}</div>
                <div>Recaudado: {{ formatMoney(turn.collected_amount, cycle.currency) }}</div>
                <div v-if="turn.disbursement && turn.disbursement.status === 'completado'">
                  Entregado: {{ formatMoney(turn.disbursement.delivered_amount, cycle.currency) }}
                </div>
                <q-btn
                  v-if="cycle.status === 'en_proceso' && turn.status !== 'completado' && turn.status !== 'cancelado'"
                  class="q-mt-md"
                  unelevated
                  color="primary"
                  label="Registrar entrega"
                  @click="openDisbursement(turn)"
                />
              </q-card-section>
            </q-card>
          </div>
        </div>
      </q-tab-panel>

      <q-tab-panel name="summary" class="q-pa-none">
        <div class="row q-col-gutter-md" v-if="summary">
          <div class="col-12 col-sm-6 col-md-3" v-for="item in summaryCards" :key="item.label">
            <q-card flat bordered>
              <q-card-section>
                <div class="text-caption text-grey-7">{{ item.label }}</div>
                <div class="text-h6">{{ item.value }}</div>
              </q-card-section>
            </q-card>
          </div>
        </div>
      </q-tab-panel>
    </q-tab-panels>

    <q-dialog v-model="memberDialog">
      <q-card class="associate-members-card">
        <q-card-section class="q-pb-sm">
          <div class="text-h6">Asociar socio</div>
          <div class="text-grey-7">Marque los socios que desea incorporar a este ciclo.</div>
        </q-card-section>
        <q-card-section class="q-pt-sm">
          <q-input v-model="memberSearch" outlined dense placeholder="Buscar por nombre, documento o correo" class="q-mb-md">
            <template #prepend>
              <q-icon name="search" />
            </template>
          </q-input>
          <q-list bordered separator class="associate-members-list">
            <q-item v-if="!filteredPeople.length">
              <q-item-section class="text-grey-7">No hay socios para mostrar.</q-item-section>
            </q-item>
            <q-item
              v-for="person in filteredPeople"
              :key="person.id"
              tag="label"
              :disable="person.associated"
            >
              <q-item-section avatar>
                <q-checkbox
                  v-model="selectedPersonIds"
                  :val="person.id"
                  :disable="person.associated"
                />
              </q-item-section>
              <q-item-section>
                <q-item-label>{{ person.full_name }}</q-item-label>
                <q-item-label caption>
                  {{ person.identification_type }} {{ person.identification_number }}
                  <span v-if="person.email"> · {{ person.email }}</span>
                  <span v-if="person.associated"> · Ya asociado</span>
                </q-item-label>
              </q-item-section>
            </q-item>
          </q-list>
        </q-card-section>
        <q-card-actions align="right" class="q-px-md q-pb-md">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn unelevated color="primary" label="Guardar" :loading="saving" @click="saveSelectedMembers" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="paymentDialog">
      <q-card style="min-width: min(420px, 96vw)">
        <q-card-section class="text-h6">Registrar aporte</q-card-section>
        <q-card-section>
          <div class="q-mb-md">{{ currentContribution?.person?.full_name }} · esperado {{ formatMoney(currentContribution?.expected_amount, cycle.currency) }}</div>
          <q-input v-model="paymentForm.amount" outlined dense type="number" min="1" label="Monto recibido" />
          <q-input v-model="paymentForm.observations" outlined dense class="q-mt-md" label="Observaciones" />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn unelevated color="primary" label="Guardar" :loading="saving" @click="savePayment" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <q-dialog v-model="disbursementDialog">
      <q-card style="min-width: min(420px, 96vw)">
        <q-card-section class="text-h6">Registrar entrega</q-card-section>
        <q-card-section>
          <div>Beneficiario: {{ currentTurn?.beneficiary?.full_name }}</div>
          <div>Previsto: {{ formatMoney(currentTurn?.expected_gross_amount, cycle.currency) }}</div>
          <div class="q-mb-md">Recaudado: {{ formatMoney(currentTurn?.collected_amount, cycle.currency) }}</div>
          <q-input v-model="disbursementForm.delivered_amount" outlined dense type="number" min="0" label="Monto entregado" />
          <q-input v-model="disbursementForm.observations" outlined dense class="q-mt-md" label="Observaciones" />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="Cancelar" v-close-popup />
          <q-btn unelevated color="primary" label="Confirmar entrega" :loading="saving" @click="saveDisbursement" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <PaymentReportDialog v-model="reportDialog" :report="currentReport" can-review @reviewed="load" />
  </q-page>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Notify, useQuasar } from 'quasar'
import PaymentReportDialog from '@/components/PaymentReportDialog.vue'
import StatusChip from '@/components/StatusChip.vue'
import { listPeople } from '@/services/people'
import { listPaymentReports } from '@/services/paymentReports'
import {
  addMembers,
  closeCycle,
  finalizeCycle,
  getCycle,
  getSummary,
  registerDisbursement,
  registerPayment,
  removeMember,
  reorderMembers,
  startCycle,
  updateCycle,
} from '@/services/cycles'
import { errorMessage, formatDate, formatMoney, formatMoneyInput, parseMoneyInput } from '@/utils/format'

const $q = useQuasar()
const route = useRoute()
const cycle = ref(null)
const summary = ref(null)
const tab = ref('info')
const saving = ref(false)
const memberDialog = ref(false)
const paymentDialog = ref(false)
const disbursementDialog = ref(false)
const selectedPersonIds = ref([])
const memberSearch = ref('')
const peopleOptions = ref([])
const currentContribution = ref(null)
const currentTurn = ref(null)
const paymentReports = ref([])
const reportDialog = ref(false)
const currentReport = ref(null)
const form = reactive({})
const quotaDisplay = ref('')
const paymentForm = reactive({ amount: '', observations: '' })
const validQuota = (val) => {
  const amount = parseMoneyInput(val)
  return (amount !== null && amount > 0) || 'Ingrese una cuota semanal válida'
}
const disbursementForm = reactive({ delivered_amount: '', observations: '' })
const weekdays = [
  { label: 'Lunes', value: 1 },
  { label: 'Martes', value: 2 },
  { label: 'Miércoles', value: 3 },
  { label: 'Jueves', value: 4 },
  { label: 'Viernes', value: 5 },
  { label: 'Sábado', value: 6 },
  { label: 'Domingo', value: 7 },
]

const canEdit = computed(() => cycle.value?.status === 'abierto')
const filteredPeople = computed(() => {
  const term = memberSearch.value.trim().toLowerCase()
  const people = peopleOptions.value

  if (!term) {
    return people
  }

  return people.filter((person) => {
    const haystack = [
      person.full_name,
      person.identification_number,
      person.email,
    ].join(' ').toLowerCase()

    return haystack.includes(term)
  })
})
const contributions = computed(() => (cycle.value?.turns || []).flatMap((turn) =>
  (turn.contributions || []).map((item) => ({ ...item, turn_number: turn.turn_number }))))
const summaryCards = computed(() => ([
  { label: 'Total previsto', value: formatMoney(summary.value?.expected_total_contributions, cycle.value?.currency) },
  { label: 'Total recaudado', value: formatMoney(summary.value?.collected_total, cycle.value?.currency) },
  { label: 'Total entregado', value: formatMoney(summary.value?.delivered_total, cycle.value?.currency) },
  { label: 'Aportes pendientes', value: summary.value?.pending_contributions ?? 0 },
]))
const turnColumns = [
  { name: 'turn_number', label: 'Turno', field: 'turn_number', align: 'left' },
  { name: 'beneficiary', label: 'Beneficiario', field: (row) => row.beneficiary?.full_name, align: 'left' },
  { name: 'expected_contribution_date', label: 'Aporte', field: (row) => formatDate(row.expected_contribution_date), align: 'left' },
  { name: 'expected_disbursement_date', label: 'Entrega', field: (row) => formatDate(row.expected_disbursement_date), align: 'left' },
  { name: 'expected_gross_amount', label: 'Previsto', field: 'expected_gross_amount', align: 'left' },
  { name: 'collected_amount', label: 'Recaudado', field: 'collected_amount', align: 'left' },
  { name: 'status', label: 'Estado', field: 'status', align: 'left' },
]
const contributionColumns = [
  { name: 'turn_number', label: 'Turno', field: 'turn_number', align: 'left' },
  { name: 'person', label: 'Socio', field: (row) => row.person?.full_name, align: 'left' },
  { name: 'expected_amount', label: 'Esperado', field: 'expected_amount', align: 'left' },
  { name: 'received_amount', label: 'Recibido', field: 'received_amount', align: 'left' },
  { name: 'status', label: 'Estado', field: 'status', align: 'left' },
  { name: 'actions', label: '', field: 'actions', align: 'right' },
]
const reportColumns = [
  { name: 'created_at', label: 'Fecha', field: (row) => formatDate(row.created_at), align: 'left' },
  { name: 'person', label: 'Socio', field: (row) => row.person?.full_name, align: 'left' },
  { name: 'amount', label: 'Monto', field: 'amount', align: 'left' },
  { name: 'status', label: 'Estado', field: 'status', align: 'left' },
  { name: 'actions', label: '', field: 'actions', align: 'right' },
]

async function load() {
  cycle.value = await getCycle(route.params.id)
  Object.assign(form, {
    name: cycle.value.name,
    description: cycle.value.description || '',
    start_date: cycle.value.start_date,
    contribution_weekday: cycle.value.contribution_weekday,
    first_contribution_date: cycle.value.first_contribution_date,
    first_disbursement_date: cycle.value.first_disbursement_date,
    weekly_quota: Number(cycle.value.weekly_quota),
    observations: cycle.value.observations || '',
    currency: cycle.value.currency,
  })
  quotaDisplay.value = formatMoneyInput(form.weekly_quota)
  summary.value = await getSummary(route.params.id)
  const reports = await listPaymentReports({
    savings_cycle_id: route.params.id,
    per_page: 100,
  })
  paymentReports.value = reports.items
}

function onQuotaFocus() {
  if (!canEdit.value) {
    return
  }

  const amount = parseMoneyInput(quotaDisplay.value)
  quotaDisplay.value = amount ? String(amount) : ''
}

function onQuotaBlur() {
  const amount = parseMoneyInput(quotaDisplay.value)
  form.weekly_quota = amount
  quotaDisplay.value = amount ? formatMoneyInput(amount) : ''
}

async function loadPeople() {
  try {
    const used = new Set((cycle.value?.members || []).map((item) => Number(item.person_id)))
    const byId = new Map()
    let page = 1
    let lastPage = 1

    do {
      const result = await listPeople({ is_active: true, per_page: 100, page })
      lastPage = result.meta.last_page || 1
      result.items.forEach((person) => {
        byId.set(person.id, {
          ...person,
          associated: used.has(Number(person.id)),
        })
      })
      page += 1
    } while (page <= lastPage)

    for (const member of cycle.value?.members || []) {
      if (member.person && !byId.has(member.person_id)) {
        byId.set(member.person_id, {
          ...member.person,
          associated: true,
        })
      }
    }

    peopleOptions.value = [...byId.values()].sort((a, b) =>
      String(a.full_name || '').localeCompare(String(b.full_name || ''), 'es'))
    selectedPersonIds.value = [...used]
    memberSearch.value = ''
  } catch (error) {
    Notify.create({ type: 'negative', message: errorMessage(error) })
  }
}

async function saveConfig() {
  const amount = parseMoneyInput(quotaDisplay.value)

  if (!amount || amount <= 0) {
    Notify.create({ type: 'negative', message: 'Ingrese una cuota semanal válida.' })
    return
  }

  saving.value = true
  try {
    cycle.value = await updateCycle(cycle.value.id, {
      ...form,
      weekly_quota: amount,
    })
    quotaDisplay.value = formatMoneyInput(amount)
    Notify.create({ type: 'positive', message: 'Configuración guardada.' })
  } catch (error) {
    Notify.create({ type: 'negative', message: errorMessage(error) })
  } finally {
    saving.value = false
  }
}

async function saveSelectedMembers() {
  const associated = new Set((cycle.value?.members || []).map((item) => Number(item.person_id)))
  const toAdd = selectedPersonIds.value
    .map((id) => Number(id))
    .filter((id) => !associated.has(id))

  if (!toAdd.length) {
    Notify.create({ type: 'warning', message: 'Seleccione al menos un socio nuevo.' })
    return
  }

  saving.value = true
  try {
    await addMembers(cycle.value.id, toAdd)
    memberDialog.value = false
    selectedPersonIds.value = []
    await load()
    Notify.create({ type: 'positive', message: toAdd.length === 1 ? 'Socio asociado.' : 'Socios asociados.' })
  } catch (error) {
    Notify.create({ type: 'negative', message: errorMessage(error) })
  } finally {
    saving.value = false
  }
}

async function remove(member) {
  $q.dialog({ title: 'Retirar socio', message: `¿Retirar a ${member.person?.full_name} de este ciclo?`, cancel: true }).onOk(async () => {
    try {
      await removeMember(cycle.value.id, member.id)
      await load()
    } catch (error) {
      Notify.create({ type: 'negative', message: errorMessage(error) })
    }
  })
}

async function moveMember(index, direction) {
  const ids = cycle.value.members.map((item) => item.person_id)
  const target = index + direction
  const [moved] = ids.splice(index, 1)
  ids.splice(target, 0, moved)
  try {
    await reorderMembers(cycle.value.id, ids)
    await load()
  } catch (error) {
    Notify.create({ type: 'negative', message: errorMessage(error) })
  }
}

function confirmStart() {
  $q.dialog({
    title: 'Iniciar ciclo',
    message: 'El orden, la cuota y las fechas quedarán fijos. ¿Desea continuar?',
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    try {
      cycle.value = await startCycle(cycle.value.id)
      await load()
      Notify.create({ type: 'positive', message: 'Ciclo iniciado.' })
    } catch (error) {
      Notify.create({ type: 'negative', message: errorMessage(error) })
    }
  })
}

function confirmClose() {
  $q.dialog({
    title: 'Cerrar ciclo',
    message: 'Explique el motivo del cierre. Se conservará el historial financiero.',
    prompt: { model: '', type: 'text', label: 'Motivo' },
    cancel: true,
    persistent: true,
  }).onOk(async (reason) => {
    try {
      await closeCycle(cycle.value.id, reason)
      await load()
      Notify.create({ type: 'positive', message: 'Ciclo cerrado.' })
    } catch (error) {
      Notify.create({ type: 'negative', message: errorMessage(error) })
    }
  })
}

function confirmFinalize() {
  $q.dialog({
    title: 'Finalizar ciclo',
    message: 'Solo debe finalizarse si todos los turnos y entregas están completos.',
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    try {
      await finalizeCycle(cycle.value.id)
      await load()
      Notify.create({ type: 'positive', message: 'Ciclo finalizado.' })
    } catch (error) {
      Notify.create({ type: 'negative', message: errorMessage(error) })
    }
  })
}

function openReport(report) {
  currentReport.value = report
  reportDialog.value = true
}

function openPayment(contribution) {
  currentContribution.value = contribution
  const pending = Number(contribution.expected_amount) - Number(contribution.received_amount)
  paymentForm.amount = pending
  paymentForm.observations = ''
  paymentDialog.value = true
}

async function savePayment() {
  saving.value = true
  try {
    await registerPayment(currentContribution.value.id, paymentForm)
    paymentDialog.value = false
    await load()
    Notify.create({ type: 'positive', message: 'Aporte registrado.' })
  } catch (error) {
    Notify.create({ type: 'negative', message: errorMessage(error) })
  } finally {
    saving.value = false
  }
}

function openDisbursement(turn) {
  currentTurn.value = turn
  disbursementForm.delivered_amount = Number(turn.collected_amount)
  disbursementForm.observations = ''
  disbursementDialog.value = true
}

async function saveDisbursement() {
  saving.value = true
  try {
    await registerDisbursement(currentTurn.value.id, disbursementForm)
    disbursementDialog.value = false
    await load()
    Notify.create({ type: 'positive', message: 'Entrega registrada.' })
  } catch (error) {
    Notify.create({ type: 'negative', message: errorMessage(error) })
  } finally {
    saving.value = false
  }
}

watch(memberDialog, (open) => { if (open) loadPeople() })

onMounted(async () => {
  try {
    await load()
  } catch (error) {
    Notify.create({ type: 'negative', message: errorMessage(error) })
  }
})
</script>

<style scoped>
.associate-members-card {
  width: min(720px, 96vw);
}

.associate-members-list {
  max-height: min(420px, 55vh);
  overflow: auto;
}
</style>

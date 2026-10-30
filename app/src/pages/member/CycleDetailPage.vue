<template>
  <q-page class="q-pa-md" v-if="detail">
    <q-btn flat color="primary" icon="arrow_back" label="Volver" to="/socio/ciclos" class="q-ml-n-sm" />
    <div class="text-h5 text-weight-bold">{{ detail.cycle.name }}</div>
    <div class="row items-center q-gutter-sm q-mb-md">
      <StatusChip :status="detail.cycle.status" />
      <span>Posición {{ detail.membership.delivery_position }} · cuota {{ formatMoney(detail.membership.weekly_quota, detail.cycle.currency) }}</span>
    </div>

    <div class="row q-col-gutter-md q-mb-md">
      <div class="col-12 col-md-4">
        <q-card flat bordered>
          <q-card-section>
            <div class="text-caption text-grey-7">Su turno</div>
            <div class="text-h6">#{{ detail.own_turn?.turn_number || '—' }}</div>
            <StatusChip :status="detail.own_turn?.status" />
            <div class="q-mt-sm">Entrega: {{ formatDate(detail.own_turn?.expected_disbursement_date) }}</div>
            <div>Monto bruto: {{ formatMoney(detail.own_turn?.expected_gross_amount, detail.cycle.currency) }}</div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <q-card flat bordered class="q-mb-md">
      <q-card-section class="row items-center">
        <div class="text-subtitle1 text-weight-bold col">Historial de sus aportes</div>
        <q-btn
          v-if="detail.cycle.status === 'en_proceso'"
          unelevated
          color="primary"
          label="Reportar pago"
          :disable="!selectedContributionIds.length"
          @click="openCreateReport"
        />
      </q-card-section>
      <q-card-section class="q-pt-none">
        <q-table
          flat
          :rows="detail.own_contributions || []"
          :columns="columns"
          row-key="id"
          hide-pagination
          :rows-per-page-options="[0]"
        >
          <template #body-cell-select="props">
            <q-td :props="props">
              <q-checkbox
                v-if="props.row.can_report"
                v-model="selectedContributionIds"
                :val="props.row.id"
              />
            </q-td>
          </template>
          <template #body-cell-status="props">
            <q-td :props="props"><StatusChip :status="props.row.status" /></q-td>
          </template>
          <template #body-cell-report_status="props">
            <q-td :props="props">
              <StatusChip v-if="props.row.report_status" :status="props.row.report_status" />
              <span v-else class="text-grey-6">—</span>
            </q-td>
          </template>
          <template #body-cell-expected_amount="props">
            <q-td :props="props">{{ formatMoney(props.row.expected_amount, detail.cycle.currency) }}</q-td>
          </template>
          <template #body-cell-received_amount="props">
            <q-td :props="props">{{ formatMoney(props.row.received_amount, detail.cycle.currency) }}</q-td>
          </template>
        </q-table>
      </q-card-section>
    </q-card>

    <q-card flat bordered>
      <q-card-section>
        <div class="text-subtitle1 text-weight-bold q-mb-sm">Pagos reportados</div>
        <q-list v-if="detail.payment_reports?.length" bordered separator>
          <q-item v-for="report in detail.payment_reports" :key="report.id" clickable @click="openReport(report)">
            <q-item-section>
              <q-item-label>{{ formatMoney(report.amount, detail.cycle.currency) }} · {{ formatDate(report.created_at) }}</q-item-label>
              <q-item-label caption>
                {{ (report.items || []).map((item) => `Turno ${item.turn_number}`).join(', ') || 'Sin cuotas' }}
                <span v-if="report.observations"> · {{ report.observations }}</span>
              </q-item-label>
            </q-item-section>
            <q-item-section side>
              <StatusChip :status="report.status" />
            </q-item-section>
          </q-item>
        </q-list>
        <div v-else class="text-grey-7">Aún no ha reportado pagos en este ciclo.</div>
      </q-card-section>
    </q-card>

    <q-dialog v-model="reportDialog">
      <q-card style="min-width: min(480px, 96vw)">
        <q-form @submit.prevent="submitReport">
          <q-card-section class="q-pb-sm">
            <div class="text-h6">Reportar pago</div>
            <div class="text-grey-7">Adjunte una captura como soporte de las cuotas seleccionadas.</div>
          </q-card-section>
          <q-card-section class="q-pt-sm">
            <q-list bordered separator class="q-mb-md">
              <q-item v-for="item in selectedContributions" :key="item.id">
                <q-item-section>
                  <q-item-label>Turno {{ item.turn_number }}</q-item-label>
                  <q-item-label caption>{{ formatDate(item.expected_date) }}</q-item-label>
                </q-item-section>
                <q-item-section side>{{ formatMoney(item.remaining_amount || item.expected_amount, detail.cycle.currency) }}</q-item-section>
              </q-item>
            </q-list>
            <div class="text-caption text-grey-7 q-mb-sm">
              Formatos permitidos: JPG, PNG, WEBP o GIF. Tamaño máximo: 12 MB.
            </div>
            <q-banner v-if="receiptError" dense rounded class="bg-red-1 text-negative q-mb-md">
              {{ receiptError }}
            </q-banner>
            <q-file
              v-model="receipt"
              outlined
              dense
              accept=".jpg,.jpeg,.png,.webp,.gif,image/png,image/jpeg,image/webp,image/gif"
              :max-file-size="maxReceiptBytes"
              label="Captura de pantalla"
              :rules="[(val) => !!val || 'Adjunte una captura de pantalla']"
              @rejected="onReceiptRejected"
              @update:model-value="receiptError = ''"
            >
              <template #prepend>
                <q-icon name="image" />
              </template>
            </q-file>
          </q-card-section>
          <q-card-actions align="right" class="q-px-md q-pb-md">
            <q-btn flat label="Cancelar" v-close-popup />
            <q-btn unelevated color="primary" label="Enviar" type="submit" :loading="saving" />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <PaymentReportDialog v-model="reviewDialog" :report="currentReport" />
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Notify } from 'quasar'
import PaymentReportDialog from '@/components/PaymentReportDialog.vue'
import StatusChip from '@/components/StatusChip.vue'
import { getMemberCycle, reportMemberPayments } from '@/services/member'
import { errorMessage, formatDate, formatMoney } from '@/utils/format'

const route = useRoute()
const detail = ref(null)
const selectedContributionIds = ref([])
const reportDialog = ref(false)
const reviewDialog = ref(false)
const currentReport = ref(null)
const receipt = ref(null)
const receiptError = ref('')
const saving = ref(false)
const maxReceiptBytes = 12 * 1024 * 1024
const columns = [
  { name: 'select', label: '', field: 'id', align: 'left' },
  { name: 'turn_number', label: 'Turno', field: 'turn_number', align: 'left' },
  { name: 'expected_date', label: 'Fecha esperada', field: (row) => formatDate(row.expected_date), align: 'left' },
  { name: 'expected_amount', label: 'Esperado', field: 'expected_amount', align: 'left' },
  { name: 'received_amount', label: 'Recibido', field: 'received_amount', align: 'left' },
  { name: 'status', label: 'Estado', field: 'status', align: 'left' },
  { name: 'report_status', label: 'Reporte', field: 'report_status', align: 'left' },
]
const selectedContributions = computed(() =>
  (detail.value?.own_contributions || []).filter((item) => selectedContributionIds.value.includes(item.id)))

async function load() {
  detail.value = await getMemberCycle(route.params.id)
  selectedContributionIds.value = []
  receipt.value = null
  receiptError.value = ''
}

function openCreateReport() {
  receiptError.value = ''
  reportDialog.value = true
}

function openReport(report) {
  currentReport.value = report
  reviewDialog.value = true
}

function onReceiptRejected(entries) {
  const reason = entries?.[0]?.failedPropValidation

  if (reason === 'max-file-size') {
    receiptError.value = 'La imagen supera el tamaño máximo de 12 MB.'
  } else if (reason === 'accept') {
    receiptError.value = 'Formato incorrecto. Solo se permiten imágenes JPG, PNG, WEBP o GIF.'
  } else {
    receiptError.value = 'No se pudo adjuntar el archivo. Use JPG, PNG, WEBP o GIF de máximo 12 MB.'
  }

  Notify.create({ type: 'negative', message: receiptError.value })
}

async function submitReport() {
  if (!selectedContributionIds.value.length || !receipt.value) {
    receiptError.value = 'Adjunte una captura de pantalla como soporte.'
    return
  }

  receiptError.value = ''
  saving.value = true
  try {
    await reportMemberPayments(detail.value.cycle.id, selectedContributionIds.value, receipt.value)
    reportDialog.value = false
    receipt.value = null
    Notify.create({ type: 'positive', message: 'Pago reportado. Queda pendiente de validación.' })
    await load()
  } catch (error) {
    const message = errorMessage(error)
    receiptError.value = message
    Notify.create({ type: 'negative', message })
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  try {
    await load()
  } catch (error) {
    Notify.create({ type: 'negative', message: errorMessage(error) })
  }
})
</script>

<template>
  <q-dialog :model-value="modelValue" @update:model-value="$emit('update:modelValue', $event)">
    <q-card class="report-dialog-card">
      <q-card-section class="q-pb-sm">
        <div class="text-h6">Pago reportado</div>
        <div class="text-grey-7" v-if="report">
          {{ report.person?.full_name }} · {{ report.cycle?.name }}
        </div>
      </q-card-section>

      <q-card-section class="q-pt-sm" v-if="report">
        <div class="row items-center q-gutter-sm q-mb-md">
          <StatusChip :status="report.status" />
          <span>{{ formatMoney(report.amount, report.cycle?.currency) }}</span>
          <span class="text-grey-7">{{ formatDate(report.created_at) }}</span>
        </div>

        <div class="text-subtitle2 q-mb-sm">Cuotas</div>
        <q-list bordered separator class="q-mb-md">
          <q-item v-for="item in report.items || []" :key="item.id">
            <q-item-section>
              <q-item-label>Turno {{ item.turn_number || '—' }}</q-item-label>
              <q-item-label caption>{{ formatDate(item.expected_date) }}</q-item-label>
            </q-item-section>
            <q-item-section side>{{ formatMoney(item.amount, report.cycle?.currency) }}</q-item-section>
          </q-item>
        </q-list>

        <div class="text-subtitle2 q-mb-sm">Soporte</div>
        <div v-if="loadingReceipt" class="text-grey-7 q-mb-md">Cargando comprobante...</div>
        <div v-else-if="receiptUrl" class="q-mb-md">
          <q-btn
            v-if="receiptExpanded"
            unelevated
            dense
            color="primary"
            icon="photo_size_select_small"
            label="Volver a miniatura"
            class="q-mb-sm"
            @click="receiptExpanded = false"
          />
          <div :class="receiptExpanded ? 'receipt-full-wrap' : ''">
            <img
              :src="receiptUrl"
              alt="Comprobante"
              :class="receiptExpanded ? 'receipt-full' : 'receipt-thumb'"
              :title="receiptExpanded ? '' : 'Clic para ver tamaño real'"
              @click="expandReceipt"
            />
          </div>
        </div>
        <div v-else class="text-grey-7 q-mb-md">No hay comprobante disponible.</div>

        <div v-if="report.observations" class="q-mb-md">
          <div class="text-subtitle2">Observaciones</div>
          <div>{{ report.observations }}</div>
        </div>
        <div v-if="report.reviewer" class="text-grey-7 q-mb-md">
          Revisado por {{ report.reviewer.full_name }} · {{ formatDate(report.reviewed_at) }}
        </div>

        <q-input
          v-if="canReview && report.status === 'pendiente_validar'"
          v-model="observations"
          outlined
          dense
          type="textarea"
          autogrow
          label="Observaciones"
        />
      </q-card-section>

      <q-card-actions align="right" class="q-px-md q-pb-md">
        <q-btn flat label="Cerrar" v-close-popup />
        <template v-if="canReview && report?.status === 'pendiente_validar'">
          <q-btn outline color="negative" label="Pago rechazado" :loading="saving" @click="review('pago_rechazado')" />
          <q-btn unelevated color="primary" label="Pago correcto" :loading="saving" @click="review('pago_correcto')" />
        </template>
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Notify } from 'quasar'
import StatusChip from '@/components/StatusChip.vue'
import { getPaymentReportReceiptUrl, reviewPaymentReport } from '@/services/paymentReports'
import { errorMessage, formatDate, formatMoney } from '@/utils/format'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  report: { type: Object, default: null },
  canReview: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue', 'reviewed'])

const observations = ref('')
const receiptUrl = ref('')
const receiptExpanded = ref(false)
const loadingReceipt = ref(false)
const saving = ref(false)

watch(
  () => ({ open: props.modelValue, id: props.report?.id }),
  async ({ open, id }) => {
    revokeReceipt()
    receiptExpanded.value = false
    observations.value = props.report?.observations || ''

    if (!open || !id) {
      loadingReceipt.value = false
      return
    }

    const requestId = id
    loadingReceipt.value = true
    try {
      const url = await getPaymentReportReceiptUrl(requestId)
      if (props.modelValue && props.report?.id === requestId) {
        receiptUrl.value = url
      }
    } catch (error) {
      Notify.create({ type: 'negative', message: errorMessage(error, 'No se pudo cargar el comprobante.') })
    } finally {
      if (props.report?.id === requestId) {
        loadingReceipt.value = false
      }
    }
  },
)

function expandReceipt() {
  if (!receiptExpanded.value) {
    receiptExpanded.value = true
  }
}

function revokeReceipt() {
  if (receiptUrl.value.startsWith('blob:')) {
    URL.revokeObjectURL(receiptUrl.value)
  }

  receiptUrl.value = ''
}

async function review(status) {
  if (status === 'pago_rechazado' && !observations.value.trim()) {
    Notify.create({ type: 'warning', message: 'Indique la observación del rechazo.' })
    return
  }

  saving.value = true
  try {
    await reviewPaymentReport(props.report.id, {
      status,
      observations: observations.value || null,
    })
    emit('update:modelValue', false)
    emit('reviewed')
    Notify.create({
      type: 'positive',
      message: status === 'pago_correcto' ? 'Pago marcado como correcto.' : 'Pago rechazado.',
    })
  } catch (error) {
    Notify.create({ type: 'negative', message: errorMessage(error) })
  } finally {
    saving.value = false
  }
}
</script>

<style scoped>
.report-dialog-card {
  width: min(720px, 96vw);
}

.receipt-thumb {
  display: block;
  width: 140px;
  height: 140px;
  object-fit: cover;
  background: #f5f5f5;
  border-radius: 8px;
  cursor: zoom-in;
}

.receipt-full-wrap {
  overflow: auto;
  max-height: min(70vh, 720px);
}

.receipt-full {
  display: block;
  width: auto;
  height: auto;
  max-width: none;
  background: #f5f5f5;
  border-radius: 8px;
}
</style>

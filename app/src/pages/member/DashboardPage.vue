<template>
  <q-page class="q-pa-md">
    <div class="text-h5 text-weight-bold q-mb-xs">Hola, {{ dashboard?.person?.full_name || auth.fullName }}</div>
    <div class="text-grey-7 q-mb-md">Consulte su cuota, su turno y las fechas de su ciclo actual.</div>

    <div class="row q-col-gutter-md">
      <div class="col-12 col-sm-6 col-md-3" v-for="card in cards" :key="card.label">
        <q-card flat bordered>
          <q-card-section>
            <div class="text-caption text-grey-7">{{ card.label }}</div>
            <div class="text-h6">{{ card.value }}</div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <q-card flat bordered class="q-mt-md" v-if="dashboard?.current_cycle">
      <q-card-section>
        <div class="text-subtitle1 text-weight-bold">Ciclo actual</div>
        <div>{{ dashboard.current_cycle.name }}</div>
        <StatusChip :status="dashboard.current_cycle.status" />
        <div class="q-mt-sm">Turno {{ dashboard.turn_number || '—' }} · {{ statusLabel(dashboard.turn_status) }}</div>
        <q-btn class="q-mt-md" unelevated color="primary" label="Ver detalle" :to="`/socio/ciclos/${dashboard.current_cycle.id}`" />
      </q-card-section>
    </q-card>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { Notify } from 'quasar'
import StatusChip from '@/components/StatusChip.vue'
import { useAuthStore } from '@/stores/auth'
import { getMemberDashboard } from '@/services/member'
import { errorMessage, formatDate, formatMoney, statusLabel } from '@/utils/format'

const auth = useAuthStore()
const dashboard = ref(null)
const cards = computed(() => [
  { label: 'Ciclos históricos', value: dashboard.value?.cycles_count ?? 0 },
  { label: 'Cuota semanal', value: formatMoney(dashboard.value?.weekly_quota) },
  { label: 'Próximo aporte', value: formatDate(dashboard.value?.next_contribution_date) },
  { label: 'Su entrega', value: `${formatDate(dashboard.value?.next_disbursement_date)} · ${formatMoney(dashboard.value?.expected_gross_amount)}` },
])

onMounted(async () => {
  try {
    dashboard.value = await getMemberDashboard()
  } catch (error) {
    Notify.create({ type: 'negative', message: errorMessage(error) })
  }
})
</script>

<template>
  <q-page class="q-pa-md q-pa-md-lg">
    <div class="text-h5 text-weight-bold q-mb-md">Dashboard</div>

    <div class="row q-col-gutter-md q-mb-md">
      <div v-for="card in cards" :key="card.label" class="col-12 col-sm-6 col-md-3">
        <q-card flat bordered class="stat-card">
          <q-card-section>
            <div class="text-caption text-grey-7">{{ card.label }}</div>
            <div class="text-h5 text-weight-bold">{{ card.value }}</div>
          </q-card-section>
        </q-card>
      </div>
    </div>

    <div class="row q-col-gutter-md">
      <div class="col-12 col-md-6">
        <q-card flat bordered>
          <q-card-section>
            <div class="text-subtitle1 text-weight-bold">Próxima entrega</div>
            <div v-if="dashboard?.next_turn">
              <div class="text-h6 q-mt-sm">{{ dashboard.next_turn.beneficiary?.full_name }}</div>
              <div class="text-grey-7">{{ formatDate(dashboard.next_turn.expected_disbursement_date) }}</div>
              <div class="text-weight-medium q-mt-sm">{{ formatMoney(dashboard.next_turn.expected_gross_amount) }}</div>
              <StatusChip :status="dashboard.next_turn.status" />
            </div>
            <div v-else class="text-grey-7 q-mt-sm">No hay entregas pendientes.</div>
          </q-card-section>
        </q-card>
      </div>
      <div class="col-12 col-md-6">
        <q-card flat bordered>
          <q-card-section>
            <div class="text-subtitle1 text-weight-bold q-mb-sm">Actividad reciente</div>
            <q-list v-if="dashboard?.recent_activity?.length" separator>
              <q-item v-for="item in dashboard.recent_activity" :key="item.id">
                <q-item-section>
                  <q-item-label>{{ item.description || item.action }}</q-item-label>
                  <q-item-label caption>{{ item.user }} · {{ formatDate(item.created_at) }}</q-item-label>
                </q-item-section>
              </q-item>
            </q-list>
            <div v-else class="text-grey-7">Aún no hay actividad registrada.</div>
          </q-card-section>
        </q-card>
      </div>
    </div>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { Notify } from 'quasar'
import StatusChip from '@/components/StatusChip.vue'
import { getAdminDashboard } from '@/services/cycles'
import { errorMessage, formatDate, formatMoney } from '@/utils/format'

const dashboard = ref(null)

const cards = computed(() => [
  { label: 'Socios activos', value: dashboard.value?.active_people ?? 0 },
  { label: 'Ciclos abiertos', value: dashboard.value?.open_cycles ?? 0 },
  { label: 'Ciclos en proceso', value: dashboard.value?.in_progress_cycles ?? 0 },
  { label: 'Pagos por validar', value: dashboard.value?.pending_payment_reports ?? 0 },
  { label: 'Próximo monto', value: formatMoney(dashboard.value?.next_turn?.expected_gross_amount) },
])

onMounted(async () => {
  try {
    dashboard.value = await getAdminDashboard()
  } catch (error) {
    Notify.create({ type: 'negative', message: errorMessage(error) })
  }
})
</script>

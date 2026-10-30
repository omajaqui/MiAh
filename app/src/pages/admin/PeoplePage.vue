<template>
  <q-page class="q-pa-md">
    <div class="row items-center q-mb-md">
      <div class="text-h5 text-weight-bold col">Personas</div>
      <q-btn unelevated color="primary" icon="person_add" label="Crear persona" @click="openPerson()" />
    </div>

    <div class="row q-col-gutter-md q-mb-md">
      <div class="col-12 col-md-6">
        <q-input v-model="filters.search" outlined dense debounce="400" placeholder="Buscar por nombre, correo o documento" @update:model-value="load(1)">
          <template #prepend>
            <q-icon name="search" />
          </template>
        </q-input>
      </div>
      <div class="col-6 col-md-3">
        <q-select v-model="filters.is_active" outlined dense emit-value map-options :options="statusOptions" label="Estado" @update:model-value="load(1)" />
      </div>
      <div class="col-6 col-md-3">
        <q-select v-model="filters.has_account" outlined dense emit-value map-options :options="accountOptions" label="Cuenta" @update:model-value="load(1)" />
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
      <template #body-cell-is_active="props">
        <q-td :props="props">
          <StatusChip :status="props.row.is_active ? 'activo' : 'retirado'" />
        </q-td>
      </template>
      <template #body-cell-user="props">
        <q-td :props="props">
          <div v-if="props.row.user">
            {{ props.row.user.role_label }}
            <div class="text-caption text-grey-7">{{ props.row.user.is_active ? 'Acceso activo' : 'Acceso inactivo' }}</div>
          </div>
          <span v-else class="text-grey-6">Sin cuenta</span>
        </q-td>
      </template>
      <template #body-cell-actions="props">
        <q-td :props="props">
          <q-btn flat dense icon="edit" color="primary" @click="openPerson(props.row)">
            <q-tooltip>Editar</q-tooltip>
          </q-btn>
          <q-btn
            v-if="!isCurrentPerson(props.row)"
            flat
            dense
            icon="toggle_on"
            color="secondary"
            @click="toggleStatus(props.row)"
          >
            <q-tooltip>{{ props.row.is_active ? 'Desactivar' : 'Activar' }}</q-tooltip>
          </q-btn>
          <q-btn
            v-if="!props.row.user && !isCurrentPerson(props.row)"
            flat
            dense
            icon="person_add_alt"
            color="primary"
            @click="openAccount(props.row)"
          >
            <q-tooltip>Crear cuenta</q-tooltip>
          </q-btn>
          <q-btn
            v-else-if="props.row.user && !isCurrentPerson(props.row)"
            flat
            dense
            icon="manage_accounts"
            color="primary"
            @click="openAccount(props.row)"
          >
            <q-tooltip>Administrar acceso</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <q-dialog v-model="personDialog">
      <q-card class="person-form-card">
        <q-form @submit.prevent="savePerson">
          <q-card-section class="q-pb-sm">
            <div class="text-h6">{{ editing ? 'Editar persona' : 'Crear persona' }}</div>
          </q-card-section>

          <q-card-section class="q-pt-sm">
            <div class="person-form-grid">
              <q-input v-model="personForm.first_name" outlined dense label="Nombres" :rules="[required]" />
              <q-input v-model="personForm.last_name" outlined dense label="Apellidos" :rules="[required]" />
              <q-select v-model="personForm.identification_type" outlined dense emit-value map-options :options="idTypes" label="Tipo de identificación" />
              <q-input v-model="personForm.identification_number" outlined dense label="Número de identificación" :rules="[required]" />
              <q-input v-model="personForm.phone" outlined dense label="Teléfono" :rules="[required]" />
              <q-input v-model="personForm.email" outlined dense type="email" label="Correo electrónico" :rules="[required]" />
              <q-select
                v-model="personForm.bank_type_id"
                outlined
                dense
                emit-value
                map-options
                :options="bankTypeOptions"
                option-value="id"
                option-label="name"
                label="Tipo de banco"
                :rules="[required]"
              />
              <q-input v-model="personForm.account_number" outlined dense label="Número de cuenta" :rules="[required]" />
              <q-input v-model="personForm.address" class="span-2" outlined dense label="Dirección (opcional)" />
            </div>
          </q-card-section>

          <q-card-actions align="right" class="q-px-md q-pb-md">
            <q-btn flat label="Cancelar" v-close-popup />
            <q-btn unelevated color="primary" :loading="saving" :label="editing ? 'Guardar' : 'Crear'" type="submit" />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>

    <q-dialog v-model="accountDialog">
      <q-card class="account-form-card">
        <q-form @submit.prevent="saveAccount">
          <q-card-section class="q-pb-sm">
            <div class="text-h6">{{ selectedPerson?.user ? 'Administrar acceso' : 'Crear cuenta' }}</div>
            <div class="text-grey-7">{{ selectedPerson?.full_name }} · {{ selectedPerson?.email }}</div>
          </q-card-section>

          <q-card-section class="q-pt-sm">
            <div class="account-form-grid">
              <q-select v-model="accountForm.role" outlined dense emit-value map-options :options="roleOptions" label="Rol" />
              <q-toggle v-if="selectedPerson?.user" v-model="accountForm.is_active" label="Acceso activo" />
              <q-toggle v-if="selectedPerson?.user" v-model="accountForm.reset" label="Restablecer contraseña" />

              <template v-if="showPasswordFields">
                <q-banner dense class="bg-teal-1 text-teal-10 password-hint" rounded>
                  La contraseña debe tener mínimo 8 caracteres y coincidir con la confirmación.
                </q-banner>
                <q-input
                  v-model="accountForm.password"
                  outlined
                  dense
                  :type="showPassword ? 'text' : 'password'"
                  label="Contraseña"
                  :rules="[required, minPassword]"
                  autocomplete="new-password"
                >
                  <template #append>
                    <q-icon
                      :name="showPassword ? 'visibility_off' : 'visibility'"
                      class="cursor-pointer"
                      @click="showPassword = !showPassword"
                    />
                  </template>
                </q-input>
                <q-input
                  v-model="accountForm.password_confirmation"
                  outlined
                  dense
                  :type="showPasswordConfirm ? 'text' : 'password'"
                  label="Confirmar contraseña"
                  :rules="[required, samePassword]"
                  autocomplete="new-password"
                >
                  <template #append>
                    <q-icon
                      :name="showPasswordConfirm ? 'visibility_off' : 'visibility'"
                      class="cursor-pointer"
                      @click="showPasswordConfirm = !showPasswordConfirm"
                    />
                  </template>
                </q-input>
              </template>
            </div>
          </q-card-section>

          <q-card-actions align="right" class="q-px-md q-pb-md">
            <q-btn flat label="Cancelar" v-close-popup />
            <q-btn unelevated color="primary" :loading="saving" label="Guardar" type="submit" />
          </q-card-actions>
        </q-form>
      </q-card>
    </q-dialog>
  </q-page>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { Notify, useQuasar } from 'quasar'
import StatusChip from '@/components/StatusChip.vue'
import { createAccount, createPerson, listBankTypes, listPeople, resetPassword, updateAccount, updatePerson, updatePersonStatus } from '@/services/people'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/utils/format'

const $q = useQuasar()
const auth = useAuthStore()
const loading = ref(false)
const saving = ref(false)
const rows = ref([])
const personDialog = ref(false)
const accountDialog = ref(false)
const editing = ref(false)
const selectedPerson = ref(null)
const pagination = ref({ page: 1, rowsPerPage: 10, rowsNumber: 0 })
const filters = reactive({ search: '', is_active: null, has_account: null })
const personForm = reactive(emptyPerson())
const bankTypes = ref([])
const bankTypeOptions = computed(() => bankTypes.value)
const accountForm = reactive(emptyAccount())
const showPassword = ref(false)
const showPasswordConfirm = ref(false)
const showPasswordFields = computed(() => !selectedPerson.value?.user || accountForm.reset)
const required = (val) => !!val || 'Campo obligatorio'
const minPassword = (val) => (val && String(val).length >= 8) || 'Debe tener mínimo 8 caracteres'
const samePassword = (val) => val === accountForm.password || 'Las contraseñas no coinciden'
const idTypes = [
  { label: 'Cédula de ciudadanía', value: 'CC' },
  { label: 'Cédula de extranjería', value: 'CE' },
  { label: 'Tarjeta de identidad', value: 'TI' },
  { label: 'Pasaporte', value: 'PAS' },
  { label: 'NIT', value: 'NIT' },
]
const roleOptions = [
  { label: 'Socio', value: 'socio' },
  { label: 'Administrador', value: 'admin' },
]
const statusOptions = [
  { label: 'Todos', value: null },
  { label: 'Activos', value: true },
  { label: 'Inactivos', value: false },
]
const accountOptions = [
  { label: 'Todas', value: null },
  { label: 'Con cuenta', value: true },
  { label: 'Sin cuenta', value: false },
]
const columns = [
  { name: 'full_name', label: 'Nombre', field: 'full_name', align: 'left', sortable: false },
  { name: 'identification_number', label: 'Identificación', field: (row) => `${row.identification_type} ${row.identification_number}`, align: 'left' },
  { name: 'email', label: 'Correo', field: 'email', align: 'left' },
  { name: 'phone', label: 'Teléfono', field: 'phone', align: 'left' },
  { name: 'account', label: 'Cuenta', field: (row) => row.bank_type?.name ? `${row.bank_type.name} · ${row.account_number || '—'}` : '—', align: 'left' },
  { name: 'is_active', label: 'Estado', field: 'is_active', align: 'left' },
  { name: 'user', label: 'Acceso', field: 'user', align: 'left' },
  { name: 'actions', label: '', field: 'actions', align: 'right' },
]

function isCurrentPerson(person) {
  return Number(auth.user?.person?.id) === Number(person?.id)
}

function emptyPerson() {
  return {
    first_name: '',
    last_name: '',
    identification_type: 'CC',
    identification_number: '',
    phone: '',
    email: '',
    address: '',
    bank_type_id: null,
    account_number: '',
  }
}

function emptyAccount() {
  return { role: 'socio', password: '', password_confirmation: '', is_active: true, reset: false }
}

async function load(page = pagination.value.page) {
  loading.value = true
  try {
    const result = await listPeople({
      search: filters.search || undefined,
      is_active: filters.is_active,
      has_account: filters.has_account,
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

function openPerson(person = null) {
  editing.value = Boolean(person)
  Object.assign(personForm, person ? {
    id: person.id,
    first_name: person.first_name,
    last_name: person.last_name,
    identification_type: person.identification_type,
    identification_number: person.identification_number,
    phone: person.phone,
    email: person.email,
    address: person.address || '',
    bank_type_id: person.bank_type_id || null,
    account_number: person.account_number || '',
  } : emptyPerson())
  personDialog.value = true
}

function openAccount(person) {
  selectedPerson.value = person
  Object.assign(accountForm, {
    role: person.user?.role || 'socio',
    password: '',
    password_confirmation: '',
    is_active: person.user?.is_active ?? true,
    reset: false,
  })
  showPassword.value = false
  showPasswordConfirm.value = false
  accountDialog.value = true
}

async function savePerson() {
  saving.value = true
  try {
    if (editing.value) {
      await updatePerson(personForm.id, personForm)
      Notify.create({ type: 'positive', message: 'Persona actualizada.' })
    } else {
      await createPerson(personForm)
      Notify.create({ type: 'positive', message: 'Persona creada.' })
    }
    personDialog.value = false
    await load()
  } catch (error) {
    Notify.create({ type: 'negative', message: errorMessage(error) })
  } finally {
    saving.value = false
  }
}

async function toggleStatus(person) {
  $q.dialog({
    title: person.is_active ? 'Desactivar persona' : 'Activar persona',
    message: person.is_active
      ? 'La persona no podrá asociarse a ciclos nuevos y su cuenta quedará inactiva.'
      : 'La persona volverá a quedar disponible.',
    cancel: true,
    persistent: true,
  }).onOk(async () => {
    try {
      await updatePersonStatus(person.id, !person.is_active)
      Notify.create({ type: 'positive', message: 'Estado actualizado.' })
      await load()
    } catch (error) {
      Notify.create({ type: 'negative', message: errorMessage(error) })
    }
  })
}

async function saveAccount() {
  saving.value = true
  try {
    if (!selectedPerson.value.user) {
      await createAccount(selectedPerson.value.id, {
        role: accountForm.role,
        password: accountForm.password,
        password_confirmation: accountForm.password_confirmation,
        is_active: true,
      })
    } else {
      await updateAccount(selectedPerson.value.id, {
        role: accountForm.role,
        is_active: accountForm.is_active,
      })
      if (accountForm.reset) {
        await resetPassword(selectedPerson.value.id, {
          password: accountForm.password,
          password_confirmation: accountForm.password_confirmation,
        })
      }
    }
    Notify.create({ type: 'positive', message: 'Cuenta actualizada.' })
    accountDialog.value = false
    await load()
  } catch (error) {
    Notify.create({ type: 'negative', message: errorMessage(error) })
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  try {
    bankTypes.value = await listBankTypes()
  } catch (error) {
    Notify.create({ type: 'negative', message: errorMessage(error) })
  }

  await load(1)
})
</script>

<style scoped>
.person-form-card,
.account-form-card {
  width: min(640px, 96vw);
}

.account-form-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
}

.account-form-grid :deep(.q-field),
.account-form-grid .password-hint {
  width: 100%;
}

.person-form-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 16px;
}

.person-form-grid :deep(.q-field) {
  width: 100%;
}

@media (min-width: 600px) {
  .person-form-grid {
    grid-template-columns: 1fr 1fr;
  }

  .person-form-grid .span-2 {
    grid-column: 1 / -1;
  }
}
</style>

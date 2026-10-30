<template>
  <q-page class="flex flex-center q-pa-md">
    <q-card class="login-card q-pa-lg" flat bordered>
      <div class="text-center q-mb-lg">
        <q-icon name="savings" color="primary" size="48px" />
        <div class="text-h5 text-weight-bold q-mt-sm">Miah</div>
        <div class="text-grey-7">Ingrese con su correo y contraseña</div>
      </div>

      <q-form @submit.prevent="submit">
        <q-input
          v-model="email"
          type="email"
          label="Correo electrónico"
          outlined
          class="q-mb-md"
          :error="Boolean(errors.email)"
          :error-message="errors.email"
          lazy-rules
          :rules="[val => !!val || 'El correo es obligatorio']"
        />
        <q-input
          v-model="password"
          :type="showPassword ? 'text' : 'password'"
          label="Contraseña"
          outlined
          class="q-mb-md"
          :error="Boolean(errors.password)"
          :error-message="errors.password"
          lazy-rules
          :rules="[val => !!val || 'La contraseña es obligatoria']"
        >
          <template #append>
            <q-icon
              :name="showPassword ? 'visibility_off' : 'visibility'"
              class="cursor-pointer"
              @click="showPassword = !showPassword"
            />
          </template>
        </q-input>

        <q-banner v-if="formError" class="bg-red-1 text-negative q-mb-md" rounded>
          {{ formError }}
        </q-banner>

        <q-btn
          type="submit"
          color="primary"
          unelevated
          class="full-width"
          size="lg"
          :loading="loading"
          label="Iniciar sesión"
        />
      </q-form>
    </q-card>
  </q-page>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { errorMessage } from '@/utils/format'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const email = ref('')
const password = ref('')
const showPassword = ref(false)
const loading = ref(false)
const formError = ref('')
const errors = ref({})

async function submit() {
  loading.value = true
  formError.value = ''
  errors.value = {}

  try {
    await auth.login(email.value, password.value)
    await router.replace(route.query.redirect || auth.homeRoute())
  } catch (error) {
    errors.value = error.response?.data?.errors || {}
    formError.value = errorMessage(error, 'No fue posible iniciar sesión.')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.login-card {
  width: 100%;
  max-width: 420px;
  border-radius: 24px;
}
</style>

<template>
  <q-layout view="hHh Lpr lFf">
    <q-header class="app-header">
      <q-toolbar>
        <q-btn flat dense round icon="menu" aria-label="Menú" @click="leftDrawerOpen = !leftDrawerOpen" />
        <q-toolbar-title class="text-weight-bold">Miah</q-toolbar-title>
        <div class="gt-xs text-caption q-mr-md">{{ auth.fullName }}</div>
        <q-btn flat round icon="logout" aria-label="Cerrar sesión" @click="logout" />
      </q-toolbar>
    </q-header>

    <q-drawer
      v-model="leftDrawerOpen"
      show-if-above
      bordered
      class="app-drawer"
      :width="280"
    >
      <div class="q-pa-lg">
        <div class="text-h6 text-weight-bold">{{ auth.fullName }}</div>
        <div class="text-caption text-grey-7">{{ auth.isAdmin ? 'Administrador' : 'Socio' }}</div>
      </div>

      <q-list padding>
        <q-item
          v-for="link in links"
          :key="link.to"
          clickable
          :to="link.to"
          exact
          active-class="app-link-active"
        >
          <q-item-section avatar>
            <q-icon :name="link.icon" />
          </q-item-section>
          <q-item-section>{{ link.label }}</q-item-section>
        </q-item>
      </q-list>
    </q-drawer>

    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/stores/auth'

const $q = useQuasar()
const router = useRouter()
const auth = useAuthStore()
const leftDrawerOpen = ref(false)

const links = computed(() => auth.isAdmin
  ? [
      { label: 'Dashboard', icon: 'dashboard', to: '/admin' },
      { label: 'Personas', icon: 'groups', to: '/admin/personas' },
      { label: 'Ciclos de ahorro', icon: 'savings', to: '/admin/ciclos' },
      { label: 'Pagos reportados', icon: 'receipt_long', to: '/admin/pagos-reportados' },
    ]
  : [
      { label: 'Mi panel', icon: 'home', to: '/socio' },
      { label: 'Mis ciclos', icon: 'event_repeat', to: '/socio/ciclos' },
    ])

async function logout() {
  $q.dialog({
    title: 'Cerrar sesión',
    message: '¿Desea salir de la aplicación?',
    cancel: { flat: true, label: 'Cancelar' },
    ok: { unelevated: true, color: 'primary', label: 'Salir' },
  }).onOk(async () => {
    await auth.logout()
    await router.push('/login')
  })
}
</script>

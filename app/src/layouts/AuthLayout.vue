<template>
  <q-layout view="hHh lpR fFf" class="auth-layout">
    <q-page-container>
      <router-view />
    </q-page-container>
  </q-layout>
</template>

<script setup>
</script>

<style scoped>
.auth-layout {
  background:
    radial-gradient(circle at top right, rgba(15, 118, 110, 0.18), transparent 32%),
    linear-gradient(180deg, #f4f7f6 0%, #e8eeec 100%);
}
</style>

<template>
  <div class="row justify-content-center">
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header fw-semibold">Mi perfil</div>
        <div class="card-body">
          <dl class="row mb-3">
            <dt class="col-sm-4">Nombre</dt>
            <dd class="col-sm-8">{{ auth.user?.name }}</dd>
            <dt class="col-sm-4">Correo</dt>
            <dd class="col-sm-8">{{ auth.user?.email }}</dd>
            <dt class="col-sm-4">Rol</dt>
            <dd class="col-sm-8 text-capitalize">{{ auth.user?.role }}</dd>
            <dt class="col-sm-4">Miembro desde</dt>
            <dd class="col-sm-8">{{ createdAt }}</dd>
          </dl>

          <hr class="border-secondary" />
          <h6 class="mb-3">Cambiar contraseña</h6>
          <form @submit.prevent="changePassword">
            <div class="mb-3">
              <label class="form-label">Contraseña actual</label>
              <input v-model="old_password" type="password" class="form-control" required />
            </div>
            <div class="mb-3">
              <label class="form-label">Nueva contraseña</label>
              <input v-model="password" type="password" class="form-control" minlength="6" required />
            </div>
            <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>
            <div v-if="success" class="alert alert-success py-2 small">{{ success }}</div>
            <button class="btn btn-warning w-100 fw-semibold" :disabled="sending">
              {{ sending ? 'Guardando…' : 'Actualizar contraseña' }}
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '../stores/auth'
import client from '../api/client'

const auth = useAuthStore()
const old_password = ref('')
const password = ref('')
const error = ref('')
const success = ref('')
const sending = ref(false)

const createdAt = computed(() => auth.user?.created_at ? new Date(auth.user.created_at).toLocaleDateString() : '—')

async function changePassword() {
  error.value = ''
  success.value = ''
  sending.value = true
  try {
    await client.post('/api/password', { old_password: old_password.value, password: password.value })
    success.value = 'Contraseña actualizada.'
    old_password.value = ''
    password.value = ''
  } catch (e) {
    error.value = e.response?.data?.message || 'No se pudo cambiar la contraseña.'
  } finally {
    sending.value = false
  }
}
</script>
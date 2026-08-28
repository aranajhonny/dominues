<template>
  <div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
      <div class="card shadow-lg">
        <div class="card-body p-4">
          <div class="text-center mb-4">
            <div class="display-4 text-warning">♦</div>
            <h1 class="h4 mb-1">Crear cuenta</h1>
            <p class="text-secondary small">Únete y compite por grandes premios</p>
          </div>
          <form @submit.prevent="submit">
            <div class="mb-3">
              <label class="form-label">Nombre</label>
              <input v-model="name" class="form-control" required />
            </div>
            <div class="mb-3">
              <label class="form-label">Correo Electrónico</label>
              <input v-model="email" type="email" class="form-control" required />
            </div>
            <div class="mb-3">
              <label class="form-label">Contraseña</label>
              <input v-model="password" type="password" class="form-control" minlength="6" required />
            </div>
            <div class="mb-3">
              <label class="form-label">Confirmar contraseña</label>
              <input v-model="password_confirmation" type="password" class="form-control" required />
            </div>
            <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>
            <button class="btn btn-warning w-100 fw-semibold" :disabled="auth.loading">
              {{ auth.loading ? 'Creando…' : 'Registrarme' }}
            </button>
          </form>
          <div class="text-center mt-3">
            <router-link to="/login" class="small text-info">¿Ya tienes cuenta? Inicia sesión</router-link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const name = ref('')
const email = ref('')
const password = ref('')
const password_confirmation = ref('')
const error = ref('')

async function submit() {
  error.value = ''
  if (password.value !== password_confirmation.value) {
    error.value = 'Las contraseñas no coinciden.'
    return
  }
  try {
    await auth.register({ name: name.value, email: email.value, password: password.value, password_confirmation: password_confirmation.value })
    router.push('/dashboard')
  } catch (e) {
    error.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || 'No se pudo crear la cuenta.'
  }
}
</script>
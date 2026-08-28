<template>
  <div class="row justify-content-center">
    <div class="col-md-5 col-lg-4">
      <div class="card shadow-lg">
        <div class="card-body p-4">
          <div class="text-center mb-4">
            <div class="display-4 text-warning">♦</div>
            <h1 class="h4 mb-1">Bienvenido de vuelta</h1>
            <p class="text-secondary small">Inicia sesión para continuar jugando</p>
          </div>
          <form @submit.prevent="submit" v-if="!auth.user">
            <div class="mb-3">
              <label class="form-label">Correo Electrónico</label>
              <input v-model="email" type="email" class="form-control" required autocomplete="email" />
            </div>
            <div class="mb-3">
              <label class="form-label">Contraseña</label>
              <input v-model="password" type="password" class="form-control" required autocomplete="current-password" />
            </div>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="remember" />
              <label class="form-check-label small" for="remember">Recordar sesión</label>
            </div>
            <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>
            <button class="btn btn-warning w-100 fw-semibold" :disabled="auth.loading">
              {{ auth.loading ? 'Entrando…' : 'Continuar' }}
            </button>
          </form>
          <div class="text-center mt-3">
            <router-link to="/register" class="small text-info">¿No tienes cuenta? Regístrate</router-link>
          </div>
        </div>
      </div>
      <p class="text-center text-secondary small mt-3 mb-0">Acceso restringido al personal autorizado de Dominues.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const router = useRouter()
const email = ref('')
const password = ref('')
const error = ref('')

onMounted(async () => {
  if (auth.token) router.push('/dashboard')
})

async function submit() {
  error.value = ''
  try {
    await auth.login(email.value, password.value)
    router.push('/dashboard')
  } catch (e) {
    error.value = e.response?.data?.message || 'Credenciales inválidas.'
  }
}
</script>
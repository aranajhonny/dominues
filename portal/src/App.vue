<template>
  <div class="min-vh-100 d-flex flex-column bg-dark text-light">
    <nav v-if="auth.user" class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom border-secondary">
      <div class="container">
        <router-link to="/dashboard" class="navbar-brand fw-bold">
          <span class="text-warning">♦</span> Dominos Online
        </router-link>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
          <span class="navbar-toggler-icon"></span>
        </button>
        <div id="mainNav" class="collapse navbar-collapse">
          <ul class="navbar-nav me-auto">
            <li class="nav-item"><router-link to="/dashboard" class="nav-link">Inicio</router-link></li>
            <li class="nav-item"><router-link to="/deposits" class="nav-link">Depósitos</router-link></li>
            <li class="nav-item"><router-link to="/withdrawals" class="nav-link">Retiros</router-link></li>
            <li class="nav-item"><router-link to="/kyc" class="nav-link">Verificación</router-link></li>
            <li class="nav-item"><router-link to="/transactions" class="nav-link">Movimientos</router-link></li>
          </ul>
          <span class="navbar-text me-3">
            Saldo: <strong class="text-success" v-if="auth.user">{{ Number(auth.user.balance).toFixed(2) }}</strong> $
          </span>
          <div class="dropdown">
            <button class="btn btn-outline-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">
              {{ auth.user?.name }}
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><router-link to="/profile" class="dropdown-item">Mi perfil</router-link></li>
              <li><hr class="dropdown-divider" /></li>
              <li><button class="dropdown-item text-danger" @click="logout">Cerrar sesión</button></li>
            </ul>
          </div>
        </div>
      </div>
    </nav>

    <main class="container py-4 flex-grow-1">
      <router-view />
    </main>

    <footer class="py-3 text-center text-secondary small border-top border-secondary">
      Dominos Online © {{ year }} — Dominues · Partidas en tiempo real · Acceso restringido al personal autorizado
    </footer>
  </div>
</template>

<script setup>
import { useAuthStore } from './stores/auth'
import { useRouter } from 'vue-router'

const auth = useAuthStore()
const router = useRouter()
const year = new Date().getFullYear()

function logout() {
  auth.logout()
  router.push('/login')
}
</script>

<style>
body { background-color: #1b1f2a; }
.card { background-color: #242a3a; border-color: #3a4157; }
.form-control, .form-select { background-color: #2b3245; border-color: #3a4157; color: #e9ecef; }
.form-control:focus, .form-select:focus { background-color: #2b3245; color: #e9ecef; }
.table { --bs-table-bg: transparent; color: #e9ecef; }
.nav-link.router-link-exact-active { color: #f5b942 !important; font-weight: 600; }
</style>
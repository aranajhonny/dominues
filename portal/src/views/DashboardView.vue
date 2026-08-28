<template>
  <div>
    <div class="row g-3 mb-4">
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-body">
            <div class="text-secondary small text-uppercase">Saldo disponible</div>
            <div class="fs-2 fw-bold text-success">$ {{ Number(auth.user?.balance || 0).toFixed(2) }}</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-body">
            <div class="text-secondary small text-uppercase">Monto reservado</div>
            <div class="fs-2 fw-bold text-warning">$ {{ Number(auth.user?.reserved_balance || 0).toFixed(2) }}</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card h-100">
          <div class="card-body">
            <div class="text-secondary small text-uppercase">Verificación de identidad</div>
            <div class="mt-2">
              <span :class="badgeClass" class="badge fs-6">{{ kycStatus }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header fw-semibold">Mesas disponibles</div>
      <div class="card-body p-0">
        <div v-if="loading" class="p-4 text-center text-secondary">Cargando mesas…</div>
        <div v-else-if="games.length === 0" class="p-4 text-center text-secondary">No hay juegos activos ahora.</div>
        <div v-else class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead>
              <tr class="text-secondary small">
                <th>Juego</th>
                <th>Modo</th>
                <th>Apuesta mínima</th>
                <th>Jugadores</th>
                <th>Estado</th>
                <th class="text-end">Acción</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="g in games" :key="g.id">
                <td class="fw-semibold">{{ g.name }}</td>
                <td>{{ g.mode }}</td>
                <td>$ {{ Number(g.min_bet).toFixed(2) }}</td>
                <td>{{ g.players_count || 0 }} / {{ g.max_players }}</td>
                <td><span class="badge bg-success">Activo</span></td>
                <td class="text-end">
                  <button class="btn btn-warning btn-sm fw-semibold" @click="play(g)" :disabled="launching">
                    {{ launching ? 'Conectando…' : 'Jugar' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div v-if="error" class="alert alert-danger">{{ error }}</div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import client from '../api/client'

const auth = useAuthStore()
const games = ref([])
const loading = ref(true)
const launching = ref(false)
const error = ref('')

const kycStatus = computed(() => {
  const s = auth.user?.kyc_status
  if (!s || s === 'none') return { label: 'No verificado', cls: 'bg-secondary' }
  if (s === 'pending') return { label: 'Pendiente', cls: 'bg-warning text-dark' }
  if (s === 'approved') return { label: 'Aprobado', cls: 'bg-success' }
  return { label: 'Rechazado', cls: 'bg-danger' }
})
const badgeClass = kycStatus.value.cls
const kycLabel = kycStatus.value.label

onMounted(async () => {
  try {
    await auth.fetchMe()
    const { data } = await client.get('/api/games')
    games.value = data.games || data
  } catch (e) {
    error.value = e.response?.data?.message || 'Error cargando mesas.'
  } finally {
    loading.value = false
  }
})

async function play(game) {
  launching.value = true
  error.value = ''
  try {
    const { data } = await client.post('/api/game/session', { game_id: game.id })
    const url = `${import.meta.env.VITE_GAME_URL || 'http://localhost:8081'}/?token=${data.token}`
    window.open(url, '_blank')
  } catch (e) {
    error.value = e.response?.data?.message || 'No se pudo iniciar la sesión de juego.'
  } finally {
    launching.value = false
  }
}
</script>
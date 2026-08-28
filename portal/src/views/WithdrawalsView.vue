<template>
  <div class="row">
    <div class="col-lg-5">
      <div class="card">
        <div class="card-header fw-semibold">Solicitar retiro</div>
        <div class="card-body">
          <div v-if="kycBlocked" class="alert alert-warning small">
            <strong>Verificación de identidad requerida.</strong> Debes completar y tener <strong>aprobada</strong> tu
            verificación antes de solicitar un retiro. <router-link to="/kyc" class="text-info">Ir a verificación</router-link>
          </div>
          <div v-if="!kycBlocked" class="mb-3">
            <div class="text-secondary small">Saldo disponible</div>
            <div class="fs-3 fw-bold text-success">$ {{ Number(auth.user?.balance || 0).toFixed(2) }}</div>
            <div class="text-secondary small mt-1">Requisito de apuesta: {{ playthroughText }}</div>
            <div v-if="!worksRequirement" class="text-danger small mt-1">
              Aún no cumples el volumen de juego mínimo ({{ playthroughPercent }}% de tus depósitos netos).
            </div>
          </div>
          <form v-if="!kycBlocked" @submit.prevent="submit">
            <div class="mb-3">
              <label class="form-label">Monto (USD)</label>
              <input v-model.number="amount" type="number" min="1" step="0.01" class="form-control" required />
            </div>
            <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>
            <div v-if="success" class="alert alert-success py-2 small">{{ success }}</div>
            <button class="btn btn-warning w-100 fw-semibold" :disabled="sending || !worksRequirement">
              {{ sending ? 'Enviando…' : 'Solicitar retiro' }}
            </button>
            <div class="form-text text-secondary mt-2">
              Al solicitar, el monto se <strong>reserva</strong> y descuenta de tu saldo disponible hasta la aprobación o rechazo.
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-7 mt-3 mt-lg-0">
      <div class="card">
        <div class="card-header fw-semibold">Mis retiros</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead>
                <tr class="text-secondary small">
                  <th>ID</th><th>Fecha</th><th class="text-end">Monto</th><th>Estado</th><th>Motivo</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="w in withdrawals" :key="w.id">
                  <td>#{{ w.id }}</td>
                  <td class="small">{{ new Date(w.created_at).toLocaleString() }}</td>
                  <td class="text-end">$ {{ Number(w.amount).toFixed(2) }}</td>
                  <td><span :class="statusBadge(w.status)" class="badge">{{ w.status }}</span></td>
                  <td class="small text-secondary">{{ w.admin_note || '—' }}</td>
                </tr>
                <tr v-if="withdrawals.length === 0"><td colspan="5" class="text-center text-secondary py-4">Sin retiros aún.</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import client from '../api/client'

const auth = useAuthStore()
const amount = ref(10)
const error = ref('')
const success = ref('')
const sending = ref(false)
const withdrawals = ref([])
const kycStatus = ref(null)
const playthrough = ref({ percent: 100, jokado: 0, required: 0 })

const kycBlocked = computed(() => kycStatus.value !== 'approved')
const worksRequirement = computed(() => playthrough.value.jokado >= playthrough.value.required)
const playthroughText = computed(() => {
  const p = playthrough.value
  return p.percent >= 100 ? `Jugar ${p.percent}% de tus depósitos netos (${p.jokado.toFixed(2)} / ${p.required.toFixed(2)})` : 'No configurado'
})

function statusBadge(s) {
  return { pending: 'bg-warning text-dark', approved: 'bg-success', rejected: 'bg-danger' }[s] || 'bg-secondary'
}

onMounted(async () => {
  try {
    await auth.fetchMe()
    const [kycRes, wdRes, ptRes] = await Promise.all([
      client.get('/api/kyc'),
      client.get('/api/withdrawals'),
      client.get('/api/withdraw/requirements')
    ])
    kycStatus.value = kycRes.data.kyc?.status || 'none'
    withdrawals.value = wdRes.data.withdrawals || []
    playthrough.value = ptRes.data.requirements || playthrough.value
  } catch (_) { /* handled by 401 interceptor */ }
})

async function submit() {
  error.value = ''
  success.value = ''
  sending.value = true
  try {
    const { data } = await client.post('/api/withdrawals', { amount: amount.value })
    withdrawals.value.unshift(data.withdrawal)
    success.value = 'Retiro solicitado. El monto queda reservado.'
    await auth.fetchMe()
    amount.value = 10
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al solicitar el retiro.'
  } finally {
    sending.value = false
  }
}
</script>
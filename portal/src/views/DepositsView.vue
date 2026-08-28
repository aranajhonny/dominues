<template>
  <div class="row">
    <div class="col-lg-5">
      <div class="card">
        <div class="card-header fw-semibold">Solicitar depósito</div>
        <div class="card-body">
          <form @submit.prevent="submit">
            <div class="mb-3">
              <label class="form-label">Monto (USD)</label>
              <input v-model.number="amount" type="number" min="1" step="0.01" class="form-control" required />
            </div>
            <div class="mb-3">
              <label class="form-label">Método</label>
              <select v-model="method" class="form-select">
                <option value="pago_movil">Pago móvil</option>
                <option value="bank_transfer">Transferencia bancaria</option>
                <option value="instapago">InstaPago</option>
                <option value="payku">Payku</option>
                <option value="blockbee" disabled>BlockBee (temporalmente desactivado)</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Referencia / Nº de operación</label>
              <input v-model="reference" class="form-control" required />
            </div>
            <div class="mb-3">
              <label class="form-label">Comprobante (imagen)</label>
              <input type="file" accept="image/*" class="form-control" @change="onFile" />
              <div class="form-text text-secondary">Formato PNG/JPG. Se guarda cifrado como soporte.</div>
            </div>
            <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>
            <div v-if="success" class="alert alert-success py-2 small">{{ success }}</div>
            <button class="btn btn-warning w-100 fw-semibold" :disabled="sending">
              {{ sending ? 'Enviando…' : 'Solicitar depósito' }}
            </button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-lg-7 mt-3 mt-lg-0">
      <div class="card">
        <div class="card-header fw-semibold">Mis depósitos</div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead>
                <tr class="text-secondary small">
                  <th>ID</th><th>Fecha</th><th>Método</th><th class="text-end">Monto</th><th>Estado</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="d in deposits" :key="d.id">
                  <td>#{{ d.id }}</td>
                  <td class="small">{{ new Date(d.created_at).toLocaleString() }}</td>
                  <td class="small">{{ d.method }}</td>
                  <td class="text-end">$ {{ Number(d.amount).toFixed(2) }}</td>
                  <td><span :class="statusBadge(d.status)" class="badge">{{ d.status }}</span></td>
                </tr>
                <tr v-if="deposits.length === 0"><td colspan="5" class="text-center text-secondary py-4">Sin depósitos aún.</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import client from '../api/client'

const auth = useAuthStore()
const amount = ref(10)
const method = ref('pago_movil')
const reference = ref('')
const proofBase64 = ref(null)
const error = ref('')
const success = ref('')
const sending = ref(false)
const deposits = ref([])

function onFile(e) {
  const file = e.target.files[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = () => (proofBase64.value = String(reader.result).split(',')[1] || reader.result)
  reader.readAsDataURL(file)
}

function statusBadge(s) {
  return {
    pending: 'bg-warning text-dark',
    approved: 'bg-success',
    rejected: 'bg-danger'
  }[s] || 'bg-secondary'
}

onMounted(async () => {
  try {
    const { data } = await client.get('/api/deposits')
    deposits.value = data.deposits || data
  } catch (_) { /* non-critical */ }
})

async function submit() {
  error.value = ''
  success.value = ''
  sending.value = true
  try {
    const { data } = await client.post('/api/deposits', {
      amount: amount.value,
      method: method.value,
      reference: reference.value,
      proof_base64: proofBase64.value
    })
    success.value = 'Solicitud registrada. Se acreditará al aprobarse.'
    deposits.value.unshift(data.deposit)
    await auth.fetchMe()
    amount.value = 10
    reference.value = ''
    proofBase64.value = null
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al registrar el depósito.'
  } finally {
    sending.value = false
  }
}
</script>
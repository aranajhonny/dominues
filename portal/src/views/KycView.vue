<template>
  <div class="row justify-content-center">
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header fw-semibold">Verificación de identidad (KYC)</div>
        <div class="card-body">
          <div v-if="kyc" class="mb-4">
            <div class="d-flex justify-content-between align-items-center">
              <span class="text-secondary">Estado actual:</span>
              <span :class="badgeClass" class="badge fs-6">{{ kycLabel }}</span>
            </div>
            <div v-if="kyc.status === 'pending'" class="alert alert-warning small mt-3 mb-0">
              Tu documento está en revisión. Debe ser <strong>aprobado</strong> para poder solicitar retiros.
            </div>
            <div v-else-if="kyc.status === 'rejected'" class="alert alert-danger small mt-3 mb-0">
              <strong>Rechazado:</strong> {{ kyc.admin_note || 'Motivo no indicado.' }} Puedes volver a enviar un documento.
            </div>
            <div v-else class="alert alert-success small mt-3 mb-0">Identidad aprobada. Ya puedes solicitar retiros.</div>
          </div>

          <form v-if="allowSubmit" @submit.prevent="submit">
            <div class="mb-3">
              <label class="form-label">Tipo de documento</label>
              <select v-model="document_type" class="form-select">
                <option value="cedula">Cédula de identidad</option>
                <option value="passport">Pasaporte</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Número de documento</label>
              <input v-model="document_number" class="form-control" required />
            </div>
            <div class="mb-3">
              <label class="form-label">Imagen del documento (anverso)</label>
              <input type="file" accept="image/*" class="form-control" @change="onFile" required />
            </div>
            <div v-if="error" class="alert alert-danger py-2 small">{{ error }}</div>
            <div v-if="success" class="alert alert-success py-2 small">{{ success }}</div>
            <button class="btn btn-warning w-100 fw-semibold" :disabled="sending">
              {{ sending ? 'Enviando…' : 'Enviar documento' }}
            </button>
          </form>
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
const kyc = ref(null)
const document_type = ref('cedula')
const document_number = ref('')
const imageBase64 = ref(null)
const error = ref('')
const success = ref('')
const sending = ref(false)

const kycLabel = computed(() => {
  if (!kyc.value) return 'No verificado'
  return { pending: 'Pendiente', approved: 'Aprobado', rejected: 'Rechazado' }[kyc.value.status] || '—'
})
const badgeClass = computed(() => {
  if (!kyc.value) return 'bg-secondary'
  return { pending: 'bg-warning text-dark', approved: 'bg-success', rejected: 'bg-danger' }[kyc.value.status] || 'bg-secondary'
})
const allowSubmit = computed(() => !kyc.value || kyc.value.status !== 'pending')

function onFile(e) {
  const file = e.target.files[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = () => (imageBase64.value = String(reader.result).split(',')[1] || reader.result)
  reader.readAsDataURL(file)
}

onMounted(async () => {
  try {
    const { data } = await client.get('/api/kyc')
    kyc.value = data.kyc
  } catch (_) { /* pending or none */ }
  if (auth.user && !auth.user.kyc_status) await auth.fetchMe()
})

async function submit() {
  error.value = ''
  success.value = ''
  sending.value = true
  try {
    const { data } = await client.post('/api/kyc', {
      document_type: document_type.value,
      document_number: document_number.value,
      document_image_base64: imageBase64.value
    })
    kyc.value = data.kyc
    success.value = 'Documento enviado. Queda pendiente de revisión.'
    await auth.fetchMe()
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al enviar el documento.'
  } finally {
    sending.value = false
  }
}
</script>
<template>
  <div class="card">
    <div class="card-header fw-semibold">Historial de movimientos</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead>
            <tr class="text-secondary small">
              <th>ID</th><th>Fecha</th><th>Tipo</th><th>Detalle</th><th class="text-end">Monto</th><th>Estado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in transactions" :key="t.id">
              <td>#{{ t.id }}</td>
              <td class="small">{{ new Date(t.created_at).toLocaleString() }}</td>
              <td><span class="badge" :class="typeBadge(t.type)">{{ t.type }}</span></td>
              <td class="small text-secondary">{{ t.meta?.reference || t.reference || '—' }}</td>
              <td class="text-end fw-semibold" :class="Number(t.amount) >= 0 ? 'text-success' : 'text-danger'">
                {{ Number(t.amount) >= 0 ? '+' : '' }}{{ Number(t.amount).toFixed(2) }}
              </td>
              <td class="small text-capitalize">{{ t.status }}</td>
            </tr>
            <tr v-if="transactions.length === 0"><td colspan="6" class="text-center text-secondary py-4">Sin movimientos aún.</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import client from '../api/client'

const transactions = ref([])

function typeBadge(t) {
  return {
    deposit: 'bg-success',
    withdrawal: 'bg-danger',
    game_stake: 'bg-info text-dark',
    game_win: 'bg-warning text-dark',
    refund: 'bg-secondary'
  }[t] || 'bg-secondary'
}

onMounted(async () => {
  try {
    const { data } = await client.get('/api/transactions')
    transactions.value = data.transactions || []
  } catch (_) { /* intercepted */ }
})
</script>
<script setup>
import { onMounted, ref } from 'vue'
import api from '@/lib/api'

const state = ref('loading')
const health = ref(null)
const error = ref(null)

async function checkBackend() {
  state.value = 'loading'
  error.value = null

  try {
    const { data } = await api.get('/health')
    health.value = data
    state.value = 'connected'
  } catch (e) {
    error.value = e.response
      ? `${e.response.status} ${e.response.statusText}`
      : e.message
    state.value = 'failed'
  }
}

onMounted(checkBackend)
</script>

<template>
  <main>
    <h1>Frontend &rarr; Backend</h1>

    <p v-if="state === 'loading'" class="status loading">Contacting the API&hellip;</p>

    <template v-else-if="state === 'connected'">
      <p class="status ok">Connected to Laravel</p>
      <dl>
        <template v-for="(value, key) in health" :key="key">
          <dt>{{ key }}</dt>
          <dd>{{ value }}</dd>
        </template>
      </dl>
    </template>

    <template v-else>
      <p class="status error">Could not reach the API &mdash; {{ error }}</p>
      <p class="hint">
        Is the Laravel dev server running? Start it with
        <code>php artisan serve</code> in <code>backend/</code>.
      </p>
    </template>

    <button type="button" :disabled="state === 'loading'" @click="checkBackend">
      Check again
    </button>
  </main>
</template>

<style scoped>
main {
  max-width: 34rem;
  margin: 4rem auto;
  padding: 0 1rem;
  font-family: system-ui, sans-serif;
  line-height: 1.5;
}

h1 {
  font-size: 1.5rem;
  margin-bottom: 1rem;
}

.status {
  font-weight: 600;
}

.status.ok {
  color: #15803d;
}

.status.error {
  color: #b91c1c;
}

.status.loading {
  color: #6b7280;
}

dl {
  display: grid;
  grid-template-columns: max-content 1fr;
  gap: 0.25rem 1rem;
  margin: 1rem 0;
}

dt {
  color: #6b7280;
}

dd {
  margin: 0;
  font-variant-numeric: tabular-nums;
}

.hint {
  color: #6b7280;
  font-size: 0.9rem;
}

code {
  font-size: 0.85em;
  background: #f3f4f6;
  padding: 0.1em 0.35em;
  border-radius: 0.25rem;
}

button {
  font: inherit;
  padding: 0.4rem 0.9rem;
  border: 1px solid #d1d5db;
  border-radius: 0.375rem;
  background: #fff;
  cursor: pointer;
}

button:disabled {
  opacity: 0.6;
  cursor: default;
}
</style>

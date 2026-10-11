<script setup>
import { computed, ref } from 'vue'
import { ArrowPathIcon, CheckCircleIcon, ExclamationTriangleIcon, QuestionMarkCircleIcon } from '@heroicons/vue/24/outline'
import raw from '../../VERSION?raw'
import api from '@/lib/api'
import { locale, t } from '@/lib/i18n'

/*
 * Site health (every signed-in user; admins also get internals + "Run migrations"): API, database, migrations, storage against the
 * Supabase free-plan limit, hosting platform status and configuration checks.
 */
const report = ref(null)
const failed = ref(false)
const loading = ref(false)
const roundTrip = ref(null)
const frontendVersion = raw.trim()

async function refresh() {
  loading.value = true
  failed.value = false
  const started = performance.now()
  try {
    const { data } = await api.get('/admin/health')
    report.value = data
    roundTrip.value = Math.round(performance.now() - started)
  } catch {
    failed.value = true
  } finally {
    loading.value = false
  }
}
refresh()

const nf = (opts = {}) => new Intl.NumberFormat(locale.value === 'en' ? 'en-GB' : 'lv', opts)
const mb = (bytes) => (bytes == null ? '—' : `${nf({ maximumFractionDigits: bytes < 10 * 1024 ** 2 ? 1 : 0 }).format(bytes / 1024 ** 2)} MB`)
const num = (n) => (n == null ? '—' : nf().format(n))
const time = (iso) => new Intl.DateTimeFormat(locale.value === 'en' ? 'en-GB' : 'lv', { hour: '2-digit', minute: '2-digit', second: '2-digit' }).format(new Date(iso))

const db = computed(() => report.value?.database)
const storage = computed(() => {
  const used = db.value?.size_bytes
  const limit = db.value?.limit_bytes
  if (used == null || !limit) return null
  const ratio = Math.min(1, used / limit)
  return { used, limit, ratio, status: ratio < 0.8 ? 'ok' : 'warning' }
})
const migrations = computed(() => report.value?.migrations)

/* "Run migrations": two taps (it changes the live database), then show Laravel's output. */
const confirmRun = ref(false)
const running = ref(false)
const runResult = ref(null)

async function runMigrations() {
  if (!confirmRun.value) {
    confirmRun.value = true
    return
  }
  running.value = true
  confirmRun.value = false
  try {
    const { data } = await api.post('/admin/migrate')
    runResult.value = data
  } catch (e) {
    runResult.value = e.response?.data?.output ? e.response.data : { ok: false, output: t('health.migrateFailed') }
  } finally {
    running.value = false
    await refresh()
  }
}
const checks = computed(() => report.value?.checks ?? [])
// Row counts are admin-only (the API sends null to everyone else).
const showRows = computed(() => db.value?.tables?.some((x) => x.rows != null))

/** Every problem on the page, for the summary banner. */
const problems = computed(() => {
  const r = report.value
  if (!r) return []
  return [
    r.api.status !== 'ok' && t('health.api'),
    db.value.status !== 'ok' && t('health.database'),
    migrations.value.status !== 'ok' && t('health.migrations'),
    storage.value?.status === 'warning' && t('health.storage'),
    ...r.platforms.filter((p) => p.status === 'warning').map((p) => t(`health.platform.${p.name}`)),
    ...checks.value.filter((c) => !c.ok).map((c) => t(`health.check.${c.key}`)),
  ].filter(Boolean)
})

const icons = { ok: CheckCircleIcon, warning: ExclamationTriangleIcon, error: ExclamationTriangleIcon, unknown: QuestionMarkCircleIcon }
</script>

<template>
  <div class="page wide">
    <header class="head">
      <div>
        <h1 class="page-title">{{ $t('health.title') }}</h1>
        <p v-if="report" class="checked">{{ $t('health.checkedAt', { time: time(report.checked_at) }) }}</p>
      </div>
      <button type="button" class="btn btn-ghost refresh" :disabled="loading" @click="refresh">
        <ArrowPathIcon :class="{ spinning: loading }" aria-hidden="true" />{{ $t('health.refresh') }}
      </button>
    </header>

    <p v-if="failed" class="empty-state" role="alert">{{ $t('health.unreachable') }}</p>

    <template v-if="report">
      <!-- Summary: Seagrass when everything passes, plain text listing what needs a look otherwise. -->
      <section class="summary" :class="{ ok: !problems.length }" aria-live="polite">
        <component :is="problems.length ? ExclamationTriangleIcon : CheckCircleIcon" class="summary-icon" aria-hidden="true" />
        <div>
          <p class="summary-title">{{ problems.length ? $t('health.problems', { n: problems.length }) : $t('health.allGood') }}</p>
          <p v-if="problems.length" class="summary-list">{{ problems.join(' · ') }}</p>
        </div>
      </section>

      <div class="grid">
        <!-- API -->
        <section class="card">
          <header class="card-head">
            <h2 class="section-title">{{ $t('health.api') }}</h2>
            <span class="status" :class="report.api.status">
              <component :is="icons[report.api.status]" aria-hidden="true" />{{ $t(`health.status.${report.api.status}`) }}
            </span>
          </header>
          <dl class="facts">
            <div><dt>{{ $t('health.responseTime') }}</dt><dd>{{ roundTrip }} ms</dd></div>
            <div><dt>{{ $t('health.serverTime') }}</dt><dd>{{ report.duration_ms }} ms</dd></div>
            <div><dt>{{ $t('health.environment') }}</dt><dd>{{ report.api.environment }}</dd></div>
            <div><dt>Laravel · PHP</dt><dd>{{ report.api.laravel }} · {{ report.api.php }}</dd></div>
            <div v-if="report.api.region"><dt>{{ $t('health.region') }}</dt><dd>{{ report.api.region }}</dd></div>
            <div v-if="report.api.commit"><dt>{{ $t('health.deploy') }}</dt><dd>{{ report.api.branch }} · {{ report.api.commit }}</dd></div>
            <div><dt>{{ $t('health.frontend') }}</dt><dd>{{ frontendVersion }}</dd></div>
          </dl>
        </section>

        <!-- Database (Supabase) -->
        <section class="card">
          <header class="card-head">
            <h2 class="section-title">{{ $t('health.database') }}</h2>
            <span class="status" :class="db.status">
              <component :is="icons[db.status]" aria-hidden="true" />{{ $t(`health.status.${db.status}`) }}
            </span>
          </header>
          <dl class="facts">
            <div><dt>{{ $t('health.latency') }}</dt><dd>{{ db.latency_ms ?? '—' }} ms</dd></div>
            <div><dt>{{ $t('health.engine') }}</dt><dd>{{ db.version ?? db.driver }}</dd></div>
            <div v-if="db.host"><dt>{{ $t('health.host') }}</dt><dd class="mono">{{ db.host }}</dd></div>
            <div v-if="db.connections"><dt>{{ $t('health.connections') }}</dt><dd>{{ db.connections.active }} / {{ db.connections.max }}</dd></div>
          </dl>
        </section>

        <!-- Migrations -->
        <section class="card">
          <header class="card-head">
            <h2 class="section-title">{{ $t('health.migrations') }}</h2>
            <span class="status" :class="migrations.status">
              <component :is="icons[migrations.status]" aria-hidden="true" />{{ $t(`health.status.${migrations.status}`) }}
            </span>
          </header>
          <p class="big">
            {{ migrations.ran ?? '—' }}<span class="of">/{{ migrations.total ?? '—' }}</span>
          </p>
          <p class="note">{{ migrations.pending.length ? $t('health.pending') : $t('health.migrationsDone') }}</p>
          <ul v-if="migrations.pending.length" class="pending">
            <li v-for="name in migrations.pending" :key="name" class="mono">{{ name }}</li>
          </ul>
          <!-- Only admins may change the live database; everyone else just sees what's pending. -->
          <button
            v-if="migrations.pending.length && report.can_migrate"
            type="button"
            class="btn btn-primary run"
            :disabled="running"
            @click="runMigrations"
          >
            <ArrowPathIcon v-if="running" class="spinning" aria-hidden="true" />
            {{ running ? $t('health.migrating') : confirmRun ? $t('health.migrateConfirm') : $t('health.migrate') }}
          </button>
          <div v-if="runResult" class="run-result" role="status">
            <p class="run-status">
              <component :is="runResult.ok ? CheckCircleIcon : ExclamationTriangleIcon" aria-hidden="true" />
              {{ runResult.ok ? $t('health.migrateDone') : $t('health.migrateFailed') }}
            </p>
            <pre v-if="runResult.output" class="mono">{{ runResult.output }}</pre>
          </div>
        </section>

        <!-- Storage -->
        <section class="card">
          <header class="card-head">
            <h2 class="section-title">{{ $t('health.storage') }}</h2>
            <span v-if="storage" class="status" :class="storage.status">
              <component :is="icons[storage.status]" aria-hidden="true" />{{ $t(`health.status.${storage.status}`) }}
            </span>
          </header>
          <template v-if="storage">
            <p class="big">{{ mb(storage.used) }}<span class="of"> / {{ mb(storage.limit) }}</span></p>
            <div
              class="meter"
              role="progressbar"
              :aria-valuenow="Math.round(storage.ratio * 100)"
              aria-valuemin="0"
              aria-valuemax="100"
              :aria-label="$t('health.storage')"
            >
              <span class="meter-fill" :style="{ width: `${Math.max(1, storage.ratio * 100)}%` }" />
            </div>
            <p class="note">{{ $t('health.storageNote', { pct: nf({ maximumFractionDigits: 1 }).format(storage.ratio * 100) }) }}</p>
          </template>
          <p v-else class="note">{{ $t('health.unavailable') }}</p>
        </section>

        <!-- Tables -->
        <section class="card">
          <header class="card-head">
            <h2 class="section-title">{{ $t('health.tables') }}</h2>
          </header>
          <table class="table">
            <thead>
              <tr>
                <th scope="col">{{ $t('health.table') }}</th>
                <th v-if="showRows" scope="col">{{ $t('health.rows') }}</th>
                <th v-if="db.tables?.some((x) => x.bytes != null)" scope="col">{{ $t('health.size') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in db.tables" :key="row.name">
                <td class="mono">{{ row.name }}</td>
                <td v-if="showRows">{{ num(row.rows) }}</td>
                <td v-if="db.tables.some((x) => x.bytes != null)">{{ mb(row.bytes) }}</td>
              </tr>
            </tbody>
          </table>
        </section>

        <!-- Hosting platforms -->
        <section class="card">
          <header class="card-head">
            <h2 class="section-title">{{ $t('health.platforms') }}</h2>
          </header>
          <ul class="rows">
            <li v-for="p in report.platforms" :key="p.name">
              <span>
                <span class="row-name">{{ $t(`health.platform.${p.name}`) }}</span>
                <span v-if="p.description" class="row-note">{{ p.description }}</span>
              </span>
              <span class="status" :class="p.status">
                <component :is="icons[p.status]" aria-hidden="true" />{{ $t(`health.status.${p.status}`) }}
              </span>
            </li>
          </ul>
        </section>

        <!-- Configuration -->
        <section class="card span-2">
          <header class="card-head">
            <h2 class="section-title">{{ $t('health.config') }}</h2>
          </header>
          <ul class="rows">
            <li v-for="c in checks" :key="c.key">
              <span>
                <span class="row-name">{{ $t(`health.check.${c.key}`) }}</span>
                <span v-if="c.value" class="row-note mono">{{ c.value }}</span>
              </span>
              <span class="status" :class="c.ok ? 'ok' : 'warning'">
                <component :is="c.ok ? CheckCircleIcon : ExclamationTriangleIcon" aria-hidden="true" />{{ $t(c.ok ? 'health.status.ok' : 'health.status.warning') }}
              </span>
            </li>
          </ul>
        </section>
      </div>
    </template>
  </div>
</template>

<style scoped>
.head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.checked {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: 0.85rem;
  font-weight: 600;
}

.refresh {
  flex-shrink: 0;
  height: 40px;
}

.refresh svg {
  width: 18px;
  height: 18px;
}

.spinning {
  animation: spin 0.9s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Summary banner */
.summary {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px;
  border: 1px solid var(--line);
  border-radius: 18px;
  background: var(--surface);
}

.summary.ok {
  border-color: var(--seagrass);
  background: var(--seagrass);
  color: var(--black);
}

.summary-icon {
  flex-shrink: 0;
  width: 32px;
  height: 32px;
}

.summary-title {
  margin: 0;
  font-weight: 800;
}

.summary-list {
  margin: 4px 0 0;
  color: var(--muted);
  font-size: 0.85rem;
}

.grid {
  display: grid;
  gap: 10px;
}

.card-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

/* Status: icon + word, never colour alone. OK is Seagrass; problems stay in text colour (brand rule). */
.status {
  display: inline-flex;
  flex-shrink: 0;
  align-items: center;
  gap: 4px;
  font-size: 0.8rem;
  font-weight: 800;
}

.status svg {
  width: 18px;
  height: 18px;
}

.status.ok {
  color: var(--seagrass);
}

.status.warning,
.status.error {
  color: var(--fg);
}

.status.unknown {
  color: var(--muted);
}

.facts {
  display: grid;
  gap: 8px;
  margin: 0;
}

.facts div {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  font-size: 0.85rem;
}

.facts dt {
  color: var(--muted);
  font-weight: 600;
}

.facts dd {
  min-width: 0;
  margin: 0;
  overflow: hidden;
  font-weight: 700;
  text-align: right;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 0.8em;
}

.big {
  margin: 0;
  font-size: 1.7rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  letter-spacing: -0.03em;
}

.of {
  color: var(--muted);
  font-size: 1rem;
}

.note {
  margin: 6px 0 0;
  color: var(--muted);
  font-size: 0.85rem;
}

.pending {
  margin: 8px 0 0;
  padding-left: 18px;
  font-size: 0.85rem;
}

.run {
  width: 100%;
  height: 40px;
  margin-top: 12px;
}

.run svg {
  width: 16px;
  height: 16px;
}

.run-result {
  margin-top: 12px;
}

.run-status {
  display: flex;
  align-items: center;
  gap: 6px;
  margin: 0 0 6px;
  font-size: 0.85rem;
  font-weight: 700;
}

.run-status svg {
  width: 18px;
  height: 18px;
  color: var(--seagrass);
}

.run-result pre {
  max-height: 160px;
  margin: 0;
  padding: 10px;
  overflow: auto;
  border-radius: 10px;
  background: var(--bg);
  white-space: pre-wrap;
  word-break: break-word;
}

.meter {
  height: 8px;
  margin-top: 12px;
  overflow: hidden;
  border-radius: 4px;
  background: var(--line);
}

.meter-fill {
  display: block;
  height: 100%;
  border-radius: 4px;
  background: var(--seagrass);
}

.table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.85rem;
}

.table th {
  padding: 0 0 6px;
  color: var(--muted);
  font-size: 0.72rem;
  font-weight: 700;
  text-align: left;
  text-transform: uppercase;
}

.table td {
  padding: 6px 0;
  border-top: 1px solid var(--line);
  font-weight: 600;
}

.table th:not(:first-child),
.table td:not(:first-child) {
  text-align: right;
  font-variant-numeric: tabular-nums;
}

.rows {
  display: grid;
  margin: 0;
  padding: 0;
  list-style: none;
}

.rows li {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 10px 0;
  border-top: 1px solid var(--line);
}

.rows li:first-child {
  padding-top: 0;
  border-top: 0;
}

.rows li > span:first-child {
  display: grid;
  gap: 2px;
  min-width: 0;
}

.row-name {
  font-size: 0.9rem;
  font-weight: 700;
}

.row-note {
  overflow: hidden;
  color: var(--muted);
  font-size: 0.8rem;
  text-overflow: ellipsis;
  white-space: nowrap;
}

@media (min-width: 860px) {
  .grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .span-2 {
    grid-column: span 2;
  }
}

@media (prefers-reduced-motion: reduce) {
  .spinning {
    animation: none;
  }
}
</style>

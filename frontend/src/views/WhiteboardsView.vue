<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline'
import BottomSheet from '@/components/BottomSheet.vue'
import { locale, t } from '@/lib/i18n'
import { createBoard, deleteBoard, listBoards, saveBoard } from '@/lib/whiteboards'

// The board list: a preview card per board, newest first.
const router = useRouter()
const boards = ref(null)
const failed = ref(false)
const creating = ref(false)
const confirming = ref(null)
const renaming = ref(null)

listBoards()
  .then((list) => (boards.value = list))
  .catch(() => (failed.value = true))

/* "New board" asks for a name first; an empty name gets the server default. */
const naming = ref(false)
const newTitle = ref('')

function askName() {
  newTitle.value = ''
  naming.value = true
}

async function create() {
  creating.value = true
  try {
    const board = await createBoard(newTitle.value.trim() || null)
    naming.value = false
    await router.push({ name: 'whiteboard', params: { id: board.id } })
  } catch {
    failed.value = true
  } finally {
    creating.value = false
  }
}

async function remove(board) {
  if (confirming.value !== board.id) {
    confirming.value = board.id
    return
  }
  await deleteBoard(board.id)
  boards.value = boards.value.filter((b) => b.id !== board.id)
  confirming.value = null
}

async function rename(board, title) {
  renaming.value = null
  const next = title.trim()
  if (!next || next === board.title) return
  board.title = next
  await saveBoard(board.id, { title: next }).catch(() => {})
}

/** "edited 2 hours ago" in the interface language. */
function edited(iso) {
  const minutes = Math.round((new Date(iso) - Date.now()) / 60000)
  const rtf = new Intl.RelativeTimeFormat(locale.value, { numeric: 'auto' })
  if (Math.abs(minutes) < 60) return rtf.format(minutes, 'minute')
  if (Math.abs(minutes) < 60 * 24) return rtf.format(Math.round(minutes / 60), 'hour')
  return rtf.format(Math.round(minutes / 1440), 'day')
}
</script>

<template>
  <div class="page wide">
    <header class="head">
      <h1 class="page-title">{{ $t('whiteboard.title') }}</h1>
      <button type="button" class="btn btn-primary new" :disabled="creating" @click="askName">
        <PlusIcon aria-hidden="true" />{{ $t('whiteboard.new') }}
      </button>
    </header>

    <p v-if="failed" class="empty-state" role="alert">{{ $t('auth.errorGeneric') }}</p>

    <div v-else-if="boards && !boards.length" class="empty-state">
      <span>{{ $t('whiteboard.empty') }}</span>
      <button type="button" class="btn btn-primary" :disabled="creating" @click="askName">{{ $t('whiteboard.new') }}</button>
    </div>

    <ul v-else-if="boards" class="grid">
      <li v-for="board in boards" :key="board.id" class="board">
        <RouterLink :to="{ name: 'whiteboard', params: { id: board.id } }" class="thumb" :aria-label="board.title">
          <img v-if="board.preview" :src="board.preview" alt="" />
          <PencilSquareIcon v-else class="thumb-empty" aria-hidden="true" />
        </RouterLink>
        <div class="meta">
          <input
            v-if="renaming === board.id"
            class="input rename"
            :value="board.title"
            maxlength="120"
            :aria-label="$t('whiteboard.rename')"
            @keydown.enter="$event.target.blur()"
            @blur="rename(board, $event.target.value)"
          />
          <button v-else type="button" class="name" :title="$t('whiteboard.rename')" @click="renaming = board.id">
            {{ board.title }}
          </button>
          <span class="when">{{ edited(board.updated_at) }}</span>
        </div>
        <button
          type="button"
          class="icon-btn bare delete"
          :class="{ confirm: confirming === board.id }"
          :aria-label="confirming === board.id ? t('goal.confirmDelete') : t('goal.delete')"
          :title="confirming === board.id ? t('goal.confirmDelete') : t('goal.delete')"
          @click="remove(board)"
          @blur="confirming === board.id && (confirming = null)"
        >
          <TrashIcon aria-hidden="true" />
        </button>
      </li>
    </ul>

    <BottomSheet :open="naming" :title="$t('whiteboard.new')" @close="naming = false">
      <form class="name-form" @submit.prevent="create">
        <label class="form-field">
          <span class="form-label">{{ $t('whiteboard.boardTitle') }}</span>
          <input
            v-model="newTitle"
            autofocus
            class="input"
            type="text"
            maxlength="120"
            enterkeyhint="done"
            :placeholder="$t('whiteboard.untitled')"
          />
        </label>
        <button type="submit" class="btn btn-primary btn-lg" :disabled="creating">
          {{ creating ? $t('goal.saving') : $t('whiteboard.create') }}
        </button>
      </form>
    </BottomSheet>
  </div>
</template>

<style scoped>
.name-form {
  display: grid;
  gap: 18px;
}

.head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.new {
  height: 40px;
}

.new svg {
  width: 18px;
  height: 18px;
}

.grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 12px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.board {
  display: grid;
  grid-template-columns: 1fr auto;
  grid-template-rows: auto auto;
  overflow: hidden;
  border: 1px solid var(--line);
  border-radius: 18px;
  background: var(--surface);
  transition: border-color 0.2s;
}

.board:hover {
  border-color: var(--accent);
}

/* Previews are always rendered on white (ink stored as black), like paper. */
.thumb {
  grid-column: 1 / -1;
  display: grid;
  place-items: center;
  aspect-ratio: 16 / 10;
  background: var(--white);
}

.thumb img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.thumb-empty {
  width: 36px;
  height: 36px;
  color: color-mix(in srgb, var(--black) 30%, var(--white));
}

.meta {
  display: grid;
  gap: 2px;
  min-width: 0;
  padding: 10px 0 12px 14px;
}

.name {
  overflow: hidden;
  padding: 0;
  border: 0;
  background: none;
  color: var(--fg);
  font-size: 0.95rem;
  font-weight: 700;
  text-align: left;
  text-overflow: ellipsis;
  white-space: nowrap;
  cursor: text;
}

.rename {
  min-height: 34px;
  padding: 4px 10px;
}

.when {
  color: var(--muted);
  font-size: 0.78rem;
  font-weight: 600;
}

.delete {
  align-self: center;
  margin-right: 6px;
  color: var(--muted);
}

.delete.confirm {
  border-color: var(--fg);
  color: var(--fg);
}
</style>

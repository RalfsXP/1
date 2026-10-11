<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { onBeforeRouteLeave, useRoute } from 'vue-router'
import { Canvas, classRegistry, Ellipse, IText, Line, Path, PencilBrush, Point, Rect, StaticCanvas, Textbox } from 'fabric'
import { EraserBrush } from '@erase2d/fabric'
import {
  ArrowUpRightIcon,
  ArrowUturnLeftIcon,
  ArrowUturnRightIcon,
  ArrowsPointingInIcon,
  BackspaceIcon,
  BoldIcon,
  ChevronLeftIcon,
  CursorArrowRaysIcon,
  DocumentTextIcon,
  HandRaisedIcon,
  PencilIcon,
  SlashIcon,
  StopCircleIcon,
  StopIcon,
  TrashIcon,
} from '@heroicons/vue/24/outline'
import { SWATCHES, readableOn } from '@/lib/goals'
import { t } from '@/lib/i18n'
import { theme } from '@/lib/theme'
import { getBoard, saveBoard } from '@/lib/whiteboards'

/*
 * Freeform board on Fabric.js: pen, eraser, text, sticky notes, shapes and
 * arrows; pan/zoom (wheel, trackpad, two fingers); undo/redo; autosave.
 *
 * Ink is stored as black and drawn white in dark mode (swapped on load, theme
 * change and save), so boards read in both themes and previews are always
 * black-on-white. Sticky notes keep their own colours.
 */

/** Sticky note: a Textbox with a rounded, padded background. */
class StickyNote extends Textbox {
  static type = 'StickyNote'

  _renderBackground(ctx) {
    if (!this.backgroundColor) return
    const p = this.padding
    ctx.fillStyle = this.backgroundColor
    ctx.beginPath()
    ctx.roundRect(-this.width / 2 - p, -this.height / 2 - p, this.width + p * 2, this.height + p * 2, 10)
    ctx.fill()
    this._removeShadow(ctx)
  }
}
classRegistry.setClass(StickyNote)

/** UI colour (handles, cursors) from the active design; note colours are content and stay put. */
const accent = () => getComputedStyle(document.documentElement).getPropertyValue('--accent').trim() || '#4c956c'
const NOTE_DEFAULT = SWATCHES[0]
const INK_LIGHT = '#000000'
const INK_DARK = '#ffffff'
const SIZES = [
  { stroke: 2, text: 20 },
  { stroke: 5, text: 30 },
  { stroke: 10, text: 46 },
]
const ZOOM_MIN = 0.2
const ZOOM_MAX = 4

const TOOLS = [
  { key: 'select', icon: CursorArrowRaysIcon, hotkey: 'v' },
  { key: 'hand', icon: HandRaisedIcon, hotkey: 'h' },
  { key: 'pen', icon: PencilIcon, hotkey: 'p' },
  { key: 'eraser', icon: BackspaceIcon, hotkey: 'e' },
  { key: 'text', icon: BoldIcon, hotkey: 't' },
  { key: 'note', icon: DocumentTextIcon, hotkey: 'n' },
  { key: 'rect', icon: StopIcon, hotkey: 'r' },
  { key: 'ellipse', icon: StopCircleIcon, hotkey: 'o' },
  { key: 'line', icon: SlashIcon, hotkey: 'l' },
  { key: 'arrow', icon: ArrowUpRightIcon, hotkey: 'a' },
]
const SHAPES = ['rect', 'ellipse', 'line', 'arrow']

const route = useRoute()
const id = route.params.id // read once: the view is keyed by path

const host = ref(null)
const canvasEl = ref(null)
const title = ref('')
const loaded = ref(false)
const loadError = ref(false)
const saveState = ref('saved') // saved | pending | saving | error
const tool = ref('pen')
const color = ref('ink')
const size = ref(1)
const hasSelection = ref(false)
const zoomPct = ref(100)
const historyIndex = ref(-1)
const historyLength = ref(0)

let canvas = null
let restoring = false // loading a snapshot: don't record history
let drafting = false // a shape is being dragged out: don't record each frame

const isDark = () => theme.value === 'dark'
const ink = () => (isDark() ? INK_DARK : INK_LIGHT)
const paint = (c) => (c === 'ink' ? ink() : c)
const isNote = (o) => o instanceof StickyNote || o.type === 'StickyNote'
const noteText = (bg) => (readableOn(bg) === 'var(--black)' ? INK_LIGHT : INK_DARK)

const usesColor = computed(() => !['hand', 'eraser'].includes(tool.value) || hasSelection.value)
const canUndo = computed(() => historyIndex.value > 0)
const canRedo = computed(() => historyIndex.value < historyLength.value - 1)

/* ---------- Ink ⇄ theme ---------- */

/** Swap black/white ink on one object (or its serialised form). Notes are left alone. */
function swapInk(o, toDark) {
  if (isNote(o)) return
  const [from, to] = toDark ? [INK_LIGHT, INK_DARK] : [INK_DARK, INK_LIGHT]
  for (const prop of ['stroke', 'fill']) {
    if (typeof o[prop] === 'string' && o[prop].toLowerCase() === from) {
      if (typeof o.set === 'function') o.set(prop, to)
      else o[prop] = to
    }
  }
}

/** The scene as stored: ink in light-mode colours, plus the viewport. */
function sceneJson() {
  const json = canvas.toObject()
  if (isDark()) json.objects.forEach((o) => swapInk(o, false))
  json.viewport = [...canvas.viewportTransform]
  return json
}

/* ---------- History + autosave ---------- */

const history = []

function commit() {
  if (restoring || drafting || !loaded.value) return
  const json = sceneJson()
  delete json.viewport
  const snap = JSON.stringify(json)
  if (snap === history[historyIndex.value]) return
  history.splice(historyIndex.value + 1)
  history.push(snap)
  if (history.length > 50) history.shift()
  historyIndex.value = history.length - 1
  historyLength.value = history.length
  scheduleSave()
}

async function restore(snap) {
  restoring = true
  const vpt = [...canvas.viewportTransform]
  await canvas.loadFromJSON(JSON.parse(snap))
  if (isDark()) canvas.getObjects().forEach((o) => swapInk(o, true))
  canvas.setViewportTransform(vpt)
  restoring = false
  applyTool()
  canvas.requestRenderAll()
  scheduleSave()
}

function undo() {
  if (!canUndo.value) return
  historyIndex.value--
  restore(history[historyIndex.value])
}

function redo() {
  if (!canRedo.value) return
  historyIndex.value++
  restore(history[historyIndex.value])
}

let saveTimer = null
let saving = null

function scheduleSave() {
  saveState.value = 'pending'
  clearTimeout(saveTimer)
  saveTimer = setTimeout(save, 1200)
}

/** Save now (also used to flush on leave). Saves never overlap; a newer one follows. */
async function save() {
  clearTimeout(saveTimer)
  if (!canvas || !loaded.value) return
  if (saving) {
    await saving
    if (saveState.value !== 'pending') return
  }
  saveState.value = 'saving'
  saving = (async () => {
    try {
      const data = sceneJson()
      await saveBoard(id, { data, preview: await preview(data) })
      saveState.value = 'saved'
    } catch {
      saveState.value = 'error'
    }
  })()
  await saving
  saving = null
}

/** A small black-on-white image of the whole board for the list (WebP). */
async function preview(data) {
  if (!data.objects?.length) return null
  const W = 480
  const H = 300
  const sc = new StaticCanvas(document.createElement('canvas'), { width: W, height: H, renderOnAddRemove: false })
  await sc.loadFromJSON({ ...data, viewport: undefined })
  sc.backgroundColor = '#ffffff'
  const box = bounds(sc.getObjects())
  const k = Math.min(W / (box.width + 40), H / (box.height + 40), 2)
  sc.setViewportTransform([k, 0, 0, k, W / 2 - (box.left + box.width / 2) * k, H / 2 - (box.top + box.height / 2) * k])
  const url = sc.toDataURL({ format: 'webp', quality: 0.7 })
  sc.dispose()
  return url
}

/** Union of the objects' bounding boxes, in scene coordinates. */
function bounds(objects) {
  let [x1, y1, x2, y2] = [Infinity, Infinity, -Infinity, -Infinity]
  for (const o of objects) {
    const r = o.getBoundingRect()
    x1 = Math.min(x1, r.left)
    y1 = Math.min(y1, r.top)
    x2 = Math.max(x2, r.left + r.width)
    y2 = Math.max(y2, r.top + r.height)
  }
  return { left: x1, top: y1, width: x2 - x1, height: y2 - y1 }
}

/* ---------- Tools ---------- */

function styleObject(o) {
  o.set({
    borderColor: accent(),
    cornerColor: accent(),
    cornerStrokeColor: accent(),
    cornerStyle: 'circle',
    transparentCorners: false,
    cornerSize: 12,
    borderScaleFactor: 1.5,
  })
  if (isNote(o)) o.set({ padding: 14 })
  o.erasable = !isNote(o)
}

function applyTool() {
  if (!canvas) return
  const current = tool.value
  const selecting = current === 'select'
  canvas.isDrawingMode = current === 'pen' || current === 'eraser'
  if (current === 'pen') {
    const brush = new PencilBrush(canvas)
    brush.color = paint(color.value)
    brush.width = SIZES[size.value].stroke
    brush.decimate = 1.5
    canvas.freeDrawingBrush = brush
  } else if (current === 'eraser') {
    const eraser = new EraserBrush(canvas)
    eraser.width = SIZES[size.value].stroke * 4 + 10
    eraser.on('end', () => setTimeout(commit, 0))
    canvas.freeDrawingBrush = eraser
  }
  canvas.selection = selecting
  canvas.skipTargetFind = !selecting
  canvas.defaultCursor = current === 'hand' ? 'grab' : selecting ? 'default' : 'crosshair'
  canvas.getObjects().forEach((o) => o.set({ selectable: selecting, evented: selecting }))
  if (!selecting) canvas.discardActiveObject()
  canvas.requestRenderAll()
}

watch([tool, size], applyTool)

// A new colour: paint the selection with it, and use it for whatever comes next.
watch(color, (c) => {
  applyTool()
  const targets = canvas?.getActiveObjects() ?? []
  if (!targets.length) return
  const value = paint(c)
  targets.forEach((o) => {
    if (isNote(o)) {
      const bg = c === 'ink' ? NOTE_DEFAULT : c
      o.set({ backgroundColor: bg, fill: noteText(bg) })
    } else if (o instanceof IText) o.set('fill', value)
    else o.set('stroke', value)
  })
  canvas.requestRenderAll()
  commit()
})

watch(theme, (next) => {
  if (!canvas) return
  canvas.getObjects().forEach((o) => swapInk(o, next === 'dark'))
  applyTool()
})

/** Keep a newly placed text/note pinned at the tap point while it grows (default origin is centre). */
function pinTopLeft(obj, x, y) {
  const place = () => {
    obj.set({ left: x + (obj.width * obj.scaleX) / 2, top: y + (obj.height * obj.scaleY) / 2 })
    obj.setCoords()
  }
  place()
  obj.on('changed', place)
  obj.on('editing:exited', () => obj.off('changed', place))
}

function placeText(p) {
  const text = new IText('', {
    fontFamily: 'Montserrat',
    fontWeight: 700,
    fontSize: SIZES[size.value].text,
    fill: paint(color.value),
    cursorColor: accent(),
    editingBorderColor: accent(),
  })
  canvas.add(text)
  pinTopLeft(text, p.x, p.y)
  startEditing(text)
}

function placeNote(p) {
  const bg = color.value === 'ink' ? NOTE_DEFAULT : color.value
  const note = new StickyNote(t('whiteboard.notePlaceholder'), {
    width: 200,
    fontFamily: 'Montserrat',
    fontWeight: 600,
    fontSize: 20,
    lineHeight: 1.25,
    fill: noteText(bg),
    backgroundColor: bg,
    padding: 14,
    cursorColor: noteText(bg),
    editingBorderColor: accent(),
  })
  canvas.add(note)
  pinTopLeft(note, p.x + 14, p.y + 14)
  startEditing(note)
  note.selectAll()
}

function startEditing(obj) {
  tool.value = 'select'
  applyTool()
  canvas.setActiveObject(obj)
  obj.enterEditing()
  canvas.requestRenderAll()
}

function makeShape(kind, a, b) {
  const stroke = paint(color.value)
  const strokeWidth = SIZES[size.value].stroke
  const common = { stroke, strokeWidth, strokeUniform: true, strokeLineCap: 'round', strokeLineJoin: 'round' }
  const [dx, dy] = [b.x - a.x, b.y - a.y]
  const center = { left: (a.x + b.x) / 2, top: (a.y + b.y) / 2 }
  if (kind === 'rect') return new Rect({ ...common, ...center, width: Math.abs(dx), height: Math.abs(dy), rx: 6, ry: 6, fill: 'transparent' })
  if (kind === 'ellipse') return new Ellipse({ ...common, ...center, rx: Math.abs(dx) / 2, ry: Math.abs(dy) / 2, fill: 'transparent' })
  if (kind === 'line') return new Line([a.x, a.y, b.x, b.y], common)
  // Arrow: a line plus a two-stroke head at the end.
  const angle = Math.atan2(dy, dx)
  const head = Math.max(12, strokeWidth * 4)
  const wing = (s) => [b.x - head * Math.cos(angle + s * (Math.PI / 6)), b.y - head * Math.sin(angle + s * (Math.PI / 6))]
  const [l, r] = [wing(1), wing(-1)]
  return new Path(`M ${a.x} ${a.y} L ${b.x} ${b.y} M ${l[0]} ${l[1]} L ${b.x} ${b.y} L ${r[0]} ${r[1]}`, { ...common, fill: '' })
}

function deleteSelection() {
  const targets = canvas.getActiveObjects()
  if (!targets.length) return
  drafting = true
  canvas.discardActiveObject()
  canvas.remove(...targets)
  drafting = false
  canvas.requestRenderAll()
  commit()
}

/* ---------- View: zoom, pan, fit ---------- */

function syncView() {
  const v = canvas.viewportTransform
  const z = v[0]
  zoomPct.value = Math.round(z * 100)
  // The dot grid moves and scales with the board.
  host.value.style.backgroundSize = `${24 * z}px ${24 * z}px`
  host.value.style.backgroundPosition = `${v[4]}px ${v[5]}px`
}

function zoomTo(z, point) {
  const next = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, z))
  canvas.zoomToPoint(point ?? new Point(canvas.width / 2, canvas.height / 2), next)
  syncView()
}

function fit() {
  const objects = canvas.getObjects()
  if (!objects.length) {
    canvas.setViewportTransform([1, 0, 0, 1, 0, 0])
  } else {
    const box = bounds(objects)
    const k = Math.min(canvas.width / (box.width + 120), canvas.height / (box.height + 160), 2)
    canvas.setViewportTransform([k, 0, 0, k, canvas.width / 2 - (box.left + box.width / 2) * k, canvas.height / 2 - (box.top + box.height / 2) * k])
  }
  syncView()
  scheduleSave()
}

function onWheel(e) {
  e.preventDefault()
  const rect = host.value.getBoundingClientRect()
  if (e.ctrlKey || e.metaKey) {
    // Trackpad pinch (and Ctrl+wheel) zooms around the pointer.
    // Capped per event: a mouse-wheel notch (±100) is ~18%, trackpad pinch deltas are tiny.
    const delta = Math.max(-100, Math.min(100, e.deltaY))
    zoomTo(canvas.getZoom() * 0.998 ** delta, new Point(e.clientX - rect.left, e.clientY - rect.top))
  } else {
    canvas.relativePan(new Point(-e.deltaX, -e.deltaY))
    syncView()
  }
}

/* Two fingers: pinch to zoom + pan, whatever the tool (cancels a stroke in progress). */
const pointers = new Map()
let gesture = null
let suspended = false // drawing was switched off for a gesture; restore it afterwards

function onPointerDown(e) {
  pointers.set(e.pointerId, { x: e.clientX, y: e.clientY })
  if (pointers.size !== 2) return
  if (canvas.isDrawingMode) {
    canvas.isDrawingMode = false
    canvas.clearContext(canvas.contextTop)
  }
  suspended = true
  if (drag?.obj) canvas.remove(drag.obj)
  drag = null
  drafting = false
  const [a, b] = [...pointers.values()]
  gesture = { dist: Math.hypot(a.x - b.x, a.y - b.y), zoom: canvas.getZoom(), center: mid(a, b) }
}

function onPointerMove(e) {
  if (!pointers.has(e.pointerId)) return
  pointers.set(e.pointerId, { x: e.clientX, y: e.clientY })
  if (!gesture || pointers.size !== 2) return
  const [a, b] = [...pointers.values()]
  const center = mid(a, b)
  const rect = host.value.getBoundingClientRect()
  zoomTo(gesture.zoom * (Math.hypot(a.x - b.x, a.y - b.y) / gesture.dist), new Point(center.x - rect.left, center.y - rect.top))
  canvas.relativePan(new Point(center.x - gesture.center.x, center.y - gesture.center.y))
  gesture.center = center
  syncView()
}

function onPointerUp(e) {
  pointers.delete(e.pointerId)
  if (gesture && pointers.size < 2) {
    gesture = null
    scheduleSave()
  }
  // Runs before Fabric's own pointerup (capture phase): rebuilding the brush here on a normal
  // stroke would hand Fabric an empty brush and turn the stroke into a dot, so only do it
  // after a gesture.
  if (!pointers.size && suspended) {
    suspended = false
    setTimeout(applyTool, 0)
  }
}

const mid = (a, b) => ({ x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 })

/* ---------- Canvas events ---------- */

let drag = null

function bindCanvas() {
  canvas.on('object:added', ({ target }) => {
    styleObject(target)
    target.set({ selectable: tool.value === 'select', evented: tool.value === 'select' })
    commit()
  })
  canvas.on('object:modified', commit)
  canvas.on('object:removed', commit)
  canvas.on('path:created', ({ path }) => path.set({ strokeLineCap: 'round', strokeLineJoin: 'round' }))
  canvas.on('text:editing:exited', ({ target }) => {
    if (!target.text.trim()) canvas.remove(target)
    else commit()
  })
  const selection = () => (hasSelection.value = canvas.getActiveObjects().length > 0)
  canvas.on('selection:created', selection)
  canvas.on('selection:updated', selection)
  canvas.on('selection:cleared', selection)

  canvas.on('mouse:down', (opt) => {
    if (gesture) return
    const current = tool.value
    if (current === 'hand' || spaceHeld) {
      drag = { pan: true, last: opt.viewportPoint }
      canvas.setCursor('grabbing')
    } else if (current === 'text') placeText(opt.scenePoint)
    else if (current === 'note') placeNote(opt.scenePoint)
    else if (SHAPES.includes(current)) {
      drag = { shape: current, start: opt.scenePoint, obj: null }
      drafting = true
    }
  })

  canvas.on('mouse:move', (opt) => {
    if (!drag || gesture) return
    if (drag.pan) {
      const v = opt.viewportPoint
      canvas.relativePan(new Point(v.x - drag.last.x, v.y - drag.last.y))
      drag.last = v
      syncView()
      return
    }
    if (drag.obj) canvas.remove(drag.obj)
    drag.obj = makeShape(drag.shape, drag.start, opt.scenePoint)
    canvas.add(drag.obj)
  })

  canvas.on('mouse:up', () => {
    if (drag?.pan) scheduleSave()
    if (drag?.shape) {
      drafting = false
      if (drag.obj) commit()
    }
    drag = null
  })
}

/* ---------- Keyboard (desktop) ---------- */

let spaceHeld = false

function onKey(e) {
  const typing = canvas?.getActiveObject()?.isEditing || ['INPUT', 'TEXTAREA'].includes(document.activeElement?.tagName)
  if (typing) return
  const mod = e.metaKey || e.ctrlKey
  if (mod && e.key.toLowerCase() === 'z') {
    e.preventDefault()
    e.shiftKey ? redo() : undo()
  } else if (mod && e.key.toLowerCase() === 'y') {
    e.preventDefault()
    redo()
  } else if (e.key === 'Delete' || e.key === 'Backspace') {
    if (hasSelection.value) {
      e.preventDefault()
      deleteSelection()
    }
  } else if (e.key === ' ' && e.type === 'keydown') {
    spaceHeld = true
  } else if (!mod && !e.altKey) {
    const hit = TOOLS.find((x) => x.hotkey === e.key.toLowerCase())
    if (hit) tool.value = hit.key
  }
}

function onKeyUp(e) {
  if (e.key === ' ') spaceHeld = false
}

/* ---------- Lifecycle ---------- */

let resizer = null

async function load() {
  try {
    const board = await getBoard(id)
    title.value = board.title
    restoring = true
    if (board.data?.objects) await canvas.loadFromJSON(board.data)
    if (isDark()) canvas.getObjects().forEach((o) => swapInk(o, true))
    if (board.data?.viewport) canvas.setViewportTransform(board.data.viewport)
    restoring = false
    loaded.value = true
    applyTool()
    syncView()
    commit() // first history entry (doesn't save: identical scene)
    saveState.value = 'saved'
    clearTimeout(saveTimer)
    // Boards created elsewhere (e.g. the demo seeder) have no list preview yet: make one now.
    if (!board.preview && board.data?.objects?.length) save()
  } catch {
    loadError.value = true
  }
}

async function renameBoard(value) {
  const next = value.trim()
  if (!next || next === title.value) return
  title.value = next
  await saveBoard(id, { title: next }).catch(() => (saveState.value = 'error'))
}

const onVisibility = () => document.visibilityState === 'hidden' && saveState.value === 'pending' && save()

onMounted(() => {
  canvas = new Canvas(canvasEl.value, {
    width: host.value.clientWidth,
    height: host.value.clientHeight,
    preserveObjectStacking: true,
    // Pointer events: one code path for mouse, finger and stylus (Apple Pencil).
    enablePointerEvents: true,
    selectionColor: `${accent()}1f`,
    selectionBorderColor: accent(),
    selectionLineWidth: 1.5,
  })
  bindCanvas()
  resizer = new ResizeObserver(() => {
    canvas.setDimensions({ width: host.value.clientWidth, height: host.value.clientHeight })
    canvas.requestRenderAll()
  })
  resizer.observe(host.value)
  host.value.addEventListener('wheel', onWheel, { passive: false })
  addEventListener('keydown', onKey)
  addEventListener('keyup', onKeyUp)
  document.addEventListener('visibilitychange', onVisibility)
  load()
})

onBeforeRouteLeave(async () => {
  if (saveState.value === 'pending' || saving) await save()
})

onBeforeUnmount(() => {
  clearTimeout(saveTimer)
  resizer?.disconnect()
  removeEventListener('keydown', onKey)
  removeEventListener('keyup', onKeyUp)
  document.removeEventListener('visibilitychange', onVisibility)
  canvas?.dispose()
  canvas = null
})
</script>

<template>
  <div class="board-page">
    <div
      ref="host"
      class="surface"
      @pointerdown.capture="onPointerDown"
      @pointermove.capture="onPointerMove"
      @pointerup.capture="onPointerUp"
      @pointercancel.capture="onPointerUp"
    >
      <canvas ref="canvasEl" />
    </div>

    <header class="bar top">
      <RouterLink :to="{ name: 'whiteboards' }" class="icon-btn bare" :aria-label="$t('whiteboard.back')" :title="$t('whiteboard.back')">
        <ChevronLeftIcon aria-hidden="true" />
      </RouterLink>
      <input
        class="title"
        :value="title"
        maxlength="120"
        :aria-label="$t('whiteboard.boardTitle')"
        @keydown.enter="$event.target.blur()"
        @blur="renameBoard($event.target.value)"
      />
      <span class="status" :class="saveState" aria-live="polite">{{ $t(`whiteboard.status.${saveState}`) }}</span>
      <span class="spacer" />
      <button type="button" class="icon-btn bare" :disabled="!canUndo" :aria-label="$t('whiteboard.undo')" :title="$t('whiteboard.undo')" @click="undo">
        <ArrowUturnLeftIcon aria-hidden="true" />
      </button>
      <button type="button" class="icon-btn bare" :disabled="!canRedo" :aria-label="$t('whiteboard.redo')" :title="$t('whiteboard.redo')" @click="redo">
        <ArrowUturnRightIcon aria-hidden="true" />
      </button>
      <button type="button" class="zoom" :aria-label="$t('whiteboard.fit')" :title="$t('whiteboard.fit')" @click="fit">
        <ArrowsPointingInIcon aria-hidden="true" />{{ zoomPct }}%
      </button>
    </header>

    <nav class="bar tools" :aria-label="$t('whiteboard.tools')">
      <button
        v-for="item in TOOLS"
        :key="item.key"
        type="button"
        class="tool"
        :aria-pressed="tool === item.key"
        :aria-label="$t(`whiteboard.tool.${item.key}`)"
        :title="`${$t(`whiteboard.tool.${item.key}`)} (${item.hotkey.toUpperCase()})`"
        @click="tool = item.key"
      >
        <component :is="item.icon" aria-hidden="true" />
      </button>
    </nav>

    <div v-if="usesColor" class="bar options">
      <div class="colors" role="group" :aria-label="$t('goal.color')">
        <button
          type="button"
          class="swatch ink"
          :aria-pressed="color === 'ink'"
          :aria-label="$t('whiteboard.ink')"
          @click="color = 'ink'"
        />
        <button
          v-for="(swatch, i) in SWATCHES"
          :key="swatch"
          type="button"
          class="swatch"
          :style="{ background: swatch }"
          :aria-pressed="color === swatch"
          :aria-label="$t('colors')[i]"
          @click="color = swatch"
        />
      </div>
      <span class="divider" />
      <div class="sizes" role="group" :aria-label="$t('whiteboard.size')">
        <button
          v-for="(s, i) in SIZES"
          :key="i"
          type="button"
          class="size"
          :aria-pressed="size === i"
          :aria-label="$t('whiteboard.sizes')[i]"
          @click="size = i"
        >
          <span :style="{ width: `${4 + i * 4}px`, height: `${4 + i * 4}px` }" />
        </button>
      </div>
      <template v-if="hasSelection">
        <span class="divider" />
        <button type="button" class="icon-btn bare" :aria-label="$t('whiteboard.deleteSelection')" :title="$t('whiteboard.deleteSelection')" @click="deleteSelection">
          <TrashIcon aria-hidden="true" />
        </button>
      </template>
    </div>

    <p v-if="loadError" class="empty-state load-error" role="alert">{{ $t('auth.errorGeneric') }}</p>
  </div>
</template>

<style scoped>
.board-page {
  position: relative;
  height: 100%;
  overflow: hidden;
}

/* The board: a dot grid that pans/zooms with the content (see syncView). */
.surface {
  position: absolute;
  inset: 0;
  background-color: var(--bg);
  background-image: radial-gradient(circle, var(--line) 1.2px, transparent 1.3px);
  background-size: 24px 24px;
  /* Our own gestures: no browser panning/zooming on the board. */
  touch-action: none;
}

.surface :deep(canvas) {
  touch-action: none;
}

/* Floating pill bars over the board. */
.bar {
  position: absolute;
  z-index: 5;
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 6px;
  border: 1px solid var(--line);
  border-radius: 18px;
  background: color-mix(in srgb, var(--bg) 92%, transparent);
  box-shadow: 0 6px 24px color-mix(in srgb, var(--black) 18%, transparent);
  backdrop-filter: blur(8px);
}

.top {
  top: 12px;
  right: 12px;
  left: 12px;
}

.title {
  min-width: 0;
  max-width: 280px;
  flex: 1;
  height: 36px;
  padding: 0 8px;
  border: 1px solid transparent;
  border-radius: 10px;
  background: transparent;
  color: var(--fg);
  font-size: 16px;
  font-weight: 800;
  letter-spacing: -0.01em;
}

.title:hover,
.title:focus {
  border-color: var(--line);
  outline: none;
}

.status {
  flex-shrink: 0;
  color: var(--muted);
  font-size: 0.75rem;
  font-weight: 700;
}

.status.error {
  color: var(--fg);
}

.spacer {
  flex: 1;
}

.icon-btn:disabled {
  opacity: 0.35;
  cursor: default;
}

.zoom {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  height: 36px;
  padding: 0 10px;
  border: 0;
  border-radius: 10px;
  background: transparent;
  color: var(--fg);
  font-size: 0.78rem;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
  cursor: pointer;
}

.zoom:hover {
  color: var(--accent);
}

.zoom svg {
  width: 18px;
  height: 18px;
}

/* Tools: a rail on the left (desktop), a scrollable strip at the bottom (phones). */
.tools {
  top: 50%;
  left: 12px;
  flex-direction: column;
  transform: translateY(-50%);
}

.tool {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  flex-shrink: 0;
  border: 0;
  border-radius: 12px;
  background: transparent;
  color: var(--fg);
  cursor: pointer;
  transition: background-color 0.15s, color 0.15s;
}

.tool:hover {
  color: var(--accent);
}

.tool[aria-pressed='true'] {
  background: var(--accent);
  color: var(--on-accent);
}

.tool svg {
  width: 20px;
  height: 20px;
}

.options {
  bottom: max(16px, env(safe-area-inset-bottom));
  left: 50%;
  max-width: calc(100% - 24px);
  overflow-x: auto;
  transform: translateX(-50%);
  scrollbar-width: none;
}

.colors,
.sizes {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 0 4px;
}

.swatch {
  flex-shrink: 0;
  width: 26px;
  height: 26px;
  padding: 0;
  border: 2px solid transparent;
  border-radius: 50%;
  cursor: pointer;
}

.swatch.ink {
  background: var(--fg);
}

.swatch[aria-pressed='true'] {
  box-shadow: 0 0 0 2px var(--bg), 0 0 0 4px var(--fg);
}

.size {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  flex-shrink: 0;
  border: 0;
  border-radius: 10px;
  background: transparent;
  cursor: pointer;
}

.size span {
  border-radius: 50%;
  background: var(--fg);
}

.size[aria-pressed='true'] {
  background: var(--line);
}

.divider {
  flex-shrink: 0;
  width: 1px;
  height: 24px;
  margin: 0 4px;
  background: var(--line);
}

.load-error {
  position: absolute;
  top: 80px;
  right: 16px;
  left: 16px;
  z-index: 6;
  background: var(--bg);
}

@media (max-width: 860px) {
  .top {
    top: 8px;
    right: 8px;
    left: 8px;
  }

  /* Room for the title: no spacer, status only while saving or on error. */
  .spacer,
  .status.saved {
    display: none;
  }

  .zoom {
    padding: 0 6px;
  }

  .tools {
    top: auto;
    bottom: max(12px, env(safe-area-inset-bottom));
    left: 8px;
    right: 8px;
    flex-direction: row;
    overflow-x: auto;
    transform: none;
    scrollbar-width: none;
  }

  /* Colours/sizes sit just above the tool strip. */
  .options {
    bottom: calc(max(12px, env(safe-area-inset-bottom)) + 66px);
  }
}
</style>

// Regenerate: cd frontend && npm i --no-save opentype.js@1.3.4 @fontsource/montserrat@5 @resvg/resvg-js@2 && node scripts/build-logo.mjs
// Then paste the paths from scripts/logo-geometry.json into components/AppLogo.vue + LogoMark.vue.
// Builds the "mērķis" wordmark (option E) as vector outlines + every derived asset.
import fs from 'node:fs'
import path from 'node:path'
import opentype from 'opentype.js'
import { Resvg } from '@resvg/resvg-js'

// Repo root (this file lives in frontend/scripts/).
const REPO = path.resolve(path.dirname(new URL(import.meta.url).pathname), '../..')
// V1 palette (v1.5): Toasted Almond marks, Turf Green tile, Light Yellow letters on the deep Turf ink.
const ALMOND = '#d68c45'
const TURF = '#2c6e49'
const CREAM = '#fefee3'
const INK = '#0d1f15'
const INK_LIGHT = '#143823'
const font = opentype.parse(fs.readFileSync(new URL(import.meta.resolve('@fontsource/montserrat/files/montserrat-latin-800-normal.woff'))).buffer)

const SIZE = 100 // font units -> px at 100
const TRACK = -0.03 * SIZE
const r = (n) => Math.round(n * 100) / 100

function layout(text) {
  let x = 0
  const glyphs = []
  const chars = [...text]
  chars.forEach((ch, i) => {
    const g = font.charToGlyph(ch)
    const p = g.getPath(x, 0, SIZE)
    glyphs.push({ ch, d: p.toPathData(2), box: p.getBoundingBox(), x })
    const next = chars[i + 1] ? font.charToGlyph(chars[i + 1]) : null
    const kern = next ? font.getKerningValue(g, next) : 0
    x += (g.advanceWidth + kern) * (SIZE / font.unitsPerEm) + (next ? TRACK : 0)
  })
  return glyphs
}

/* Wordmark: merkis + macron over e + dot under k (the ē and ķ of mērķis). */
const word = layout('merkis')
const e = word[1].box
const k = word[3].box
const stroke = 13 // ≈ Montserrat 800 stem at 100px
const barW = (e.x2 - e.x1) * 0.78
const bar = { x: (e.x1 + e.x2) / 2 - barW / 2, y: e.y1 - 9 - stroke, w: barW, h: stroke }
const dotR = 7.5
const dot = { cx: (k.x1 + k.x2) / 2 - 2, cy: k.y2 + 4 + dotR, r: dotR }

const minX = Math.min(...word.map((g) => g.box.x1))
const maxX = Math.max(...word.map((g) => g.box.x2))
const minY = Math.min(bar.y, ...word.map((g) => g.box.y1))
const maxY = Math.max(dot.cy + dot.r, ...word.map((g) => g.box.y2))
const wordVB = [r(minX), r(minY), r(maxX - minX), r(maxY - minY)]
const wordPath = word.map((g) => g.d).join('')
const accents = (fill) =>
  `<rect x="${r(bar.x)}" y="${r(bar.y)}" width="${r(bar.w)}" height="${bar.h}" rx="${bar.h / 2}" fill="${fill}"/>` +
  `<circle cx="${r(dot.cx)}" cy="${r(dot.cy)}" r="${dot.r}" fill="${fill}"/>`
const wordSvg = (textFill, accentFill = ALMOND) =>
  `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${wordVB.join(' ')}"><path d="${wordPath}" fill="${textFill}"/>${accents(accentFill)}</svg>`

/* Mark: "m" + Almond dot at its baseline (the wordmark's dot, borrowed). */
const m = layout('m')[0]
const markDot = { cx: m.box.x2 + 6 + dotR, cy: m.box.y2 - dotR, r: dotR }
const markBox = { x1: m.box.x1, y1: m.box.y1, x2: markDot.cx + dotR, y2: m.box.y2 }
const markVB = [r(markBox.x1), r(markBox.y1), r(markBox.x2 - markBox.x1), r(markBox.y2 - markBox.y1)]
const markInner = (fill, dotFill = ALMOND, withDot = true) =>
  `<path d="${m.d}" fill="${fill}"/>` + (withDot ? `<circle cx="${r(markDot.cx)}" cy="${r(markDot.cy)}" r="${markDot.r}" fill="${dotFill}"/>` : '')

/** Square icon: background + mark centred, mark width = `scale` of the side. */
function iconSvg({ bg, fill, dotFill = ALMOND, withDot = true, radius = 0, scale = 0.56 }) {
  const S = 100
  const box = withDot ? markBox : m.box
  const w = box.x2 - box.x1
  const h = box.y2 - box.y1
  const k2 = (S * scale) / w
  const tx = (S - w * k2) / 2 - box.x1 * k2
  const ty = (S - h * k2) / 2 - box.y1 * k2
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${S} ${S}"><rect width="${S}" height="${S}" rx="${radius}" fill="${bg}"/><g transform="translate(${r(tx)} ${r(ty)}) scale(${r(k2 * 1000) / 1000})">${markInner(fill, dotFill, withDot)}</g></svg>`
}

const png = (svg, width) => new Resvg(svg, { fitTo: { mode: 'width', value: width } }).render().asPng()
const write = (rel, data) => {
  const file = path.join(REPO, rel)
  fs.mkdirSync(path.dirname(file), { recursive: true })
  fs.writeFileSync(file, data)
  console.log('wrote', rel, typeof data === 'string' ? `${data.length} chars` : `${data.length} bytes`)
}

// Brand files (README, anything outside the app).
write('frontend/public/brand/logo-on-dark.svg', wordSvg(CREAM))
write('frontend/public/brand/logo-on-light.svg', wordSvg(INK_LIGHT))
write('frontend/public/brand/mark.svg', `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${markVB.join(' ')}">${markInner(INK_LIGHT)}</svg>`)

// Favicon: Turf Green tile, Light Yellow "m" — readable at 16 px in light and dark tab bars.
const fav = iconSvg({ bg: TURF, fill: CREAM, withDot: false, radius: 22, scale: 0.66 })
write('frontend/public/favicon.svg', fav)
write('frontend/public/favicon-32.png', png(fav, 32))

// Home-screen icons: ink full-bleed (iOS/Android round it), Light Yellow m + Almond dot inside the safe zone.
const app = iconSvg({ bg: INK, fill: CREAM, scale: 0.58 })
write('frontend/public/apple-touch-icon.png', png(app, 180))
write('frontend/public/icon-192.png', png(app, 192))
write('frontend/public/icon-512.png', png(app, 512))

// favicon.ico with PNG-encoded 16/32/48 entries.
const sizes = [16, 32, 48].map((s) => [s, png(fav, s)])
const header = Buffer.alloc(6 + 16 * sizes.length)
header.writeUInt16LE(0, 0); header.writeUInt16LE(1, 2); header.writeUInt16LE(sizes.length, 4)
let offset = header.length
sizes.forEach(([s, buf], i) => {
  const o = 6 + i * 16
  header.writeUInt8(s, o); header.writeUInt8(s, o + 1); header.writeUInt8(0, o + 2); header.writeUInt8(0, o + 3)
  header.writeUInt16LE(1, o + 4); header.writeUInt16LE(32, o + 6)
  header.writeUInt32LE(buf.length, o + 8); header.writeUInt32LE(offset, o + 12)
  offset += buf.length
})
write('frontend/public/favicon.ico', Buffer.concat([header, ...sizes.map(([, b]) => b)]))

// Email header (ink background): Light Yellow wordmark, 3× its 160 px display width.
write('frontend/public/email/logo.png', png(wordSvg(CREAM), 480))

// Data for the Vue components.
fs.writeFileSync(path.join(REPO, 'frontend/scripts/logo-geometry.json'), JSON.stringify({ wordVB, wordPath, bar: { ...bar, x: r(bar.x), y: r(bar.y), w: r(bar.w) }, dot: { ...dot, cx: r(dot.cx), cy: r(dot.cy) }, markVB, mPath: m.d, markDot: { ...markDot, cx: r(markDot.cx), cy: r(markDot.cy) } }))
console.log('wordVB', wordVB, 'markVB', markVB)

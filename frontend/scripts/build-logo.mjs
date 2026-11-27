// Regenerate: cd frontend && npm i --no-save opentype.js@1.3.4 @fontsource/bricolage-grotesque@5 @resvg/resvg-js@2 && node scripts/build-logo.mjs
// Builds every brand asset (v1.8) from the sticker mark: a tilted lavender tile with an ink "m" and a butter dot.
// The same geometry lives in components/LogoMark.vue (keep both in sync); the lockup wordmark "mērķis" is
// Bricolage Grotesque ExtraBold — live text in the app, outlines here for the README files.
import fs from 'node:fs'
import path from 'node:path'
import opentype from 'opentype.js'
import { Resvg } from '@resvg/resvg-js'

const REPO = path.resolve(path.dirname(new URL(import.meta.url).pathname), '../..')
const LAV = '#cbbcff'
const BUTTER = '#ffe27a'
const INK = '#17171c'
const PAPER = '#f3efe7'

/* ---------- The sticker (viewBox 0 0 52 52) ---------- */
const sticker = (x = 0, y = 0, k = 1) =>
  `<g transform="translate(${x} ${y}) scale(${k})">` +
  `<g transform="rotate(-8 26 26)"><rect x="4" y="4" width="44" height="44" rx="15" fill="${LAV}" stroke="${INK}" stroke-width="2.5"/>` +
  `<path d="M14.5 34V23.5c0-2.4 1.8-4.2 4-4.2s4 1.8 4 4.2V34M22.5 23.5c0-2.4 1.8-4.2 4-4.2s4 1.8 4 4.2V34" fill="none" stroke="${INK}" stroke-width="3.6" stroke-linecap="round"/></g>` +
  `<circle cx="40" cy="36" r="6.5" fill="${BUTTER}" stroke="${INK}" stroke-width="2.5"/></g>`
const markSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">${sticker()}</svg>`

/** Square icon: background + sticker centred at `scale` of the side. */
const iconSvg = ({ bg = null, scale = 0.62 } = {}) => {
  const S = 100
  const k = (S * scale) / 52
  const o = (S - 52 * k) / 2
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${S} ${S}">${bg ? `<rect width="${S}" height="${S}" fill="${bg}"/>` : ''}${sticker(o, o, k)}</svg>`
}

/* ---------- Wordmark outlines (README lockups) ---------- */
const fontFile = (subset) =>
  opentype.parse(fs.readFileSync(new URL(import.meta.resolve(`@fontsource/bricolage-grotesque/files/bricolage-grotesque-${subset}-800-normal.woff`))).buffer)
const fonts = [fontFile('latin'), fontFile('latin-ext')]
const glyphOf = (ch) => {
  for (const f of fonts) {
    const g = f.charToGlyph(ch)
    if (g.index !== 0) return { f, g }
  }
  throw new Error(`No glyph for ${ch}`)
}
const SIZE = 40
const TRACK = -0.045 * SIZE
function wordPath(text, x0, baseline) {
  let x = x0
  return [...text]
    .map((ch, i, all) => {
      const { f, g } = glyphOf(ch)
      const d = g.getPath(x, baseline, SIZE).toPathData(2)
      x += g.advanceWidth * (SIZE / f.unitsPerEm) + (i < all.length - 1 ? TRACK : 0)
      return d
    })
    .join('')
}
const word = wordPath('mērķis', 64, 38)
const width = (() => {
  let x = 0
  const chars = [...'mērķis']
  chars.forEach((ch, i) => {
    const { f, g } = glyphOf(ch)
    x += g.advanceWidth * (SIZE / f.unitsPerEm) + (i < chars.length - 1 ? TRACK : 0)
  })
  return Math.ceil(64 + x + 4)
})()
const lockup = (text) =>
  `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${width} 56">${sticker(2, 2)}<path d="${word}" fill="${text}"/></svg>`

/* ---------- Output ---------- */
const png = (svg, size) => new Resvg(svg, { fitTo: { mode: 'width', value: size } }).render().asPng()
const write = (rel, data) => {
  const file = path.join(REPO, rel)
  fs.mkdirSync(path.dirname(file), { recursive: true })
  fs.writeFileSync(file, data)
  console.log('wrote', rel, typeof data === 'string' ? `${data.length} chars` : `${data.length} bytes`)
}

// Brand files (README, anything outside the app).
write('frontend/public/brand/logo-on-light.svg', lockup(INK))
write('frontend/public/brand/logo-on-dark.svg', lockup(PAPER))
write('frontend/public/brand/mark.svg', markSvg)

// Favicon: the sticker on its own (it carries its ink outline, so it reads on light and dark tab bars).
const fav = iconSvg({ scale: 0.96 })
write('frontend/public/favicon.svg', fav)
write('frontend/public/favicon-32.png', png(fav, 32))

// favicon.ico with PNG-encoded 16/32/48 entries.
const sizes = [16, 32, 48].map((s) => [s, png(fav, s)])
const header = Buffer.alloc(6 + 16 * sizes.length)
header.writeUInt16LE(0, 0)
header.writeUInt16LE(1, 2)
header.writeUInt16LE(sizes.length, 4)
let offset = header.length
sizes.forEach(([s, buf], i) => {
  const o = 6 + i * 16
  header.writeUInt8(s, o)
  header.writeUInt8(s, o + 1)
  header.writeUInt16LE(1, o + 4)
  header.writeUInt16LE(32, o + 6)
  header.writeUInt32LE(buf.length, o + 8)
  header.writeUInt32LE(offset, o + 12)
  offset += buf.length
})
write('frontend/public/favicon.ico', Buffer.concat([header, ...sizes.map(([, b]) => b)]))

// Home-screen icons: paper full-bleed (iOS/Android round it), the sticker inside the maskable safe zone.
const app = iconSvg({ bg: PAPER, scale: 0.6 })
write('frontend/public/apple-touch-icon.png', png(app, 180))
write('frontend/public/icon-192.png', png(app, 192))
write('frontend/public/icon-512.png', png(app, 512))

// Email header: the sticker, transparent, 3× its 64 px display size.
write('frontend/public/email/logo.png', png(markSvg, 192))

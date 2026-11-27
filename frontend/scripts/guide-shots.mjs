// Guide screenshots (public/guide/<name>-<dark|light>.webp): headless Chrome at iPhone size
// (390×844 @1.5x, touch, Home Screen look), signed in as the demo account, dark + light.
//
// Needs the local dev setup against a THROWAWAY database (never prod), seeded with the demo account:
//   DemoSeeder (DEMO_PASSWORD=… php artisan db:seed --class=DemoSeeder --force with the scratch DB_* vars, see CLAUDE.md),
//   the PHP server on :8000 and `npm run dev` on :5173. Then, from frontend/:
//   DEMO_PASSWORD=… node scripts/guide-shots.mjs [name,name]   (no names = all 17 shots × 2 themes)
// Google Chrome must be installed in /Applications.
import { spawn } from 'node:child_process'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'

const outDir = path.resolve(path.dirname(new URL(import.meta.url).pathname), '../public/guide')
const password = process.env.DEMO_PASSWORD
if (!password) throw new Error('Set DEMO_PASSWORD to the demo account password.')
const only = process.argv[2] ? process.argv[2].split(',') : null
const BASE = 'http://localhost:5173'
const PORT = 9333
const SCRATCH = fs.mkdtempSync(path.join(os.tmpdir(), 'merkis-guide-'))
const sleep = (ms) => new Promise((r) => setTimeout(r, ms))

const chrome = spawn('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', [
  '--headless=new', `--remote-debugging-port=${PORT}`, `--user-data-dir=${SCRATCH}`,
  '--hide-scrollbars', '--no-first-run', '--no-default-browser-check', 'about:blank',
], { stdio: 'ignore' })

let targets
for (let i = 0; i < 50; i++) {
  try { targets = await (await fetch(`http://127.0.0.1:${PORT}/json/list`)).json(); if (targets.length) break } catch {}
  await sleep(200)
}
const page = targets.find((t) => t.type === 'page')
const ws = new WebSocket(page.webSocketDebuggerUrl)
await new Promise((r) => ws.addEventListener('open', r))
let id = 0
const pending = new Map()
ws.addEventListener('message', (e) => {
  const msg = JSON.parse(e.data)
  if (msg.id && pending.has(msg.id)) { pending.get(msg.id)(msg); pending.delete(msg.id) }
})
const send = (method, params = {}) => new Promise((resolve, reject) => {
  const n = ++id
  pending.set(n, (m) => (m.error ? reject(new Error(`${method}: ${m.error.message}`)) : resolve(m.result)))
  ws.send(JSON.stringify({ id: n, method, params }))
})
const js = async (expr) => {
  const r = await send('Runtime.evaluate', { expression: `(async () => { ${expr} })()`, awaitPromise: true, returnByValue: true })
  if (r.exceptionDetails) throw new Error(r.exceptionDetails.exception?.description ?? r.exceptionDetails.text)
  return r.result.value
}

await send('Page.enable')
await send('Runtime.enable')
await send('Emulation.setDeviceMetricsOverride', { width: 390, height: 844, deviceScaleFactor: 1.5, mobile: true })
await send('Emulation.setTouchEmulationEnabled', { enabled: true, maxTouchPoints: 5 })

const PREP = `
  await document.fonts.ready;
  document.documentElement.classList.add('standalone');
  if (!document.getElementById('shot-style')) {
    const s = document.createElement('style'); s.id = 'shot-style';
    s.textContent = '*,*::before,*::after{transition:none!important;animation-duration:0s!important;animation-delay:0s!important;caret-color:transparent!important}#__vue-devtools-container__,[id^=__vue-devtools],.vue-devtools__anchor{display:none!important}';
    document.head.appendChild(s);
  }
  return true;`

async function go(route) {
  await send('Page.navigate', { url: BASE + route })
  await sleep(2600)
  await js(PREP)
  await sleep(500)
}

async function shot(name, theme) {
  if (only && !only.includes(name)) return
  await js(PREP)
  await sleep(350)
  const { data } = await send('Page.captureScreenshot', { format: 'webp', quality: 78 })
  const file = path.join(outDir, `${name}-${theme}.webp`)
  fs.writeFileSync(file, Buffer.from(data, 'base64'))
  console.log('wrote', file, Math.round(Buffer.from(data, 'base64').length / 1024) + ' KB')
}

const click = (sel) => js(`const el = [...document.querySelectorAll(${JSON.stringify(sel)})].pop(); if (!el) throw new Error('missing ' + ${JSON.stringify(sel)}); el.click(); return true`)
const clickText = (sel, text) => js(`const el = [...document.querySelectorAll(${JSON.stringify(sel)})].reverse().find((e) => e.textContent.trim() === ${JSON.stringify(text)}); if (!el) throw new Error('missing ' + ${JSON.stringify(text)}); el.click(); return true`)
const type = (sel, value) => js(`const el = [...document.querySelectorAll(${JSON.stringify(sel)})].pop(); el.value = ${JSON.stringify(value)}; el.dispatchEvent(new Event('input', { bubbles: true })); return true`)
// Scroll the last open sheet's scrolling box to the bottom (or by `px`).
const scrollSheet = (px) => js(`const sheet = [...document.querySelectorAll('.sheet')].pop();
  const box = [sheet, ...sheet.querySelectorAll('*')].find((e) => e.scrollHeight > e.clientHeight + 4 && /auto|scroll/.test(getComputedStyle(e).overflowY));
  if (box) box.scrollTop = ${px ?? 'box.scrollHeight'}; return !!box`)
const scrollMainTo = (sel, offset = 76) => js(`const main = document.querySelector('main.main'); const el = document.querySelector(${JSON.stringify(sel)});
  main.scrollTop += el.getBoundingClientRect().top - ${offset}; return main.scrollTop`)

async function hold(sel) {
  const box = await js(`const r = [...document.querySelectorAll(${JSON.stringify(sel)})][0].getBoundingClientRect(); return { x: r.left + r.width / 2, y: r.top + r.height / 2 }`)
  await send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: box.x, y: box.y }] })
  await sleep(700)
  await send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] })
  await sleep(900)
}

for (const theme of ['dark', 'light']) {
  await go('/')
  await js(`localStorage.setItem('merkis-theme', '${theme}'); localStorage.setItem('merkis-locale', 'en'); localStorage.setItem('merkis-hold-hint', 'dismissed'); localStorage.setItem('merkis-cal-view', 'month');
    const x = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
    if (x) await fetch('/api/logout', { method: 'POST', headers: { 'X-XSRF-TOKEN': x, Accept: 'application/json' } }); return true`)

  await go('/')
  await shot('landing', theme)
  await go('/register')
  await shot('signup', theme)

  // Log in as the demo account.
  await js(`await fetch('/sanctum/csrf-cookie');
    const x = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)[1]);
    const r = await fetch('/api/login', { method: 'POST', headers: { 'X-XSRF-TOKEN': x, 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email: 'demo@demo.lv', password: ${JSON.stringify(password)}, remember: true }) });
    if (!r.ok) throw new Error('login ' + r.status); return true`)

  await go('/home')
  await shot('overview', theme)
  await click('.pm-toggle')
  await sleep(1200)
  await shot('menu', theme)

  await go('/calendar')
  await shot('calendar', theme)
  await scrollMainTo('.day-panel')
  await sleep(400)
  await shot('calendar-day', theme)
  await js(`document.querySelector('main.main').scrollTop = 0; [...document.querySelectorAll('.view-switch button')][1].click(); return true`)
  await sleep(900)
  await shot('calendar-week', theme)
  await js(`localStorage.setItem('merkis-cal-view', 'month'); return true`)

  await go('/events')
  await shot('events', theme)
  await click('.pm-toggle')
  await sleep(900)
  await click('.pm-add')
  await sleep(900)
  await type('.sheet input[type=text]', "Grandma's birthday")
  await js(`[...document.querySelectorAll('.sheet .segmented button')].find((b) => b.getAttribute('aria-label') === 'Every year').click(); return true`)
  await sleep(400)
  await shot('event-form', theme)

  await go('/goals/daily')
  await shot('daily', theme)
  await click('.pm-toggle')
  await sleep(900)
  await click('.pm-add')
  await sleep(900)
  await type('.sheet input[type=text]', 'Morning walk')
  await sleep(300)
  await shot('goal-form', theme)
  await clickText('.sheet .segmented button', 'Every day')
  await sleep(300)
  await js(`[...document.querySelectorAll('.sheet .toggle input')].pop().click(); return true`)
  await sleep(500)
  await js(`const sheet = [...document.querySelectorAll('.sheet')].pop(); const el = sheet.querySelector('.days') || sheet.querySelector('.segmented.outline');
    const box = [sheet, ...sheet.querySelectorAll('*')].find((e) => e.scrollHeight > e.clientHeight + 4 && /auto|scroll/.test(getComputedStyle(e).overflowY));
    if (box) box.scrollTop += el.getBoundingClientRect().top - box.getBoundingClientRect().top - 40; return true`)
  await sleep(400)
  await shot('goal-form-more', theme)

  await go('/goals/daily')
  await hold('.list .row, .list li')
  await scrollSheet()
  await sleep(400)
  await shot('goal-edit', theme)

  await go('/goals/weekly')
  await shot('weekly', theme)
  await go('/goals/custom')
  await shot('custom', theme)

  await go('/settings')
  await shot('settings', theme)
  await js(`document.querySelector('.row-head').click(); return true`)
  await sleep(600)
  await scrollMainTo('.row.open', 90)
  await sleep(400)
  await shot('settings-badge', theme)
}

ws.close()
chrome.kill()
// Chrome may still be writing its profile while it exits: clean up a moment later, and never fail on it.
setTimeout(() => {
  try {
    fs.rmSync(SCRATCH, { recursive: true, force: true })
  } catch {}
}, 500)

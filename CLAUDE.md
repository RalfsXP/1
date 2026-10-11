# Merkis.app — project guide for Claude

> Read this first. It is the source of truth for how the project is built, why, and what already went wrong once.
> **Keep it current:** every change that alters behaviour, structure, env vars, design rules or the version MUST update this file in the same change (see "Maintaining this file"). Keep it dense — it is loaded every session, so every line costs tokens.

## What it is
Mērķis = Latvian for "goal". A minimal planner web app, live at **https://merkis.app**. Current version: **v1.1** (`frontend/VERSION`). Owner: Ralfs (GitHub `RalfsXP/1`). Real product features (planning, goals) are not built yet — only landing, auth, home ("Welcome") and infrastructure.

## Stack & hosting (all free tier except the domain)
| Part | Tech | Where |
|---|---|---|
| Frontend | Vue 3 + Vue Router + Vite (plain JS, `<script setup>`) | Vercel project **merkis** (root `frontend/`) → merkis.app |
| Backend | Laravel 13, PHP 8.4, Sanctum (cookie/session SPA auth) | Vercel project **planner-api** (root `backend/`, `vercel-php@0.8.0`) → `planner-api-ralfs.vercel.app` |
| DB | PostgreSQL | Supabase, project ref `duudrkutiojibyxidaqt`, eu-west-1, **session pooler** (port 5432; direct conn is IPv6-only → unusable from Vercel) |
| Email | Resend via SMTP (`smtp.resend.com:587`, user `resend`) | domain merkis.app verified (DKIM `resend._domainkey`, `send`, `rsend`) |
| Domain/DNS | merkis.app bought on Vercel (Vercel DNS) | team `ralfs` |

Request flow: browser → merkis.app → `frontend/vercel.json` rewrites `/api/*` and `/sanctum/*` to the backend → same origin, so no CORS and cookies just work. Never call the backend URL directly from the browser.

## Design rules (non-negotiable — the owner checks these)
- **Only 3 colours:** Black `#000000`, Seagrass `#66a182`, White `#ffffff`. Greys are allowed **only** as black/white mixes (`color-mix`) for muted text and lines. No other hues — not even red for errors (errors = normal text + Seagrass ⓘ icon).
- Tokens live in `frontend/src/assets/main.css` (`--black --seagrass --white`, derived `--bg --fg --muted --line --frame --frame-edge --screen --glow`). Use tokens, never raw hex in components.
- Buttons on Seagrass use **black** text (7:1); white on Seagrass fails contrast.
- **Font: Montserrat everywhere** (matches the wordmark — Montserrat ExtraBold 800). Buttons/inputs need `font-family: inherit` (global rule exists). Clock is Montserrat 800 in Seagrass.
- Light + dark mode: `data-theme` on `<html>`, set pre-paint by the inline script in `frontend/index.html`, toggled via `src/lib/theme.js`. Light mode flips black↔white; Seagrass stays.
- **Minimal UI.** Owner dislikes extra text, feature claims for things that don't exist, emojis, and "cards" around the logo. Don't add copy or decoration unasked.
- Logo is a **raster PNG** (no vector exists): `src/assets/Logo.png` (white text, for dark) and `Logo-light.png` (black text, for light); checkmark-only `LogoMark(.png|-light.png)`. `AppLogo`/`LogoMark` components swap them by theme. Never recolour via CSS; never reintroduce the old SVG wordmark.
- Mobile first-class: no page scroll on landing/home (exact `100dvh`), inputs ≥16px (iOS zoom), safe-area padding, `overscroll-behavior: none`. Breakpoints: landing/home 640px, auth 860px.

## Map (where things live)
```
frontend/
  index.html            theme pre-paint script, icons, Montserrat font, viewport-fit=cover
  VERSION               "v1.1" → shown bottom-left on every page (AppVersion.vue)
  vercel.json           /api + /sanctum proxy to backend, SPA fallback
  public/               favicon.svg (switches black/white mark by scheme), favicon-32.png, favicon.ico,
                        apple-touch-icon.png (mark on black), icon-192/512.png, site.webmanifest, email/logo.png
  src/main.js           registers router + global $t
  src/router.js         / landing · /login + /register (same AuthView, meta.viewKey 'auth') · /home (requiresAuth); guard uses fetchUser
  src/lib/api.js        axios (baseURL /api, withCredentials, withXSRFToken, Accept-Language = locale)
  src/lib/auth.js       user ref, fetchUser/login/register/logout, changeLocale (PATCH /user/locale when signed in)
  src/lib/i18n.js       tiny i18n: locale ref, t(key, params), setLocale; default 'lv'; localStorage 'merkis-locale'
  src/lib/loader.js     withLoader(task, minMs=1100) → full-screen spinning LogoMark
  src/lib/theme.js      theme ref + toggleTheme (localStorage 'merkis-theme')
  src/lang/{lv,en}.json ALL UI strings (incl. month names). Add every new string to both.
  src/views/            LandingView (laptop+phone mockup w/ logo) · AuthView (login/register split, panels swap with slide+blur, strength meter) · HomeView ("Welcome, {name}")
  src/components/       AppLogo, LogoMark, LiveClock, ThemeToggle, LanguageSwitch, LoadingScreen, AppVersion
backend/
  api/index.php         Vercel entry; forces SCRIPT_NAME=/index.php (else /api prefix gets stripped → 404s)
  vercel.json           framework:null, outputDirectory public, all routes → api/index.php
  bootstrap/app.php     trustProxies('*'), statefulApi(), SetLocale on api group
  routes/api.php        GET health · POST register/login (throttle 10/min) · auth:sanctum: GET user, PATCH user/locale, POST logout
  app/Http/Controllers/Api/AuthController.php   register (saves locale, sends WelcomeMail in try/catch) · login · logout · user · updateLocale
  app/Http/Middleware/SetLocale.php             app locale from Accept-Language (lv|en)
  app/Mail/WelcomeMail.php + resources/views/emails/welcome(.blade|-text.blade).php
  app/Models/User.php   fillable locale; HasLocalePreference → mail auto-localised
  lang/lv/{auth,validation,mail}.php, lang/en/mail.php   (EN auth/validation come from the framework)
  config/app.php        supported_locales ['lv','en'], frontend_url
  tests/Feature/AuthTest.php   10 tests (12 total in suite): auth flow, locale, welcome mail, LV validation
```

## Commands
```bash
cd backend && php artisan test            # sqlite :memory: (phpunit.xml sets SANCTUM_STATEFUL_DOMAINS=localhost:5173)
npm --prefix frontend run build           # build check
npm --prefix frontend run dev             # :5173, proxies /api,/sanctum → :8000
```
Local backend against a throwaway DB (preferred — see "DANGER" below):
```bash
cd backend/public && DB_CONNECTION=sqlite DB_URL= DB_DATABASE=<scratch>/dev.sqlite SESSION_DRIVER=cookie \
  SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173 MAIL_MAILER=log FRONTEND_URL=http://localhost:5173 \
  php -S 127.0.0.1:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
```
(Run from `public/`; migrate the scratch DB first with the same DB_* vars + `php artisan migrate --force`.)

## Env vars (backend, Vercel → planner-api; secrets never in repo)
APP_KEY, APP_ENV=production, APP_DEBUG=false, APP_NAME, APP_URL, FRONTEND_URL=https://merkis.app, DB_CONNECTION=pgsql, DB_URL (Supabase session pooler), DB_SSLMODE=require, SANCTUM_STATEFUL_DOMAINS=`merkis.app,www.merkis.app` (hosts only — no `https://`), SESSION_DRIVER=cookie, CACHE_STORE=array, QUEUE_CONNECTION=sync, LOG_CHANNEL=stderr, VIEW_COMPILED_PATH=/tmp, APP_{CONFIG,EVENTS,PACKAGES,ROUTES,SERVICES}_CACHE=/tmp/*.php, MAIL_MAILER=smtp, MAIL_HOST=smtp.resend.com, MAIL_PORT=587, MAIL_USERNAME=resend, MAIL_PASSWORD (Resend key, Secret), MAIL_FROM_ADDRESS=hello@merkis.app, MAIL_FROM_NAME=Merkis.app. Env changes need a **Redeploy**.

## DANGER / rules of engagement
- **`backend/.env` points at PRODUCTION Supabase.** Any `php artisan migrate|tinker|db:*` without DB overrides hits live data. Never run `db:show` (prints the DB URL incl. password). Destructive DB ops (truncate/drop/delete) → give the owner the SQL; don't run them.
- Deploy order for schema changes: run `php artisan migrate` on Supabase **before** merging to `main` (new columns must exist before new code ships). Additive migrations only.
- Git: owner commits & pushes himself. Work on a `version/x.y` branch from `main`; never commit/push unless asked. Exclude `.idea/` and `.claude/` from suggested `git add` (use `git add backend frontend README.md CLAUDE.md`).
- Merging to `main` = production deploy of both Vercel projects.

## Known gotchas (each cost real time once)
- Login "works" with wrong passwords even if session auth is broken (fails before touching the session). Real check: `POST https://merkis.app/api/login` **without** X-XSRF-TOKEN must return **419**; 422 means SANCTUM_STATEFUL_DOMAINS is wrong/missing → successful logins 500.
- vercel-php: `/api/*` routes 404 unless `api/index.php` fakes SCRIPT_NAME. Vercel FS is read-only except `/tmp`. `framework: null` required or Vercel treats backend as Vite (`dist` error).
- Vercel preview/deployment URLs sit behind Vercel Authentication; production domains are public.
- CSS: `filter` or `will-change: transform` on an ancestor becomes the containing block for `position:absolute` children (caused the jumping header during the auth swap). Auth header (`.form-top`) is therefore a direct child of `.auth-page`, not of a panel.
- `localhost` in the test browser can hold stale cookie-session cookies from earlier runs → bogus 401s. Test auth on `http://127.0.0.1:5173` for a clean jar.
- Claude desktop preview launcher can't access ~/Documents via bash/python (macOS TCC) → start local servers with Bash `run_in_background` instead of `.claude/launch.json` entries (only the `frontend` npm entry works).
- Browser-pane screenshots at emulated sizes sometimes render scaled/mid-transition; trust `getBoundingClientRect` measurements, wait ~1s after transitions.
- New DNS names: local router/ISP caches NXDOMAIN for up to 15 min; check with `dig @1.1.1.1`.
- Email clients: table layout + inline styles only; logo must be an absolute PNG URL (`FRONTEND_URL/email/logo.png`).

## Efficient workflow (save tokens, keep quality)
- Trust the map above; read only the files you'll touch. Don't re-explore the tree or re-derive facts listed here.
- For UI changes verify in the browser pane: desktop 1280×800 + mobile 375×812 (+ 375×667), both themes, both languages, `scrollHeight === innerHeight` on landing/home.
- After code changes: `php artisan test` + frontend build. Add a feature test for new backend behaviour.
- Owner is learning hosting — give click-by-click steps for dashboards (Vercel/Supabase/Resend), with direct links when possible.

## Changelog
- **v1.1** — mobile polish (no-scroll landing, 50px buttons, 16px inputs, safe areas); LV/EN switcher with locale saved on user (`users.locale`, PATCH /api/user/locale, Accept-Language → localized server messages); Latvian/English HTML welcome email via Resend; auth header pinned during panel swap.
- **v1.0** — Sanctum auth (register/login/logout), landing/auth/home views, spinning-logo loader, password-strength meter, light/dark theme, favicons + home-screen icons, VERSION label, README.
- **v0** — Laravel + Vue scaffold, Vercel + Supabase hosting, merkis.app domain.

## Maintaining this file
On every change: update the relevant section(s) above (map, env vars, rules, gotchas) and add a Changelog line under the current version. On a version bump: update `frontend/VERSION`, the README badge, "Current version" at the top, and start a new Changelog entry. Remove anything that stops being true. Keep it under ~200 lines.

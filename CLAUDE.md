# Merkis.app — project guide for Claude

> Read this first. It is the source of truth for how the project is built, why, and what already went wrong once.
> **Keep it current:** every change that alters behaviour, structure, env vars, design rules or the version MUST update this file in the same change (see "Maintaining this file"). Keep it dense — it is loaded every session, so every line costs tokens.

## What it is
Mērķis = Latvian for "goal". A minimal planner web app, live at **https://merkis.app**. Current version: **v1.4** (`frontend/VERSION`). Owner: Ralfs (GitHub `RalfsXP/1`). Landing, auth, and the signed-in planner: side nav, Overview dashboard (/home), Calendar, Daily/Weekly/Monthly/Yearly/Custom-period goals (priorities, recurrence, completion per occurrence, time left, custom badges), Settings, Site health (all users; admins see internals + can run migrations), Whiteboards (Fabric.js).

## Stack & hosting (all free tier except the domain)
| Part | Tech | Where |
|---|---|---|
| Frontend | Vue 3 + Vue Router + Vite (plain JS, `<script setup>`) | Vercel project **merkis** (root `frontend/`) → merkis.app |
| Backend | Laravel 13, PHP 8.4, Sanctum (cookie/session SPA auth) | Vercel project **planner-api** (root `backend/`, `vercel-php@0.8.0`, **region `dub1`** = next to Supabase) → `planner-api-ralfs.vercel.app` |
| DB | PostgreSQL | Supabase, project ref `duudrkutiojibyxidaqt`, eu-west-1, **session pooler** (port 5432; direct conn is IPv6-only → unusable from Vercel) |
| Email | Resend via SMTP (`smtp.resend.com:587`, user `resend`) | domain merkis.app verified (DKIM `resend._domainkey`, `send`, `rsend`) |
| Domain/DNS | merkis.app bought on Vercel (Vercel DNS) | team `ralfs` |

Request flow: browser → merkis.app → `frontend/vercel.json` rewrites `/api/*` and `/sanctum/*` to the backend → same origin, so no CORS and cookies just work. Never call the backend URL directly from the browser.

## Design rules (non-negotiable — the owner checks these)
- **Designs (v1.4):** three switchable palettes, `data-design` on `<html>` (none = default), chosen in Settings and saved on the user (`users.design`, PATCH /api/user/design) + localStorage `merkis-design` (pre-painted in index.html):
  default = Black/Seagrass/White · `new` = Imperial Blue #0a2463, Blue Bell #3e92cc, Ghost White #fffaff, Magenta Bloom #d8315b, Carbon Black #1e1b18 · `ocean` = Sky #8ecae6, Blue Green #219ebc, Deep Space #023047, Amber #ffb703, Orange #fb8500.
  Components use ONLY role tokens — `--accent` / `--on-accent` (main actions + text on them), `--highlight` / `--on-highlight` (today, clock, logo ē/ķ marks), `--link`, plus `--bg --fg --muted --line --surface…` — never `--seagrass` or a palette colour directly. A new design = one `:root[data-design=x]` block + its light variant in main.css + config/app.php `designs` + DESIGNS in design.js + preview dots in DesignSwitch.
- **Default design: only 3 colours:** Black `#000000`, Seagrass `#66a182`, White `#ffffff`. Greys are allowed **only** as black/white mixes (`color-mix`) for muted text and lines. No other hues — not even red for errors (errors = normal text + Seagrass ⓘ icon).
- **Sole exception (owner-approved v1.2/v1.3):** priority flags (`--flag-low/high/important` = green/yellow/red), user-picked goal badge colours (presets in `SWATCHES` + free `<input type=color>`) and whiteboard content (ink + `SWATCHES`). Never use these hues for app chrome.
- Tokens live in `frontend/src/assets/main.css` (palettes, roles `--accent --on-accent --highlight --on-highlight --link`, derived `--bg --fg --muted --line --frame --frame-edge --screen --glow --surface --backdrop`, flags). Use tokens, never raw hex in components. Badge text colour comes from `readableOn(hex)` (black/white by luminance).
- **Icons: Heroicons only** (`@heroicons/vue`, 24/outline for UI, 20/solid for badges/flags). Badge icon list = `src/lib/icons.js` ⇄ `backend/config/goals.php` (keep in sync).
- Shared UI classes in main.css — use them, don't restyle per view: `.page(.narrow|.wide)`, `.page-title`, `.section-title`, `.section-label`, `.card`, `.list`, `.empty-state`, `.btn(-ghost|-primary|-lg)`, `.icon-btn(.bare)`, `.form-field/.form-label/.input`, `.segmented(.outline)`. One global `:focus-visible` ring. (AuthView has its own scoped `.field` — don't reuse that name globally.)
- Language switch + theme toggle live top-right in every layout (auth header, app top bar) — never in the side nav.
- Buttons on Seagrass use **black** text (7:1); white on Seagrass fails contrast.
- **Font: Montserrat everywhere** (matches the wordmark — Montserrat ExtraBold 800). Buttons/inputs need `font-family: inherit` (global rule exists). Clock is Montserrat 800 in Seagrass.
- Light + dark mode: `data-theme` on `<html>`, set pre-paint by the inline script in `frontend/index.html`, toggled via `src/lib/theme.js`. Light mode flips black↔white; Seagrass stays.
- **Minimal UI.** Owner dislikes extra text, feature claims for things that don't exist, emojis, and "cards" around the logo. Don't add copy or decoration unasked.
- **Logo (v1.2, owner-chosen option E):** lowercase wordmark **"mērķis"** — Montserrat 800 outlines in the text colour, with a Seagrass bar for the ē macron and a Seagrass dot for the ķ cedilla (letters are plain "merkis"). Mark = the "m" + Seagrass dot. Both are inline SVG components (`AppLogo`, `LogoMark`, `fill: currentColor` → follow the theme). Favicon = Seagrass tile + black m; home-screen icons = black + white m + Seagrass dot; email = white wordmark PNG. All generated by `frontend/scripts/build-logo.mjs` (outlines in `scripts/logo-geometry.json`) — regenerate, never hand-edit paths. The old checkmark PNG logo is gone; don't bring it back.
- Mobile first-class (owner uses it mostly on phone): no zoom (viewport `maximum-scale=1, user-scalable=no` + global `touch-action: manipulation` + iOS `gesturestart` guard in main.js); body never scrolls (exact `100dvh`); in the app shell only `<main>` scrolls. Inputs ≥16px (iOS zoom), safe-area padding, `overscroll-behavior: none`. Breakpoints: landing 640px; auth + app shell 860px (below: drawer nav + top bar; above: 248px side nav); calendar day panel beside the grid only ≥1180px; sheets become dialogs ≥640px.

## Map (where things live)
```
frontend/
  index.html            theme pre-paint script, icons, Montserrat font, viewport-fit=cover
  VERSION               "v1.4" → shown bottom-left (AppVersion.vue; inside the side nav in the app shell)
  vercel.json           /api + /sanctum proxy to backend, SPA fallback
  public/               (icon URLs carry ?v=2 in index.html + manifest — bump on any icon change) favicon.svg (Seagrass tile + black m), favicon-32.png, favicon.ico (16/32/48), apple-touch-icon.png + icon-192/512.png
                        (black, white m + dot; maskable), site.webmanifest, email/logo.png (white wordmark), brand/ (logo-on-dark|light.svg, mark.svg — README uses these)
  scripts/build-logo.mjs  generates all of the above from Montserrat 800 outlines (deps installed --no-save; see its header)
  src/main.js           registers router + global $t (+ iOS pinch-zoom guard)
  src/App.vue           <Analytics/> (Vercel Web Analytics, @vercel/analytics — enable it in Vercel → merkis → Analytics), page transition, AppVersion, LoadingScreen
  src/router.js         / landing · /login + /register (same AuthView, viewKey 'auth') · /home = AppShell (requiresAuth, viewKey 'app') with
                        children '' → DashboardView (name 'home'), /calendar → CalendarView, /goals/:period(daily|weekly|monthly|yearly|custom)?date= → GoalsView,
                        /whiteboards → WhiteboardsView, /whiteboards/:id → WhiteboardView (Fabric chunk),
                        /settings → SettingsView, /health → HealthView (every signed-in user). Nav order: Overview, Calendar, Daily…Yearly, Custom, Whiteboards;
                        Settings + Site health + Log out at the bottom
  src/lib/api.js        axios (baseURL /api, withCredentials, withXSRFToken, Accept-Language = locale)
  src/lib/auth.js       user ref, fetchUser/login/register/logout, changeLocale / changeDesign (PATCH when signed in); a signed-in user's locale + design win
  src/lib/i18n.js       tiny i18n: locale ref, t(key, params), setLocale; default 'lv'; localStorage 'merkis-locale'
  src/lib/loader.js     withLoader(task, minMs=1100) → full-screen spinning LogoMark
  src/lib/theme.js      theme ref + toggleTheme (localStorage 'merkis-theme')
  src/lib/design.js     design ref, DESIGNS, setDesign (data-design + localStorage), syncThemeColor (meta theme-color from a --bg probe; <body> fades)
  src/lib/input.js      touchUI (hover:none + pointer:coarse), hold-hint dismissal (localStorage 'merkis-hold-hint')
  src/lib/press.js      usePress: touch tap = click (a11y-safe), hold ≥450 ms = onHold + vibrate, swallows the click after a hold, move/scroll cancels
  src/lib/periods.js    date helpers (Monday weeks, ISO week no.), occursOn/occurrencesIn (mirror Goal::occursOn), deadlineOf, labels
  src/lib/goals.js      goal store (cache by id, cleared when the user changes; loadRange: in-flight dedupe + 30 s freshness, refreshTick on window focus;
                        merges completions outside the loaded window; setDone optimistic + stale-response guard), badgeFor, categoryStyle, SWATCHES
  src/lib/whiteboards.js  list/create/get/save/delete boards
  src/lib/timeLeft.js   timeLeft(deadline) via Intl.RelativeTimeFormat (LV plurals for free) + shared 30s `now` and `todayISO` (rolls over at midnight — use it, not a one-off today());
                        completionInfo(occ) → early (done on an earlier day than the deadline) / late / on time + date (LV day counts hand-declined: "1 dienu", "4 dienas")
  src/lib/icons.js      badge icon name → Heroicon component
  src/lang/{lv,en}.json ALL UI strings (incl. month/weekday names). Add every new string to both.
  src/views/            LandingView (logo + log in/sign up + lang/theme header; DeviceScene layout="landing"; phones: laptop + phone only,
                        buttons right under the scene, block above centre; never scrolls) · AuthView (same glow + wordmark-home/controls header;
                        art side = DeviceScene layout="auth"; login/register panels swap with slide+blur; phones: no scene, form only; strength meter)
                        DashboardView (3×2 tiles: yesterday, today, week, month, year, overdue = one-offs past deadline + repeating goals missed in the last 7 days —
                        a finished tile turns Seagrass with a check; 7-day completions bars; focus list = late one-offs, open now, then recent misses)
                        CalendarView (month + selected-day panel) · GoalsView (one category, period navigator — custom: past year + ahead, no navigator — FAB, completed group)
                        SettingsView (per-category default badge accordion, theme, language, account)
                        WhiteboardsView (cards: white preview, title rename, two-tap delete) · WhiteboardView (Fabric 7 + @erase2d/fabric: select, pan, pen,
                        eraser, text, StickyNote (custom Textbox class), rect/ellipse/line/arrow; ink stored black and swapped to white in dark mode;
                        wheel/trackpad/two-finger zoom+pan, Space-drag, fit; 50-step undo; 1.2 s debounced autosave with WebP preview; hotkeys V H P E T N R O L A)
                        HealthView (all users: API, DB latency/version/connections, migrations ran/total, DB size vs free limit, table rows/sizes,
                        Supabase+Vercel statuspage (fetched by the browser, CORS), config checks; OK = Seagrass check, problems = text + ⚠ — no red;
                        "Run migrations" button — admins only (report.can_migrate) — when some are pending: two taps, POST /api/admin/migrate, shows artisan output)
  src/components/       DesignSwitch (Settings slider, sliding thumb, palette dots), HoldHint (touch only, dismiss once), DeviceScene (shared floating 3D laptop/tablet/phone; layouts landing | auth; sizes in stage cqw), DemoScreen (static miniature of the app in cqw — variant desktop=Overview, tablet=Calendar, phone=today; demo.* strings;
                        root classes are v-* because a parent's scoped .phone/.tablet would hit a child root), AppLogo (wordmark SVG), LogoMark (m. SVG), LiveClock (top bar, every page), ThemeToggle, LanguageSwitch,
                        LoadingScreen (m. breathing inside an orbiting ring), AppVersion,
                        AppShell (layout/drawer), SideNav, CalendarMonth, GoalItem, GoalBadge, PriorityFlag, GoalForm,
                        BadgePicker, BottomSheet (grabber, drag-down to close), ProgressMeter (done/total bar),
                        DatePicker (brand calendar: day / whole week / whole month / year tiles / custom 2-tap range; current period highlighted from today;
                        `series` prop shades a repeating goal's later occurrences up to its until; min/max), DateField (optional date in any period mode → inline DatePicker)
backend/
  api/index.php         Vercel entry; forces SCRIPT_NAME=/index.php (else /api prefix gets stripped → 404s)
  vercel.json           framework:null, outputDirectory public, all routes → api/index.php
  bootstrap/app.php     trustProxies('*'), statefulApi(), SetLocale + ServerTiming on api group
  vercel.json           regions ["dub1"] — the function must sit next to Supabase eu-west-1 (iad1 made every query ~200 ms)
  app/Http/Middleware/ServerTiming.php   `Server-Timing: app;dur, db;dur;desc="N queries"` on every API response (devtools → Network → Timing)
  routes/api.php        GET health · POST register/login (throttle 10/min) · auth:sanctum: GET user, PATCH user/locale, PATCH user/design,
                        PUT user/goal-styles, POST logout, goals (GET ?from&to, POST, PATCH, DELETE), PUT|DELETE goals/{goal}/completions/{Y-m-d}
  app/Http/Controllers/Api/GoalController.php   range query (+ PHP overlap filter), normalise (snap dates, drop N/A recurrence/due_at), per-occurrence completions; other users' goals → 404
  app/Http/Controllers/Api/GoalStyleController.php  per-category default badge {color, icon} → users.goal_styles
  app/Models/Goal.php   period day|week|month|year|custom, period_start (first occurrence), period_end (custom only; custom never repeats), priority, due_at (one-off only), repeats, repeat_days (ISO weekdays, daily only, null = every day),
                        repeat_until, badge_color/icon (null = category default); appends completed_on[] + completed_at{occurs_on: ISO}. GoalCompletion = (goal_id, occurs_on) unique
  config/goals.php      periods, priorities, icon names, default category styles
  app/Http/Controllers/Api/WhiteboardController.php  apiResource whiteboards (index without data, newest first; data ≤ 2 MB, preview ≤ 200 KB data:image; other users → 404)
  app/Models/Whiteboard.php  title, data (json Fabric scene), preview; lang/{lv,en}/whiteboard.php
  config/health.php     admin_emails (ADMIN_EMAILS: full health details + Run migrations), db_limit_mb (DB_SIZE_LIMIT_MB, default 500 = Supabase free)
  app/Http/Controllers/Api/SystemHealthController.php  GET /api/admin/health (any signed-in user; ~5 queries; non-admins get host, config values and row counts nulled) + POST /api/admin/migrate (admins only, else 404; throttle 3/min; runs `migrate --force`); each probe isolated (try/catch), never returns secrets;
                        platform feeds fetched in parallel (Http::pool, 3 s timeout); Sanctum check compares host[:port]
  app/Http/Controllers/Api/AuthController.php   register (saves locale, sends WelcomeMail in try/catch) · login · logout · user · updateLocale
  app/Http/Middleware/SetLocale.php             app locale from Accept-Language (lv|en)
  app/Mail/WelcomeMail.php + resources/views/emails/welcome(.blade|-text.blade).php
  app/Models/User.php   fillable locale, design, goal_styles (json); appends is_admin (email ∈ ADMIN_EMAILS); hasMany goals; HasLocalePreference → mail auto-localised
  lang/lv/{auth,validation,mail}.php, lang/en/{mail,validation}.php   (EN validation = framework file + our attribute names)
  config/app.php        supported_locales ['lv','en'], designs ['default','new','ocean'], frontend_url
  tests/Feature/AuthTest.php   10 tests: auth flow, locale, welcome mail, LV validation
  tests/Feature/GoalTest.php   13 tests: CRUD, snapping, range/recurrence listing (incl. across New Year), custom periods, per-occurrence completion, ownership, styles
  tests/Feature/SystemHealthTest.php   4 tests: non-admin view redacted, is_admin flag, admin full report incl. no secret leakage, run pending migrations (admin only)
  tests/Feature/WhiteboardTest.php     5 tests: auth, CRUD + LV default title, list without data, ownership 404, size/preview limits
  tests/Feature/DemoSeederTest.php     2 tests: full demo account, re-runnable, refuses without DEMO_PASSWORD (37 total in suite; AuthTest has 11 incl. design)
  database/seeders/DemoSeeder.php  demo@demo.lv "Demo": habits with ~6 weeks of history, goals around today, week/month/year/custom goals,
                        badges, 2 boards — dates relative to the run day; replaces only the demo user; password from DEMO_PASSWORD (never in the repo)
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
Demo account (prod or scratch): `DEMO_PASSWORD='…' php artisan db:seed --class=DemoSeeder --force` — owner runs it on prod.

## Env vars (backend, Vercel → planner-api; secrets never in repo)
APP_KEY, APP_ENV=production, APP_DEBUG=false, APP_NAME, APP_URL, FRONTEND_URL=https://merkis.app, DB_CONNECTION=pgsql, DB_URL (Supabase session pooler), DB_SSLMODE=require, SANCTUM_STATEFUL_DOMAINS=`merkis.app,www.merkis.app` (hosts only — no `https://`), SESSION_DRIVER=cookie, CACHE_STORE=array, QUEUE_CONNECTION=sync, LOG_CHANNEL=stderr, VIEW_COMPILED_PATH=/tmp, APP_{CONFIG,EVENTS,PACKAGES,ROUTES,SERVICES}_CACHE=/tmp/*.php, MAIL_MAILER=resend (HTTP API, one request; resend/resend-php) + RESEND_API_KEY (Secret) — the SMTP vars MAIL_HOST=smtp.resend.com, MAIL_PORT=587, MAIL_USERNAME=resend, MAIL_PASSWORD stay as a fallback (MAIL_MAILER=smtp), MAIL_FROM_ADDRESS=hello@merkis.app, MAIL_FROM_NAME=Merkis.app, ADMIN_EMAILS (comma list; who sees Site health), optional DB_SIZE_LIMIT_MB (default 500). Optional: Vercel "Automatically expose System Environment Variables" → health page shows region + commit. Env changes need a **Redeploy**.

## DANGER / rules of engagement
- **`backend/.env` points at PRODUCTION Supabase.** Any `php artisan migrate|tinker|db:*` without DB overrides hits live data. Never run `db:show` (prints the DB URL incl. password). Destructive DB ops (truncate/drop/delete) → give the owner the SQL; don't run them.
- Deploy order for schema changes: run `php artisan migrate` on Supabase **before** merging to `main` (new columns must exist before new code ships). Additive migrations only. v1.2 adds `goals`, `goal_completions`, `users.goal_styles`. From v1.2 on, Site health → "Run migrations" can do it from the live site — but only once code that runs without the new columns is deployed; for v1.2 itself run it from the terminal first.
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
- GoalForm: type switching runs in `choosePeriod()` (the pill tap), never a `watch(form.period)` — loading a goal into the form changes the period too and a watcher wiped saved dates (custom end).
- Browser pane hidden ⇒ Vue leave transitions never finish (they wait on rAF): closed sheets stay in the DOM. In scripted checks read the LAST `.sheet`, or reload per case.
- AppShell's page `<Transition mode="out-in">` keeps the old view mounted while the route already changed: read route params once at setup in views (GoalsView), never reactively.
- Slow prod = check `x-vercel-id`: the middle region code is where the PHP function ran; it must be dub1 (Supabase is eu-west-1). Each request opens a fresh DB connection, so distance multiplies.
- Goal rows: desktop = check toggles, click edits; touch (`touchUI`) = tap anywhere toggles, hold edits (GoalItem + calendar strip badges via usePress). Don't add click-to-edit on touch.
- BottomSheet focuses `[autofocus]` inside it if present, else the dialog.
- Whiteboard: Fabric needs `enablePointerEvents: true`; the surface needs its own `touch-action: none` (global is `manipulation`). Never rebuild `freeDrawingBrush` in a capture-phase pointerup — Fabric then finishes the stroke on an empty brush (a dot at 0,0). Fabric 7 default origin is centre.
- Every period (incl. 'custom') needs an entry in BOTH `DEFAULT_STYLES` (src/lib/goals.js) and `config/goals.php` — a missing one makes `readableOn(undefined)` crash the render.
- Dates: SQLite (tests) stores dates as text → bind query dates as `toDateString()`, never Carbon. JS week numbers: round the day diff (DST makes it an hour short).
- Browser pane: background-tab rendering is throttled → CSS transitions/screenshots lag seconds; verify via DOM/JS, not screenshots taken right after a click. For screenshots inject a temporary `*{transition:none!important}` style via javascript_tool (debug only), and use `zoom` to force a fresh capture.

## Efficient workflow (save tokens, keep quality)
- Trust the map above; read only the files you'll touch. Don't re-explore the tree or re-derive facts listed here.
- For UI changes verify in the browser pane: desktop 1280×800 + mobile 375×812 (+ 375×667), both themes, both languages, `scrollHeight === innerHeight` on landing/home.
- After code changes: `php artisan test` + frontend build. Add a feature test for new backend behaviour.
- Owner is learning hosting — give click-by-click steps for dashboards (Vercel/Supabase/Resend), with direct links when possible.

## Changelog
- **v1.4** — design switch in Settings (labels Noklusējuma / V2 / V3 = ids default / new / ocean: V2 Imperial Blue + Blue Bell + Magenta, V3 Deep Space + Amber + Orange), each light + dark, saved on the account; colours refactored to role tokens; phones: tap = done, hold = edit (+ dismissible hint); whiteboards ask for a name first.
- **v1.3** — speed: API moved to dub1 next to Supabase, Server-Timing header, health endpoint ~15 → 5 queries with platform status fetched by the browser, deduplicated/cached goal loads + refresh on focus, Resend HTTP mailer; Whiteboards (Fabric.js: pen, eraser, text, sticky notes, shapes, arrows, zoom/pan, undo, autosave, previews); overdue tile also counts repeating goals missed in the last 7 days; goal cache cleared on account switch; DemoSeeder for a presentable demo account; boards without a preview get one on first open.
- **v1.2** — planner: side nav (drawer on phones), calendar home (badges/dots, week numbers, swipe, day panel), Daily/Weekly/Monthly/Yearly goal pages, priorities with green/yellow/red flags, recurring goals (every period or chosen weekdays) completed per occurrence, time-left countdowns + optional earlier deadline, per-category and per-goal badge colour/icon, Heroicons everywhere (auth + theme toggle icons migrated). Completion timestamps per occurrence → "Finished 4 days early · Wed 7 Oct" / late / on time; progress meters; full-width colour chips in calendar cells; calm done rows; sheet grabber + drag-to-close. Overview dashboard at /home, calendar moved to /calendar, Settings page (category badges, theme, language), custom brand date picker (whole-week/month highlight), custom-period goals (start–end, shown as bands in the calendar). Audit pass: controls top-right like auth, picker shades repeat series, form keeps the picked day across type switches, deadline capped to the period, until in period units, calendar selection follows month paging, New-Year/custom completion fixes, a11y (labels, focus trap, inert drawer), shared page classes. Overview tiles 3×2 incl. yesterday + overdue with green check when done; zoom disabled on phones. Site health page for admins (ADMIN_EMAILS). Date pickers verified for create + edit of every period type; fixed custom end being wiped on edit. New logo: "mērķis" wordmark (vector, generated) + m. mark, new favicons/app icons/email logo; Run migrations button on Site health; Overview: clock in the top bar, no separate date line under the greeting. Site health visible to all signed-in users (internals redacted for non-admins). Landing remade as a 3D laptop/tablet/phone scene with live demo screens; login/register match it (shared DeviceScene); mobile landing without tablet, buttons under the scene.
- **v1.1** — mobile polish (no-scroll landing, 50px buttons, 16px inputs, safe areas); LV/EN switcher with locale saved on user (`users.locale`, PATCH /api/user/locale, Accept-Language → localized server messages); Latvian/English HTML welcome email via Resend; auth header pinned during panel swap.
- **v1.0** — Sanctum auth (register/login/logout), landing/auth/home views, spinning-logo loader, password-strength meter, light/dark theme, favicons + home-screen icons, VERSION label, README.
- **v0** — Laravel + Vue scaffold, Vercel + Supabase hosting, merkis.app domain.

## Maintaining this file
On every change: update the relevant section(s) above (map, env vars, rules, gotchas) and add a Changelog line under the current version. On a version bump: update `frontend/VERSION`, the README badge, "Current version" at the top, and start a new Changelog entry. Remove anything that stops being true. Keep it under ~200 lines.

<script setup>
import { GUIDE, parts } from '@/lib/guide'
import { theme } from '@/lib/theme'

// Step-by-step guide (English only) with phone screenshots that follow the light / dark theme.
const src = (name) => `/guide/${name}-${theme.value}.webp`

// Scroll <main> itself: scrollIntoView would also scroll the (locked) window on phones.
function jump(id) {
  const el = document.getElementById(`guide-${id}`)
  const main = el?.closest('main')
  if (!main) return el?.scrollIntoView()
  const margin = parseFloat(getComputedStyle(el).scrollMarginTop) || 0
  const top = main.scrollTop + el.getBoundingClientRect().top - main.getBoundingClientRect().top - margin
  main.scrollTo({ top, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' })
}
</script>

<template>
  <div class="page wide guide">
    <header class="page-head">
      <div>
        <h1 class="page-title">{{ $t('guide.title') }}</h1>
        <p class="page-sub">{{ $t('guide.lead') }}</p>
      </div>
    </header>

    <nav class="toc" :aria-label="$t('guide.contents')">
      <button v-for="section in GUIDE" :key="section.id" type="button" class="toc-link" @click="jump(section.id)">
        {{ section.title }}
      </button>
    </nav>

    <section
      v-for="(section, n) in GUIDE"
      :id="`guide-${section.id}`"
      :key="section.id"
      v-reveal
      class="card topic"
      :class="{ 'no-shots': !section.shots.length }"
      :aria-labelledby="`guide-${section.id}-title`"
    >
      <div class="text">
        <h2 :id="`guide-${section.id}-title`" class="section-title topic-title">
          <span class="topic-no">{{ n + 1 }}</span>{{ section.title }}
        </h2>
        <ol class="steps">
          <li v-for="(step, i) in section.steps" :key="i">
            <template v-for="(part, j) in parts(step)" :key="j">
              <strong v-if="part.bold">{{ part.text }}</strong>
              <template v-else>{{ part.text }}</template>
            </template>
          </li>
        </ol>
      </div>

      <div v-if="section.shots.length" class="shots" :class="{ single: section.shots.length === 1 }">
        <img
          v-for="shot in section.shots"
          :key="shot.name"
          class="shot"
          :src="src(shot.name)"
          :alt="shot.alt"
          width="390"
          height="844"
          loading="lazy"
          decoding="async"
        />
      </div>
    </section>
  </div>
</template>

<style scoped>
/* Contents: wrapping white pills that jump to a topic. */
.toc {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.toc-link {
  min-height: 42px;
  padding: 0 16px;
  border: 0;
  border-radius: 999px;
  background: var(--surface);
  box-shadow: var(--card-shadow), inset 0 0 0 1px var(--card-edge);
  color: var(--fg);
  font-size: 0.88rem;
  font-weight: 700;
  cursor: pointer;
  transition: background-color 0.2s, color 0.2s, transform 0.25s var(--spring);
}

@media (hover: hover) {
  .toc-link:hover {
    background: var(--accent);
    color: var(--on-accent);
  }
}

.toc-link:active {
  transform: scale(0.94);
}

/* Phones: one row that swipes sideways instead of a tall block of pills. */
@media (max-width: 859px) {
  .toc {
    flex-wrap: nowrap;
    margin: 0 -18px;
    padding: 2px 18px 6px;
    overflow-x: auto;
    scrollbar-width: none;
  }

  .toc::-webkit-scrollbar {
    display: none;
  }

  .toc-link {
    flex-shrink: 0;
    white-space: nowrap;
  }
}

/* One topic: steps, then the screenshots (side by side on wide screens). */
.topic {
  display: grid;
  gap: 20px;
  padding: 22px 20px;
  /* Land below the phone top bar when jumped to. */
  scroll-margin-top: 96px;
}

.topic-title {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0 0 14px;
}

.topic-no {
  display: grid;
  flex-shrink: 0;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 12px;
  background: var(--topic-tint, var(--lav));
  color: var(--ink);
  font-family: var(--font-display);
  font-size: 1rem;
  font-weight: 800;
  transform: rotate(-6deg);
}

.topic:nth-of-type(5n + 2) { --topic-tint: var(--mint); }
.topic:nth-of-type(5n + 3) { --topic-tint: var(--peach); }
.topic:nth-of-type(5n + 4) { --topic-tint: var(--butter); }
.topic:nth-of-type(5n + 5) { --topic-tint: var(--sky); }

.steps {
  display: grid;
  gap: 12px;
  margin: 0;
  padding: 0;
  list-style: none;
  counter-reset: step;
}

.steps li {
  position: relative;
  padding-left: 34px;
  font-size: 0.95rem;
  font-weight: 500;
  line-height: 1.5;
  counter-increment: step;
}

.steps li::before {
  content: counter(step);
  position: absolute;
  top: 1px;
  left: 0;
  display: grid;
  place-items: center;
  width: 24px;
  height: 24px;
  border-radius: 50%;
  background: var(--surface-2);
  color: var(--fg);
  font-size: 0.75rem;
  font-weight: 700;
}

.steps strong {
  font-weight: 700;
}

.shots {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  align-items: start;
}

.shots.single {
  grid-template-columns: minmax(0, 1fr);
  justify-items: center;
}

.shots.single .shot {
  width: min(100%, 260px);
}

.shot {
  display: block;
  width: 100%;
  height: auto;
  border-radius: 24px;
  background: var(--bg);
  box-shadow: 0 12px 30px -16px rgb(0 0 0 / 0.35), 0 0 0 1px var(--line);
}

@media (min-width: 860px) {
  .topic {
    padding: 28px;
    scroll-margin-top: 24px;
  }

  .shots {
    max-width: 560px;
  }
}

/* Wide screens: text left, screenshots right. */
@media (min-width: 1180px) {
  .topic {
    grid-template-columns: minmax(0, 1fr) 460px;
    gap: 32px;
    align-items: start;
  }

  .topic.no-shots {
    grid-template-columns: minmax(0, 1fr);
  }

  .shots.single .shot {
    width: 224px;
  }
}
</style>

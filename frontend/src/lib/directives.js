import { reducedMotion } from './motion'

/**
 * v-slide-pill: a pill that slides between the options of a pill group instead of each option lighting up
 * on its own. Used on every `.segmented` (chosen = aria-pressed="true") and on tab rows
 * (`v-slide-pill="'.tab.active'"`). It adds `.has-pill` + one `.seg-pill` element; CSS in main.css.
 */
export const vSlidePill = {
  mounted(el, binding) {
    const selector = binding.value || '[aria-pressed="true"]'
    const pill = document.createElement('span')
    pill.className = 'seg-pill'
    pill.setAttribute('aria-hidden', 'true')
    el.prepend(pill)
    el.classList.add('has-pill')
    let first = true
    const place = () => {
      const target = el.querySelector(selector)
      if (!target) {
        pill.style.opacity = '0'
        return
      }
      if (first) pill.style.transition = 'none'
      pill.style.opacity = '1'
      pill.style.width = `${target.offsetWidth}px`
      pill.style.height = `${target.offsetHeight}px`
      pill.style.transform = `translate(${target.offsetLeft}px, ${target.offsetTop}px)`
      if (first) {
        first = false
        requestAnimationFrame(() => requestAnimationFrame(() => (pill.style.transition = '')))
      }
    }
    el._pill = {
      mo: new MutationObserver(place),
      ro: new ResizeObserver(place),
    }
    el._pill.mo.observe(el, { subtree: true, childList: true, attributes: true, attributeFilter: ['aria-pressed', 'class'] })
    el._pill.ro.observe(el)
    place()
    document.fonts?.ready.then(place)
  },
  unmounted(el) {
    el._pill?.mo.disconnect()
    el._pill?.ro.disconnect()
  },
}

/** v-reveal: the element rises in the first time it scrolls into view (long pages on phones). */
let observer
const watcher = () =>
  (observer ??= new IntersectionObserver(
    (entries) =>
      entries.forEach((e) => {
        if (!e.isIntersecting) return
        e.target.classList.add('revealed')
        observer.unobserve(e.target)
      }),
    { rootMargin: '0px 0px -6% 0px' },
  ))

export const vReveal = {
  mounted(el) {
    if (reducedMotion()) return
    el.classList.add('reveal')
    watcher().observe(el)
  },
  unmounted(el) {
    observer?.unobserve(el)
  },
}

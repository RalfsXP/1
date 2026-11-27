/**
 * Cached Intl formatters. Building one costs far more than using it, and every goal/event row formats
 * dates and countdowns — a fresh formatter per row made long lists stutter when they appeared.
 */
const cache = new Map()

export function intl(Ctor, locale, options = {}) {
  const key = `${Ctor.name}|${locale}|${JSON.stringify(options)}`
  let formatter = cache.get(key)
  if (!formatter) cache.set(key, (formatter = new Ctor(locale, options)))
  return formatter
}

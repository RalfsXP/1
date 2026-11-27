import {
  BookOpenIcon,
  CakeIcon,
  CalendarDateRangeIcon,
  CalendarDaysIcon,
  CalendarIcon,
  Cog6ToothIcon,
  FlagIcon,
  Squares2X2Icon,
  StarIcon,
  SunIcon,
  ViewColumnsIcon,
} from '@heroicons/vue/24/outline'
import { logout } from './auth'
import { withLoader } from './loader'

/**
 * Main navigation (v1.8): the desktop top pill nav and the big tiles of the phone menu (`tint` = pastel token).
 * `names` = route names that light the item up (Goals covers every period page).
 */
export const NAV_MAIN = [
  { to: { name: 'home' }, label: 'nav.home', icon: Squares2X2Icon, names: ['home'], tint: 'lav' },
  { to: { name: 'calendar' }, label: 'nav.calendar', icon: CalendarDaysIcon, names: ['calendar'], tint: 'sky' },
  { to: { name: 'goals', params: { period: 'daily' } }, label: 'nav.goals', icon: FlagIcon, names: ['goals'], tint: 'butter' },
  { to: { name: 'events' }, label: 'nav.events', icon: CakeIcon, names: ['events'], tint: 'rose' },
  // Whiteboards (/whiteboards) are hidden from the menus for now; the pages still work by URL.
]

/** Goal categories: the tabs on the Goals page and the tiles in the phone menu. `tint` = pastel token. */
export const GOAL_TABS = [
  { period: 'daily', key: 'day', label: 'periods.day', icon: SunIcon, tint: 'lav' },
  { period: 'weekly', key: 'week', label: 'periods.week', icon: ViewColumnsIcon, tint: 'sky' },
  { period: 'monthly', key: 'month', label: 'periods.month', icon: CalendarIcon, tint: 'butter' },
  { period: 'yearly', key: 'year', label: 'periods.year', icon: StarIcon, tint: 'mint' },
  { period: 'custom', key: 'custom', label: 'periods.custom', icon: CalendarDateRangeIcon, tint: 'peach' },
]

export const NAV_FOOT = [
  { to: { name: 'guide' }, label: 'nav.guide', icon: BookOpenIcon, names: ['guide'] },
  { to: { name: 'settings' }, label: 'nav.settings', icon: Cog6ToothIcon, names: ['settings'] },
  // Site health (/health) is hidden from the menus since v1.6.3; the page still works by URL.
]

export const isActive = (link, route) => link.names.includes(route.name)

export function signOut(router) {
  return withLoader(async () => {
    await logout()
    await router.push({ name: 'landing' })
  })
}

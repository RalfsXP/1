import {
  CakeIcon,
  CalendarDateRangeIcon,
  CalendarDaysIcon,
  CalendarIcon,
  Cog6ToothIcon,
  SignalIcon,
  Squares2X2Icon,
  StarIcon,
  SunIcon,
  ViewColumnsIcon,
} from '@heroicons/vue/24/outline'
import { logout } from './auth'
import { withLoader } from './loader'

/** App navigation, shared by the desktop side nav and the phone menu sheet. */
export const NAV_LINKS = [
  { to: { name: 'home' }, label: 'nav.home', icon: Squares2X2Icon },
  { to: { name: 'calendar' }, label: 'nav.calendar', icon: CalendarDaysIcon },
  { to: { name: 'events' }, label: 'nav.events', icon: CakeIcon },
  { to: { name: 'goals', params: { period: 'daily' } }, label: 'periods.day', icon: SunIcon },
  { to: { name: 'goals', params: { period: 'weekly' } }, label: 'periods.week', icon: ViewColumnsIcon },
  { to: { name: 'goals', params: { period: 'monthly' } }, label: 'periods.month', icon: CalendarIcon },
  { to: { name: 'goals', params: { period: 'yearly' } }, label: 'periods.year', icon: StarIcon },
  { to: { name: 'goals', params: { period: 'custom' } }, label: 'periods.custom', icon: CalendarDateRangeIcon },
  // Whiteboards (/whiteboards) are hidden from the menus for now; the pages still work by URL.
]

export const NAV_FOOT = [
  { to: { name: 'settings' }, label: 'nav.settings', icon: Cog6ToothIcon },
  { to: { name: 'health' }, label: 'nav.health', icon: SignalIcon },
]

export function signOut(router) {
  return withLoader(async () => {
    await logout()
    await router.push({ name: 'landing' })
  })
}

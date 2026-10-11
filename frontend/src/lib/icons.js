// Goal badge icons (Heroicons, 20px solid). Keep in sync with backend/config/goals.php.
import {
  AcademicCapIcon, BanknotesIcon, BeakerIcon, BoltIcon, BookOpenIcon, BriefcaseIcon, CakeIcon,
  CalendarIcon, CameraIcon, CheckBadgeIcon, ClockIcon, FaceSmileIcon, FireIcon, FlagIcon, GiftIcon,
  GlobeEuropeAfricaIcon, HeartIcon, HomeIcon, LightBulbIcon, MoonIcon, MusicalNoteIcon,
  PaperAirplaneIcon, PuzzlePieceIcon, RocketLaunchIcon, ShoppingCartIcon, SparklesIcon, StarIcon,
  SunIcon, TrophyIcon, UserGroupIcon,
} from '@heroicons/vue/20/solid'

export const GOAL_ICONS = {
  flag: FlagIcon,
  star: StarIcon,
  heart: HeartIcon,
  bolt: BoltIcon,
  fire: FireIcon,
  trophy: TrophyIcon,
  sun: SunIcon,
  moon: MoonIcon,
  'book-open': BookOpenIcon,
  'academic-cap': AcademicCapIcon,
  briefcase: BriefcaseIcon,
  home: HomeIcon,
  'shopping-cart': ShoppingCartIcon,
  banknotes: BanknotesIcon,
  'musical-note': MusicalNoteIcon,
  camera: CameraIcon,
  'globe-europe-africa': GlobeEuropeAfricaIcon,
  'paper-airplane': PaperAirplaneIcon,
  'light-bulb': LightBulbIcon,
  'puzzle-piece': PuzzlePieceIcon,
  'rocket-launch': RocketLaunchIcon,
  sparkles: SparklesIcon,
  'face-smile': FaceSmileIcon,
  'user-group': UserGroupIcon,
  calendar: CalendarIcon,
  clock: ClockIcon,
  'check-badge': CheckBadgeIcon,
  beaker: BeakerIcon,
  cake: CakeIcon,
  gift: GiftIcon,
}

export const ICON_NAMES = Object.keys(GOAL_ICONS)

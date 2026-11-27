/**
 * Content of the Guide page (English only, by the owner's choice — it isn't in lang/*.json).
 * `shots` are phone screenshots in public/guide/<name>-<dark|light>.webp, regenerated with
 * scripts/guide-shots.mjs. **Bold** marks the names of buttons and fields as they appear in the app.
 * Keep this in step with the app: when a feature changes, update the steps and retake the shots.
 */
export const GUIDE = [
  {
    id: 'start',
    title: 'Get started',
    shots: [
      { name: 'landing', alt: 'Start page with the Sign up and Log in buttons' },
      { name: 'signup', alt: 'Create your account form' },
    ],
    steps: [
      'Open merkis.app and tap **Sign up**.',
      'Enter your **Name**, **Email** and a **Password** of at least 8 characters. The strength bar fills up as the password gets stronger.',
      'Type the password again in **Confirm password** and tap **Create account**. You are signed in straight away and a welcome email is on its way.',
      'Next time, tap **Log in** on the start page and use the same email and password.',
    ],
  },
  {
    id: 'around',
    title: 'Find your way around',
    shots: [{ name: 'menu', alt: 'The menu opened from the round button' }],
    steps: [
      'On a phone, tap the round dark button in the bottom-right corner: it opens into the menu. At the top is the button to add something to the page you are on (**New goal** or **New event**), then **Overview**, **Calendar**, **Goals** and **Events**, the goal categories, and **Guide**, **Settings** and **Log out**. Tap **×**, or anywhere outside it, to close it.',
      'On a computer, the same pages sit in the bar at the top, and each page has its own **New goal** / **New event** button next to its title. Tap your initial in the top-right corner for **Settings**.',
      'Tap the **mērķis** logo to go back to the Overview. When you scroll down, the page name moves into the bar at the top.',
      'The sun / moon button in the top-right corner switches between light and dark mode.',
    ],
  },
  {
    id: 'overview',
    title: 'Overview',
    shots: [{ name: 'overview', alt: 'Overview with progress rings, the streak and the last 7 days' }],
    steps: [
      '**Goals today** shows how many of today’s daily goals are done as a ring that fills up. When everything is done, it closes with a check mark.',
      '**Goals yesterday** shows how yesterday went. Tap either ring to open those daily goals.',
      '**Streak** counts the days in a row on which every daily goal was done. Days without daily goals don’t break it. The dots show the last 7 days.',
      '**Completed · last 7 days** counts the goals you finished each day. Tap a bar to see its number; today is the dark bar.',
      '**Coming up** shows your next three events with their countdowns. The arrow opens **Events**.',
      '**In focus now** lists what needs attention: overdue goals first, then everything that is open right now. Tick goals off right there.',
      '**Calendar** at the top of that list opens the calendar.',
    ],
  },
  {
    id: 'add-goal',
    title: 'Add a goal',
    shots: [
      { name: 'goal-form', alt: 'New goal: name, type and date' },
      { name: 'goal-form-more', alt: 'New goal: repeat days, priority and badge' },
    ],
    steps: [
      'Open **Goals**, pick a category at the top (**Day**, **Week**, **Month**, **Year** or **Custom**), then open the menu and tap **New goal** (on a computer: **New goal** next to the title).',
      'Write what you want to do in **Goal**.',
      'Choose the **Type**: Day, Week, Month, Year or Custom.',
      'Pick the **Date**. For a week, month or year the whole period is selected. For Custom, tap the first day and then the last day.',
      'Under **Repeat**, keep **Once** or choose **Every day** (week, month, year). A daily goal can repeat on chosen weekdays only: tap the days under **On days**. Set **Until** if the goal should stop repeating.',
      'A one-off goal can have an **Earlier deadline**: a date and time before the end of its period.',
      'Choose a **Priority**: Low, High or Important. Days with an important goal get a flag in the calendar.',
      'Add **Notes** if you like.',
      'The goal gets its category’s badge. Switch on **Custom badge** to choose its own colour and icon.',
      'Tap **Save**.',
    ],
  },
  {
    id: 'complete',
    title: 'Complete, edit and delete goals',
    shots: [
      { name: 'daily', alt: 'Daily goals with progress and a completed goal' },
      { name: 'goal-edit', alt: 'Edit goal with the Delete and Save buttons' },
    ],
    steps: [
      'The card at the top shows the period (Today, This week…) and a progress bar. Use the arrows to look at earlier or later periods, and tap **Today** to come back.',
      'On a phone, tap a goal to mark it done and tap it again to undo. On a computer, click the circle. Done goals take on their badge colour.',
      'Finished goals move to **Completed** with the time you finished them, and whether that was early, on time or late.',
      'A repeating goal is completed separately every time it comes round, so ticking off today doesn’t touch tomorrow.',
      'To edit a goal, hold it on a phone or click its name on a computer. Change anything and tap **Save**.',
      'To delete a goal, open it, tap **Delete** and then **Confirm delete**. This can’t be undone.',
    ],
  },
  {
    id: 'periods',
    title: 'Weekly, monthly, yearly and custom goals',
    shots: [
      { name: 'weekly', alt: 'Weekly goals for this week' },
      { name: 'custom', alt: 'Custom goals with their date ranges' },
    ],
    steps: [
      'Switch categories with the pills at the top of **Goals**. **Week** goals run from Monday to Sunday; **Month** and **Year** goals cover the whole month or year. They work just like daily goals.',
      'Every goal shows how much time is left, for example “in 3 days”.',
      '**Custom** goals cover any range of days you choose, like a renovation or a holiday. They don’t repeat.',
      'In the calendar, weekly goals show next to the week number, monthly and yearly goals above the month, and custom goals as a band across their days.',
    ],
  },
  {
    id: 'calendar',
    title: 'Calendar',
    shots: [
      { name: 'calendar', alt: 'Month view with goals and events on each day' },
      { name: 'calendar-day', alt: 'The selected day with its goals' },
      { name: 'calendar-week', alt: 'Week view: every day with its events and goals' },
    ],
    steps: [
      'Switch between **Month** and **Week** at the top. The calendar remembers your choice.',
      'The month shows your goals and events as coloured marks on each day.',
      'Swipe left or right, or use the arrows, to change the month or week. **Today** brings you back.',
      'Longer goals sit in a row above the days: monthly and yearly goals in the month, weekly and monthly goals in the week. On a phone, tap one to mark it done or hold it to edit; on a computer, click it to edit.',
      'Tap a day to see its events and goals under the calendar (beside it on a wide screen). Tick goals off there; **New goal** in the menu (on a computer the **+** next to the date) adds one on that day.',
      'Tap a week number to open that week in the **Week** view. It lists every day with its events (by time) and daily goals; the small counter shows how many daily goals are done. Tap the circle to tick a goal off.',
    ],
  },
  {
    id: 'events',
    title: 'Events',
    shots: [
      { name: 'events', alt: 'Events with countdowns, soonest first' },
      { name: 'event-form', alt: 'New event: name, date, time and repeat' },
    ],
    steps: [
      '**Events** lists birthdays, anniversaries, appointments and other dates, soonest first, with a countdown: today, tomorrow or the number of days left.',
      'The next event sits at the top in a big dark card with its countdown.',
      'To add one, open the menu and tap **New event** (on a computer: **New event** next to the title).',
      'Enter a **Name** and pick the **Date**.',
      'Add a **Time** if it has one. Leave it empty for an all-day event.',
      'Choose **Repeat**: Once, Week, Month or Year. A birthday set to Year comes back every year; a Month event comes back on the same day every month.',
      'Switch on **Custom badge** to give it its own colour and icon, then tap **Save**.',
      'Tap an event to edit or delete it. Past one-off events move to **Past** at the bottom of the list.',
      'Events show up in the calendar too.',
    ],
  },
  {
    id: 'settings',
    title: 'Settings',
    shots: [
      { name: 'settings', alt: 'Settings: theme, category badges and account' },
      { name: 'settings-badge', alt: 'Choosing the colour and icon for daily goals' },
    ],
    steps: [
      '**Theme**: choose **Light mode** or **Dark mode**.',
      '**Category badges**: tap a category (Daily goals, Weekly goals… Events) to open it.',
      'Pick a colour, or your own one with the multicoloured circle, and an icon. Tap **Save**. Every goal or event in that category without a custom badge uses it.',
      '**Account** shows your name and email. Tap **Log out** to sign out on this device.',
    ],
  },
  {
    id: 'install',
    title: 'Use it like an app on iPhone',
    shots: [],
    steps: [
      'Open merkis.app in Safari.',
      'Tap the **Share** button, then **Add to Home Screen**, then **Add**.',
      'Open Merkis from its new icon. It fills the whole screen like an app. Log in once there and it remembers you.',
    ],
  },
]

/** "Tap **Save**" → [{ text: 'Tap ' }, { text: 'Save', bold: true }] */
export const parts = (step) => step.split(/\*\*(.+?)\*\*/).map((text, i) => ({ text, bold: i % 2 === 1 })).filter((p) => p.text)

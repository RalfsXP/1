<?php

/*
 * Goal options shared with the frontend (frontend/src/lib/goals.js keeps the
 * same lists). Icons are Heroicons names in kebab-case.
 */
return [

    'periods' => ['day', 'week', 'month', 'year', 'custom'],

    'priorities' => ['low', 'high', 'important'],

    'icons' => [
        'flag', 'star', 'heart', 'bolt', 'fire', 'trophy', 'sun', 'moon',
        'book-open', 'academic-cap', 'briefcase', 'home', 'shopping-cart', 'banknotes',
        'musical-note', 'camera', 'globe-europe-africa', 'paper-airplane', 'light-bulb',
        'puzzle-piece', 'rocket-launch', 'sparkles', 'face-smile', 'user-group',
        'calendar', 'clock', 'check-badge', 'beaker', 'cake', 'gift',
    ],

    'default_styles' => [
        'day' => ['color' => '#489fb5', 'icon' => 'sun'],
        'week' => ['color' => '#82c0cc', 'icon' => 'bolt'],
        'month' => ['color' => '#ffa62b', 'icon' => 'star'],
        'year' => ['color' => '#16697a', 'icon' => 'trophy'],
        'custom' => ['color' => '#0a2128', 'icon' => 'flag'],
        // Not a goal period: the default badge for events (Events page).
        'event' => ['color' => '#e5604d', 'icon' => 'calendar'],
    ],

];

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
        'day' => ['color' => '#cbbcff', 'icon' => 'sun'],
        'week' => ['color' => '#c2e1ff', 'icon' => 'bolt'],
        'month' => ['color' => '#ffe27a', 'icon' => 'star'],
        'year' => ['color' => '#bdebc9', 'icon' => 'trophy'],
        'custom' => ['color' => '#ffc9a6', 'icon' => 'flag'],
        // Not a goal period: the default badge for events (Events page).
        'event' => ['color' => '#ffbfd0', 'icon' => 'calendar'],
    ],

];

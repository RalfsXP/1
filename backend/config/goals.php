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
        'day' => ['color' => '#4c956c', 'icon' => 'sun'],
        'week' => ['color' => '#489fb5', 'icon' => 'bolt'],
        'month' => ['color' => '#ffc9b9', 'icon' => 'star'],
        'year' => ['color' => '#ffa62b', 'icon' => 'trophy'],
        'custom' => ['color' => '#16697a', 'icon' => 'flag'],
    ],

];

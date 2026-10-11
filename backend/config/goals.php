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
        'day' => ['color' => '#66a182', 'icon' => 'sun'],
        'week' => ['color' => '#3b82f6', 'icon' => 'bolt'],
        'month' => ['color' => '#a855f7', 'icon' => 'star'],
        'year' => ['color' => '#f59e0b', 'icon' => 'trophy'],
        'custom' => ['color' => '#14b8a6', 'icon' => 'flag'],
    ],

];

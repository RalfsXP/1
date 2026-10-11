<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines (Latvian)
    |--------------------------------------------------------------------------
    |
    | Only the rules the app uses are translated; anything else falls back to
    | English (config app.fallback_locale).
    |
    */

    'after_or_equal' => 'Laukā :attribute jābūt datumam, kas nav agrāks par :date.',
    'array' => 'Laukā :attribute jābūt sarakstam.',
    'between' => [
        'numeric' => 'Laukā :attribute jābūt vērtībai no :min līdz :max.',
    ],
    'boolean' => 'Laukā :attribute jābūt "jā" vai "nē".',
    'confirmed' => 'Lauka :attribute apstiprinājums nesakrīt.',
    'date' => 'Laukā :attribute jābūt derīgam datumam.',
    'distinct' => 'Laukā :attribute ir dublēta vērtība.',
    'email' => 'Laukā :attribute jābūt derīgai e-pasta adresei.',
    'in' => 'Izvēlētā :attribute vērtība nav derīga.',
    'integer' => 'Laukā :attribute jābūt veselam skaitlim.',
    'lowercase' => 'Laukā :attribute jābūt tikai mazajiem burtiem.',
    'max' => [
        'string' => 'Lauks :attribute nedrīkst būt garāks par :max rakstzīmēm.',
    ],
    'required_if' => 'Lauks :attribute ir obligāts, ja :other ir :value.',
    'regex' => 'Lauka :attribute formāts nav derīgs.',
    'required_with' => 'Lauks :attribute ir obligāts, ja ir norādīts :values.',
    'min' => [
        'string' => 'Laukā :attribute jābūt vismaz :min rakstzīmēm.',
    ],
    'required' => 'Lauks :attribute ir obligāts.',
    'string' => 'Laukā :attribute jābūt tekstam.',
    'unique' => 'Šāds :attribute jau tiek izmantots.',

    'password' => [
        'letters' => 'Parolē jābūt vismaz vienam burtam.',
        'mixed' => 'Parolē jābūt vismaz vienam lielajam un vienam mazajam burtam.',
        'numbers' => 'Parolē jābūt vismaz vienam ciparam.',
        'symbols' => 'Parolē jābūt vismaz vienam simbolam.',
        'uncompromised' => 'Šī parole ir atrasta datu noplūdē. Lūdzu, izvēlies citu.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'name' => 'vārds',
        'email' => 'e-pasts',
        'password' => 'parole',
        'locale' => 'valoda',
        'title' => 'nosaukums',
        'notes' => 'piezīmes',
        'period' => 'periods',
        'period_start' => 'datums',
        'period_end' => 'beigu datums',
        'priority' => 'prioritāte',
        'due_at' => 'termiņš',
        'repeats' => 'atkārtošana',
        'repeat_days' => 'atkārtošanas dienas',
        'repeat_until' => 'atkārtot līdz',
        'badge_color' => 'krāsa',
        'badge_icon' => 'ikona',
        'date' => 'datums',
    ],

];

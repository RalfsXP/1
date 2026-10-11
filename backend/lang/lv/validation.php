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

    'confirmed' => 'Lauka :attribute apstiprinājums nesakrīt.',
    'email' => 'Laukā :attribute jābūt derīgai e-pasta adresei.',
    'in' => 'Izvēlētā :attribute vērtība nav derīga.',
    'lowercase' => 'Laukā :attribute jābūt tikai mazajiem burtiem.',
    'max' => [
        'string' => 'Lauks :attribute nedrīkst būt garāks par :max rakstzīmēm.',
    ],
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
    ],

];

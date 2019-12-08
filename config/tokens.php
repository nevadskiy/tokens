<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Define tokens
    |--------------------------------------------------------------------------
    */

    'define' => [
        //'password.reset' => [
        //    'ttl' => 60,
        //    'previous' => 'remove',
        //],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Token Options
    |--------------------------------------------------------------------------
    |
    | * TTL - Number of minutes how long token is alive.
    | * previous -  Token generation strategy, when the same token already exists in the database. Can be one of `remove`, `reuse` or `keep`.
    | * generation_throttling - Determine whether the token should use throttling for the generation process.
    | * generation_attempts - How many attempts per user are available for generating the same token type.
    | * generation_attempts_interval - Number of minutes how many generation attempts can be processed within.
    | * usage_throttling - Determine whether the token should use throttling for the usage process.
    | * usage_attempts - How many attempts per user are available for using the same token type.
    | * usage_attempts_interval - Number of minutes how many usage attempts can be processed within.
    | * generator - A generator class for generation token strings.
    |
    */

    'defaults' => [
        'ttl' => 43200, // minutes in month (60 * 24 * 30)
        'previous' => 'remove',
        'generation_throttling' => true,
        'generation_attempts' => 3,
        'generation_attempts_interval' => 10,
        'usage_throttling' => true,
        'usage_attempts' => 5,
        'usage_attempts_interval' => 10,
        'generator' => Nevadskiy\Tokens\Generator\RandomHashGenerator::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tokens database table name
    |--------------------------------------------------------------------------
    */

    'table' => 'tokens',
];

<?php

use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Factory;
use Nevadskiy\Tokens\Tests\Support\Models\User;

/** @var Factory $factory */
$factory->define(User::class, function (Faker $faker) {
    return [
        'password' => 'SECRET_HASHED_PASSWORD'
    ];
});

<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Test;
use Faker\Generator as Faker;

$factory->define(Test::class, function (Faker $faker) {
    $faker->locale('ja_JP');
    return [
        'num' => random_int(100, 10000),
        'hoge' => $faker->name,
    ];
});

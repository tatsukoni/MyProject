<?php
use App\Models\PointLog;

$factory->define(PointLog::class, function (Faker\Generator $faker) {
    return [
        'detail' => PointLog::ACCEPT_DELIVERY,
        'trade_id' => 1,
    ];
});

$factory->state(PointLog::class, 'accept_delivery', function ($faker) {
    return [
        'detail' => PointLog::ACCEPT_DELIVERY
    ];
});

$factory->state(PointLog::class, 'deferred_accept_delivery', function ($faker) {
    return [
        'detail' => PointLog::DEFERRED_ACCEPT_DELIVERY
    ];
});

$factory->state(PointLog::class, 'delivery', function ($faker) {
    return [
        'detail' => PointLog::DELIVER
    ];
});

$factory->state(PointLog::class, 'deferred_delivery', function ($faker) {
    return [
        'detail' => PointLog::DEFERRED_DELIVER
    ];
});

$factory->state(PointLog::class, 'deferred_option_purchase', function ($faker) {
    return [
        'detail' => PointLog::DEFERRED_PURCHASE_SERVICE
    ];
});

$factory->state(PointLog::class, 'transfer_request', function ($faker) {
    return [
        'detail' => PointLog::APPLY_FOR_POINTS_CONVERSION
    ];
});

$factory->state(PointLog::class, 'permit_points_conversion', function ($faker) {
    return [
        'detail' => PointLog::PERMIT_POINTS_CONVERSION
    ];
});

$factory->state(PointLog::class, 'purchase', function ($faker) {
    return [
        'detail' => PointLog::PURCHASE
    ];
});

$factory->state(PointLog::class, 'purchase_credit_conversion', function ($faker) {
    return [
        'detail' => PointLog::PURCHASE_CREDIT_CONVERSION
    ];
});

$factory->state(PointLog::class, 'purchase_credit', function ($faker) {
    return [
        'detail' => PointLog::PURCHASE_CREDIT
    ];
});

$factory->state(PointLog::class, 'deferred_payment', function ($faker) {
    return [
        'detail' => PointLog::DEFERRED_PAYMENT
    ];
});

$factory->state(PointLog::class, 'task_registration', function ($faker) {
    return [
        'detail' => PointLog::TASK_REGISTRATION
    ];
});

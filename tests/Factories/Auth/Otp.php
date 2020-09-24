<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use Jslmariano\AuthenticationOtp\Models\Auth\Otp as AuthOtp;
use Carbon\Carbon;

$factory->define(AuthOtp::class, function () {
    return [
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];
});

<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use Jslmariano\AuthenticationLog\AuthenticationLog;
use Carbon\Carbon;

$factory->define(AuthenticationLog::class, function () {
    return [
        'authenticatable_type' => 'App\Models\User',
        'ip_address'           => '127.0.0.1',
        'user_agent'           => 'Symfony',
        'login_at'             => date('Y-m-d H:i:s'),
    ];
});

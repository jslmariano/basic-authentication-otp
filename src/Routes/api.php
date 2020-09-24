<?php
use Illuminate\Support\Facades\Route;

Route::group(['prefix' => 'auth'], function () {
    Route::get('otp/length', 'Auth\OtpController@length');
    Route::get('otp/refresh', 'Auth\OtpController@refresh')
            ->middleware('throttle:5,1');
    Route::post('otp/verify', 'Auth\OtpController@verify')
            ->middleware('throttle:5,1');

    Route::group(['middleware' => 'jwt.auth'], function () {
            Route::get('otp/enable', 'Auth\OtpController@enable')
                ->middleware('throttle:5,1');
    });
});

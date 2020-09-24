# Jslmariano Authentication OTP

[![Total Downloads](https://poser.pugx.org/jslmariano/laravel-authentication-log/downloads?format=flat)](https://packagist.org/packages/jslmariano/laravel-authentication-log)
[![GitHub license](https://img.shields.io/badge/license-MIT-blue.svg?style=flat)](https://raw.githubusercontent.com/jslmariano/laravel-authentication-log/master/LICENSE)

## Installation

> Laravel Authentication Log requires Laravel 5.5 or higher, and PHP 7.0+.

You may use Composer to install Laravel Authentication Log into your Laravel project:

    composer require jslmariano/laravel-authentication-otp

### Configuration

After installing the Laravel Authentication Log, publish its config, migration and view, using the `vendor:publish` Artisan command:

    php artisan vendor:publish --provider="Jslmariano\AuthenticationOtp\Providers\OtpServiceProvider"

Next, you need to migrate your database. The Laravel Authentication Log migration will create the table your application needs to store authentication logs:

    php artisan migrate

Then, integrate the `Services\Auth\OTP` to your user login route (by default, `App\Http\Controllers\Auth\AuthController` controller, but please look on your routes as guide to your login controller). This service provides a method to block the authentication process and ensure that it needs the OTP code verified first. You should include this in your login logic before the creation of session or auth token.

```php
namespace App\Http\Controllers\Auth;

use Jslmariano\AuthenticationOtp\Services\Auth\OTP as OTPService;

class AuthController extends Controller
{
    ...
    
    public function login(Request $request)
    {
        ...
        
        /**
         * OTP FEATURE
         */
        $otp_service = new OTPService();
        if ($otp_service->resolveUser($credentials)->isUserNeedsOTP($request)) {
            $otp_service->processOtp($request);
            return $otp_service->getResponse();
        }
        /**
         * OTP FEATURE END
         * This is to avoid generation of token
         */
    
        ...
        /* Should be before this code below  */
        /* Code below may vary depending on how you authenticate your users  */
        
        if (!$token = JWTAuth::attempt($credentials)) {
            return response([
                'status' => 'error',
                'error' => 'invalid.credentials',
                'msg' => 'Invalid Credentials.',
            ], 400);
        }

        Auth::loginUsingId(Auth::User()->id);
    
    ...
}
```

For the vuejs frontend you need install [@bachdgvn/vue-otp-input](https://www.npmjs.com/package/@bachdgvn/vue-otp-input) first:

    npm install --save @bachdgvn/vue-otp-input


Finally, add the OTP POP-UP as vue component to your vue login. ( Code may also vary depending on how you handle your user login in frontend )

```html
<template>
   <container>
   
       ...
       
       <v-text-field v-model="email"></v-text-field>
       <v-text-field v-model="password"></v-text-field>
       
       <!--  Handle on login button click method -->
       <v-btn type="submit" @click="otp_check()">
            Log In
       </v-btn>
       
       ...
       
   </container>
    <auth-login-otp
      :password.sync="password"
      :email.sync="email"
      ref="authLoginOtp"
      @on-check-error="onOtpCheckError"
      @on-verify-error="onOtpVerifyError"
      @on-verified="onOtpVerified"
      @on-resend-error="onOtpResendError"
      @on-resend="onOtpResend"
    />
  </v-content>
</template>

<script>
import AuthOtpInput from '../../components/auth/login/otp.vue';

export default {
  components: {
    'auth-login-otp' : AuthOtpInput,
  },

  ...
  
  methods: {
    otp_check() {
      this.$validator.validate().then(result => {
        if (!result) {
          return false;
        }
        this.$refs.authLoginOtp.check();
      });
    },
    ...
}

...
</script>

```



## Contributing

Thank you for considering contributing to the Laravel Authentication OTP.

## License

Laravel Authentication Log is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).

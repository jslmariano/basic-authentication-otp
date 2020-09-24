<?php

namespace Jslmariano\AuthenticationOtp\Http\Controllers\Auth;

/**
 * Vedors
 */
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\ThrottlesLogins;

/**
 * Apps
 */
use App\Http\Controllers\Controller;
use App\Models\User;
use Auth;

/**
 * Package
 */
use Jslmariano\AuthenticationOtp\Services\Auth\OTP as OTPService;

/**
 * Controls the data flow into an otp object and updates the view whenever data changes.
 */
class OtpController extends Controller
{
    use ThrottlesLogins;

    /**
     * Max login attempts allowed.
     */
    public $maxAttempts = 10;
    /**
     * Number of minutes to lock the login.
     */
    public $decayMinutes = 1;

    public function username()
    {
        return 'email';
    }

    /**
     * Verify an suers otp code also behaves like login, but does not geenrate
     * token
     *
     * @param      \Illuminate\Http\Request  $request      The request
     * @param      OTPService                $otp_service  The otp service
     *
     * @return     void
     */
    public function verify(Request $request, OTPService $otp_service)
    {
        $validatedData = $request->validate([
            'email' => 'required|email|max:255',
            'password' => 'required',
        ]);

        if ($this->hasTooManyLoginAttempts($request)) {
            //Fire the lockout event.
            $this->fireLockoutEvent($request);
            //redirect the user back after lockout.
            return $this->sendLockoutResponse($request);
        }

        $credentials = $request->only('email', 'password');

        $this->incrementLoginAttempts($request);

        /**
         * OTP FEATURE
         */
        $otp_service = new OTPService();

        /**
         * let api know it is disabled
         */
        if (!$otp_service->enabled) {
            return response([
                'otp_status' => OTPService::OTP_DISABLED,
                'status'     => 'success',
                'msg'        => 'OTP feature is disabled',
            ]);
        }

        /**
         * Process it
         */
        $otp_service->resolveUser($credentials);
        if ($otp_service->isUserNeedsOTP($request)) {
            $otp_service->processOtp($request);
            return $otp_service->getResponse();
        }

        /**
         * Check if it's turn off on user
         */
        if ($otp_service->isUserValid()) {
            if (!$otp_service->getUser()->otp_enabled) {
                return response([
                    'otp_status' => OTPService::OTP_DISABLED,
                    'status'     => 'success',
                    'msg'        => 'OTP feature is disabled for this user',
                ]);
            }
        }

        /**
         * OTP FEATURE END
         */
        return response([
            'otp_status' => OTPService::STATUS_ALREADY_VERFIED,
            'status'     => 'success',
            'msg'        => 'OTP Code already verified',
        ]);
    }

    /**
     * Refresh an otp by user
     *
     * @param Request $request
     * @return void
     */
    public function refresh(Request $request, OTPService $otp_service)
    {
        $user = User::where('email', $request->query('email'))->first();

        if (!$user) {
            return response([
                'otp_status' => 'error',
                'status'     => 'error',
                'msg'        => 'User not found!',
            ]);
        }

        $otp_service->setUser($user);
        $otp_service->sendOtpSms();
        return response([
            'otp_status' => OTPService::STATUS_READY,
            'status'     => 'success',
            'msg'        => 'OTP Code refreshed',
        ]);
    }

    /**
     * Get the otp length
     *
     * @param Request $request
     * @return void
     */
    public function length()
    {
        return response([
            'otp_length' => config('otp.code_length'),
            'status'     => 'success',
        ]);
    }

    /**
     * Enable user OTP
     *
     * @param Request $request
     * @return void
     */
    public function enable(Request $request)
    {
        $user = User::where('email', $request->query('email'))->first();

        if (!$user) {
            return response([
                'otp_status' => 'error',
                'status'     => 'error',
                'msg'        => 'User not found!',
            ]);
        }

        $user->otp_enabled = (int) $request->query('otp_enabled');
        $user->save();

        return response([
            'status' => 'success',
            'msg'    => 'OTP enabled successfuly',
        ]);
    }

}

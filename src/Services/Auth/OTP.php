<?php

namespace Jslmariano\AuthenticationOtp\Services\Auth;

/**
 * Vendors
 */
use Illuminate\Http\Request;
use Carbon\Carbon;
use Nexmo\Client\Credentials\Basic as NexmoCredential;
use Nexmo\Client as NexmoClient;

/**
 * Stallion APP
  */
use Auth;
use App\Models\User;

/**
 * Package
 */
use Jslmariano\AuthenticationLog\AuthenticationLog;
use Jslmariano\AuthenticationOtp\Models\Auth\Otp as AuthOtp;

/**
 * This class describes an auth otp.
 */
class OTP
{
    /**
     * @var \Nexmo\Client\Credentials\Basic $basic
     */
    protected  $basic;

    /**
     * @var \Nexmo\Client $client
     */
    protected  $client;

    /**
     * @var \App\Models\User $user
     */
    protected  $user = false;

    /**
     * @var int $registered_number
     */
    private  $registered_number;

    /**
     * @var int $otp_code_length
     */
    public  $otp_code_length;

    /**
     * @var array $force_positions
     */
    public  $force_positions;

    /**
     * @var int $expire_seconds
     */
    public  $expire_seconds;

    /**
     * @var string $project_name
     */
    public  $project_name;

    /**
     * @var array $response
     */
    public  $response = array();

    const STATUS_READY           = 'ready';
    const STATUS_VALID_OTP       = 'valid_otp';
    const STATUS_INVALID_OTP     = 'invalid_otp';
    const STATUS_ALREADY_VERFIED = 'already_verified';
    const OTP_DISABLED           = 'otp_disabled';

    /**
     * Constructs a new instance.
     */
    public function __construct()
    {
        $this->basic           = new NexmoCredential(
            config('otp.nexmo.api_key'),
            config('otp.nexmo.api_secret')
        );
        $this->client            = new NexmoClient($this->basic);
        $this->otp_code_length   = config('otp.code_length');
        $this->registered_number = config('otp.nexmo.number');
        $this->force_positions   = config('otp.force_positions');
        $this->expire_seconds    = config('otp.expire_seconds');
        $this->force_expire_days = config('otp.force_expire_days');
        $this->project_name      = config('app.name');
        $this->enabled           = config('otp.enabled');
    }

    /**
     * Determines if user needs otp.
     *
     * @param      \Illuminate\Http\Request  $request  The request
     *
     * @return     boolean                   True if user needs otp, False otherwise.
     */
    public function isUserNeedsOTP(Request $request)
    {
        /**
         * No one needs otp if it's disabled
         */
        if (!$this->enabled) {
            return false;
        }

        /**
         * Dont proceed if you don't have valid user
         */
        if (!$this->isUserValid()) {
            return false;
        }

        /**
         * Check if account is otp enabled, and Force positions to use OTP
         */
        if ($this->user->otp_enabled || in_array($this->user->position_id, $this->force_positions)) {

            $otp_code = $request->post('code');
            $auth_otp = $this->getUserOtp($this->user->id);

            /* If no otp found then it needs otp */
            if (!$auth_otp) {
                return true;
            }

            /* If it's a differnt IP then they need OTP */
            $ip         = $request->ip();
            $user_agent = $request->userAgent();
            $known      = $this->getAuthLog($this->user, $ip, $user_agent);
            if ($this->user->authentications()->count() > 0 && !$known) {
                return true;
            }

            /* If otp found and already verified the it doesn't need otp */
            if ($auth_otp->is_verified) {
                /**
                 * If its verified then see if it's expired more than 30 days
                 */
                $start_time     = Carbon::parse($auth_otp->updated_at);
                $finish_time    = Carbon::now();
                $total_duration = $finish_time->diffInDays($start_time);
                $is_expired    = ((int)$total_duration >= $this->force_expire_days);
                if ($is_expired) {
                    return true;
                }
                return false;
            }

            /* Needs otp if 2fa enabled and otp is not verified yet */
            return true;
        }

        return false;
    }

    /**
     * Gets the auth log.
     *
     * @param      \App\Models\User                             $user        The user
     * @param      string                                       $ip_address  The ip address
     * @param      string                                       $user_agent  The user agent
     *
     * @return     Jslmariano\AuthenticationLog\AuthenticationLog  The auth log.
     */
    public function getAuthLog(User $user, string $ip_address, string $user_agent)
    {
        return $user->authentications()
            ->whereIpAddress($ip_address)
            ->whereUserAgent($user_agent)
            ->first();
    }

    /**
     * Determines if user valid.
     *
     * @return     boolean  True if user valid, False otherwise.
     */
    public function isUserValid()
    {
        return (boolean)$this->user;
    }

    /**
     * Sets the user.
     *
     * @param      \App\Models\User  $user   The user
     *
     * @return     self              self instance
     */
    public function setUser(User $user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Resolve user by credentials and fetch the user by email
     *
     * @param      array  $credentials  The credentials
     *
     * @return     self    self instance
     */
    public function resolveUser(array $credentials)
    {
        if (!Auth::validate($credentials)) {
            return $this;
        }
        $this->user = User::where('email', $credentials['email'])->first();
        return $this;
    }

    /**
     * Process the user's OTP
     *
     * @param      \Illuminate\Http\Request  $request  The request
     *
     * @return     string                    OTP STATU
     */
    public function processOtp(Request $request)
    {
        $otp_code = $request->post('code');

        /**
         * Create OTP if not exists and notify user
         */
        $auth_otp = $this->getUserOtp($this->user->id);
        if (!$auth_otp) {
            $this->sendOtpSms();
            $this->response = response([
                'otp_status' => self::STATUS_READY,
                'status'     => 'success',
            ]);
            return self::STATUS_READY;
        }

        /**
         * If somehow user still not verified yet the otp
         */
        if (!$auth_otp->is_verified && !$otp_code) {
            $this->response = response([
                'otp_status' => self::STATUS_READY,
                'status'     => 'success',
            ]);
            return self::STATUS_READY;
        }

        /**
         * If somehow user is verified but got old otp
         */
        $start_time     = Carbon::parse($auth_otp->updated_at);
        $finish_time    = Carbon::now();
        $total_duration = $finish_time->diffInDays($start_time);
        $is_expired     = ((int)$total_duration > $this->force_expire_days);
        $is_obsolete    = ($auth_otp->is_verified && $is_expired);
        if ($is_obsolete) {
            $this->sendOtpSms();
            $this->response = response([
                'otp_status' => self::STATUS_READY,
                'status'     => 'success',
            ]);
            return self::STATUS_READY;
        }

        /**
         * If has OTP but device is not known, update the otp and send it
         */
        $known = $this->getAuthLog($this->user, $request->ip(), $request->userAgent());
        if ($this->user->authentications()->count() > 0 && !$known && !$otp_code) {
            $this->sendOtpSms();
            $this->response = response([
                'otp_status' => self::STATUS_READY,
                'status'     => 'success',
            ]);
            return self::STATUS_READY;
        }

        $verify = $this->verify($otp_code);

        /**
         * Generates the authentication log to avoid login failure due to
         * unknown device by this otp feature
         */
        $this->createAuthenticationLog($this->user, $request->ip(), $request->userAgent());

        if (!$verify['success']) {
            $this->response =  response([
                'otp_status' => self::STATUS_INVALID_OTP,
                'status'     => 'error',
                'error'      => 'invalid.credentials',
                'msg'        => $verify['message'],
            ], 200);
            return self::STATUS_INVALID_OTP;
        }

        $this->response = response([
            'otp_status' => self::STATUS_VALID_OTP,
            'status'     => 'success',
        ]);
        return self::STATUS_VALID_OTP;
    }


    /**
     * Verify the OTP code if it's correct per user
     *
     * @param      string      $code   The code
     *
     * @return     boolean  True if verified, false otherwise
     */
    public function verify(string $code)
    {
        $auth_otp = $this->getUserOtp($this->user->id);
        if (!$auth_otp) {
            return array('success' => false, 'message' => 'Ivalid OTP');
        }

        if ($auth_otp->code != $code) {
            return array('success' => false, 'message' => 'Ivalid OTP');
        }

        /**
         * Measure if expired in seconds
         */
        $start_time  = Carbon::parse($auth_otp->updated_at);
        $finish_time = Carbon::now();
        $total_duration = $finish_time->diffInSeconds($start_time);
        if ($this->expire_seconds < $total_duration) {
            return array('success' => false, 'message' => 'OTP expired');
        }

        /**
         * We're good here, now so we flag it as verified
         */
        $auth_otp = $this->saveUserOtp($this->user, [
            'is_verified' => 1,
            'code'        => $code,
        ]);

        return array('success' => true, 'message' => 'OTP verified');
    }

    /**
     * Creates an authentication log.
     *
     * @param      \App\Models\User  $user        The user
     * @param      string            $ip_address  The ip address
     * @param      string            $user_agent  The user agent
     */
    public function createAuthenticationLog($user, $ip_address, $user_agent)
    {
        $authenticationLog = new AuthenticationLog([
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'login_at'   => Carbon::now(),
            'location'   => null,
        ]);

        $user->authentications()->save($authenticationLog);
    }

    /**
     * Gets the user.
     *
     * @return \App\Models\User The user.
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Gets the response.
     *
     * @return     Response  The response.
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Gets the user otp.
     *
     * @param      int                  $user_id  The user identifier
     *
     * @return     App\Models\Auth\Otp  The user otp.
     */
    public function getUserOtp(int $user_id)
    {
        return AuthOtp::where('user_id', $user_id)->first();
    }

    /**
     * Generates the otp code
     *
     * @return     string  The otp code
     */
    public function generateOtpCode()
    {
        $digits = $this->otp_code_length;
        $otp_code = str_pad(rand(0, pow(10, $digits)-1), $digits, '0', STR_PAD_LEFT);

        /**
         * make sure we get the exact length
         */
        if (strlen((string)$otp_code) != $digits) {
            return $this->generateOtpCode();
        }

        return $otp_code;
    }

    /**
     * Gets the message.
     *
     * @param      string  $otp_code  The otp code
     *
     * @return     string   The message.
     */
    public function getMessage(string $otp_code)
    {
        return "Hello from " . $this->project_name . ", your OTP CODE is " . $otp_code;
    }

    /**
     * Removes user otp codes.
     *
     * @return     self  Self instance
     */
    public function removeUserOtpCodes()
    {
        AuthOtp::where('user_id', $this->user_->id)->delete();
        return $this;
    }

    /**
     * Saves an user otp. This also refresh the existing otp code
     *
     * @param      \App\Models\User     $user     The user
     * @param      array                $options  The options
     *
     * @return     App\Models\Auth\Otp  The otp object
     */
    public function saveUserOtp(User $user, $options = array())
    {
        $search      = array('user_id' => $user->id);
        $update_data = array(
            'email' => $user->email,
            'phone' => $user->phone,
        );

        $update_data = array_merge($update_data, $options);
        $auth_otp    = AuthOtp::updateOrCreate($search, $update_data);
        return $auth_otp;
    }

    /**
     * Sends otp sms.
     */
    public function sendOtpSms()
    {
        $auth_otp = $this->saveUserOtp($this->user, [
            'code'        => $this->generateOtpCode(),
            'is_verified' => 0,
        ]);


        /**
         * Avoid incurring charges to NEXMO API when unit-testing
         */
        if (!\App::runningUnitTests()) {
            $message = $this->client->message()->send([
                'to'   => $this->user->phone,
                'from' => $this->registered_number,
                'text' => $this->getMessage($auth_otp->code)
            ]);
        }


        return $this;
    }
}
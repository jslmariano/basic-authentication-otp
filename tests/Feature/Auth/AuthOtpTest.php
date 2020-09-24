<?php

namespace Jslmariano\AuthenticationOtp\Tests\Feature\Auth;

/**
 * Vendors
 */
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Jslmariano\AuthenticationLog\AuthenticationLog;
use Illuminate\Database\Eloquent\Factory as EloquentFactory;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * APP
 */
use App\Models\User;

/**
 * Package
 */
use Jslmariano\AuthenticationOtp\Models\Auth\Otp as AuthOtp;
use Jslmariano\AuthenticationOtp\Services\Auth\OTP as OTPService;
/**
 */
class AuthOtpTest extends TestCase
{
    use DatabaseTransactions;


    public function setUp(): void
    {
        parent::setUp();

        $this->app
            ->make(EloquentFactory::class)
            ->load(__DIR__.'/../../Factories');
    }

    public function test_assert()
    {
        $this->assertTrue(true);
    }

    /**
     * Test valid OTP
     */
    public function test_valid_otp()
    {
        $user = factory(User::class)->create([
            'phone'       => '+639954340284',
            'position_id' => 1,
            'otp_enabled' => 1,
        ]);

        $otp = factory(AuthOtp::class)->create([
            'user_id' => $user->id,
            'phone'   => $user->phone,
            'email'   => $user->email,
            'code'    => 1234,
        ]);

        $response = $this
            ->postJson(
                'api/auth/login',
                [
                    'email'    => $user->email,
                    'password' => 'password',
                    'code' => 1234,
                ]
            );

        $otp = $otp->fresh();

        $response
            ->assertStatus(200)
            ->assertJson([
                'otp_status' => OTPService::STATUS_VALID_OTP,
                'status'     => 'success',
            ]);
        $response->assertHeaderMissing('Authorization');

        $this->assertEquals($otp->is_verified, 1);
    }

    /**
     * Test invalid OTP
     */
    public function test_invalid_otp()
    {
        $user = factory(User::class)->create([
            'phone'       => '+639954340284',
            'position_id' => 1,
            'otp_enabled' => 1,
        ]);

        $otp = factory(AuthOtp::class)->create([
            'user_id' => $user->id,
            'phone'   => $user->phone,
            'email'   => $user->email,
            'code'    => 1234,
        ]);

        $response = $this
            ->postJson(
                'api/auth/login',
                [
                    'email'    => $user->email,
                    'password' => 'password',
                    'code' => 9999,
                ]
            );

        $otp = $otp->fresh();

        $response
            ->assertStatus(200)
            ->assertJson([
                'otp_status' => OTPService::STATUS_INVALID_OTP,
                'status'     => 'error',
            ]);
        $response->assertHeaderMissing('Authorization');

        $this->assertEquals($otp->is_verified, 0);
        $this->assertEquals($otp->code, 1234);
    }

    /**
     * Test already Verified OTP
     */
    public function test_already_verified_otp()
    {
        $user = factory(User::class)->create([
            'phone'       => '+639954340284',
            'position_id' => 1,
            'otp_enabled' => 1,
        ]);

        $otp = factory(AuthOtp::class)->create([
            'user_id'     => $user->id,
            'phone'       => $user->phone,
            'email'       => $user->email,
            'code'        => 1234,
            'is_verified' => 1,
        ]);

        $authentication_log = factory(AuthenticationLog::class)->create([
            'authenticatable_id' => $user->id,
        ]);

        $response = $this
            ->postJson(
                'api/auth/login',
                [
                    'email'    => $user->email,
                    'password' => 'password',
                ]
            );

        $response
            ->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ])
            ->assertHeader('Authorization');
    }

    /**
     * Test create OTP
     */
    public function test_create_otp()
    {
        $user = factory(User::class)->create([
            'phone'       => '+639954340284',
            'position_id' => 1,
            'otp_enabled' => 1,
        ]);

        $response = $this
            ->postJson(
                'api/auth/login',
                [
                    'email'    => $user->email,
                    'password' => 'password',
                ]
            );

        $auth_otp = AuthOtp::where('user_id', $user->id)->first();

        $response
            ->assertStatus(200)
            ->assertJson([
                'otp_status' => OTPService::STATUS_READY,
                'status'     => 'success',
            ]);
        $response->assertHeaderMissing('Authorization');

        $this->assertEquals($auth_otp->user_id, $user->id);
        $this->assertEquals($auth_otp->email, $user->email);
        $this->assertEquals($auth_otp->is_verified, 0);
    }

    /**
     * Test Expired OTP
     */
    public function test_expired_otp()
    {
        $user = factory(User::class)->create([
            'phone'       => '+639954340284',
            'position_id' => 1,
            'otp_enabled' => 1,
        ]);

        $otp = factory(AuthOtp::class)->create([
            'user_id'    => $user->id,
            'phone'      => $user->phone,
            'email'      => $user->email,
            'code'       => 1234,
            'created_at' => date('1999-m-d H:i:s'),
            'updated_at' => date('1999-m-d H:i:s'),
        ]);

        $authentication_log = factory(AuthenticationLog::class)->create([
            'authenticatable_id' => $user->id,
        ]);

        $response = $this
            ->postJson(
                'api/auth/login',
                [
                    'email'    => $user->email,
                    'password' => 'password',
                    'code' => 1234,
                ]
            );

        $response
            ->assertStatus(200)
            ->assertJson([
                'otp_status' => OTPService::STATUS_INVALID_OTP,
                'status'     => 'error',
                'msg'        => 'OTP expired',
            ]);
        $response->assertHeaderMissing('Authorization');
    }


    /**
     * Test verified_Expired OTP
     */
    public function test_verified_expired_otp()
    {
        $user = factory(User::class)->create([
            'phone'       => '+639954340284',
            'position_id' => 1,
            'otp_enabled' => 1,
        ]);

        $plus_days = 1 + (int)config('otp.force_expire_days');
        $otp = factory(AuthOtp::class)->create([
            'user_id'     => $user->id,
            'phone'       => $user->phone,
            'email'       => $user->email,
            'code'        => 1234,
            'is_verified' => 1,
            'created_at'  => Carbon::now(),
            'updated_at'  => Carbon::now()->addDays($plus_days),
        ]);

        $authentication_log = factory(AuthenticationLog::class)->create([
            'authenticatable_id' => $user->id,
        ]);

        $response = $this
            ->postJson(
                'api/auth/otp/verify',
                [
                    'email'    => $user->email,
                    'password' => 'password',
                ]
            );

        $response
            ->assertStatus(200)
            ->assertJson([
                'otp_status' => OTPService::STATUS_READY,
                'status'     => 'success',
            ]);
        $response->assertHeaderMissing('Authorization');

        $otp = $otp->fresh();
        $this->assertEquals($otp->is_verified, 0);
        $this->assertTrue($otp->code != 1234);
    }

    /**
     * Test resumable otp, unverified but not expired from different ip
     */
    public function test_resume_existing_otp()
    {
        $user = factory(User::class)->create([
            'phone'       => '+639954340284',
            'position_id' => 1,
            'otp_enabled' => 1,
        ]);

        $otp = factory(AuthOtp::class)->create([
            'user_id'     => $user->id,
            'phone'       => $user->phone,
            'email'       => $user->email,
            'code'        => 1234,
            'is_verified' => 0,
        ]);

        $authentication_log = factory(AuthenticationLog::class)->create([
            'ip_address'         => '9.9.9.9',
            'authenticatable_id' => $user->id,
        ]);

        $response = $this
            ->postJson(
                'api/auth/otp/verify',
                [
                    'email'    => $user->email,
                    'password' => 'password',
                ]
            );

        $otp = $otp->fresh();

        $response
            ->assertStatus(200)
            ->assertJson([
                'otp_status' => OTPService::STATUS_READY,
                'status'     => 'success',
            ]);
        $response->assertHeaderMissing('Authorization');

        $this->assertEquals($otp->user_id, $user->id);
        $this->assertEquals($otp->email, $user->email);
        $this->assertEquals($otp->is_verified, 0);
        $this->assertEquals($otp->code, 1234);

        $response = $this
            ->postJson(
                'api/auth/otp/verify',
                [
                    'email'    => $user->email,
                    'password' => 'password',
                    'code'     => 1234,
                ]
            );

        $otp = $otp->fresh();

        $response
            ->assertStatus(200)
            ->assertJson([
                'otp_status' => OTPService::STATUS_VALID_OTP,
                'status'     => 'success',
            ]);
        $response->assertHeaderMissing('Authorization');
        $this->assertEquals($otp->is_verified, 1);
    }

    /**
     * Test OTP Unfamiliar ip
     */
    public function test_otp_unfamiliar_ip()
    {
        $user = factory(User::class)->create([
            'phone'       => '+639954340284',
            'position_id' => 1,
            'otp_enabled' => 1,
        ]);

        $otp = factory(AuthOtp::class)->create([
            'user_id'     => $user->id,
            'phone'       => $user->phone,
            'email'       => $user->email,
            'code'        => 1234,
            'is_verified' => 1,
        ]);

        $authentication_log = factory(AuthenticationLog::class)->create([
            'ip_address'         => '9.9.9.9',
            'authenticatable_id' => $user->id,
        ]);

        $response = $this
            ->postJson(
                'api/auth/login',
                [
                    'email'    => $user->email,
                    'password' => 'password',
                ]
            );

        $otp = $otp->fresh();

        $response
            ->assertStatus(200)
            ->assertJson([
                'otp_status' => OTPService::STATUS_READY,
                'status'     => 'success',
            ]);
        $response->assertHeaderMissing('Authorization');

        $this->assertEquals($otp->user_id, $user->id);
        $this->assertEquals($otp->email, $user->email);
        $this->assertEquals($otp->is_verified, 0);
    }

    /**
     * Test User enable otp
     */
    public function test_enable_otp()
    {
        $user = factory(User::class)->create([
            'phone'       => '+639954340284',
            'otp_enabled' => 0,
        ]);

        $this->localActingAs($user);
        $queries = [
            'email' => $user->email,
            'otp_enabled' => 1,
        ];

        $response = $this->getJson(
            'api/auth/otp/enable' . "?" . http_build_query($queries)
        );

        $response
            ->assertStatus(200)
            ->assertJson([
                'status'     => 'success',
            ]);

        $user = $user->fresh();
        $this->assertEquals($user->otp_enabled, 1);
    }

    /**
     * Test User disable otp
     */
    public function test_disable_otp()
    {
        $user = factory(User::class)->create([
            'phone'       => '+639954340284',
            'otp_enabled' => 1,
        ]);

        $this->localActingAs($user);
        $queries = [
            'email' => $user->email,
            'otp_enabled' => 0,
        ];

        $response = $this->getJson(
            'api/auth/otp/enable' . "?" . http_build_query($queries)
        );

        $response
            ->assertStatus(200)
            ->assertJson([
                'status'     => 'success',
            ]);

        $user = $user->fresh();
        $this->assertEquals($user->otp_enabled, 0);
    }

    /**
     * Test Refresh otp code
     */
    public function test_refresh_otp_code()
    {
        $user = factory(User::class)->create([
            'phone'       => '+639954340284',
            'otp_enabled' => 1,
        ]);

        $otp = factory(AuthOtp::class)->create([
            'user_id'     => $user->id,
            'phone'       => $user->phone,
            'email'       => $user->email,
            'code'        => 1234,
            'is_verified' => 1,
        ]);

        $queries = [
            'email' => $user->email,
        ];

        $response = $this->getJson(
            'api/auth/otp/refresh' . "?" . http_build_query($queries)
        );

        $response
            ->assertStatus(200)
            ->assertJson([
                'status'     => 'success',
            ]);

        $otp = $otp->fresh();

        $this->assertTrue($otp->code != 1234);
    }

}

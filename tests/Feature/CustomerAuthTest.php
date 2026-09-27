<?php

namespace Tests\Feature;

use App\Contracts\SmsServiceInterface;
use App\Models\PhoneVerification;
use App\Models\User;
use App\Services\Sms\HttpSmsService;
use App\Services\Sms\LogSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class CustomerAuthTest extends TestCase
{
    use RefreshDatabase;

    private $sms;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->sms = new class implements SmsServiceInterface {
            public array $messages = [];
            public bool $fail = false;
            public function sendOtp(string $mobile, string $code): void
            {
                if ($this->fail) throw new \RuntimeException('Provider secret must not leak');
                $this->messages[] = compact('mobile', 'code');
            }
        };
        $this->app->instance(SmsServiceInterface::class, $this->sms);
    }

    private function customer(string $mobile = '09121234567', array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'mobile' => $mobile, 'role' => 'customer', 'is_active' => true,
            'password' => 'secret-password',
        ], $overrides));
    }

    private function send(string $mobile = '09121234567'): string
    {
        $this->postJson('/api/customer/send-otp', ['mobile' => $mobile])->assertOk()
            ->assertJsonPath('retry_after', 60)->assertJsonPath('expires_in', 120)
            ->assertJsonMissingPath('code')->assertJsonMissingPath('token');
        return $this->sms->messages[array_key_last($this->sms->messages)]['code'];
    }

    private function verify(string $code, string $mobile = '09121234567')
    {
        return $this->postJson('/api/customer/verify-otp', compact('mobile', 'code'));
    }

    public function test_mobile_formats_normalize_to_same_user(): void
    {
        $user = $this->customer('+989121234567');
        $this->assertSame('09121234567', $user->mobile);
        foreach (['09121234567', '989121234567', '+989121234567', '۰۹۱۲۱۲۳۴۵۶۷'] as $mobile) {
            $code = $this->send($mobile);
            $this->assertSame('09121234567', end($this->sms->messages)['mobile']);
            $this->verify($code, $mobile)->assertOk()->assertJsonPath('user.id', $user->id);
            $this->travel(61)->seconds();
        }
    }

    public function test_send_stores_only_hash_with_two_minute_expiry(): void
    {
        $this->travelTo(now()->startOfSecond());
        $this->customer();
        $code = $this->send();
        $otp = PhoneVerification::firstOrFail();
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $code);
        $this->assertNotSame($code, $otp->code_hash);
        $this->assertTrue(Hash::check($code, $otp->code_hash));
        $this->assertTrue($otp->expires_at->equalTo(now()->addSeconds(120)));
        $this->assertNull($otp->used_at);
        $this->assertSame(0, $otp->attempts);
        $this->assertArrayNotHasKey('code_hash', $otp->toArray());
    }

    public function test_send_validates_mobile(): void
    {
        foreach ([null, '', '123', '08121234567', '+12025550123', ['mobile']] as $mobile) {
            $this->postJson('/api/customer/send-otp', compact('mobile'))
                ->assertUnprocessable()->assertJsonValidationErrors('mobile');
        }
        $this->assertSame([], $this->sms->messages);
    }

    public function test_resend_is_rate_limited_across_normalized_formats(): void
    {
        $this->customer();
        $this->send();
        $this->postJson('/api/customer/send-otp', ['mobile' => '+989121234567'])
            ->assertStatus(429)->assertHeader('Retry-After');
        $this->assertCount(1, $this->sms->messages);
    }

    public function test_hourly_send_limit_is_enforced(): void
    {
        $this->customer();
        for ($i = 0; $i < 5; $i++) {
            $this->send();
            $this->travel(61)->seconds();
        }
        $this->postJson('/api/customer/send-otp', ['mobile' => '09121234567'])->assertStatus(429);
        $this->assertCount(5, $this->sms->messages);
    }

    public function test_new_code_invalidates_previous_code(): void
    {
        $this->customer();
        $this->send();
        $first = PhoneVerification::first();
        $this->travel(61)->seconds();
        $code = $this->send();
        $this->assertNotNull($first->fresh()->used_at);
        $this->assertSame(1, PhoneVerification::whereNull('used_at')->count());
        $this->verify($code)->assertOk();
    }

    public function test_correct_code_authenticates_correct_customer_with_sanctum_ability(): void
    {
        $user = $this->customer();
        $user->createToken('customer-dashboard', ['customer']);
        $response = $this->verify($this->send())->assertOk()->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.mobile', '09121234567')->assertJsonMissingPath('user.password')
            ->assertJsonMissingPath('code')->assertJsonMissingPath('code_hash');
        $token = PersonalAccessToken::findToken($response->json('token'));
        $this->assertSame(['customer'], $token->abilities);
        $this->assertSame($user->id, $token->tokenable_id);
        $this->assertSame(1, $user->tokens()->count());
        $this->withToken($response->json('token'))->getJson('/api/customer/me')
            ->assertOk()->assertJsonPath('user.id', $user->id);
        $this->assertNotNull(PhoneVerification::first()->used_at);
    }

    public function test_wrong_code_increments_attempts_without_token(): void
    {
        $this->customer();
        $code = $this->send();
        $wrong = $code === '111111' ? '222222' : '111111';
        $this->verify($wrong)->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertSame(1, PhoneVerification::first()->attempts);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_expired_code_is_rejected(): void
    {
        $this->customer();
        $code = $this->send();
        $this->travel(120)->seconds();
        $this->verify($code)->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_used_code_cannot_be_replayed(): void
    {
        $this->customer();
        $code = $this->send();
        $this->verify($code)->assertOk();
        $this->verify($code)->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_five_failed_attempts_prevent_correct_code_from_working(): void
    {
        $this->customer();
        $code = $this->send();
        $wrong = $code === '111111' ? '222222' : '111111';
        for ($i = 0; $i < 5; $i++) $this->verify($wrong)->assertUnprocessable();
        $this->verify($code)->assertUnprocessable();
        $this->assertSame(5, PhoneVerification::first()->attempts);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_verify_has_separate_mobile_rate_limit(): void
    {
        for ($i = 0; $i < 10; $i++) $this->verify('123456')->assertUnprocessable();
        $this->verify('123456')->assertStatus(429);
    }

    public function test_code_cannot_authenticate_another_mobile(): void
    {
        $this->customer();
        $this->customer('09351234567');
        $code = $this->send();
        $this->verify($code, '09351234567')->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->verify($code)->assertOk();
    }

    public function test_unknown_number_does_not_create_user_or_disclose_existence(): void
    {
        $this->customer();
        $known = $this->postJson('/api/customer/send-otp', ['mobile' => '09121234567'])->assertOk();
        $this->postJson('/api/customer/send-otp', ['mobile' => '09351234567'])->assertOk()
            ->assertExactJson($known->json());
        $this->assertCount(1, $this->sms->messages);
        $this->assertDatabaseCount('users', 1);
        $this->verify('123456', '09351234567')->assertUnprocessable();
    }

    public function test_inactive_and_admin_accounts_cannot_use_customer_otp(): void
    {
        $this->customer('09121234567', ['is_active' => false]);
        $this->customer('09351234567', ['role' => 'admin']);
        foreach (['09121234567', '09351234567'] as $mobile) {
            $this->postJson('/api/customer/send-otp', compact('mobile'))->assertOk();
            PhoneVerification::create(['mobile' => $mobile, 'code_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(2)]);
            $this->verify('123456', $mobile)->assertUnprocessable();
        }
        $this->assertSame([], $this->sms->messages);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_sms_failure_invalidates_code_and_hides_provider_details(): void
    {
        $this->customer();
        $this->sms->fail = true;
        $this->postJson('/api/customer/send-otp', ['mobile' => '09121234567'])
            ->assertStatus(503)->assertDontSee('Provider secret');
        $this->assertNotNull(PhoneVerification::first()->used_at);
    }

    public function test_code_validation_requires_six_digits(): void
    {
        foreach (['12345', '1234567', 'abcdef', null] as $code) {
            $this->postJson('/api/customer/verify-otp', ['mobile' => '09121234567', 'code' => $code])
                ->assertUnprocessable()->assertJsonValidationErrors('code');
        }
    }

    public function test_email_password_login_still_works_for_existing_user_without_mobile(): void
    {
        $user = User::factory()->create(['password' => 'secret-password', 'mobile' => null]);
        $response = $this->postJson('/api/customer/login', ['email' => $user->email, 'password' => 'secret-password'])
            ->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonPath('user.mobile', null);
        $this->assertSame(['customer'], PersonalAccessToken::findToken($response->json('token'))->abilities);
        $this->postJson('/api/customer/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_register_stores_normalized_unique_mobile(): void
    {
        $payload = ['name' => 'Customer', 'email' => 'new@example.com', 'mobile' => '+989121234567',
            'password' => 'secret-password', 'password_confirmation' => 'secret-password', 'role' => 'admin'];
        $this->postJson('/api/customer/register', $payload)->assertCreated()
            ->assertJsonPath('user.mobile', '09121234567')->assertJsonPath('user.role', 'customer');
        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'mobile' => '09121234567']);
        $payload['email'] = 'other@example.com';
        $payload['mobile'] = '989121234567';
        $this->postJson('/api/customer/register', $payload)->assertUnprocessable()->assertJsonValidationErrors('mobile');
        unset($payload['mobile']);
        $this->postJson('/api/customer/register', $payload)->assertUnprocessable()->assertJsonValidationErrors('mobile');
    }

    public function test_http_provider_uses_configured_relay_without_real_network(): void
    {
        config([
            'sms.endpoint' => 'https://sms.example.test/send',
            'sms.token' => 'test-only-token',
            'sms.template_id' => '123456',
        ]);
        Http::preventStrayRequests();
        Http::fake(['sms.example.test/*' => Http::response(['accepted' => true], 200)]);
        (new HttpSmsService())->sendOtp('09121234567', '123456');
        Http::assertSent(fn ($request) => $request->url() === 'https://sms.example.test/send'
            && $request->hasHeader('X-API-KEY', 'test-only-token')
            && $request['Mobile'] === '09121234567'
            && $request['TemplateId'] === 123456
            && isset($request['Parameters'][0]['Name']) && $request['Parameters'][0]['Name'] === 'CODE'
            && isset($request['Parameters'][0]['Value']) && $request['Parameters'][0]['Value'] === '123456');
    }

    public function test_log_provider_cannot_expose_codes_in_production(): void
    {
        $this->app->instance('env', 'production');
        $this->expectException(\RuntimeException::class);
        (new LogSmsService())->sendOtp('09121234567', '123456');
    }
}

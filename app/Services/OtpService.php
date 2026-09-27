<?php

namespace App\Services;

use App\Contracts\SmsServiceInterface;
use App\Models\PhoneVerification;
use App\Models\User;
use App\Support\IranianMobile;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OtpService
{
    public function __construct(private SmsServiceInterface $sms) {}

    public function normalize(mixed $mobile): string
    {
        return IranianMobile::normalize($mobile);
    }

    private function limit(string $key, int $maximum, int $seconds): void
    {
        if (RateLimiter::tooManyAttempts($key, $maximum)) {
            throw new HttpException(429, 'تعداد درخواست‌ها بیش از حد مجاز است. کمی صبر کنید.', null,
                ['Retry-After' => (string) RateLimiter::availableIn($key)]);
        }
        RateLimiter::hit($key, $seconds);
    }

    private function locked(string $mobile, callable $operation): mixed
    {
        try {
            return Cache::lock('otp:lock:'.hash('sha256', $mobile), 20)->block(2, $operation);
        } catch (LockTimeoutException) {
            throw new HttpException(429, 'درخواست دیگری در حال انجام است. دوباره تلاش کنید.', null, ['Retry-After' => '3']);
        }
    }

    public function send(string $mobile): void
    {
        $this->locked($mobile, function () use ($mobile) {
            $key = hash('sha256', $mobile);
            $this->limit('otp:send:minute:'.$key, 1, 60);
            $this->limit('otp:send:hour:'.$key, 5, 3600);

            // Unknown/inactive/admin numbers receive the same public response.
            $user = User::where('mobile', $mobile)->where('role', 'customer')->where('is_active', true)->first();
            if (!$user) return;

            $code = (string) random_int(100000, 999999);
            $verification = DB::transaction(function () use ($mobile, $code) {
                PhoneVerification::where('mobile', $mobile)->whereNull('used_at')->update(['used_at' => now()]);
                return PhoneVerification::create([
                    'mobile' => $mobile, 'code_hash' => Hash::make($code),
                    'expires_at' => now()->addMinutes(2),
                ]);
            });
            try {
                $this->sms->sendOtp($mobile, $code);
            } catch (\Throwable) {
                $verification->update(['used_at' => now()]);
                throw new HttpException(503, 'ارسال پیامک موقتاً امکان‌پذیر نیست. لطفاً بعداً تلاش کنید.');
            }
        });
    }

    public function verify(string $mobile, string $code): User
    {
        $user = $this->locked($mobile, function () use ($mobile, $code) {
            $this->limit('otp:verify:'.hash('sha256', $mobile), 10, 300);
            return DB::transaction(function () use ($mobile, $code) {
                $otp = PhoneVerification::where('mobile', $mobile)->latest('id')->lockForUpdate()->first();
                if (!$otp || $otp->used_at || $otp->expires_at->lte(now()) || $otp->attempts >= 5) return null;

                // Return failure from the transaction so the increment is committed.
                $otp->increment('attempts');
                if (!Hash::check($code, $otp->code_hash)) return null;

                $otp->update(['used_at' => now()]);
                return User::where('mobile', $mobile)->where('role', 'customer')->where('is_active', true)->first();
            });
        });
        if (!$user) {
            throw ValidationException::withMessages(['code' => 'کد نامعتبر یا منقضی شده است. اگر حساب ندارید، ابتدا ثبت‌نام کنید.']);
        }
        return $user;
    }
}

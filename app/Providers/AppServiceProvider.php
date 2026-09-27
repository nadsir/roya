<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Contracts\SmsServiceInterface::class, function () {
            return match (config('sms.driver')) {
                'log' => new \App\Services\Sms\LogSmsService(),
                'http' => new \App\Services\Sms\HttpSmsService(),
                default => throw new \RuntimeException('Unsupported SMS driver.'),
            };
        });

        $this->app->bind(
            \App\Contracts\PaymentGatewayInterface::class,
            function () {
                if (config('services.aghayepardakht.pin')) {
                    return new \App\Services\Payment\AghayePardakhtGateway();
                }

                return new \App\Services\Payment\FakePaymentGateway();
            },
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

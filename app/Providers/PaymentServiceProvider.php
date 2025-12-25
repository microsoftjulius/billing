<?php

namespace App\Providers;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Services\Payment\MpesaService;
use App\Services\Payment\StripeService;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            $provider = config('payment.default');
            $config = config("payment.providers.{$provider}");

            return match($provider) {
                'mpesa' => new MpesaService($config),
                'stripe' => new StripeService($config),
                default => throw new \InvalidArgumentException("Unsupported payment provider: {$provider}")
            };
        });
    }
}

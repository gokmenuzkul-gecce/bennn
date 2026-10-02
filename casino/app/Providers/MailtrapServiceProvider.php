<?php

namespace VanguardLTE\Providers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use VanguardLTE\Mail\Transport\MailtrapTransport;

class MailtrapServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register the mailer at runtime so the framework's default
        // mail.mailers (smtp/log/...) are preserved, not replaced.
        $this->app['config']->set('mail.mailers.mailtrap', [
            'transport' => 'mailtrap',
            'token' => env('MAILTRAP_API_TOKEN', ''),
            // Not named "url": Laravel's ConfigurationUrlParser rewrites "transport"
            // whenever a "url" key is present.
            'api_url' => env('MAILTRAP_API_URL', 'https://send.api.mailtrap.io'),
        ]);
    }

    public function boot(): void
    {
        Mail::extend('mailtrap', function (array $config) {
            return new MailtrapTransport(
                (string) ($config['token'] ?? ''),
                (string) ($config['api_url'] ?? 'https://send.api.mailtrap.io')
            );
        });
    }
}

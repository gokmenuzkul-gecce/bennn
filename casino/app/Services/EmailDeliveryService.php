<?php

namespace VanguardLTE\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmailDeliveryService
{
    public function send(string $to, string $subject, string $html, ?string $text = null): bool
    {
        $payload = [
            'to' => strtolower(trim($to)),
            'subject' => trim($subject),
            'html' => $html,
            'text' => $text,
        ];

        try {
            $provider = DeliveryGatewaySettings::provider('email');
            if ($provider === 'brevo') return $this->sendBrevo($payload);
            if ($provider === 'resend') return $this->sendResend($payload);
            if ($provider === 'postmark') return $this->sendPostmark($payload);
            if ($provider === 'mailtrap') return $this->sendMailtrap($payload);
            if ($provider === 'custom') {
                $endpoint = DeliveryGatewaySettings::customEndpoint('email');
                $token = DeliveryGatewaySettings::secret('email');
                if ($endpoint === '' || $token === '') {
                    return false;
                }
                return Http::withToken($token)->acceptJson()->asJson()->timeout(10)->post($endpoint, $payload)->successful();
            }

            return false;
        } catch (\Throwable $e) {
            Log::warning('[Email Delivery] Provider request failed.', ['type' => get_class($e)]);
            return false;
        }
    }

    private function sendBrevo(array $payload): bool
    {
        $sender = $this->sender();
        return Http::withHeaders(['api-key' => DeliveryGatewaySettings::secret('email')])
            ->acceptJson()->asJson()->timeout(10)->post('https://api.brevo.com/v3/smtp/email', [
                'sender' => $sender, 'to' => [['email' => $payload['to']]], 'subject' => $payload['subject'],
                'htmlContent' => $payload['html'], 'textContent' => $payload['text'], 'tags' => ['promex-transactional'],
            ])->successful();
    }

    private function sendResend(array $payload): bool
    {
        $sender = $this->fromHeader($this->sender());
        return Http::withToken(DeliveryGatewaySettings::secret('email'))->acceptJson()->asJson()->timeout(10)
            ->post('https://api.resend.com/emails', [
                'from' => $sender, 'to' => [$payload['to']], 'subject' => $payload['subject'],
                'html' => $payload['html'], 'text' => $payload['text'], 'tags' => [['name' => 'type', 'value' => 'transactional']],
            ])->successful();
    }

    private function sendMailtrap(array $payload): bool
    {
        $token = DeliveryGatewaySettings::secret('email');
        if ($token === '') {
            throw new \RuntimeException('The Mailtrap provider needs an API token.');
        }

        $sender = $this->sender();

        return Http::withToken($token)->acceptJson()->asJson()->timeout(10)
            ->post('https://send.api.mailtrap.io/api/send', [
                'from' => ['email' => $sender['address'], 'name' => $sender['name']],
                'to' => [['email' => $payload['to']]],
                'subject' => $payload['subject'],
                'html' => $payload['html'],
                'text' => $payload['text'],
                'category' => 'promex-transactional',
            ])->successful();
    }

    private function sendPostmark(array $payload): bool
    {
        return Http::withHeaders(['X-Postmark-Server-Token' => DeliveryGatewaySettings::secret('email')])
            ->acceptJson()->asJson()->timeout(10)->post('https://api.postmarkapp.com/email', [
                'From' => $this->fromHeader($this->sender()), 'To' => $payload['to'], 'Subject' => $payload['subject'],
                'HtmlBody' => $payload['html'], 'TextBody' => $payload['text'], 'Tag' => 'promex-transactional',
                'MessageStream' => 'outbound',
            ])->successful();
    }

    private function sender(): array
    {
        $sender = DeliveryGatewaySettings::emailSender();
        if (!filter_var($sender['address'], FILTER_VALIDATE_EMAIL) || DeliveryGatewaySettings::secret('email') === '') {
            throw new \RuntimeException('The selected email provider needs a verified sender address and API token.');
        }
        return $sender;
    }

    private function fromHeader(array $sender): string
    {
        return $sender['name'] === '' ? $sender['address'] : $sender['name'] . ' <' . $sender['address'] . '>';
    }
}

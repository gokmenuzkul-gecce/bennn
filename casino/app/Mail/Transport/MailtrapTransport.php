<?php

namespace VanguardLTE\Mail\Transport;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Sends mail through the Mailtrap Email API (POST /api/send, JSON body).
 * Useful when the host blocks outbound SMTP but HTTPS is allowed.
 */
class MailtrapTransport extends AbstractTransport
{
    private Client $client;

    public function __construct(
        private string $token,
        private string $apiUrl = 'https://send.api.mailtrap.io',
        ?Client $client = null
    ) {
        parent::__construct();

        if ($token === '') {
            throw new TransportException('Mailtrap API token is not configured (set MAILTRAP_API_TOKEN).');
        }

        $this->client = $client ?? new Client(['timeout' => 15, 'http_errors' => false]);
    }

    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();

        if (!$original instanceof Email) {
            throw new TransportException('Mailtrap transport only supports Symfony Mime Email messages.');
        }

        $email = MessageConverter::toEmail($original);

        $payload = [
            'from' => $this->address($email->getFrom()[0] ?? new Address('noreply@localhost')),
            'to' => array_map([$this, 'address'], $email->getTo()),
            'subject' => (string) $email->getSubject(),
        ];

        if ($replyTo = $email->getReplyTo()) {
            $payload['reply_to'] = $this->address($replyTo[0]);
        }
        if ($cc = $email->getCc()) {
            $payload['cc'] = array_map([$this, 'address'], $cc);
        }
        if ($bcc = $email->getBcc()) {
            $payload['bcc'] = array_map([$this, 'address'], $bcc);
        }
        if ($text = $email->getTextBody()) {
            $payload['text'] = $text;
        }
        if ($html = $email->getHtmlBody()) {
            $payload['html'] = $html;
        }

        $headers = [];
        foreach ($email->getHeaders()->all() as $header) {
            if (in_array(strtolower($header->getName()), ['from', 'to', 'cc', 'bcc', 'subject', 'reply-to'], true)) {
                continue;
            }
            $headers[$header->getName()] = $header->getBodyAsString();
        }
        if ($headers) {
            $payload['headers'] = $headers;
        }

        $attachments = [];
        foreach ($email->getAttachments() as $part) {
            /** @var DataPart $part */
            $attachments[] = [
                'filename' => $part->getFilename() ?? 'attachment',
                'content' => base64_encode($part->getBody()),
                'type' => $part->getMediaType() . '/' . $part->getMediaSubtype(),
            ];
        }
        if ($attachments) {
            $payload['attachments'] = $attachments;
        }

        try {
            $response = $this->client->post(rtrim($this->apiUrl, '/') . '/api/send', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
            ]);
        } catch (GuzzleException $e) {
            throw new TransportException('Mailtrap request failed: ' . $e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($status < 200 || $status >= 300) {
            throw new TransportException('Mailtrap rejected the message (HTTP ' . $status . '): ' . $body);
        }

        $decoded = json_decode($body, true);
        if (is_array($decoded) && !empty($decoded['message_ids'][0])) {
            $message->setMessageId((string) $decoded['message_ids'][0]);
        }
    }

    private function address(Address $address): array
    {
        return array_filter([
            'email' => $address->getAddress(),
            'name' => $address->getName(),
        ], static fn ($value) => $value !== null && $value !== '');
    }

    public function __toString(): string
    {
        return 'mailtrap';
    }
}

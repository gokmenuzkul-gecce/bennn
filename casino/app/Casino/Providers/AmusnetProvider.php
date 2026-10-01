<?php

namespace VanguardLTE\Casino\Providers;

class AmusnetProvider extends AbstractCasinoProvider
{
    public const KEY = 'amusnet';

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return 'Amusnet';
    }

    /**
     * Amusnet never returns a direct game URL: the launch endpoint answers with
     * a tiny page that auto-submits a hidden form to its own game host. A
     * cross-origin iframe cannot run that submission, so we fetch the page here,
     * extract the form and hand it back for the lobby to replay locally.
     *
     * @return array{url: string, form?: array{action: string, fields: array<string, string>}}
     */
    public function embeddedLaunchPayload(string $userCode, string $gameId, string $lang = 'tr'): array
    {
        $launcherUrl = $this->launchUrl($userCode, $gameId, $lang);
        $form = $this->extractAutoForm($launcherUrl);

        return $form === null ? ['url' => $launcherUrl] : ['url' => $form['action'], 'form' => $form];
    }

    /**
     * @return array{action: string, fields: array<string, string>}|null
     */
    private function extractAutoForm(string $launcherUrl): ?array
    {
        $ch = curl_init($launcherUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
        ]);
        $body = curl_exec($ch);
        curl_close($ch);

        if (!is_string($body) || $body === '') {
            return null;
        }

        if (!preg_match('/<form[^>]*action="([^"]+)"/i', $body, $actionMatch)) {
            return null;
        }
        if (!preg_match_all('/<input[^>]*name="([^"]+)"[^>]*value="([^"]*)"/i', $body, $fieldMatches, PREG_SET_ORDER)) {
            return null;
        }

        $fields = [];
        foreach ($fieldMatches as $match) {
            $fields[$match[1]] = $match[2];
        }
        if ($fields === []) {
            return null;
        }

        return [
            'action' => $this->absoluteUrl($launcherUrl, $actionMatch[1]),
            'fields' => $fields,
        ];
    }

    private function absoluteUrl(string $base, string $path): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $parts = parse_url($base);
        $root = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');

        return $root . '/' . ltrim($path, '/');
    }
}

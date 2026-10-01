<?php

use Illuminate\Support\Str;						   
if (! function_exists('settings')) {
    /**
     * Get / set the specified settings value.
     *
     * If an array is passed as the key, we will assume you want to set an array of values.
     *
     * @param  array|string  $key
     * @param  mixed  $default
     * @return mixed
     */
    function settings($key = null, $default = null)
    {
        try {
            if (is_null($key)) {
                return app('anlutro\LaravelSettings\SettingStore');
            }

            $value = app('anlutro\LaravelSettings\SettingStore')->get($key, $default);
            if ($key === 'frontend' && (!$value || strtolower($value) === 'default')) {
                return 'Minimal';
            }

            return $value;
        } catch (\Exception $e) {
            if ($key === 'frontend') {
                return 'Minimal';
            }
            return $default;
        }
    }
}

function encoded($str)
{
    return base64_encode(base64_encode($str));
}
function decoded($str)
{
    return base64_decode(base64_decode($str));
}

function hpRand($digit = 4)
{
    return substr(rand(0, 12345) . strrev(time()), 0, $digit);
}
function hpRandStr($digit = 4)
{
    $random = Str::random($digit);
    return $random;
}

/**
 * Resolve a game's cover image, preferring the same-origin localized copy.
 *
 * Provider CDNs are only a fallback: the lobby renders thousands of cards, and
 * one cross-origin request each is slow and hostage to the vendor's hotlink
 * policy. Local covers live in the served repo-root frontend dir.
 */
function game_cover($game)
{
    $name = is_object($game) ? ($game->name ?? null) : $game;
    $external = is_object($game) ? ($game->icon_url ?? null) : null;
    if ($name) {
        $local = dirname(base_path()) . '/frontend/Default/ico/' . $name . '.jpg';
        if (is_file($local)) {
            return '/frontend/Default/ico/' . $name . '.jpg';
        }
    }
    if ($external) {
        return $external;
    }
    return $name ? '/frontend/Default/ico/' . $name . '.jpg' : '';
}

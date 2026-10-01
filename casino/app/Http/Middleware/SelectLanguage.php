<?php
namespace VanguardLTE\Http\Middleware
{
    class SelectLanguage
    {
        public function handle($request, \Closure $next)
        {
            $locale = config('app.locale', 'tr');

            if (auth()->check() && auth()->user()->language)
            {
                $locale = auth()->user()->language;
            }

            if (isset($_COOKIE['language']))
            {
                $locale = htmlspecialchars($_COOKIE['language']);
            }

            \App::setLocale($locale);

            return $next($request);
        }
    }
}

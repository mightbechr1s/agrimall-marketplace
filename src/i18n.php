<?php
// i18n helper: English/Filipino locale resolution for public-facing copy.
// Locale is chosen via ?lang=tl|en, persisted to session when logged in,
// otherwise remembered via cookie. Default: en.
declare(strict_types=1);

if (!function_exists('locale_current')) {
    /** Return the resolved two-letter locale ('en'|'tl'). */
    function locale_current(): string
    {
        static $locale = null;
        if ($locale !== null) {
            return $locale;
        }

        $locale = 'en'; // default
        $lang = $_GET['lang'] ?? '';

        if ($lang === 'tl' || $lang === 'fil') {
            $locale = 'tl';
        } elseif ($lang === 'en') {
            $locale = 'en';
        } elseif (function_exists('current_user_id') && current_user_id() && class_exists('DB')) {
            try {
                $stmt = DB::pdo()->prepare('SELECT locale FROM user_preferences WHERE user_id = :id LIMIT 1');
                $stmt->execute(['id' => current_user_id()]);
                $savedLocale = $stmt->fetchColumn();
                if ($savedLocale === 'tl' || $savedLocale === 'en') {
                    $locale = $savedLocale;
                } elseif (isset($_SESSION['lang']) && in_array($_SESSION['lang'], ['en', 'tl'], true)) {
                    $locale = $_SESSION['lang'];
                }
            } catch (Throwable $e) {
                $savedLocale = $_SESSION['lang'] ?? ($_COOKIE['animal_lang'] ?? '');
                if ($savedLocale === 'tl' || $savedLocale === 'fil') {
                    $locale = 'tl';
                } elseif ($savedLocale === 'en') {
                    $locale = 'en';
                }
            }
        } else {
            $savedLocale = $_SESSION['lang'] ?? ($_COOKIE['animal_lang'] ?? '');
            if ($savedLocale === 'tl' || $savedLocale === 'fil') {
                $locale = 'tl';
            } elseif ($savedLocale === 'en') {
                $locale = 'en';
            }
        }

        // Persist to cookie when language comes from query param
        if (isset($_GET['lang'])) {
            locale_switch($locale);
        }

        return $locale;
    }
}

if (!function_exists('locale_switch')) {
    /** Persist a locale choice. */
    function locale_switch(string $lang): void
    {
        $lang = $lang === 'tl' ? 'tl' : 'en';
        $_SESSION['lang'] = $lang;
        setcookie('animal_lang', $lang, [
            'expires'  => time() + 60 * 60 * 24 * 365,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

if (!function_exists('locale_url')) {
    /**
     * Return a URL to the same request with the locale switched to $lang,
     * preserving other query parameters.
     */
    function locale_url(string $lang, bool $keepQuery = true): string
    {
        $lang = $lang === 'tl' ? 'tl' : 'en';
        $query = $_GET;
        unset($query['lang']);
        if ($keepQuery) {
            $query['lang'] = $lang;
        } elseif (!$keepQuery) {
            // drop all other params, keep only lang
            $query = ['lang' => $lang];
        }

        $base = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        return $base . ($query ? '?' . http_build_query($query) : '');
    }
}

if (!function_exists('t')) {
    /**
     * Translate a key from the active dictionary.
     * Expects the copy deck structure: section = [ 'en' => [...], 'tl' => [...] ].
     * Fallback chain: [lang] -> 'en' -> raw key.
     */
    function t(array $dict, string $key): string
    {
        $lang = locale_current();
        if (isset($dict[$lang][$key])) {
            return $dict[$lang][$key];
        }
        if (isset($dict['en'][$key])) {
            return $dict['en'][$key];
        }
        return $key;
    }
}
<?php

namespace Spartan\I18n;

/**
 * Translator I18n
 *
 * @package Spartan\I18n
 * @author  Iulian N. <iulian@spartanphp.com>
 * @license LICENSE MIT
 */
class Translator
{
    protected string $locale;
    protected string $fallback;
    protected string $domain;

    /**
     * @var mixed[]
     */
    protected static array $messages = [];

    /**
     * Translator constructor.
     *
     * @param string $locale
     * @param string $domain
     * @param string $fallback
     */
    public function __construct(string $locale = 'en_US', string $domain = './resources/locales', string $fallback = 'en_US')
    {
        $this->locale   = $locale;
        $this->fallback = $fallback;
        $this->domain   = $domain;

        $this->load();
    }

    /**
     * Load translations.
     * First try from php file.
     * Fallback to json file.
     */
    public function load(): void
    {
        $i18n = [];
        if (file_exists("{$this->domain}/{$this->locale}.php")) {
            $i18n = require "{$this->domain}/{$this->locale}.php";
        } elseif (file_exists("{$this->domain}/{$this->locale}.json")) {
            $i18n = json_decode((string)file_get_contents("{$this->domain}/{$this->locale}.json"), true);
        }

        $isNamespaced = $i18n['namespaced'] ?? false;

        self::$messages = $i18n['messages'] ?? [];

        if ($this->fallback) {
            if (file_exists("{$this->domain}/{$this->fallback}.php")) {
                $i18n = require "{$this->domain}/{$this->fallback}.php";
            } elseif (file_exists("{$this->domain}/{$this->fallback}.json")) {
                $i18n = json_decode((string)file_get_contents("{$this->domain}/{$this->fallback}.json"), true);
            }
        }

        self::$messages += $i18n['messages'] ?? [];

        if ($isNamespaced) {
            self::$messages = self::flatten(self::$messages);
        }
    }

    /**
     * @param string $locale
     * @param string $domain
     * @param string $fallback
     *
     * @return Translator
     */
    public static function init(string $locale = '', string $domain = '', string $fallback = '')
    {
        return new self(
            $locale ?: (string)getenv('I18N_LOCALE'),
            $domain ?: (string)getenv('I18N_DOMAIN'),
            $fallback ?: (string)getenv('I18N_FALLBACK'),
        );
    }

    /**
     * @return mixed[]
     */
    public static function messages(): array
    {
        return self::$messages;
    }

    /**
     * @param string $key
     * @param mixed  $params
     *
     * @return string
     */
    public function message(string $key, $params = null): string
    {
        if (!isset(self::$messages[$key])) {
            return $key;
        }

        $text = self::$messages[$key];

        if (is_array($params)) {
            $keys = array_map(
                function ($value) {
                    return "{{$value}}";
                },
                array_keys($params)
            );
            $text = str_replace($keys, $params, $text);
        } elseif (is_numeric($params)) {
            $plurals = explode('|', $text);
            if (isset($plurals[$params])) {
                return trim(str_replace('{n}', (string)$params, $plurals[$params]));
            }

            return trim(str_replace('{n}', (string)$params, $plurals[count($plurals) - 1]));
        } elseif ($params instanceof \Closure) {
            return (string)$params($text);
        }

        return $text;
    }

    /**
     * Avoid dependency for a single function.
     *
     * @param mixed[] $iterable
     * @param int     $depth
     * @param string  $prefix
     *
     * @return mixed[]
     */
    public static function flatten(array $iterable, $depth = PHP_INT_MAX, $prefix = ''): array
    {
        $result = [];
        foreach ($iterable as $key => $value) {
            if (is_array($value) && $value && $depth) {
                $result = array_merge($result, self::flatten($value, --$depth, $prefix . $key . '.'));
            } else {
                $result[$prefix . $key] = $value;
            }
        }

        return $result;
    }
}

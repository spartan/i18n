<?php

use Spartan\I18n\Translator;

if (!function_exists('t')) {

    /**
     * @param string $key
     * @param mixed   $params
     *
     * @return string
     */
    function t(string $key, $params = null)
    {
        static $translator;

        if (!$translator) {
            $translator = Translator::init();
        }

        return $translator->message($key, $params);
    }
}

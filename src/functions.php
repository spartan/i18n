<?php

use Spartan\I18n\Translator;

if (!function_exists('t')) {

    /**
     * @param string          $key
     * @param mixed           $params
     * @param Translator|null $translator
     *
     * @return string
     */
    function t(string $key, $params = null, Translator $translator = null)
    {
        static $t;

        if ($translator) {
            $t = $translator;
        }

        if (!$t) {
            $t = Translator::init();
        }

        return $t->message($key, $params);
    }
}

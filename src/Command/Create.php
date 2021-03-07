<?php

namespace Spartan\I18n\Command;

use Spartan\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Create Command
 *
 * @property string $name
 *
 * @package Spartan\I18n
 * @author  Iulian N. <iulian@spartanphp.com>
 * @license LICENSE MIT
 */
class Create extends Command
{
    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure(): void
    {
        $this->withSynopsis('i18n:create', 'Create a json translation file')
             ->withArgument('name', 'Locale names. Ex: en_US,fr_FR');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        self::loadEnv();

        $dest = getenv('I18N_DOMAIN') ?: './resource/locales';

        foreach (explode(',', $this->name) as $locale) {
            $locale = preg_replace('/[^a-zA-Z]+/', '-', $locale);
            $file   = "{$dest}/{$locale}.json";

            file_put_contents(
                $file,
                json_encode(
                    [
                        'language'   => '--undefined--',
                        'locale'     => $locale,
                        'direction'  => 'ltr',
                        'namespaced' => false,
                        'messages'   => [],
                        'context'    => [],
                    ],
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                )
            );
        }

        return 0;
    }
}

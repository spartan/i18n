<?php

namespace Spartan\I18n\Command;

use Spartan\Console\Command;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Translate Command
 *
 * @package Spartan\I18n
 * @author  Iulian N. <iulian@spartanphp.com>
 * @license LICENSE MIT
 */
class Translate extends Command
{
    protected function configure(): void
    {
        $this->withSynopsis('i18n:translate', 'Translate strings from json files')
             ->withOption('lang', 'Source language. Defaults to en-US', 'en-US')
             ->withOption('delay', 'Delay seconds between each translation (required to avoid banning of IP)', 5);
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

        /** @var string $lang */
        $lang  = $input->getOption('lang');
        $src   = (getenv('I18N_DOMAIN') ?: './resources/locales/') . $lang . '.json';
        $delay = $input->getOption('delay');
        $delay = is_array($delay) ? 0 : (int)$delay;

        /*
         * ['key' => 'translation']
         */
        $sourceTranslations = json_decode(
                                  (string)file_get_contents("{$src}/{$lang}.json"),
                                  true
                              )['messages'] ?? [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src));
        foreach ($iterator as $item) {
            if (!$item->isDir() && substr($item->getPathName(), -4) == 'json') {
                if (substr($item->getPathName(), -9) == "{$lang}.json") {
                    continue;
                }

                $file = $item->getPathName();
                // en-US, fr-FR, ro-RO
                $locale = substr($file, strrpos($file, '/') + 1, strlen($file) - strrpos($file, '.'));
                $json   = json_decode((string)file_get_contents($file), true);

                $tr = new GoogleTranslate();
                $tr->setSource($lang); // en-US default
                $tr->setTarget($locale);
                $tr->setOptions(
                    [
                        'headers' => [
                            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:81.0) Gecko/20100101 Firefox/84.0',
                        ],
                    ]
                );

                $output->writeln("<success>Switching to {$locale}</success>");

                foreach ($json['messages'] as $key => &$translation) {
                    if ($translation === '') {
                        try {
                            $srcTranslation = $sourceTranslations[$key] ?? $key;
                            if ($srcTranslation == '') {
                                $output->writeln("<warning>Missing translation for {$key}</warning>");
                            } else {
                                $output->write("Translating... {$srcTranslation} -> ");
                                $translation = $tr->translate($srcTranslation);
                                /** @var string $translation */
                                $output->write($translation);
                                $output->writeln('');
                                sleep($delay);
                            }
                        } catch (\Exception $e) {
                            $output->writeln('Failed translation: ' . $e->getMessage());
                        }
                    }
                }

                file_put_contents(
                    $file,
                    json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                );
            }
        }

        return 0;
    }
}

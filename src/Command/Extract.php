<?php

namespace Spartan\I18n\Command;

use Spartan\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Extract translation keys Command
 *
 * @package Spartan\I18n
 * @author  Iulian N. <iulian@spartanphp.com>
 * @license LICENSE MIT
 */
class Extract extends Command
{
    /**
     * @throws \Symfony\Component\Console\Exception\InvalidArgumentException
     */
    protected function configure(): void
    {
        $this->withSynopsis('i18n:extract', 'Extract translations from different files into .json')
             ->withOption('src', 'Source path(s) with files to parse', './src')
             ->withOption('dry', 'Dry run without saving')
             ->withOption('dst', 'Path of locale files')
             ->withOption('smart-copy', 'Text with spaces are considered paragraphs and copied verbatim')
             ->withOption('smart-label', 'Text starting with # are transformed into titleCase')
             ->withOption('locale', 'Only update this locale')
             ->withOption('append', 'Append without removing');
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

        /** @var string $src */
        $src          = $input->getOption('src');
        $dst          = $input->getOption('dst') ?: (getenv('I18N_DOMAIN') ?: './resources / locales');
        $append       = $this->isOptionPresent('append');
        $isSmartCopy  = $this->isOptionPresent('smart-copy');
        $isSmartLabel = $this->isOptionPresent('smart-label');
        $onlyLocale   = $input->getOption('locale');

        $keys = [];
        foreach (explode(',', $src) as $oneSrc) {
            $keys += $this->extractJs($oneSrc);
            $keys += $this->extractPhp($oneSrc);
        }

        $locales = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dst));
        foreach ($iterator as $item) {
            if (!$item->isDir() && substr($item->getPathName(), -4) == 'json') {
                $file     = $item->getPathName();
                $locale   = substr($file, strrpos($file, '/') + 1, strlen($file) - strrpos($file, '.'));
                $json     = json_decode((string)file_get_contents($file), true);
                $messages = $this->flatten($json['messages']);

                if ($onlyLocale && $locale != $onlyLocale) {
                    continue;
                }

                $locales[$locale] = [
                    'common'     => $append ? $messages : array_intersect_key($messages, $keys),
                    'deprecated' => $append ? [] : array_diff_key($messages, $keys),
                    'new'        => array_diff_key($keys, $messages),
                    'messages'   => $messages,
                    'json'       => $json,
                    'file'       => $item->getPathName(),
                ];
            }
        }

        /*
         * Show removed
         */
        $deprecated = [];
        foreach ($locales as $locale => $data) {
            $deprecated += $data['deprecated'];
        }
        $rows = [];
        foreach ($deprecated as $key => $value) {
            $rows[] = [$key];
        }
        $this->table(['Deprecated'], $rows)->render();

        /*
         * Show new
         */
        $new = [];
        foreach ($locales as $locale => $data) {
            $new += $data['new'];
        }
        $rows = [];
        foreach ($new as $key => $value) {
            $rows[] = [$key];
        }
        $this->table(['New'], $rows)->render();

        /*
         * Dry
         */
        if (!$this->isOptionPresent('dry')) {
            foreach ($locales as $locale => $data) {
                $json = $data['json'];
                // original
                $json['messages'] = $data['messages'];

                // remove deprecated
                $json['messages'] = array_diff_key($json['messages'], $data['deprecated']);

                // add new
                $json['messages'] += $data['new'];

                if ($isSmartCopy) {
                    foreach ($json['messages'] as $key => &$value) {
                        if (strpos($key, ' ') > 0 && $value === '') {
                            $value = $key;
                        } elseif (preg_match('/^[A-Z]{1}/', $key)) {
                            $value = $key;
                        }
                    }
                }

                if ($isSmartLabel) {
                    foreach ($json['messages'] as $key => &$value) {
                        if ($key[0] == '#' && $value === '') {
                            $value = mb_convert_case(str_replace('_', ' ', substr($key, 1)), MB_CASE_TITLE, 'utf8');
                        }
                    }
                }

                file_put_contents(
                    $data['file'],
                    json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                );

                $output->writeln('Saving ' . $data['file']);
            }
        }

        return 0;
    }

    /**
     * @param string         $src
     * @param array|string[] $extensions
     *
     * @return mixed[]
     */
    protected function extractJs(string $src, array $extensions = ['vue', 'js'])
    {
        $keys     = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src));
        foreach ($iterator as $item) {
            if (!$item->isDir()) {
                $ext = substr($item->getPathname(), strrpos($item->getPathName(), '.') + 1);
                if (!in_array($ext, $extensions)) {
                    continue;
                }

                $contents = file_get_contents($item->getPathname());

                $patterns = [
                    '#(?:\$|i18n\.)t\(\"([^\"]+)(.*)#',
                    '#(?:\$|i18n\.)t\(\'([^\']+)(.*)#',
                    '#(?:\$|i18n\.)tc\(\"([^\"]+)(.*)#',
                    '#(?:\$|i18n\.)tc\(\'([^\']+)(.*)#',
                    '#"([^"]+)"[ ,;]{1,3}//tt(.*)?#',
                    "#'([^']+)'[ ,;]{1,3}//tt(.*)?#",
                ];

                foreach ($patterns as $pattern) {
                    preg_match_all($pattern, (string)$contents, $matches);

                    $translations = array_combine(
                        $matches[1],
                        fluent($matches[2])->map(
                            function ($value) {
                                $translation = strpos($value, '//tt') !== false
                                    ? (explode('//tt', $value) + ['', ''])[1]
                                    : (string)$value;

                                return trim($translation, ' "\'');
                            }
                        )->toArray()
                    );

                    if (count($translations)) {
                        $keys += $translations;
                    }
                }
            }
        }

        return $keys;
    }

    /**
     * @param string         $src
     * @param array|string[] $extensions
     *
     * @return mixed[]
     */
    protected function extractPhp($src, array $extensions = ['php', 'phtml']): array
    {
        $keys     = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src));
        foreach ($iterator as $item) {
            if (!$item->isDir()) {
                $ext = substr($item->getPathname(), strrpos($item->getPathName(), '.') + 1);
                if (!in_array($ext, $extensions)) {
                    continue;
                }

                $contents = file_get_contents($item->getPathname());

                preg_match_all('#[^a-zA-Z0-9]_\(\'([^\']+)\'#', (string)$contents, $translationsPhp1);
                preg_match_all('#[^a-zA-Z0-9]t\(\'([^\']+)\'#', (string)$contents, $translationsPhp2);

                $translationsPhp1 = array_filter((array)$translationsPhp1);
                $translationsPhp2 = array_filter((array)$translationsPhp2);

                if (count($translationsPhp1)) {
                    $keys += array_flip($translationsPhp1[1]);
                }

                if (count($translationsPhp2)) {
                    $keys += array_flip($translationsPhp2[1]);
                }
            }
        }

        return array_fill_keys(array_keys($keys), '');
    }

    /**
     * @param iterable<mixed> $iterable
     * @param int             $depth
     * @param string          $prefix
     *
     * @return mixed[]
     */
    public function flatten(iterable $iterable, $depth = PHP_INT_MAX, $prefix = ''): array
    {
        $result = [];
        foreach ($iterable as $key => $value) {
            if (is_array($value) && $value && $depth) {
                $result = array_merge($result, $this->flatten($value, --$depth, $prefix . $key . '.'));
            } else {
                $result[$prefix . $key] = $value;
            }
        }

        return $result;
    }
}

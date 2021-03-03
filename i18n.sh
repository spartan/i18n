#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$status = (new \Spartan\Console\Application())
    ->withCommandsDir(__DIR__ . '/src/Command')
    ->withDefaultStyles()
    ->run();

exit($status);

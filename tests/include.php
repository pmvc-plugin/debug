<?php

$path = __DIR__ . '/../vendor/autoload.php';
include $path;

\PMVC\Load::plug(
    [
        'unit' => null,
        'debug' => null,
    ],
    [__DIR__ . '/../../']
);

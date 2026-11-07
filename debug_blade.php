<?php

use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$source = __DIR__.'/storage/framework/views/livewire/views/c8e7ba2d.blade.php';
$output = __DIR__.'/storage/framework/views/debug_compiled.php';

$compiler = $app->make('blade.compiler');
$compiled = $compiler->compileString(file_get_contents($source));

file_put_contents($output, $compiled);

echo 'written: '.$output.' ('.strlen($compiled).' bytes)'.PHP_EOL;

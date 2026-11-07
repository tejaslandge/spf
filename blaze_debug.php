<?php

use Illuminate\Contracts\Console\Kernel;
use Livewire\Blaze\BlazeManager;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$path = 'storage/framework/views/livewire/views/c8e7ba2d.blade.php';
$src = file_get_contents($path);

$out = app(BlazeManager::class)->compile($src, $path);

file_put_contents('C:/Users/DELL/AppData/Local/Temp/opencode/blaze_intermediate.txt', $out);

echo 'length: '.strlen($out).PHP_EOL;

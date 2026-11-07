<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Bootstrap\BootProviders;
use Illuminate\Foundation\Bootstrap\HandleExceptions;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Bootstrap\RegisterFacades;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\View\Compilers\BladeCompiler;

require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->bootstrapWith([
    LoadEnvironmentVariables::class,
    LoadConfiguration::class,
    HandleExceptions::class,
    RegisterFacades::class,
    RegisterProviders::class,
    BootProviders::class,
]);

$blade = new BladeCompiler(
    new Filesystem,
    __DIR__.'/storage/framework/views',
    $app
);

$cacheSource = file_get_contents('storage/framework/views/livewire/views/c8e7ba2d.blade.php');

echo "=== compileString(cache file) ===\n";
try {
    $blade->compileString($cacheSource);
    echo "COMPILED OK\n";
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
}

echo "\n=== view()->file(cache file) ===\n";
try {
    $app->make('view')->file('storage/framework/views/livewire/views/c8e7ba2d.blade.php', []);
    echo "RENDERED OK\n";
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
    $trace = explode("\n", $e->getTraceAsString());
    echo implode("\n", array_slice($trace, 0, 10))."\n";
}

echo "\n=== compileString(source file) ===\n";
$source = file_get_contents('resources/views/pages/loans/show.blade.php');
try {
    $blade->compileString($source);
    echo "COMPILED OK\n";
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
}

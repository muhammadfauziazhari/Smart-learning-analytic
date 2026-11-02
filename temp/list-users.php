<?php
$base = dirname(__DIR__).'/laravel';
require $base.'/vendor/autoload.php';
$app = require_once $base.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach (App\Models\User::query()->select('id','nama','email','role')->get() as $u) {
    echo $u->id.' | '.$u->email.' | '.$u->role.PHP_EOL;
}

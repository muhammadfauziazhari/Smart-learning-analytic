<?php
$base = dirname(__DIR__).'/laravel';
require $base.'/vendor/autoload.php';
$app = require_once $base.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach (['admin@edutrack.com','adhiguna-skom@edutrack.local'] as $email) {
    $u = App\Models\User::where('email',$email)->first();
    echo $email.' => '.($u ? (Illuminate\Support\Facades\Hash::check('password123',$u->password) ? 'password123 OK' : 'BEDA') : 'TIDAK ADA').PHP_EOL;
}

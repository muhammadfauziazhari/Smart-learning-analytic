<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Akun Admin
        User::create([
            'name' => 'Administrator',
            'email' => 'admin@gmail.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        // Akun Kaprodi
        User::create([
            'name' => 'Ketua Prodi',
            'email' => 'kaprodi@gmail.com',
            'password' => Hash::make('password'),
            'role' => 'kaprodi',
        ]);

        // Akun Dosen
        User::create([
            'name' => 'Dosen Pengajar',
            'email' => 'dosen@gmail.com',
            'password' => Hash::make('password'),
            'role' => 'dosen',
        ]);
    }
}

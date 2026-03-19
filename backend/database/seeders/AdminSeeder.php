<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('username', 'popsand.tech@gmail.com')->exists()) {
            return;
        }

        User::create([
            'username' => 'popsand.tech@gmail.com',
            'name' => '系统管理员',
            'password' => Hash::make('Popsand678'),
            'role' => 'admin',
            'status' => 'active',
        ]);
    }
}

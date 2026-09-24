<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['username' => 'jmaeacido'],
            [
                'name' => 'jmaeacido',
                'email' => null,
                'password' => 'Password123!',
            ],
        );
    }
}

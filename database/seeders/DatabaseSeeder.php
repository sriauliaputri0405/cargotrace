<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['Administrator', 'admin@cargotrace.test', User::ROLE_ADMIN],
            ['Logistics Operator', 'operator@cargotrace.test', User::ROLE_OPERATOR],
            ['Customer', 'customer@cargotrace.test', User::ROLE_CUSTOMER],
        ];

        foreach ($accounts as [$name, $email, $role]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->forceFill([
                'name' => $name,
                'password' => 'password',
                'role' => $role,
            ])->save();
        }
    }
}
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('cargotrace.seed_password') ?: 'password';

        if (app()->isProduction() && $password === 'password') {
            throw new RuntimeException('Set SEED_PASSWORD in .env to a strong value before seeding in production.');
        }

        $accounts = [
            ['Administrator', 'admin@cargotrace.test', User::ROLE_ADMIN],
            ['Logistics Operator', 'operator@cargotrace.test', User::ROLE_OPERATOR],
            ['Customer', 'customer@cargotrace.test', User::ROLE_CUSTOMER],
        ];

        foreach ($accounts as [$name, $email, $role]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->forceFill([
                'name'     => $name,
                'password' => $password,
                'role'     => $role,
            ])->save();
        }
    }
}
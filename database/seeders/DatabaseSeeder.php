<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Features;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(PermissionSeeder::class);
        $this->call(SettingsSeeder::class);

        $this->seedDemoUsers();

        $this->call(MasterDataSeeder::class);

        Features::setDisabled(['master-data']);
    }

    /**
     * Satu user demo per peran supaya tiap peran bisa langsung dicoba login selama pengembangan.
     * Password sama untuk semua: "password". Jangan jalankan seeder ini di production.
     */
    protected function seedDemoUsers(): void
    {
        $demoUsers = [
            ['name' => 'Superadmin Demo', 'email' => 'superadmin@example.test', 'role' => 'superadmin'],
            ['name' => 'Admin Demo', 'email' => 'admin@example.test', 'role' => 'admin'],
            ['name' => 'Staff Demo', 'email' => 'staff@example.test', 'role' => 'staff'],
            ['name' => 'Owner Demo', 'email' => 'owner@example.test', 'role' => 'owner'],
        ];

        foreach ($demoUsers as $demoUser) {
            User::firstOrCreate(
                ['email' => $demoUser['email']],
                [
                    'name' => $demoUser['name'],
                    'username' => Str::before($demoUser['email'], '@'),
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            )->assignRole($demoUser['role']);
        }
    }
}

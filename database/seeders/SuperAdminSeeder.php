<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class SuperAdminSeeder extends Seeder
{
    /**
     * Создание роли супер админ, назначение ей всех пермишшинов и добавление роли юзеру с именем admin
     */
    public function run(): void
    {
        // Создаём роль Super Admin
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);

        // Назначаем все разрешения роли Super Admin
        $permissions = Permission::all();
        $superAdminRole->syncPermissions($permissions);

        // Создаём или ищем пользователя admin
        $adminUser = User::firstOrCreate(
            ['email' => 'mixa.narg@gmail.com'], // Уникальный идентификатор
            [
                'name' => 'admin',
                'email_verified_at' => now(),
                'password' => Hash::make('acc30697'), // Зашифрованный пароль
                'remember_token' => \Illuminate\Support\Str::random(10),
            ]
        );

        // Назначаем роль Super Admin пользователю admin
        $adminUser->assignRole($superAdminRole);

        $this->command->info('Пользователь "admin" создан (или уже существует) и назначена роль "Super Admin".');
    }
}

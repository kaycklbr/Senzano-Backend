<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;

class UserStorySeeder extends BaseSeeder
{
    /**
     * Credentials
     */
    const ADMIN_CREDENTIALS = [
        'email' => 'admin@admin.com',
        'password' => 'admin123',
    ];

    public function runFake()
    {
        // Grab all roles for reference
        $roles = Role::all();

        // Create an admin user
        \App\Models\User::factory()->create([
            'name'         => 'Admin',
            'email'        => static::ADMIN_CREDENTIALS['email'],
            'password'     => bcrypt(static::ADMIN_CREDENTIALS['password']),
            'primary_role' => $roles->where('name', 'admin')->first()->role_id,
        ]);

        // Create regular user
        // \App\Models\User::factory()->create([
        //     'name'         => 'Bob',
        //     'email'        => 'bob@bob.com',
        //     'primary_role' => $roles->where('name', 'regular')->first()->role_id,
        // ]);

        // Get some random roles to assign to users
        $fakeRolesToAssignCount = 3;
        $fakeRolesToAssign = RoleTableSeeder::getRandomRoles($fakeRolesToAssignCount);

        // Assign fake roles to users
        for ($i = 0; $i < 5; ++$i) {
            $user = \App\Models\User::factory()->create([
                'primary_role' => $roles->random()->role_id,
            ]);

            for ($j = 0; $j < count($fakeRolesToAssign); ++$j) {
                $user->roles()->save($fakeRolesToAssign->shift());
            }
        }
    }
}

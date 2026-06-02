<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Branch;
use Illuminate\Support\Facades\Hash;

class UsersTableSeeder extends Seeder
{
    public function run()
    {
        // Get admin role and HQ branch
        $adminRole = Role::where('name', 'admin')->first();
        $headOffice = Branch::where('code', 'HQ')->first();
        
        // Create admin user
        User::create([
            'name' => 'Administrator',
            'email' => 'admin@pos.com',
            'password' => Hash::make('password123'),
            'role_id' => $adminRole->id,
            'branch_id' => $headOffice->id,
            'is_active' => true,
        ]);
        
        $this->command->info('Admin user created: admin@pos.com / password123');
    }
}

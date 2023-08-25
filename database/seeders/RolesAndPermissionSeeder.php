<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $roles = array(
            array('name' => 'admin', 'guard_name' => 'web'),
            array('name' => 'member', 'guard_name' => 'web')
        );

        foreach ($roles as $role)
        {
            if (!Role::where('name',$role['name'])->exists()) DB::table('roles')->insert($role);
        }

        $permissions = array(

            /**Users */
            array('name' => 'show_all_users', 'display_name' => 'Show All Users', 'group_name' => 'Users', 'guard_name' => 'web'),
            array('name' => 'show_user', 'display_name' => 'Show User', 'group_name' => 'Users', 'guard_name' => 'web'),
            array('name' => 'create_user', 'display_name' => 'Create User', 'group_name' => 'Users', 'guard_name' => 'web'),
            array('name' => 'edit_user', 'display_name' => 'Edit User', 'group_name' => 'Users', 'guard_name' => 'web'),
            array('name' => 'delete_user', 'display_name' => 'Delete User', 'group_name' => 'Users', 'guard_name' => 'web'),

            /** Stables */
            array('name' => 'show_all_stables', 'display_name' => 'Show All Stables', 'group_name' => 'Stables', 'guard_name' => 'web'),
            array('name' => 'show_stable', 'display_name' => 'Show Stable', 'group_name' => 'Stables', 'guard_name' => 'web'),
            array('name' => 'create_stable', 'display_name' => 'Create Stable', 'group_name' => 'Stables', 'guard_name' => 'web'),
            array('name' => 'edit_stable', 'display_name' => 'Edit Stable', 'group_name' => 'Stables', 'guard_name' => 'web'),
            array('name' => 'delete_stable', 'display_name' => 'Delete Stable', 'group_name' => 'Stables', 'guard_name' => 'web'),            
        );

        foreach ($permissions as $permission)
        {
            if (!Permission::where('name',$permission['name'])->exists()) DB::table('permissions')->insert($permission);
        }
        
        $role = Role::findByName('admin', 'web');

        foreach ($permissions as $permission)
        {
            $role->givePermissionTo($permission['name']);
        }

        $user = User::where('email', 'azad-kh@outlook.com')->first();
        
        if(isset($user))
        {
            $user->assignRole('admin');
            $user->assignRole('member');   
        }
        else
        {
            User::create([
                'first_name' => 'Azad',
                'last_name' => 'Alketaan',
                'username' => 'Azad-KH',
                'email' => 'azad-kh@outlook.com',
                'password' => bcrypt(12345678)
            ]);
        }
    }
}

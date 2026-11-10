<?php

declare(strict_types=1);

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Auth\Enums\UserRole;

class RegisterUser
{
    /**
     * @param  array{name: string, email: string, password: string, role: string}  $data
     */
    public function execute(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'password' => $data['password'],
            'role' => UserRole::from($data['role']),
        ]);
    }
}

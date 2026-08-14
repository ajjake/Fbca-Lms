<?php

namespace App\Application;

use App\Core\Session;
use App\Infrastructure\Repositories\UserRepository;

final class AuthService
{
    private UserRepository $users;

    public function __construct()
    {
        $this->users = new UserRepository();
    }

    public function authenticate(string $username, string $password): ?array
    {
        $user = $this->users->findByUsername($username);

        if ($user === null) {
            return null;
        }

        if (!password_verify($password, $user->password)) {
            return null;
        }

        return [
            'id' => $user->id,
            'username' => $user->username,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'level' => $user->level,
            'avatar' => $user->avatar,
        ];
    }

    public function login(string $username, string $password): bool
    {
        $user = $this->authenticate($username, $password);

        if ($user === null) {
            return false;
        }

        Session::setUser($user);
        return true;
    }
}

<?php

namespace App\Domain\Entities;

final class User
{
    public int $id;
    public string $username;
    public string $password;
    public string $name;
    public string $email;
    public string $role;
    public int $level;
    public ?string $avatar;

    public function __construct(array $data)
    {
        $this->id = (int) $data['id'];
        $this->username = $data['username'];
        $this->password = $data['password'];
        $this->name = $data['name'];
        $this->email = $data['email'];
        $this->role = $data['role'];
        $this->level = (int) ($data['level'] ?? 1);
        $this->avatar = $data['avatar'] ?? null;
    }

    public function getDisplayName(): string
    {
        $name = trim((string) $this->name);
        return $name !== '' ? $name : $this->username;
    }
}

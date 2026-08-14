<?php

namespace App\Infrastructure\Repositories;

use App\Core\Database;
use App\Domain\Entities\User;

final class UserRepository
{
    public function findByUsername(string $username): ?User
    {
        $conn = Database::getConnection();
        $stmt = $conn->prepare('SELECT id, username, password, name, email, role, level, avatar FROM users WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();

        return $data ? new User($data) : null;
    }

    public function findById(int $id): ?User
    {
        $conn = Database::getConnection();
        $stmt = $conn->prepare('SELECT id, username, password, name, email, role, level, avatar FROM users WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();

        return $data ? new User($data) : null;
    }
}

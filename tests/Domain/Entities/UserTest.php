<?php

use PHPUnit\Framework\TestCase;
use App\Domain\Entities\User;

final class UserTest extends TestCase
{
    public function testGetDisplayNameUsesNameWhenPresent()
    {
        $data = [
            'id' => 1,
            'username' => 'jdoe',
            'password' => 'secret',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => 'student',
            'level' => 2
        ];

        $user = new User($data);
        $this->assertEquals('John Doe', $user->getDisplayName());
    }

    public function testGetDisplayNameFallsBackToUsername()
    {
        $data = [
            'id' => 2,
            'username' => 'asmith',
            'password' => 'x',
            'name' => '',
            'email' => 'a@example.com',
            'role' => 'teacher'
        ];

        $user = new User($data);
        $this->assertEquals('asmith', $user->getDisplayName());
    }
}

<?php

class AdminModel extends Model
{
    protected static string $table = 'admins';

    public static function login(string $username, string $password): ?array
    {
        $admin = self::find(['username' => $username]);
        if (!$admin || !Security::verifyPassword($password, $admin['password'])) {
            return null;
        }
        unset($admin['password']);
        return $admin;
    }

    public static function changePassword(int $id, string $newPassword): int
    {
        return self::updateById(['password' => Security::hashPassword($newPassword)], $id);
    }

    public static function verifyCurrentPassword(int $id, string $password): bool
    {
        $admin = self::findById($id);
        if (!$admin || empty($admin['password'])) return false;
        return Security::verifyPassword($password, $admin['password']);
    }
}

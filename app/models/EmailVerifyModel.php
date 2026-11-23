<?php

class EmailVerifyModel extends Model
{
    protected static string $table = 'email_verify';

    public static function createCode(string $email, string $code, int $minutes = 30): int
    {
        return self::create([
            'email' => $email,
            'code' => $code,
            'expire' => date('Y-m-d H:i:s', time() + $minutes * 60),
            'used' => 0,
        ]);
    }

    public static function verify(string $email, string $code): bool
    {
        $throttle = new LoginThrottleService('emailverify', md5(strtolower($email)));
        if ($throttle->isLocked()) {
            return false;
        }
        $row = self::find(['email' => $email, 'code' => $code, 'used' => 0]);
        if (!$row || strtotime($row['expire']) < time()) {
            $throttle->recordAttempt(false);
            return false;
        }
        self::update(['used' => 1], ['id' => $row['id']]);
        $throttle->recordAttempt(true);
        return true;
    }
}

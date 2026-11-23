<?php

class UserService
{

    public function login(string $username, string $password): array
    {
        $user = UserModel::find(['username' => $username]);
        if (!$user || !Security::verifyPassword($password, $user['password'])) {
            return ['ok' => false, 'msg' => Language::get('login_failed', '用户名或密码错误')];
        }
        if (isset($user['status']) && (int)$user['status'] === 0) {
            return ['ok' => false, 'msg' => Language::get('account_disabled', '账号已被禁用')];
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];

        session_regenerate_id(true);
        return ['ok' => true, 'user' => $user];
    }

    public function register(string $username, string $password, string $email = ''): array
    {
        if (mb_strlen($username) < 3) return ['ok' => false, 'msg' => Language::get('username_length_min', '用户名至少3个字符')];
        if (mb_strlen($username) > 20) return ['ok' => false, 'msg' => Language::get('username_length_max', '用户名不能超过20个字符')];
        if (!preg_match('/^[A-Za-z0-9_\x{4e00}-\x{9fff}]+$/u', $username)) {
            return ['ok' => false, 'msg' => Language::get('username_chars_invalid', '用户名只能含字母、数字、下划线、中文')];
        }
        if (strlen($password) < 6) return ['ok' => false, 'msg' => Language::get('password_min6', '密码至少6位')];
        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'msg' => Language::get('email_format_error', '邮箱格式不正确')];
        }
        if (UserModel::find(['username' => $username])) {
            return ['ok' => false, 'msg' => Language::get('username_exists', '用户名已存在')];
        }
        if ($email && UserModel::find(['email' => $email])) {
            return ['ok' => false, 'msg' => Language::get('email_already_registered', '邮箱已被注册')];
        }

        try {
            $id = UserModel::create([
                'username' => $username,
                'password' => Security::hashPassword($password),
                'email'    => $email ?: null,
                'gold'     => 0,
                'vip_level' => 0,
            ]);
        } catch (\Throwable $e) {

            if ($email && UserModel::find(['email' => $email])) {
                return ['ok' => false, 'msg' => Language::get('email_already_registered', '邮箱已被注册')];
            }
            if (UserModel::find(['username' => $username])) {
                return ['ok' => false, 'msg' => Language::get('username_exists', '用户名已存在')];
            }
            error_log('[register] ' . $e->getMessage());
            return ['ok' => false, 'msg' => Language::get('register_failed_retry', '注册失败，请稍后重试')];
        }
        $_SESSION['user_id'] = $id;
        $_SESSION['username'] = $username;

        session_regenerate_id(true);
        return ['ok' => true, 'id' => $id];
    }

    public function getSignReward(int $userId): array
    {
        $user = UserModel::findById($userId);
        if (!$user) return ['total' => 0, 'continuous_days' => 0];

        $isConsecutive = ($user['last_sign'] === date('Y-m-d', strtotime('-1 day')));
        $continuousDays = $isConsecutive ? (int)$user['sign_days'] + 1 : 1;

        $base = (int)SettingModel::get('sign_base_gold', 10);
        if ($continuousDays === 1) {
            return ['continuous_days' => 1, 'base_gold' => $base, 'bonus' => 0, 'total' => $base];
        }

        $percent = (int)SettingModel::get('sign_continue_percent', 5);
        $minBonus = (int)SettingModel::get('sign_min_continue_bonus', 1);
        $maxBonus = (int)SettingModel::get('sign_max_continue_bonus', 100);

        $lastLog = GoldLogModel::getLastByType($userId, 'sign');
        $prevTotal = $lastLog ? (int)$lastLog['gold_change'] : $base;
        $pctBonus = (int)round($prevTotal * $percent / 100);
        $pctBonus = max($minBonus, min($maxBonus, $pctBonus));

        $incremental = $continuousDays - 1;
        $total = $base + $pctBonus + $incremental;
        return [
            'continuous_days' => $continuousDays,
            'base_gold' => $base,
            'bonus' => $pctBonus + $incremental,
            'total' => $total,
        ];
    }

    public function sign(int $userId): array
    {
        $today = date('Y-m-d');
        $db = UserModel::db();
        $db->beginTransaction();
        try {

            $locked = $db->query(
                "SELECT id, last_sign FROM " . UserModel::tableName() . " WHERE id = ? FOR UPDATE",
                [$userId]
            )->fetch();
            if (!$locked) {
                $db->rollBack();
                return ['ok' => false, 'msg' => Language::get('user_not_exists', '用户不存在')];
            }
            if ((string)$locked['last_sign'] === $today
                || GoldLogModel::existsToday($userId, 'sign', $today)) {
                $db->rollBack();
                return ['ok' => false, 'msg' => Language::get('signed_already_today', '今天已经签到过了')];
            }
            $reward = $this->getSignReward($userId);
            UserModel::update(
                ['sign_days' => $reward['continuous_days'], 'last_sign' => $today],
                ['id' => $userId]
            );
            if (!CommonService::addGold($userId, $reward['total'], 'sign', Language::get('gold_log_sign', '每日签到奖励'))) {
                $db->rollBack();
                return ['ok' => false, 'msg' => Language::get('sign_failed_retry', '签到失败，请重试')];
            }
            $db->commit();
            return ['ok' => true, 'gold' => $reward['total'], 'days' => $reward['continuous_days']];
        } catch (\Throwable $e) {
            $db->rollBack();
            error_log('[sign] ' . $e->getMessage());
            return ['ok' => false, 'msg' => Language::get('sign_failed_retry', '签到失败，请重试')];
        }
    }

    public function exchangeVip(int $userId, int $months): array
    {
        $months = max(1, $months);
        $goldPerMonth = (int)SettingModel::get('vip_gold_per_month', 3000);
        if ($goldPerMonth <= 0) return ['ok' => false, 'msg' => Language::get('exchange_rate_config_error', '兑换比例配置错误')];

        $need = $months * $goldPerMonth;
        $user = UserModel::findById($userId);
        if (!$user) return ['ok' => false, 'msg' => Language::get('user_not_exists', '用户不存在')];
        if ((int)$user['gold'] < $need) return ['ok' => false, 'msg' => Language::get('gold_insufficient', '金币不足')];

        if (!CommonService::addGold($userId, -$need, 'exchange_vip', Language::get('gold_log_vip_exchange', '金币兑换 %d 个月VIP', $months))) {
            return ['ok' => false, 'msg' => Language::get('gold_insufficient', '金币不足')];
        }

        if (!empty($user['vip_expire']) && strtotime($user['vip_expire']) > time()) {
            $newExpire = date('Y-m-d H:i:s', strtotime($user['vip_expire'] . " + {$months} months"));
        } else {
            $newExpire = date('Y-m-d H:i:s', strtotime("+ {$months} months"));
        }
        UserModel::update(['vip_level' => 1, 'vip_expire' => $newExpire], ['id' => $userId]);
        return ['ok' => true, 'expire' => $newExpire];
    }

    public function toggleBookshelf(int $userId, int $novelId): array
    {
        if (BookshelfModel::exists($userId, $novelId)) {
            BookshelfModel::delete(['user_id' => $userId, 'novel_id' => $novelId]);
            NovelModel::changeFavorites($novelId, -1);
            return ['ok' => true, 'action' => 'removed'];
        }
        BookshelfModel::add($userId, $novelId);
        NovelModel::changeFavorites($novelId, 1);
        return ['ok' => true, 'action' => 'added'];
    }

    public function addToBookshelf(int $userId, int $novelId): array
    {
        if (BookshelfModel::exists($userId, $novelId)) {
            return ['ok' => true, 'action' => 'exists'];
        }
        BookshelfModel::add($userId, $novelId);
        NovelModel::changeFavorites($novelId, 1);
        return ['ok' => true, 'action' => 'added'];
    }

    public function updateProfile(int $userId, array $data): array
    {
        $allowed = [];
        if (!empty($data['email'])) {
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'msg' => Language::get('email_format_error', '邮箱格式不正确')];
            }
            $allowed['email'] = $data['email'];
        }
        if (isset($data['avatar'])) $allowed['avatar'] = excerpt(trim((string)$data['avatar']), 255, '');
        if (isset($data['signature'])) $allowed['signature'] = excerpt(trim((string)$data['signature']), 255, '');
        if (isset($data['phone'])) {
            $phone = trim((string)$data['phone']);
            if ($phone !== '' && !preg_match('/^[\d+\-\s]{3,20}$/', $phone)) {
                return ['ok' => false, 'msg' => Language::get('contact_format_error', '联系方式格式不正确')];
            }
            $allowed['phone'] = $phone;
        }
        if (isset($data['gender'])) $allowed['gender'] = (int)$data['gender'];
        if (isset($data['wechat_account'])) $allowed['wechat_account'] = excerpt(trim((string)$data['wechat_account']), 100, '');
        if ($allowed) UserModel::update($allowed, ['id' => $userId]);
        return ['ok' => true, 'msg' => Language::get('profile_saved', '资料已保存')];
    }

    public function changePassword(int $userId, string $old, string $new): array
    {
        if (strlen($new) < 6) return ['ok' => false, 'msg' => Language::get('password_too_short', '新密码至少6位')];
        $user = UserModel::findById($userId);
        if (!Security::verifyPassword($old, $user['password'])) {
            return ['ok' => false, 'msg' => Language::get('old_password_error', '原密码错误')];
        }
        UserModel::changePassword($userId, $new);
        return ['ok' => true, 'msg' => Language::get('password_changed_ok', '密码修改成功')];
    }

    public static function isVipActive(array $user): bool
    {
        if ((int)($user['vip_level'] ?? 0) < 1) return false;
        if (!empty($user['vip_expire']) && strtotime($user['vip_expire']) < time()) return false;
        return true;
    }
}

<?php

class AuthorService
{

    public function apply(int $userId, array $data): array
    {
        $existing = AuthorApplicationModel::getByUser($userId);
        if ($existing) {
            $st = (int)($existing['status'] ?? 0);
            if ($st === 0) {
                return ['ok' => false, 'msg' => Language::get('msg_application_pending', '您的申请正在审核中，请耐心等待')];
            }
            if ($st === 1) {
                return ['ok' => false, 'msg' => Language::get('msg_already_author', '您已是作者，无需重复申请')];
            }

            AuthorApplicationModel::deleteByUser($userId);
        }
        foreach (['real_name', 'pen_name', 'id_card', 'phone'] as $required) {
            if (empty($data[$required])) {
                return ['ok' => false, 'msg' => Language::get('required_fields_complete', '请完整填写必填项')];
            }
        }
        if (!preg_match('/^1[3-9]\d{9}$/', $data['phone'])) {
            return ['ok' => false, 'msg' => Language::get('phone_format_error', '手机号格式不正确')];
        }

        $realName = trim((string)$data['real_name']);
        $penName  = trim((string)$data['pen_name']);
        $idCard   = trim((string)$data['id_card']);
        $qq       = trim((string)($data['qq'] ?? ''));
        $intro    = trim((string)($data['introduction'] ?? ''));
        if (mb_strlen($realName) < 2 || mb_strlen($realName) > 50) {
            return ['ok' => false, 'msg' => Language::get('realname_length_range', '姓名长度需在 2-50 个字符之间')];
        }
        if (mb_strlen($penName) < 2 || mb_strlen($penName) > 50) {
            return ['ok' => false, 'msg' => Language::get('penname_length_range', '笔名长度需在 2-50 个字符之间')];
        }
        if (!preg_match('/^[0-9A-Za-zXx]{6,30}$/', $idCard)) {
            return ['ok' => false, 'msg' => Language::get('idcard_format_error', '身份证号格式不正确')];
        }
        if ($qq !== '' && !preg_match('/^\d{5,12}$/', $qq)) {
            return ['ok' => false, 'msg' => Language::get('qq_format_error', 'QQ 号格式不正确')];
        }
        if (mb_strlen($intro) > 500) {
            return ['ok' => false, 'msg' => Language::get('intro_max500', '个人简介不能超过 500 字')];
        }

        $frontPath = $this->saveIdCardImage($userId, 'id_card_front');
        $backPath  = $this->saveIdCardImage($userId, 'id_card_back');
        if ($frontPath === false || $backPath === false) {
            return ['ok' => false, 'msg' => Language::get('idcard_upload_failed', '身份证图片上传失败：仅支持 JPG/PNG/GIF，且不超过 5MB')];
        }

        AuthorApplicationModel::create([
            'user_id'         => $userId,
            'real_name'       => $realName,
            'pen_name'        => $penName,
            'id_card'         => $idCard,
            'phone'           => trim((string)$data['phone']),
            'qq'              => $qq !== '' ? $qq : null,
            'id_card_front'   => $frontPath,
            'id_card_back'    => $backPath,
            'introduction'    => $intro !== '' ? $intro : null,
            'status'          => 0,
        ]);
        return ['ok' => true, 'msg' => Language::get('apply_submitted_waiting', '申请已提交，请等待审核')];
    }

    private function saveIdCardImage(int $userId, string $field): ?string
    {
        if (empty($_FILES[$field]['tmp_name']) || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
            return null;
        }
        $file = $_FILES[$field];
        if ($file['size'] > 5 * 1024 * 1024) return false;
        $info = @getimagesize($file['tmp_name']);
        if (!$info) return false;
        [$w, $h, $type] = $info;
        if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF])) return false;

        $ext = $type === IMAGETYPE_PNG ? 'png' : ($type === IMAGETYPE_GIF ? 'gif' : 'jpg');
        $dir = ROOT_PATH . 'data' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'idcards';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($file['tmp_name'], $path)) return false;
        return '/data/uploads/idcards/' . $name;
    }

    public function auditApplication(int $id, int $status, ?string $reason = null): array
    {
        $app = AuthorApplicationModel::findById($id);
        if (!$app) return array('ok' => false, 'msg' => t_admin('adm_auth_app_not_found', '申请不存在'));
        if ((int)$app['status'] !== 0) return array('ok' => false, 'msg' => t_admin('adm_auth_app_duplicate', '该申请已处理，不能重复审核'));

        if ($status === 2) {
            $reason = trim((string)$reason);
            if ($reason === '') $reason = t_admin('adm_auth_app_reject_default', '资料不完整，请补充后重新提交');
            AuthorApplicationModel::audit($id, 2, $reason);
            return array('ok' => true, 'msg' => t_admin('adm_auth_app_rejected', '已拒绝该申请'));
        }

        $penName = $app['pen_name'];
        $occupied = UserModel::find(array('pen_name' => $penName));
        if ($occupied && (int)$occupied['id'] !== (int)$app['user_id']) {
            return array('ok' => false, 'msg' => t_admin('adm_auth_app_pen_taken', '笔名已被其他用户占用，无法通过'));
        }

        $db = AuthorApplicationModel::db();
        $db->beginTransaction();
        try {
            $affected = AuthorApplicationModel::update(array('status' => 1), array('id' => $id, 'status' => 0));
            if ($affected < 1) { $db->rollBack(); return array('ok' => false, 'msg' => t_admin('adm_auth_app_processed', '该申请已处理')); }
            UserModel::update(
                array('is_author' => 1, 'pen_name' => $penName, 'real_name' => trim((string)$app['real_name'])),
                array('id' => (int)$app['user_id'])
            );
            $db->commit();
            return array('ok' => true, 'msg' => t_admin('adm_auth_app_passed', '已通过申请，用户已获得作者身份'));
        } catch (\Throwable $e) {
            $db->rollBack();
            return array('ok' => false, 'msg' => t_admin('adm_auth_app_fail', '操作失败，请稍后重试'));
        }
    }

    public function getWorks(int $userId): array
    {
        return NovelModel::getByAuthor($userId, 1, 100)['list'];
    }

    public function saveCover(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
        if (($file['size'] ?? 0) > 4 * 1024 * 1024) return null;
        $info = @getimagesize($file['tmp_name']);
        if (!$info) return null;
        [$srcW, $srcH, $type] = $info;
        if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF])) return null;

        if ($type === IMAGETYPE_JPEG) $src = @imagecreatefromjpeg($file['tmp_name']);
        elseif ($type === IMAGETYPE_PNG) $src = @imagecreatefrompng($file['tmp_name']);
        else $src = @imagecreatefromgif($file['tmp_name']);
        if (!$src) return null;

        $maxW = 600;
        if ($srcW > $maxW) {
            $nw = $maxW;
            $nh = (int)($srcH * $maxW / $srcW);
        } else {
            $nw = $srcW; $nh = $srcH;
        }
        $dst = imagecreatetruecolor($nw, $nh);
        if ($type === IMAGETYPE_PNG) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $srcW, $srcH);

        $dir = ROOT_PATH . '/uploads/covers/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $name = bin2hex(random_bytes(16)) . '.jpg';
        $path = $dir . $name;
        imagejpeg($dst, $path, 85);
        return 'uploads/covers/' . $name;
    }

    public function createNovel(int $userId, array $data): array
    {
        if (empty($data['title']) || empty($data['category_id'])) {
            return ['ok' => false, 'msg' => Language::get('fill_title_category', '请填写书名并选择分类')];
        }
        $title = trim($data['title']);
        $user = UserModel::findById($userId);
        $penName = trim($user['pen_name'] ?? ($user['username'] ?? ''));

        $dup = NovelModel::find(array('title' => $title, 'author' => $penName), 'id');
        if ($dup) {
            return ['ok' => false, 'msg' => Language::get('duplicate_title_fmt', '您已发布过《%s》，不允许重复发布', $title)];
        }
        $id = NovelModel::create([
            'title'       => $title,
            'author'      => $penName,
            'user_id'     => $userId,
            'category_id' => (int)$data['category_id'],
            'cover'       => $data['cover'] ?? null,
            'description' => $data['description'] ?? '',
            'is_vip'      => 0,
            'vip_audit_status' => -1,
            'status'      => 0,
            'views'       => 0,
            'favorites'   => 0,
            'total_words' => 0,
        ]);
        return ['ok' => true, 'id' => $id];
    }

    public function checkOwnership(int $novelId, int $userId): bool
    {
        $novel = NovelModel::findById($novelId, 'user_id');
        return $novel && (int)$novel['user_id'] === $userId;
    }

    public function createVolume(int $novelId, string $title, int $sort = 0): int
    {
        return VolumeModel::create(['novel_id' => $novelId, 'title' => $title, 'sort' => $sort]);
    }

    public function createChapter(int $novelId, array $data): array
    {
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') return ['ok' => false, 'msg' => Language::get('msg_chapter_title_required', '章节标题不能为空')];
        if (mb_strlen($title, 'UTF-8') > 100) return ['ok' => false, 'msg' => Language::get('msg_chapter_title_too_long', '章节标题不能超过100字')];
        $content = CommonService::cleanPlainText((string)($data['content'] ?? ''));
        if ($content === '') return ['ok' => false, 'msg' => Language::get('msg_please_enter_content', '请输入章节内容')];
        if (mb_strlen($content, 'UTF-8') > 50000) return ['ok' => false, 'msg' => Language::get('msg_chapter_content_too_long', '章节内容超出长度限制')];
        $wordCount = CommonService::smartWordCount($content);
        $novel = NovelModel::findById($novelId, 'is_vip');
        $signed = $novel && (int)$novel['is_vip'] === 1;

        $volumeId = (int)($data['volume_id'] ?? 0);
        $nextSort = ChapterModel::getMaxSortInVolume($volumeId) + 1;
        $chapterId = ChapterModel::create([
            'novel_id'   => $novelId,
            'volume_id'  => $volumeId,
            'title'      => $title,
            'word_count' => $wordCount,
            'sort'       => $nextSort,
            'is_vip'     => $signed ? (int)($data['is_vip'] ?? 0) : 0,
            'price'      => $signed ? (int)($data['price'] ?? 0) : 0,
        ]);
        $filePath = ChapterModel::saveContent($novelId, $chapterId, $content);
        ChapterModel::update(['file_path' => $filePath], ['id' => $chapterId]);
        NovelModel::refreshStats($novelId);
        return ['ok' => true, 'id' => $chapterId, 'chapter_id' => $chapterId];
    }

    public function updateChapter(int $chapterId, array $data): array
    {
        $chapter = ChapterModel::findById($chapterId);
        if (!$chapter) return ['ok' => false, 'msg' => Language::get('msg_chapter_not_exist', '章节不存在')];

        $title = trim((string)($data['title'] ?? $chapter['title']));
        if ($title === '') return ['ok' => false, 'msg' => Language::get('msg_chapter_title_required', '章节标题不能为空')];
        if (mb_strlen($title, 'UTF-8') > 100) return ['ok' => false, 'msg' => Language::get('msg_chapter_title_too_long', '章节标题不能超过100字')];
        $update = ['title' => $title];
        $novel = NovelModel::findById((int)$chapter['novel_id'], 'is_vip');
        $signed = $novel && (int)$novel['is_vip'] === 1;
        if ($signed) {

            $update['is_vip'] = isset($data['is_vip']) ? 1 : 0;
            if (isset($data['price'])) $update['price'] = (int)$data['price'];
        }
        if (isset($data['volume_id'])) $update['volume_id'] = (int)$data['volume_id'];

        if (array_key_exists('content', $data)) {
            $content = CommonService::cleanPlainText((string)$data['content']);
            if ($content === '') return ['ok' => false, 'msg' => Language::get('msg_please_enter_content', '请输入章节内容')];
            if (mb_strlen($content, 'UTF-8') > 50000) return ['ok' => false, 'msg' => Language::get('msg_chapter_content_too_long', '章节内容超出长度限制')];
            $update['word_count'] = CommonService::smartWordCount($content);

            ChapterModel::deleteChapterFile($chapter);
            $update['file_path'] = ChapterModel::saveContent((int)$chapter['novel_id'], $chapterId, $content);
        }
        ChapterModel::update($update, ['id' => $chapterId]);
        NovelModel::refreshStats((int)$chapter['novel_id']);
        return ['ok' => true, 'msg' => Language::get('chapter_saved', '章节已保存')];
    }

    public function getIncomeStats(int $userId): array
    {
        $stats = AuthorIncomeModel::stats($userId);
        $user = UserModel::findById($userId, 'available_income');
        $stats['available'] = $user ? (float)$user['available_income'] : 0.0;
        return $stats;
    }

    public function getIncomeByNovel(int $userId): array
    {
        return AuthorIncomeModel::byNovel($userId);
    }

    public function saveQrcode(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
        if (($file['size'] ?? 0) > 2 * 1024 * 1024) return null;
        $info = @getimagesize($file['tmp_name']);
        if (!$info) return null;
        [$srcW, $srcH, $type] = $info;
        if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF])) return null;

        if ($type === IMAGETYPE_JPEG) $src = @imagecreatefromjpeg($file['tmp_name']);
        elseif ($type === IMAGETYPE_PNG) $src = @imagecreatefrompng($file['tmp_name']);
        else $src = @imagecreatefromgif($file['tmp_name']);
        if (!$src) return null;

        $maxW = 400;
        if ($srcW > $maxW) { $nw = $maxW; $nh = (int)($srcH * $maxW / $srcW); }
        else { $nw = $srcW; $nh = $srcH; }
        $dst = imagecreatetruecolor($nw, $nh);
        if ($type === IMAGETYPE_PNG) { imagealphablending($dst, false); imagesavealpha($dst, true); }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $srcW, $srcH);

        $dir = ROOT_PATH . '/data/uploads/qrcodes/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $name = bin2hex(random_bytes(16)) . '.jpg';
        imagejpeg($dst, $dir . $name, 85);
        return 'data/uploads/qrcodes/' . $name;
    }

    public function generatePayoutQr(string $content, int $userId): ?string
    {
        $content = trim($content);
        if ($userId <= 0 || $content === '' || mb_strlen($content, 'UTF-8') > 500) return null;

        $dir = ROOT_PATH . '/data/uploads/qrcodes/';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return null;

        foreach (glob($dir . 'payout_qr_' . $userId . '.*') ?: [] as $old) {
            if (is_file($old)) @unlink($old);
        }

        $target = $dir . 'payout_qr_' . $userId . '.png';
        $ok = qr_code_save($content, $target, ['level' => 'M', 'size' => 4, 'margin' => 4]);
        if (!$ok) return null;

        return 'data/uploads/qrcodes/payout_qr_' . $userId . '.png';
    }

    public function saveWithdrawInfo(int $userId, array $data): array
    {
        $user = UserModel::findById($userId, 'real_name');
        if (!$user || empty($user['real_name'])) {
            return ['ok' => false, 'msg' => Language::get('withdraw_info_incomplete', '请先完善收款信息（真实姓名、结算方式、账号）')];
        }
        if (empty($data['account_type']) || empty($data['account'])) {
            return ['ok' => false, 'msg' => Language::get('fill_payment_info', '请完整填写收款信息')];
        }
        $update = [];

        $oldQr = (string)(UserModel::findById($userId, 'withdraw_qrcode')['withdraw_qrcode'] ?? '');
        if (!empty($data['existing_qrcode'])) {
            $qr = str_replace('\\', '/', (string)$data['existing_qrcode']);
            if (preg_match('#^data/uploads/qrcodes/(?:payout_qr_\d+|[0-9a-f]{32})\.(png|jpg|gif)$#i', $qr)) {
                $update['withdraw_qrcode'] = $qr;

                if ($oldQr !== '' && $oldQr !== $qr) {
                    $oldAbs = str_replace('\\', '/', ROOT_PATH . '/' . ltrim($oldQr, '/'));
                    if (strpos($oldAbs, str_replace('\\', '/', ROOT_PATH . '/data/uploads/qrcodes/')) === 0 && is_file($oldAbs)) {
                        @unlink($oldAbs);
                    }
                }
            }
        }
        if ($data['account_type'] === 'alipay') {
            $update['alipay_account'] = trim($data['account']);
        } elseif ($data['account_type'] === 'wechat') {
            $update['wechat_account'] = trim($data['account']);
        } else {
            return ['ok' => false, 'msg' => Language::get('account_type_invalid', '账户类型无效')];
        }
        UserModel::update($update, ['id' => $userId]);
        return ['ok' => true, 'msg' => Language::get('payment_info_saved', '收款信息已保存')];
    }

    public function getWithdrawInfo(int $userId): array
    {
        $u = UserModel::findById($userId, 'real_name, alipay_account, wechat_account, withdraw_qrcode');
        if (!$u) return ['real_name' => '', 'account_type' => '', 'account' => '', 'alipay_account' => '', 'wechat_account' => '', 'withdraw_qrcode' => ''];
        $realName = trim((string)($u['real_name'] ?? ''));
        if ($realName === '') $realName = AuthorApplicationModel::getApprovedRealName($userId);
        $alipay = (string)($u['alipay_account'] ?? '');
        $wechat = (string)($u['wechat_account'] ?? '');
        if (!empty($alipay)) {
            $type = 'alipay'; $account = $alipay;
        } elseif (!empty($wechat)) {
            $type = 'wechat'; $account = $wechat;
        } else {
            $type = ''; $account = '';
        }
        return ['real_name' => $realName, 'account_type' => $type,
                'account' => $account, 'alipay_account' => $alipay, 'wechat_account' => $wechat,
                'withdraw_qrcode' => $u['withdraw_qrcode'] ?? ''];
    }

    public function applyWithdraw(int $userId, float $amount): array
    {
        $user = UserModel::findById($userId, 'available_income');
        if (!$user) return ['ok' => false, 'msg' => Language::get('withdraw_user_not_exists', '用户不存在')];

        $available = (float)$user['available_income'];
        if ($amount <= 0) return ['ok' => false, 'msg' => Language::get('withdraw_amount_invalid', '提现金额必须大于0')];

        $min = (float)SettingModel::get('vip_min_withdraw_amount', 10);
        if ($amount < $min) return ['ok' => false, 'msg' => Language::get('withdraw_min_fmt', '最低提现金额为 %s', $min)];
        if ($amount > $available) return ['ok' => false, 'msg' => Language::get('withdraw_balance_insufficient', '可提现余额不足')];

        $feeRate = (float)SettingModel::get('withdraw_fee_rate', 5);
        $fee = round($amount * $feeRate / 100, 2);
        $actual = round($amount - $fee, 2);
        $info = $this->getWithdrawInfo($userId);

        $db = UserModel::db();
        $db->beginTransaction();
        try {
            $affected = $db->query(
                'UPDATE ' . tb('users') . ' SET available_income = available_income - ? WHERE id = ? AND available_income >= ?',
                [$amount, $userId, $amount]
            )->rowCount();
            if ($affected < 1) {
                $db->rollBack();
                return ['ok' => false, 'msg' => Language::get('withdraw_balance_insufficient', '可提现余额不足')];
            }
            WithdrawRequestModel::create([
                'user_id'       => $userId,
                'amount'        => $amount,
                'fee'           => $fee,
                'actual_amount' => $actual,
                'real_name'     => $info['real_name'],
                'account_type'  => $info['account_type'],
                'account'       => $info['account'],
                'qrcode'        => $info['withdraw_qrcode'],
                'status'        => 0,
            ]);
            AuthorIncomeModel::log($userId, 0, 0, -$amount, 'withdraw', 1);
            $db->commit();
            return ['ok' => true, 'msg' => Language::get('withdraw_apply_success', '提现申请已提交，等待审核')];
        } catch (\Throwable $e) {
            $db->rollBack();
            error_log('[withdraw] ' . $e->getMessage());
            return ['ok' => false, 'msg' => Language::get('withdraw_failed_retry', '提现失败，请稍后重试')];
        }
    }

    public function auditWithdraw(int $id, int $status): array
    {
        $req = WithdrawRequestModel::findById($id);
        if (!$req) return array('ok' => false, 'msg' => t_admin('adm_wd_req_not_found', '提现申请不存在'));
        if ((int)$req['status'] !== 0) return array('ok' => false, 'msg' => t_admin('adm_wd_req_duplicate', '该申请已处理，不能重复操作'));

        $db = WithdrawRequestModel::db();
        $db->beginTransaction();
        try {
            if ($status === 1) {
                $affected = WithdrawRequestModel::update(
                    array('status' => 1, 'updated_at' => date('Y-m-d H:i:s')),
                    array('id' => $id, 'status' => 0)
                );
                if ($affected < 1) { $db->rollBack(); return array('ok' => false, 'msg' => t_admin('adm_wd_req_processed', '该申请已处理')); }
                $db->commit();
                return array('ok' => true, 'msg' => t_admin('adm_wd_mark_passed', '已标记为通过，请线下打款'));
            }

            $affected = WithdrawRequestModel::update(
                array('status' => 2, 'updated_at' => date('Y-m-d H:i:s')),
                array('id' => $id, 'status' => 0)
            );
            if ($affected < 1) { $db->rollBack(); return array('ok' => false, 'msg' => t_admin('adm_wd_req_processed', '该申请已处理')); }

            UserModel::db()->query(
                'UPDATE ' . tb('users') . ' SET available_income = available_income + ? WHERE id = ?',
                [(float)$req['amount'], (int)$req['user_id']]
            );
            AuthorIncomeModel::log((int)$req['user_id'], 0, 0, (float)$req['amount'], 'adjust', 1);
            $db->commit();
            return array('ok' => true, 'msg' => t_admin('adm_wd_rejected_refunded', '已拒绝，金额已退回用户余额'));
        } catch (\Throwable $e) {
            $db->rollBack();
            error_log('[auditWithdraw] ' . $e->getMessage());
            return array('ok' => false, 'msg' => t_admin('adm_wd_fail', '操作失败，请稍后重试'));
        }
    }
}

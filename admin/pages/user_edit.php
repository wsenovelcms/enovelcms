<?php

if (!defined('ADMIN_BOOT')) exit;
$pageTitle = t_admin('adm_useredit', '编辑用户');

$userId = (int)($_GET['id'] ?? 0);
$user = UserModel::findById($userId);
if (!$user) admin_flash_redirect(t_admin('adm_user_not_found', '用户不存在'), 'users');

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCsrf()) {
        $error = t_admin('adm_csrf_failed_short', 'CSRF验证失败');
    } else {
        $gold = max(0, Security::post('gold', 0, 'int'));
        $vipLevel = Security::post('vip_level', 0, 'int') === 1 ? 1 : 0;
        $vipExpireInput = p('vip_expire', '', 'str');
        $vipExpire = $vipExpireInput ? date('Y-m-d H:i:s', strtotime($vipExpireInput)) : null;
        $penName = p('pen_name', '', 'str');
        $isAuthor = Security::post('is_author', 0, 'int') === 1 ? 1 : 0;
        $newPassword = p('new_password', '', 'str');
        $confirmPassword = p('confirm_password', '', 'str');

        if ($penName !== '') {
            $exist = UserModel::find(array('pen_name' => $penName));
            if ($exist && (int)$exist['id'] !== $userId) $error = t_admin('adm_useredit_pen_taken', '笔名已被其他用户使用');
        }
        if ($error === '' && $newPassword !== '') {
            if (strlen($newPassword) < 6) $error = t_admin('adm_useredit_pwd_short', '新密码至少6位');
            elseif ($newPassword !== $confirmPassword) $error = t_admin('adm_useredit_pwd_mismatch', '两次输入的密码不一致');
        }
        if ($error === '') {
            UserModel::update(array(
                'gold' => $gold, 'vip_level' => $vipLevel, 'vip_expire' => $vipExpire,
                'pen_name' => $penName, 'is_author' => $isAuthor,
            ), array('id' => $userId));
            if ($newPassword !== '') UserModel::changePassword($userId, $newPassword);
            PluginForm::save('user_edit', 'user', $userId);
            Hook::doAction('admin_user_edit_saved', $userId, $_POST);
            admin_flash_redirect($newPassword !== '' ? t_admin('adm_useredit_saved_pwd', '用户已更新，密码已修改') : t_admin('adm_useredit_saved', '用户已更新'), 'users');
        }
    }
}
?>
<div class="card">
    <h2><?= t_admin('adm_useredit_title', '编辑用户：%s', htmlspecialchars((string)$user['username'])) ?></h2>
    <?php if ($error): ?><div class="alert alert-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" style="max-width:600px">
        <?= Security::csrfField() ?>
        <div class="form-group"><label><?= t_admin('adm_user_col_username', '用户名') ?></label><input class="form-control" value="<?= htmlspecialchars((string)$user['username']) ?>" disabled></div>
        <div class="form-group"><label><?= t_admin('adm_user_col_email', '邮箱') ?></label><input class="form-control" value="<?= htmlspecialchars($user['email'] ?: '-') ?>" disabled></div>
        <div class="form-row-2">
            <div class="form-group"><label><?= t_admin('adm_user_col_gold', '金币') ?></label><input type="number" name="gold" class="form-control" min="0" value="<?= (int)$user['gold'] ?>"></div>
            <div class="form-group">
                <label><?= t_admin('adm_useredit_vip_level', 'VIP等级') ?></label>
                <select name="vip_level" class="form-control">
                    <option value="0" <?= $user['vip_level'] == 0 ? 'selected' : '' ?>><?= t_admin('adm_useredit_normal', '普通用户') ?></option>
                    <option value="1" <?= $user['vip_level'] == 1 ? 'selected' : '' ?>><?= t_admin('adm_useredit_vip_member', 'VIP会员') ?></option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label><?= t_admin('adm_useredit_vip_expire', 'VIP到期时间') ?></label>
            <input type="datetime-local" name="vip_expire" class="form-control"
                value="<?= $user['vip_expire'] ? date('Y-m-d\TH:i', strtotime($user['vip_expire'])) : '' ?>">
        </div>
        <div class="form-row-2">
            <div class="form-group"><label><?= t_admin('adm_useredit_pen_name', '笔名') ?></label><input type="text" name="pen_name" class="form-control" value="<?= htmlspecialchars((string)$user['pen_name']) ?>"></div>
            <div class="form-group">
                <label><?= t_admin('adm_useredit_author', '作者身份') ?></label>
                <select name="is_author" class="form-control">
                    <option value="0" <?= $user['is_author'] == 0 ? 'selected' : '' ?>><?= t_admin('adm_no', '否') ?></option>
                    <option value="1" <?= $user['is_author'] == 1 ? 'selected' : '' ?>><?= t_admin('adm_yes', '是') ?></option>
                </select>
            </div>
        </div>
        <div class="form-row-2">
            <div class="form-group"><label><?= t_admin('adm_useredit_new_pwd', '新密码（留空不改）') ?></label><input type="password" name="new_password" class="form-control" autocomplete="new-password"></div>
            <div class="form-group"><label><?= t_admin('adm_useredit_confirm_pwd', '确认新密码') ?></label><input type="password" name="confirm_password" class="form-control" autocomplete="new-password"></div>
        </div>
        <?= PluginForm::render('user_edit', 'user', $userId) ?>
        <?php Hook::doAction('admin_user_edit_extra', $user); ?>
        <button class="btn btn-primary"><i class="fas fa-save"></i> <?= t_admin('adm_save_changes', '保存修改') ?></button>
        <a href="<?= htmlspecialchars(admin_url('users')) ?>" class="btn btn-outline"><?= t_admin('adm_back', '返回') ?></a>
    </form>
</div>

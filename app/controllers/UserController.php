<?php

class UserController extends Controller
{
    public function handle(string $action = 'index'): void
    {
        View::assignArray(['currentModule' => 'user', 'currentAction' => $action]);
        $method = 'action' . ucfirst($action);
        if (method_exists($this, $method)) {
            $this->$method();
        } else {
            http_response_code(404);
            $this->view('404.tpl', ['message' => Language::get('page_not_found', '页面不存在')]);
        }
    }

    private function actionIndex(): void
    {
        $this->requireLogin();
        $userId = $this->request->userId();
        $user = UserModel::findById($userId);
        unset($user['password']);
        $this->view('user/index.tpl', [
            'user'      => $user,
            'bookshelf' => BookshelfModel::count(['user_id' => $userId]),
            'history'   => ReadingHistoryModel::count(['user_id' => $userId]),
        ]);
    }

    private function actionLogin(): void
    {
        if ($this->request->isLoggedIn()) {
            $this->redirect(url_full('user'));
        }
        if ($this->request->isPost()) {
            if (!Security::verifyCsrf()) {
                $this->view('user/login.tpl', ['error' => Language::get('csrf_failed_retry', 'CSRF验证失败，请重试')]);
                return;
            }
            $username = (string)$this->request->post('username', '', 'str');
            $throttle = new LoginThrottleService('front-login',
                strtolower($username) !== '' ? strtolower($username) : ($_SERVER['REMOTE_ADDR'] ?? 'guest'));
            if ($throttle->isLocked()) {
                $mins = (int)ceil($throttle->secondsUntilUnlock() / 60);
                $this->view('user/login.tpl', ['error' => Language::get('login_locked_fmt', '登录失败次数过多，请 %d 分钟后再试', $mins)]);
                return;
            }
            if ($captchaErr = Security::verifyCaptchaReason((string)$this->request->post('captcha', '', 'str'))) {
                $this->view('user/login.tpl', ['error' => $captchaErr]);
                return;
            }
            $service = new UserService();
            $result = $service->login(
                $username,
                $this->request->post('password', '', 'str')
            );
            if ($result['ok']) {
                $throttle->recordAttempt(true);
                Hook::doAction('user_login_success', $result['user'] ?? null, $_POST);
                $redirect = $this->safeInternalPath((string)$this->request->get('redirect', '/user', 'str')) ?: '/user';
                $this->redirect(BASE_URL . '/' . ltrim($redirect, '/'));
            }
            $throttle->recordAttempt(false);
            $this->view('user/login.tpl', ['error' => $result['msg']]);
            return;
        }
        $this->view('user/login.tpl', []);
    }

    private function actionRegister(): void
    {
        if ($this->request->isPost()) {
            if (!Security::verifyCsrf()) {
                $this->view('user/register.tpl', ['error' => Language::get('csrf_failed_retry', 'CSRF验证失败，请重试')]);
                return;
            }
            $throttle = new LoginThrottleService('front-register', $_SERVER['REMOTE_ADDR'] ?? 'guest');
            if ($throttle->isLocked()) {
                $mins = (int)ceil($throttle->secondsUntilUnlock() / 60);
                $this->view('user/register.tpl', ['error' => Language::get('register_locked_fmt', '注册请求过于频繁，请 %d 分钟后再试', $mins)]);
                return;
            }
            if ($captchaErr = Security::verifyCaptchaReason((string)$this->request->post('captcha', '', 'str'))) {
                $this->view('user/register.tpl', ['error' => $captchaErr]);
                return;
            }
            $service = new UserService();
            $result = $service->register(
                $this->request->post('username', '', 'str'),
                $this->request->post('password', '', 'str'),
                $this->request->post('email', '', 'str')
            );
            if ($result['ok']) {
                $throttle->recordAttempt(true);
                Hook::doAction('user_register_success', $result['user'] ?? null, $_POST);
                $this->redirect(url_full('user'));
            }
            $throttle->recordAttempt(false);
            $this->view('user/register.tpl', ['error' => $result['msg']]);
            return;
        }
        $this->view('user/register.tpl', []);
    }

    private function actionLogout(): void
    {
        if (!$this->request->isPost() || !Security::verifyCsrf()) {
            $this->redirect(url_full('home'));
        }
        $_SESSION = array();
        session_destroy();
        $this->redirect(url_full('home'));
    }

    private function safeInternalPath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || strpos($path, '//') === 0 || strpos($path, '\\') !== false
            || preg_match('#^[a-zA-Z]+://#', $path) || strpos($path, '@') !== false
            || strpos($path, '..') !== false || preg_match('#^[a-zA-Z]:#', $path)) {
            return '';
        }
        if ($path[0] !== '/') $path = '/' . $path;
        return $path;
    }

    private function actionBookshelf(): void
    {
        $this->requireLogin();
        $page = $this->request->get('page', 1, 'int') ?: 1;
        $result = BookshelfModel::getByUser($this->request->userId(), $page, 20);
        if (!empty($result['list'])) $result['list'] = NovelService::attachDisplayNames($result['list']);
        View::assign('pageUrlTpl', url('user', ['action' => 'bookshelf', 'query' => ['page' => '@@PAGE@@']]));
        $this->view('user/bookshelf.tpl', $result);
    }

    private function actionHistory(): void
    {
        $this->requireLogin();
        $page = $this->request->get('page', 1, 'int') ?: 1;
        $this->view('user/history.tpl',
            ReadingHistoryModel::getByUser($this->request->userId(), $page));
    }

    private function actionGold(): void
    {
        $this->requireLogin();
        $page = $this->request->get('page', 1, 'int') ?: 1;
        $this->view('user/gold.tpl',
            GoldLogModel::getByUser($this->request->userId(), $page));
    }

    private function actionChapters(): void
    {
        $this->requireLogin();
        $page = $this->request->get('page', 1, 'int') ?: 1;
        View::assign('pageUrlTpl', url('user', ['action' => 'chapters', 'query' => ['page' => '@@PAGE@@']]));
        $this->view('user/chapters.tpl',
            ChapterPurchaseModel::getByUser($this->request->userId(), $page, 20));
    }

    private function actionAuto(): void
    {
        $this->requireLogin();
        $list = AutoPurchaseModel::listByUser($this->request->userId());
        $this->view('user/auto.tpl', ['list' => $list]);
    }

    private function actionVip(): void
    {
        $this->requireLogin();
        $userId = $this->request->userId();

        if ($this->request->isPost()) {
            if (!Security::verifyCsrf()) {
                $this->redirect(url_full('user', ['action' => 'vip']));
            }
            $months = $this->request->post('months', 1, 'int');
            $service = new UserService();
            $result = $service->exchangeVip($userId, $months);
            $this->setFlash($result);
            $this->redirect(url_full('user', ['action' => 'vip']));
        }

        $flash = $this->takeFlash();
        if ($flash) View::assign('vipResult', $flash);
        $this->view('user/vip.tpl', [
            'user'         => UserModel::findById($userId),
            'goldPerMonth' => (int)SettingModel::get('vip_gold_per_month', 3000),
            'pricePerMonth' => (int)SettingModel::get('vip_price_per_month', 30),
        ]);
    }

    private function actionSign(): void
    {
        $this->requireLogin();
        $userId = $this->request->userId();
        $service = new UserService();
        $userRow = UserModel::findById($userId);

        if ($this->request->isPost()) {
            if (!Security::verifyCsrf()) {
                $this->setFlash(['ok' => false, 'msg' => Language::get('csrf_failed_refresh', 'CSRF验证失败，请刷新重试')]);
                $this->redirect(url_full('user', ['action' => 'sign']));
            }
            $result = $service->sign($userId);
            $this->setFlash($result);
            $this->redirect(url_full('user', ['action' => 'sign']));
        }
        $flash = $this->takeFlash();
        if ($flash) View::assign('signResult', $flash);
        $this->view('user/sign.tpl', [
            'reward'       => $service->getSignReward($userId),
            'user'         => $userRow,
            'signed_today' => ((string)($userRow['last_sign'] ?? '') === date('Y-m-d')),
            'sign_days'    => (int)($userRow['sign_days'] ?? 0),
        ]);
    }

    private function actionRecharge(): void
    {
        $this->requireLogin();
        if ($this->request->isPost()) {
            if (!Security::verifyCsrf()) {
                $this->view('user/recharge.tpl', ['error' => Language::get('csrf_failed_retry', 'CSRF验证失败，请重试')]);
                return;
            }
            $service = new PaymentService();
            $result = $service->createRecharge(
                $this->request->userId(),
                $this->request->post('money', 0, 'float'),
                $this->request->post('gateway', '', 'str'),
                $this->request->post('type', 'alipay', 'str')
            );
            if ($result['ok']) {
                $redirect = $result['redirect'];
                $type = $redirect['redirect_type'] ?? 'form';

                if ($this->request->isAjax()) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'ok'            => true,
                        'redirect_type' => $type,
                        'form_html'     => $type === 'form' ? ($redirect['payload'] ?? '') : '',
                        'redirect_url'  => $type === 'redirect' ? ($redirect['payload'] ?? '') : '',
                        'qrcode_url'    => $type === 'qrcode' ? ($redirect['payload'] ?? '') : '',
                        'trade_no'      => $result['trade_no'] ?? '',
                        'money'         => $this->request->post('money', 0, 'float'),
                    ]);
                    exit;
                }

                if ($type === 'form') {
                    echo $redirect['payload'];
                } elseif ($type === 'redirect') {
                    header('Location: ' . $redirect['payload']);
                } else {
                    $this->view('user/recharge.tpl', [
                        'error' => null,
                        'qrcode' => ['url' => $redirect['payload'] ?? '', 'trade_no' => $result['trade_no'] ?? ''],
                    ]);
                    return;
                }
                exit;
            }

            if ($this->request->isAjax()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'msg' => $result['msg']]);
                exit;
            }
            $this->view('user/recharge.tpl', ['error' => $result['msg']]);
            return;
        }
        $gatewayList = [];
        $methods = [];
        foreach (PaymentGatewayRegistry::all() as $gw) {
            $gatewayList[] = ['id' => $gw->getId(), 'name' => $gw->getName()];

            if (!$methods) $methods = $gw->getSupportedMethods();
        }
        $this->view('user/recharge.tpl', [
            'rate' => CommonService::rechargeRate(),
            'gateways' => $gatewayList,
            'methods'  => $methods,
        ]);
    }

    private function actionProfile(): void
    {
        $this->requireLogin();
        $userId = $this->request->userId();
        $userRow = UserModel::findById($userId);
        if ($this->request->isPost()) {
            if (!Security::verifyCsrf()) {
                $this->redirect(url_full('user', ['action' => 'profile']));
            }
            if ($err = Security::requireCaptcha()) {
                $this->setFlash(['ok' => false, 'msg' => $err]);
                $this->redirect(url_full('user', ['action' => 'profile']));
            }
            $newEmail = trim((string)$this->request->post('email', '', 'str'));
            $emailCode = trim((string)$this->request->post('email_code', '', 'str'));

            if ($newEmail !== '' && $newEmail !== (string)($userRow['email'] ?? '')) {
                if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                    $this->setFlash(['ok' => false, 'msg' => Language::get('email_format_error', '邮箱格式不正确')]);
                    $this->redirect(url_full('user', ['action' => 'profile']));
                }
                if (!EmailVerifyModel::verify($newEmail, $emailCode)) {
                    $this->setFlash(['ok' => false, 'msg' => Language::get('email_code_invalid', '邮箱验证码不正确或已过期')]);
                    $this->redirect(url_full('user', ['action' => 'profile']));
                }
            }
            $service = new UserService();
            $updateData = [
                'email'    => $newEmail,
                'avatar'   => $this->request->post('avatar', '', 'str'),
                'signature'=> $this->request->post('signature', '', 'str'),
                'phone'    => $this->request->post('phone', '', 'str'),
                'gender'   => (int)$this->request->post('gender', 0, 'int'),
                'wechat_account' => $this->request->post('wechat_account', '', 'str'),
            ];

            if (!empty($_FILES['avatar_file']['tmp_name']) && is_uploaded_file($_FILES['avatar_file']['tmp_name'])) {
                $uploaded = $this->handleAvatarUpload($userId, $_FILES['avatar_file']);
                if ($uploaded !== null) {
                    $updateData['avatar'] = $uploaded;
                } else {
                    $this->setFlash(['ok' => false, 'msg' => Language::get('avatar_upload_failed', '头像上传失败：仅支持 JPG/PNG/GIF，且不超过 2MB')]);
                    $this->redirect(url_full('user', ['action' => 'profile']));
                }
            }
            $result = $service->updateProfile($userId, $updateData);
            Hook::doAction('user_profile_saved', $userId, $_POST);
            $this->setFlash($result);
            $this->redirect(url_full('user', ['action' => 'profile']));
        }
        $flash = $this->takeFlash();
        if ($flash) View::assign('profileResult', $flash);
        $userRow = UserModel::findById($userId);
        $this->view('user/profile.tpl', ['user' => $userRow]);
    }

    private function handleAvatarUpload(int $userId, array $file): ?string
    {
        if (($file['size'] ?? 0) > 2 * 1024 * 1024) return null;
        $info = @getimagesize($file['tmp_name']);
        if (!$info) return null;
        [$srcW, $srcH, $type] = $info;
        if (!in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF])) return null;

        if ($type === IMAGETYPE_JPEG) $src = imagecreatefromjpeg($file['tmp_name']);
        elseif ($type === IMAGETYPE_PNG) $src = imagecreatefrompng($file['tmp_name']);
        else $src = imagecreatefromgif($file['tmp_name']);
        if (!$src) return null;

        $size = 200;
        $min = min($srcW, $srcH);
        $sx = (int)(($srcW - $min) / 2);
        $sy = (int)(($srcH - $min) / 2);
        $dst = imagecreatetruecolor($size, $size);

        if ($type === IMAGETYPE_PNG) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $size, $size, $min, $min);

        $dir = ROOT_PATH . 'uploads' . DIRECTORY_SEPARATOR . 'avatars';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $name = bin2hex(random_bytes(16)) . '.jpg';
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        $ok = imagejpeg($dst, $path, 88);
        if (!$ok) return null;
        return '/uploads/avatars/' . $name;
    }

    private function actionSendEmailCode(): void
    {
        $this->requireLogin();
        header('Content-Type: application/json; charset=utf-8');
        if (!Security::verifyCsrf()) {
            echo json_encode(['ok' => false, 'msg' => Language::get('csrf_failed', 'CSRF验证失败')]);
            return;
        }
        $email = trim((string)$this->request->post('email', '', 'str'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['ok' => false, 'msg' => Language::get('email_format_error', '邮箱格式不正确')]);
            return;
        }
        $svc = new MailService();
        $r = $svc->sendVerifyCode($email);
        echo json_encode($r);
    }

    private function actionPassword(): void
    {
        $this->requireLogin();
        $ajax = $this->request->post('ajax', '0', 'str') === '1';
        if ($this->request->isPost()) {
            if (!Security::verifyCsrf()) {
                if ($ajax) $this->json(['ok' => false, 'msg' => Language::get('csrf_failed_refresh', 'CSRF验证失败，请刷新重试')]);
                $this->redirect(url_full('user', ['action' => 'password']));
            }
            if ($err = Security::requireCaptcha()) {
                if ($ajax) $this->json(['ok' => false, 'msg' => $err]);
                $this->setFlash(['ok' => false, 'msg' => $err]);
                $this->redirect(url_full('user', ['action' => 'password']));
            }
            $service = new UserService();
            $result = $service->changePassword(
                $this->request->userId(),
                $this->request->post('old_password', '', 'str'),
                $this->request->post('new_password', '', 'str')
            );
            if ($ajax) $this->json($result);
            $this->setFlash($result);
            $this->redirect(url_full('user', ['action' => 'password']));
        }
        $flash = $this->takeFlash();
        if ($flash) View::assign('pwdResult', $flash);
        $this->view('user/password.tpl', []);
    }

    private function actionForgot(): void
    {
        if ($this->request->isPost()) {
            if (!Security::verifyCsrf()) {
                $this->view('user/forgot.tpl', ['error' => Language::get('csrf_failed_refresh', 'CSRF验证失败，请刷新重试')]);
                return;
            }
            $step = $this->request->post('step', 'send', 'str');
            $email = trim((string)$this->request->post('email', '', 'str'));
            $throttle = new LoginThrottleService('front-forgot',
                md5(strtolower($email)) ?: ($_SERVER['REMOTE_ADDR'] ?? 'guest'));

            if ($step === 'send') {
                if ($throttle->isLocked()) {
                    $mins = (int)ceil($throttle->secondsUntilUnlock() / 60);
                    $this->view('user/forgot.tpl', ['error' => Language::get('throttle_fmt', '请求过于频繁，请 %d 分钟后再试', $mins), 'email' => $email]);
                    return;
                }
                if ($captchaErr = Security::verifyCaptchaReason((string)$this->request->post('captcha', '', 'str'))) {
                    $this->view('user/forgot.tpl', ['error' => $captchaErr, 'email' => $email]);
                    return;
                }
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->view('user/forgot.tpl', ['error' => Language::get('email_invalid_address', '请输入正确的邮箱地址'), 'email' => $email]);
                    return;
                }

                $user = UserModel::findByEmail($email, 'id, username');
                if (!$user) {
                    $throttle->recordAttempt(false);
                    $this->view('user/forgot.tpl', ['error' => Language::get('email_not_registered_check', '该邮箱未注册，请检查后重试'), 'email' => $email]);
                    return;
                }
                $mail = new MailService();
                $r = $mail->sendVerifyCode($email);
                if (empty($r['ok'])) {
                    $throttle->recordAttempt(false);
                    $this->view('user/forgot.tpl', ['error' => $r['msg'] ?? Language::get('code_send_failed', '验证码发送失败，请稍后重试'), 'email' => $email]);
                    return;
                }
                $throttle->recordAttempt(true);
                $this->view('user/forgot.tpl', ['sent' => true, 'email' => $email, 'ok' => Language::get('code_sent_check_below', '验证码已发送到邮箱，请查收并在下方输入')]);
                return;
            }

            if ($step === 'reset') {
                if ($throttle->isLocked()) {
                    $mins = (int)ceil($throttle->secondsUntilUnlock() / 60);
                    $this->view('user/forgot.tpl', ['sent' => true, 'email' => $email, 'error' => Language::get('throttle_fmt', '请求过于频繁，请 %d 分钟后再试', $mins)]);
                    return;
                }

                if ($captchaErr = Security::verifyCaptchaReason((string)$this->request->post('captcha', '', 'str'))) {
                    $this->view('user/forgot.tpl', ['sent' => true, 'email' => $email, 'error' => $captchaErr]);
                    return;
                }
                $code = trim((string)$this->request->post('code', '', 'str'));
                $newPwd = (string)$this->request->post('new_password', '', 'str');
                if ($code === '' || !EmailVerifyModel::verify($email, $code)) {
                    $this->view('user/forgot.tpl', ['sent' => true, 'email' => $email, 'error' => Language::get('code_error', '验证码错误或已过期')]);
                    return;
                }
                if (strlen($newPwd) < 6) {
                    $this->view('user/forgot.tpl', ['sent' => true, 'email' => $email, 'error' => Language::get('password_too_short', '新密码至少6位')]);
                    return;
                }

                $user = UserModel::findByEmail($email, 'id');
                if (!$user) {
                    $this->view('user/forgot.tpl', ['error' => Language::get('user_not_exists', '该邮箱未注册')]);
                    return;
                }
                UserModel::changePassword((int)$user['id'], $newPwd);
                $throttle->recordAttempt(true);
                $_SESSION['forgot_success'] = Language::get('reset_password_success_login', '密码已重置，请用新密码登录');
                $this->redirect(url_full('user', ['action' => 'forgot']));
            }
        }
        $forgotSuccess = $_SESSION['forgot_success'] ?? null;
        unset($_SESSION['forgot_success']);
        $this->view('user/forgot.tpl', ['success' => $forgotSuccess]);
    }
}

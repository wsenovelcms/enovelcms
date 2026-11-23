<?php

class ApiController extends Controller
{
    public function handle(string $action = ''): void
    {

        $camel = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $action))));
        $method = 'api' . ucfirst($camel);
        if (method_exists($this, $method)) {
            $this->$method();
        } else {

            $this->json(['code' => 404, 'msg' => Language::get('api_not_found', '接口不存在')]);
        }
    }

    private function apiBookshelf(): void
    {
        if (!$this->request->isLoggedIn()) {
            $this->json(['code' => 401, 'msg' => Language::get('please_login', '请先登录')], 401);
        }
        if (!Security::verifyCsrf()) {
            $this->json(['code' => 403, 'msg' => Language::get('csrf_failed', 'CSRF验证失败')], 403);
        }
        $novelId = $this->request->post('novel_id', 0, 'int');
        $service = new UserService();

        if ($this->request->post('action', '', 'str') === 'add') {
            $result = $service->addToBookshelf($this->request->userId(), $novelId);
        } else {
            $result = $service->toggleBookshelf($this->request->userId(), $novelId);
        }
        $this->json(['code' => 0, 'data' => $result]);
    }

    private function apiSign(): void
    {
        if (!$this->request->isLoggedIn()) {
            $this->json(['code' => 401, 'msg' => Language::get('please_login', '请先登录')], 401);
        }
        if (!Security::verifyCsrf()) {
            $this->json(['code' => 403, 'msg' => Language::get('csrf_failed', 'CSRF验证失败')], 403);
        }
        $service = new UserService();
        $result = $service->sign($this->request->userId());
        $this->json(['code' => $result['ok'] ? 0 : 1, 'data' => $result]);
    }

    private function apiPurchase(): void
    {
        if (!$this->request->isLoggedIn()) {
            $this->json(['code' => 401, 'msg' => Language::get('please_login', '请先登录')], 401);
        }
        if (!Security::verifyCsrf()) {
            $this->json(['code' => 403, 'msg' => Language::get('csrf_failed', 'CSRF验证失败')], 403);
        }
        $chapterId = $this->request->post('chapter_id', 0, 'int');
        $autoRaw = $this->request->post('auto_buy', null, 'str');
        $autoBuy = $autoRaw === null ? null : ($autoRaw === '1');
        $service = new ChapterService();
        $result = $service->purchase($this->request->userId(), $chapterId, $autoBuy);
        $this->json(['code' => $result['ok'] ? 0 : 1, 'data' => $result]);
    }

    private function apiAutoPurchaseToggle(): void
    {
        if (!$this->request->isLoggedIn()) {
            $this->json(['code' => 401, 'msg' => Language::get('please_login', '请先登录')], 401);
        }
        if (!Security::verifyCsrf()) {
            $this->json(['code' => 403, 'msg' => Language::get('csrf_failed', 'CSRF验证失败')], 403);
        }
        $novelId = $this->request->post('novel_id', 0, 'int');
        $enabled = $this->request->post('enabled', 0, 'int') === 1;
        if ($novelId <= 0) {
            $this->json(['code' => 1, 'msg' => Language::get('invalid_params', '参数错误')]);
        }
        AutoPurchaseModel::set($this->request->userId(), $novelId, $enabled);
        $this->json(['code' => 0, 'data' => ['enabled' => $enabled]]);
    }

    private function apiCaptcha(): void
    {
        $reuse = isset($_GET['reuse']) && (string)$_GET['reuse'] === '1';
        Security::generateCaptcha(null, !$reuse);
        exit;
    }

    private function apiPayoutQrcode(): void
    {
        if (!$this->request->isLoggedIn()) { http_response_code(404); exit; }
        $user = UserModel::findById($this->request->userId(), 'withdraw_qrcode');
        $this->serveQrImage((string)($user['withdraw_qrcode'] ?? ''));
    }

    private function serveQrImage(string $relative): void
    {
        if ($relative === '' || strpos($relative, 'data/uploads/qrcodes/') !== 0) {
            http_response_code(404); exit;
        }
        $root = realpath(ROOT_PATH . 'data/uploads/qrcodes/');
        $realFile = realpath(ROOT_PATH . $relative);
        if ($root === false || $realFile === false
            || strpos($realFile, $root . DIRECTORY_SEPARATOR) !== 0
            || !is_file($realFile)) {
            http_response_code(404); exit;
        }
        $allowed = array('image/jpeg' => true, 'image/png' => true, 'image/gif' => true, 'image/webp' => true);
        $mime = '';
        if (function_exists('mime_content_type')) {
            $detected = @mime_content_type($realFile);
            if (isset($allowed[$detected])) $mime = $detected;
        }
        if ($mime === '') {
            $ext = strtolower(pathinfo($realFile, PATHINFO_EXTENSION));
            $map = array('jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp');
            $mime = $map[$ext] ?? '';
        }
        if ($mime === '') { http_response_code(404); exit; }
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=600');
        readfile($realFile);
        exit;
    }

    private function apiOrderStatus(): void
    {
        if (!$this->request->isLoggedIn()) {
            $this->json(['code' => 401, 'msg' => Language::get('please_login', '请先登录')], 401);
        }
        $tradeNo = (string)($this->request->get['trade_no'] ?? '');
        if ($tradeNo === '') {
            $this->json(['code' => 400, 'msg' => Language::get('missing_trade_no', '缺少订单号')], 400);
        }
        $order = OrderModel::findByTradeNo($tradeNo);
        if (!$order || (int)$order['user_id'] !== $this->request->userId()) {

            $this->json(['code' => 404, 'msg' => Language::get('order_not_found', '订单不存在')]);
        }
        $this->json([
            'code' => 0,
            'data' => [
                'trade_no' => $order['out_trade_no'],
                'status'   => (int)$order['status'],
                'paid'     => (int)$order['status'] === 1,
                'money'    => (float)$order['money'],
                'gold'     => (int)$order['gold'],
            ],
        ]);
    }
}

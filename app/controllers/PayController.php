<?php

class PayController extends Controller
{
    public function handle(string $action): void
    {
        if ($action === 'notify') {
            $this->notify();
        } elseif ($action === 'return') {
            $this->returnPage();
        } else {
            $this->redirect(url_full('home'));
        }
    }

    private function notify(): void
    {
        $gatewayId = g('gateway', 'epay', 'str');

        $data = array_merge($_GET, $_POST);
        unset($data['gateway']);
        $service = new PaymentService();
        echo $service->handleNotify($gatewayId, $data);
        exit;
    }

    private function returnPage(): void
    {
        $tradeNo = g('out_trade_no', '', 'str');
        $order = $tradeNo ? OrderModel::findByTradeNo($tradeNo) : null;
        if ($order && (!$this->request->isLoggedIn() || (int)$order['user_id'] !== $this->request->userId())) {
            $order = null;
        }
        $this->view('user/pay_return.tpl', ['order' => $order]);
    }
}

<?php

class PaymentService
{

    public const SCENE_RECHARGE = 'recharge';

    /**
     * 通用下单入口：供充值及其他业务插件（打赏、购买等）复用同一支付通道。
     * 业务方自行生成/维护业务单据，通过 scene/biz_id/ext 与订单关联；
     * 支付结果经 order_paid 钩子回传，业务插件监听钩子兑现自身逻辑。
     */
    public function createOrder(array $params): array
    {
        $userId   = (int)($params['user_id'] ?? 0);
        $money    = (float)($params['money'] ?? 0);
        $gatewayId = (string)($params['gateway'] ?? '');
        $method   = (string)($params['method'] ?? 'alipay');
        $scene    = (string)($params['scene'] ?? self::SCENE_RECHARGE);
        $bizId    = (int)($params['biz_id'] ?? 0);
        $subject  = (string)($params['subject'] ?? '');
        $gold     = (int)($params['gold'] ?? 0);
        $ext      = $params['ext'] ?? [];

        if ($userId <= 0) return ['ok' => false, 'msg' => Language::get('user_not_exists', '用户不存在')];
        if ($money <= 0) return ['ok' => false, 'msg' => Language::get('recharge_amount_invalid', '充值金额无效')];
        if (!preg_match('/^[a-z0-9_\-]{1,20}$/', $scene)) $scene = self::SCENE_RECHARGE;
        $subject = $subject !== '' ? $subject : Language::get('order_subject_default', '订单 %s', OrderModel::generateTradeNo());

        $gw = $gatewayId !== '' ? PaymentGatewayRegistry::get($gatewayId) : PaymentGatewayRegistry::default();
        if (!$gw) return ['ok' => false, 'msg' => Language::get('gateway_not_configured', '支付通道未配置，请联系管理员')];
        if (!$gw->isEnabled()) return ['ok' => false, 'msg' => Language::get('gateway_disabled', '该支付通道未启用')];

        $tradeNo = OrderModel::generateTradeNo();
        $orderId = OrderModel::create([
            'out_trade_no' => $tradeNo,
            'user_id'      => $userId,
            'money'        => $money,
            'gold'         => $gold,
            'type'         => $method,
            'gateway'      => $gw->getId(),
            'scene'        => $scene,
            'biz_id'       => $bizId,
            'ext'          => is_array($ext) && $ext ? json_encode($ext, JSON_UNESCAPED_UNICODE) : '',
            'status'       => 0,
        ]);

        $result = $gw->createOrder([
            'out_trade_no' => $tradeNo,
            'subject'      => $subject,
            'money'        => $money,
            'method'       => $method,
            'scene'        => $scene,
            'biz_id'       => $bizId,
            'notify_url'   => BASE_URL . '/pay/notify?gateway=' . $gw->getId(),
            'return_url'   => BASE_URL . '/pay/return?gateway=' . $gw->getId(),
            'client_ip'    => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);

        return [
            'ok'          => true,
            'order_id'    => $orderId,
            'trade_no'    => $tradeNo,
            'gateway'     => $gw->getId(),
            'scene'       => $scene,
            'biz_id'      => $bizId,
            'redirect'    => $result,
        ];
    }

    public function createRecharge(int $userId, float $money, string $gatewayId = '', string $method = 'alipay'): array
    {
        $siteName = (string)SettingModel::get('site_name', 'Novel Site');
        return $this->createOrder([
            'user_id'  => $userId,
            'money'    => $money,
            'gateway'  => $gatewayId,
            'method'   => $method,
            'scene'    => self::SCENE_RECHARGE,
            'gold'     => (int)round($money * CommonService::rechargeRate()),
            'subject'  => Language::get('recharge_subject_fmt', '%s 充值 %s 元', $siteName, $money),
        ]);
    }

    public function handleNotify(string $gatewayId, array $data): string
    {
        $gw = PaymentGatewayRegistry::get($gatewayId);
        if (!$gw) return 'fail';
        if (!$gw->verifyNotify($data)) return 'fail';

        $parsed = $gw->parseNotify($data);
        if (($parsed['status'] ?? '') !== 'paid') return 'fail';

        $order = OrderModel::findByTradeNo($parsed['out_trade_no'] ?? '');
        if (!$order) return 'fail';

        if ((string)$order['gateway'] !== $gatewayId) {
            error_log('[pay-notify] gateway mismatch: order=' . $order['gateway'] . ' notify=' . $gatewayId);
            return 'fail';
        }
        if ((int)$order['status'] === 1) return 'success';

        if (isset($parsed['money']) && abs((float)$parsed['money'] - (float)$order['money']) > 0.01) {
            error_log('[pay-notify] amount mismatch: order=' . $order['money'] . ' notify=' . $parsed['money']);
            return 'fail';
        }

        $db = UserModel::db();
        $db->beginTransaction();
        try {

            $locked = $db->query(
                "SELECT id, status, gold, user_id, money, scene, biz_id, ext FROM " . OrderModel::tableName() . " WHERE id = ? FOR UPDATE",
                [(int)$order['id']]
            )->fetch();
            if (!$locked) {
                $db->rollBack();
                return 'fail';
            }
            if ((int)$locked['status'] === 1) {
                $db->rollBack();
                return 'success';
            }

            if (!OrderModel::markPaid((int)$locked['id'])) {
                $db->rollBack();
                return 'success';
            }
            $gold = (int)$locked['gold'];
            if ($gold > 0) {
                CommonService::addGold((int)$locked['user_id'], $gold, 'recharge',
                    Language::get('pay_recharge_note', '充值 %s 元（%s）', $locked['money'], $gw->getName()));
            }
            $db->commit();

            Hook::doAction('order_paid', $locked, $parsed, $gw->getId());
            return 'success';
        } catch (\Throwable $e) {
            $db->rollBack();
            error_log('[pay-notify] ' . $e->getMessage());
            return 'fail';
        }
    }
}

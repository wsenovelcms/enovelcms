<?php

abstract class PaymentGateway
{

    abstract public function getId(): string;

    abstract public function getName(): string;

    public function getIcon(): string { return 'fa-credit-card'; }

    abstract public function isEnabled(): bool;

    abstract public function createOrder(array $params): array;

    abstract public function verifyNotify(array $data): bool;

    abstract public function parseNotify(array $data): array;

    public function getConfigFields(): array { return []; }

    public function getSupportedMethods(): array
    {
        return [
            ['type' => 'alipay', 'name' => Language::get('payment_alipay', '支付宝'), 'icon' => 'fa-alipay'],
            ['type' => 'wxpay', 'name' => Language::get('payment_wxpay', '微信支付'), 'icon' => 'fa-weixin'],
        ];
    }
}

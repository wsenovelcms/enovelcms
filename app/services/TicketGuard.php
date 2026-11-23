<?php

class TicketGuard
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function check(string $boundIdentity, string $appKey): array
    {
        $boundIdentity = trim($boundIdentity);
        if ($boundIdentity === '') {
            return ['ok' => true, 'reason' => ''];
        }

        $local = $this->localIdentity();
        if ($local === '') {
            return ['ok' => false, 'reason' => t_admin('adm_guard_not_connected', '本站尚未连接应用中心，插件授权未生效')];
        }
        if (!hash_equals($boundIdentity, $local)) {
            return ['ok' => false, 'reason' => t_admin('adm_guard_identity_mismatch', '插件授权与本站安装身份不一致，该应用已绑定其他站点')];
        }
        return ['ok' => true, 'reason' => ''];
    }

    public function verify(string $ticketFile, string $appKey): array
    {
        $identity = '';
        if (is_file($ticketFile)) {
            $ticket = include $ticketFile;
            if (is_array($ticket)) {
                $identity = (string)($ticket['identity'] ?? '');
            }
        }
        return $this->check($identity, $appKey);
    }

    private function localIdentity(): string
    {
        try {
            $row = $this->db->find('ac_bind', ['id' => 1]);
            return (string)($row['client_identity'] ?? '');
        } catch (Throwable $e) {
            return '';
        }
    }
}

<?php

$_phpmailerDir = (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2) . '/') . 'vendor/Phpmail/';
if (is_file($_phpmailerDir . 'PHPMailer.php')) require_once $_phpmailerDir . 'PHPMailer.php';
if (is_file($_phpmailerDir . 'SMTP.php')) require_once $_phpmailerDir . 'SMTP.php';
if (is_file($_phpmailerDir . 'Exception.php')) require_once $_phpmailerDir . 'Exception.php';
unset($_phpmailerDir);

class MailService
{

    private function buildMailer(): \PHPMailer\PHPMailer\PHPMailer
    {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string)SettingModel::get('smtp_host', 'localhost');
        $mail->Port = (int)SettingModel::get('smtp_port', 25);
        $mail->SMTPAuth = true;
        $mail->Username = (string)SettingModel::get('smtp_user');
        $mail->Password = (string)SettingModel::get('smtp_pass');

        if ($mail->Port === 465) {
            $mail->SMTPSecure = 'ssl';
        } elseif ($mail->Port === 587) {
            $mail->SMTPSecure = 'tls';
        } else {
            $mail->SMTPSecure = false;
        }

        $mail->CharSet = 'UTF-8';
        $fromEmail = (string)SettingModel::get('smtp_from', $mail->Username);
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {

            $fromEmail = filter_var($mail->Username, FILTER_VALIDATE_EMAIL) ? $mail->Username : 'noreply@localhost';
        }
        $fromName = (string)SettingModel::get('smtp_fromname', SettingModel::get('site_name', 'Novel Site'));

        $fromName = preg_replace('/[\r\n]+/', '', $fromName);
        $mail->setFrom($fromEmail, $fromName);
        $mail->isHTML(true);
        return $mail;
    }

    public function send(string $to, string $subject, string $bodyHtml): array
    {
        try {
            $mail = $this->buildMailer();
            $mail->addAddress($to);

            $mail->Subject = preg_replace('/[\r\n]+/', '', $subject);
            $mail->Body = $bodyHtml;
            $mail->AltBody = strip_tags($bodyHtml);
            $mail->send();
            return ['ok' => true];
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            error_log('[mail] ' . $mail->ErrorInfo);
            return ['ok' => false, 'msg' => Language::get('mail_send_failed', '邮件发送失败')];
        }
    }

    public function sendVerifyCode(string $email): array
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'msg' => Language::get('email_format_error', '邮箱格式不正确')];
        }
        $code = (string)mt_rand(100000, 999999);
        EmailVerifyModel::createCode($email, $code);

        $siteName = (string)SettingModel::get('site_name', 'Novel Site');
        $html = "<p>" . Language::get('email_body_hello', '你好，') . "</p><p>" . Language::get('email_body_verify', '你正在 %s 进行邮箱验证，验证码为：', $siteName) . "</p>"
              . "<h2 style='letter-spacing:4px'>{$code}</h2>"
              . "<p>" . Language::get('email_body_code_valid', '验证码30分钟内有效，请勿泄露给他人。') . "</p>";
        $result = $this->send($email, Language::get('email_subject_code_fmt', '%s 邮箱验证码', $siteName), $html);
        if (!$result['ok']) return $result;
        return ['ok' => true, 'msg' => Language::get('code_sent_to_email', '验证码已发送至邮箱')];
    }
}

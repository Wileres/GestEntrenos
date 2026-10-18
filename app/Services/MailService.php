<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Crypto;
use App\Helpers\Db;
use App\Helpers\View;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

final class MailService
{
    /** Només per a proves: substitueix l'enviament real. Rep ['to','subject','html'] i retorna bool. */
    public static ?\Closure $fake = null;

    private const MAX_ATTEMPTS = 2;

    public static function render(string $template, array $vars): string
    {
        $vars += ['club' => SettingsService::clubName(), 'hasLogo' => SettingsService::logoPath() !== null];
        $body = self::renderFile($template, $vars);
        return self::renderFile('layout', $vars + ['body' => $body]);
    }

    private static function renderFile(string $name, array $vars): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        require BASE_PATH . '/templates/emails/' . $name . '.php';
        return (string) ob_get_clean();
    }

    /**
     * Envia un correu i en registra el resultat. No llança excepcions.
     */
    public static function send(string $type, string $toEmail, string $toName, string $subject, string $template, array $vars, bool $force = false): bool
    {
        $html = self::render($template, $vars + ['name' => $toName]);
        $smtp = SettingsService::smtp();

        if (self::$fake === null && !$force && (int) $smtp['enabled'] !== 1) {
            self::log($type, $toEmail, $subject, 'skipped', 0, 'Els correus estan desactivats.');
            return false;
        }

        $attempts = 0;
        $error = null;
        while ($attempts < self::MAX_ATTEMPTS) {
            $attempts++;
            try {
                if (self::$fake !== null) {
                    $ok = (self::$fake)(['to' => $toEmail, 'subject' => $subject, 'html' => $html, 'type' => $type]);
                    if (!$ok) {
                        throw new \RuntimeException('Enviament simulat fallit.');
                    }
                } else {
                    self::deliver($smtp, $toEmail, $toName, $subject, $html);
                }
                self::log($type, $toEmail, $subject, 'sent', $attempts, null);
                return true;
            } catch (\Throwable $e) {
                $error = $e instanceof MailException ? $e->getMessage() : 'Error en l\'enviament.';
            }
        }
        self::log($type, $toEmail, $subject, 'failed', $attempts, $error);
        AuditService::log('email_failed', 'email', null, 'error', $type . ' → ' . $toEmail);
        return false;
    }

    private static function deliver(array $smtp, string $toEmail, string $toName, string $subject, string $html): void
    {
        $mail = new PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = (string) $smtp['host'];
        $mail->Port = (int) $smtp['port'];
        $mail->Timeout = 15;
        $mail->SMTPSecure = match ($smtp['encryption']) {
            'ssl' => PHPMailer::ENCRYPTION_SMTPS,
            'tls' => PHPMailer::ENCRYPTION_STARTTLS,
            default => '',
        };
        $mail->SMTPAutoTLS = $smtp['encryption'] !== 'none';
        if ($smtp['username'] !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = (string) $smtp['username'];
            $mail->Password = $smtp['password_enc'] ? (string) Crypto::decrypt((string) $smtp['password_enc']) : '';
        }
        $mail->setFrom((string) $smtp['from_email'], (string) $smtp['from_name']);
        if ($smtp['reply_to'] !== '') {
            $mail->addReplyTo((string) $smtp['reply_to']);
        }
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $logo = SettingsService::logoPath();
        if ($logo !== null) {
            $mail->addEmbeddedImage($logo, 'logo-club', 'logo');
        }
        $mail->Body = $html;
        $mail->AltBody = trim(html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/h\d|\/tr)>/i', "\n", $html) ?? ''), ENT_QUOTES, 'UTF-8'));
        $mail->send();
    }

    private static function log(string $type, string $to, string $subject, string $status, int $attempts, ?string $error): void
    {
        try {
            Db::insert('email_logs', [
                'type' => $type, 'recipient' => mb_substr($to, 0, 190), 'subject' => mb_substr($subject, 0, 255),
                'status' => $status, 'attempts' => $attempts,
                'error_message' => $error === null ? null : mb_substr($error, 0, 500),
                'created_at' => Db::now(),
            ]);
            if ($status === 'sent') {
                AuditService::log('email_sent', 'email', null, 'ok', $type . ' → ' . $to);
            }
        } catch (\Throwable $e) {
            ErrorHandler::log($e);
        }
    }
}

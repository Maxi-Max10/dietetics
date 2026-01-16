<?php

declare(strict_types=1);

/**
 * Envío simple con adjunto.
 * - Si PHPMailer está instalado, lo usa.
 * - Si no, usa mail() con MIME multiparte.
 */
function mail_send_with_attachment(array $config, string $toEmail, string $toName, string $subject, string $htmlBody, string $attachmentBytes, string $attachmentFilename, string $attachmentMime): void
{
    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Email destino inválido.');
    }

    $fromEmail = getenv('MAIL_FROM') ?: (defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $fromName = getenv('MAIL_FROM_NAME') ?: (defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : ($config['app']['name'] ?? 'Dietetic'));

    // Prevención básica de header injection
    $subject = str_replace(["\r", "\n"], '', $subject);
    $fromEmail = str_replace(["\r", "\n"], '', (string)$fromEmail);
    $fromName = str_replace(["\r", "\n"], '', (string)$fromName);

    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $fromEmail = 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }

    // PHPMailer (recomendado)
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        $smtpHost = getenv('SMTP_HOST') ?: (defined('SMTP_HOST') ? SMTP_HOST : '');
        $smtpUser = getenv('SMTP_USER') ?: (defined('SMTP_USER') ? SMTP_USER : '');
        $smtpPass = getenv('SMTP_PASS') ?: (defined('SMTP_PASS') ? SMTP_PASS : '');
        $smtpPort = (int)(getenv('SMTP_PORT') ?: (defined('SMTP_PORT') ? SMTP_PORT : 587));
        $smtpSecure = (string)(getenv('SMTP_SECURE') ?: (defined('SMTP_SECURE') ? SMTP_SECURE : 'tls'));

        // Si hay SMTP configurado, úsalo. (En Hostinger suele ser lo más confiable.)
        if ($smtpHost !== '') {
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;
            $mail->Port = $smtpPort;
            $mail->SMTPSecure = $smtpSecure;

            // Si no configuraron MAIL_FROM o quedó en un dominio no autorizado, intenta usar el usuario SMTP.
            if ((!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) && filter_var($smtpUser, FILTER_VALIDATE_EMAIL)) {
                $fromEmail = $smtpUser;
            }
            if (str_starts_with($fromEmail, 'no-reply@') && filter_var($smtpUser, FILTER_VALIDATE_EMAIL)) {
                $fromEmail = $smtpUser;
            }
        } else {
            // Sin SMTP, PHPMailer cae a mail(). En muchos hostings esto falla (o va a spam).
            $mail->isMail();
        }

        try {
            $mail->setFrom($fromEmail, (string)$fromName);
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $htmlBody;
            $mail->AltBody = strip_tags($htmlBody);
            $mail->addStringAttachment($attachmentBytes, $attachmentFilename, 'base64', $attachmentMime);

            $mail->send();
            return;
        } catch (Throwable $e) {
            $info = '';
            if (property_exists($mail, 'ErrorInfo') && is_string($mail->ErrorInfo) && $mail->ErrorInfo !== '') {
                $info = $mail->ErrorInfo;
            }
            error_log('[invoice_mail_error_detail] ' . get_class($e) . ': ' . $e->getMessage() . ($info !== '' ? (' | ' . $info) : ''));

            if ($smtpHost === '') {
                throw new RuntimeException(
                    'No se pudo enviar el email sin SMTP. Configurá SMTP_HOST/SMTP_USER/SMTP_PASS (y MAIL_FROM) en config.local.php o variables de entorno.'
                );
            }

            throw new RuntimeException('No se pudo enviar el email por SMTP. ' . ($info !== '' ? $info : $e->getMessage()));
        }
    }

    // Fallback con mail() + MIME
    $boundary = '=_Part_' . bin2hex(random_bytes(12));

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    if (function_exists('mb_encode_mimeheader')) {
        $headers[] = 'From: ' . mb_encode_mimeheader((string)$fromName) . " <{$fromEmail}>";
    } else {
        $headers[] = 'From: ' . (string)$fromName . " <{$fromEmail}>";
    }
    $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';

    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body .= $htmlBody . "\r\n\r\n";

    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: {$attachmentMime}; name=\"{$attachmentFilename}\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n";
    $body .= "Content-Disposition: attachment; filename=\"{$attachmentFilename}\"\r\n\r\n";
    $body .= chunk_split(base64_encode($attachmentBytes)) . "\r\n";
    $body .= "--{$boundary}--\r\n";

    $ok = mail($toEmail, $subject, $body, implode("\r\n", $headers));
    if (!$ok) {
        throw new RuntimeException('mail() falló al enviar el correo.');
    }
}

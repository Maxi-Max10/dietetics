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

    // PHPMailer (recomendado)
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        // Si hay SMTP configurado, úsalo.
        $smtpHost = getenv('SMTP_HOST') ?: (defined('SMTP_HOST') ? SMTP_HOST : '');
        if ($smtpHost !== '') {
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->SMTPAuth = true;
            $mail->Username = getenv('SMTP_USER') ?: (defined('SMTP_USER') ? SMTP_USER : '');
            $mail->Password = getenv('SMTP_PASS') ?: (defined('SMTP_PASS') ? SMTP_PASS : '');
            $mail->Port = (int)(getenv('SMTP_PORT') ?: (defined('SMTP_PORT') ? SMTP_PORT : 587));
            $mail->SMTPSecure = (getenv('SMTP_SECURE') ?: (defined('SMTP_SECURE') ? SMTP_SECURE : 'tls'));
        }

        $mail->setFrom($fromEmail, (string)$fromName);
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = $subject;
        $mail->isHTML(true);
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);
        $mail->addStringAttachment($attachmentBytes, $attachmentFilename, 'base64', $attachmentMime);

        $mail->send();
        return;
    }

    // Fallback con mail() + MIME
    $boundary = '=_Part_' . bin2hex(random_bytes(12));

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'From: ' . mb_encode_mimeheader((string)$fromName) . " <{$fromEmail}>";
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

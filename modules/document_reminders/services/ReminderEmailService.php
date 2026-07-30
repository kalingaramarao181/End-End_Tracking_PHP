<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

class ReminderEmailService
{
    private $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../../../config/mail.php';
        $this->validateConfiguration();
    }

    public function send(array $reminder)
    {
        // A configured compliance/admin inbox receives all automatic reminders.
        // When no fixed recipient is configured, fall back to the candidate email.
        $to = trim($this->config['to_address'] ?? ($reminder['candidate_email'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('The reminder recipient email address is invalid.');
        }

        $name = $reminder['candidate_name'] ?? 'Unknown';
        $expiry = $reminder['expiry_date'];
        $documentType = $reminder['document_type'] ?? 'Immigration Document';
        $details = is_array($reminder['document_details'] ?? null) ? $reminder['document_details'] : [];
        $reason = $details['reminder_reason'] ?? 'Review this immigration document before the target date';
        $subject = $documentType . ' Reminder - ' . $name;
        $plainBody = "Hello,\n\nThis is an automated reminder.\n\nCandidate:\n{$name}\n\nDocument:\n{$documentType}\n\nTarget Date:\n{$expiry}\n\nAction:\n{$reason}\n\nRegards\nBeeData Technologies";
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeExpiry = htmlspecialchars($expiry, ENT_QUOTES, 'UTF-8');
        $safeDocumentType = htmlspecialchars($documentType, ENT_QUOTES, 'UTF-8');
        $safeReason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
        $expiryDate = DateTimeImmutable::createFromFormat('!Y-m-d', $expiry);
        $formattedExpiry = $expiryDate ? $expiryDate->format('F j, Y') : $expiry;
        $safeFormattedExpiry = htmlspecialchars($formattedExpiry, ENT_QUOTES, 'UTF-8');
        $daysLeft = $expiryDate ? max(0, (int)(new DateTimeImmutable('today'))->diff($expiryDate)->format('%r%a')) : null;
        $daysText = $daysLeft === null ? 'Renewal required' : $daysLeft . ' days remaining';
        $safeDaysText = htmlspecialchars($daysText, ENT_QUOTES, 'UTF-8');
        $year = date('Y');
        $logoUrl = 'https://beedatatech.com/home_images/beedata_logo.png';
        $htmlBody = <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$safeDocumentType} Reminder</title>
</head>
<body style="margin:0;padding:0;background-color:#f3f6fb;font-family:Arial,Helvetica,sans-serif;color:#1e293b;">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{$safeDocumentType} reminder for {$safeName}: {$safeDaysText}.</div>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f3f6fb;">
    <tr>
      <td align="center" style="padding:32px 12px;">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,0.10);">
          <tr>
            <td align="center" style="padding:26px 28px 22px;background:#ffffff;border-bottom:1px solid #e8edf5;">
              <img src="{$logoUrl}" width="190" alt="BeeData Technologies" style="display:block;width:190px;max-width:70%;height:auto;border:0;">
            </td>
          </tr>
          <tr>
            <td style="padding:0 28px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                <tr>
                  <td align="center" style="padding:30px 18px;background:linear-gradient(135deg,#1e3a8a,#4f46e5);background-color:#3730a3;color:#ffffff;border-radius:0 0 16px 16px;">
                    <div style="font-size:13px;font-weight:700;letter-spacing:1.4px;text-transform:uppercase;color:#c7d2fe;">Immigration Document Alert</div>
                    <h1 style="margin:10px 0 8px;font-size:27px;line-height:1.25;color:#ffffff;">{$safeDocumentType} Reminder</h1>
                    <p style="margin:0;font-size:15px;line-height:1.6;color:#e0e7ff;">A time-sensitive immigration document needs your attention.</p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="padding:28px;">
              <p style="margin:0 0 12px;font-size:16px;line-height:1.65;">Hello,</p>
              <p style="margin:0 0 24px;font-size:15px;line-height:1.7;color:#475569;">This automated reminder is being sent by <strong style="color:#1e293b;">BeeData Technologies</strong> to keep this immigration case on schedule.</p>

              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc;">
                <tr>
                  <td style="padding:18px;border-bottom:1px solid #e2e8f0;">
                    <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748b;">Candidate</div>
                    <div style="margin-top:6px;font-size:18px;font-weight:700;color:#0f172a;">{$safeName}</div>
                  </td>
                </tr>
                <tr>
                  <td style="padding:18px;border-bottom:1px solid #e2e8f0;">
                    <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748b;">Document</div>
                    <div style="margin-top:6px;font-size:16px;font-weight:700;color:#0f172a;">{$safeDocumentType}</div>
                  </td>
                </tr>
                <tr>
                  <td style="padding:18px;">
                    <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#64748b;">Target Date</div>
                    <div style="margin-top:6px;font-size:22px;font-weight:800;color:#dc2626;">{$safeFormattedExpiry}</div>
                    <div style="margin-top:6px;font-size:13px;font-weight:700;color:#b45309;">{$safeDaysText}</div>
                  </td>
                </tr>
              </table>

              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin-top:22px;background:#fff7ed;border-left:4px solid #f97316;border-radius:10px;">
                <tr><td style="padding:16px 18px;font-size:14px;line-height:1.65;color:#9a3412;"><strong>Action required:</strong> {$safeReason}.</td></tr>
              </table>

              <p style="margin:26px 0 0;font-size:14px;line-height:1.7;color:#64748b;">This is an automated notification from the BeeData document reminder system. No reply is required.</p>
              <p style="margin:20px 0 0;font-size:15px;line-height:1.6;color:#334155;">Regards,<br><strong>BeeData Technologies</strong></p>
            </td>
          </tr>
          <tr>
            <td align="center" style="padding:20px 28px;background:#0f172a;color:#94a3b8;font-size:12px;line-height:1.6;">
              &copy; {$year} BeeData Technologies. All rights reserved.<br>
              Automated Immigration Document Reminder
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $this->config['host'];
            $mail->Port = $this->config['port'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['username'];
            $mail->Password = $this->config['password'];
            $mail->Timeout = 30;
            $mail->CharSet = 'UTF-8';

            if ($this->config['encryption'] === 'ssl' || $this->config['encryption'] === 'smtps') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($this->config['encryption'] === 'tls' || $this->config['encryption'] === 'starttls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPAutoTLS = false;
                $mail->SMTPSecure = false;
            }

            $mail->setFrom($this->config['from_address'], $this->config['from_name']);
            $mail->addAddress($to, $name);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $plainBody;
            $mail->send();
        } catch (PHPMailerException $exception) {
            throw new RuntimeException('SMTP reminder email failed: ' . $mail->ErrorInfo, 0, $exception);
        }

        return true;
    }

    public function sendHtml($to, $name, $subject, $htmlBody, $plainBody = '')
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Approval recipient is not a valid email address.');
        }
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $this->config['host'];
            $mail->Port = (int)$this->config['port'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['username'];
            $mail->Password = $this->config['password'];
            $mail->Timeout = 30;
            $mail->CharSet = 'UTF-8';
            if (in_array($this->config['encryption'], ['ssl', 'smtps'], true)) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif (in_array($this->config['encryption'], ['tls', 'starttls'], true)) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPAutoTLS = false;
                $mail->SMTPSecure = '';
            }
            $mail->setFrom($this->config['from_address'], $this->config['from_name'] ?? 'E2E Tracking Services');
            $mail->addAddress($to, $name);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $plainBody ?: strip_tags($htmlBody);
            $mail->send();
        } catch (PHPMailerException $exception) {
            throw new RuntimeException('SMTP email failed: ' . $mail->ErrorInfo, 0, $exception);
        }
        return true;
    }
    private function validateConfiguration()
    {
        $required = ['host', 'port', 'username', 'password', 'from_address'];
        foreach ($required as $key) {
            $value = $this->config[$key] ?? null;
            if (!$value || stripos((string)$value, 'your-') !== false) {
                throw new RuntimeException('Mail configuration is incomplete. Set ' . strtoupper('MAIL_' . $key) . ' or configure config/mail.local.php.');
            }
        }

        if (!filter_var($this->config['from_address'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('MAIL_FROM_ADDRESS is not a valid email address.');
        }

        if (!empty($this->config['to_address']) && !filter_var($this->config['to_address'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('MAIL_REMINDER_TO is not a valid email address.');
        }

        if (!in_array($this->config['encryption'], ['tls', 'starttls', 'ssl', 'smtps', 'none', ''], true)) {
            throw new RuntimeException('MAIL_ENCRYPTION must be tls, ssl, or none.');
        }
    }
}

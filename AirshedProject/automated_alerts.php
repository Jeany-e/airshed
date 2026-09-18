<?php
include "config.php"; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Siguraduhin na tama ang path ng mga files na ito sa iyong folder
require __DIR__ . '/../PHPMailer-master/src/Exception.php';
require __DIR__ . '/../PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer-master/src/SMTP.php';

// Kunin ang data mula sa sensor trigger (galing sa JavaScript fetch)
$pm25 = isset($_POST['pm25']) ? floatval($_POST['pm25']) : 0;

// Mag-e-email lang kung ang PM2.5 ay lagpas sa threshold (35 = Poor/Critical)
if ($pm25 > 35) {
    $recentAlerts = firebaseRows('alert_logs');
    usort($recentAlerts, function ($a, $b) {
        return strcmp($b['timestamp'] ?? '', $a['timestamp'] ?? '');
    });
    $lastAlertTimestamp = strtotime($recentAlerts[0]['timestamp'] ?? '');
    if ($lastAlertTimestamp && time() - $lastAlertTimestamp < 1800) {
        echo "Critical alert already sent within the last 30 minutes.";
        exit();
    }

    $mail = new PHPMailer(true);
    try {
        // --- SMTP Settings ---
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('AIRSHED_SMTP_USERNAME');
        $mail->Password   = getenv('AIRSHED_SMTP_PASSWORD');
        if (!$mail->Username || !$mail->Password) {
            throw new RuntimeException('SMTP credentials are not configured.');
        }
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // --- Sender & Recipients ---
        $mail->setFrom($mail->Username, 'City Airshed Alert System');

        // Kunin ang valid email addresses ng registered users mula sa Firebase.
        $users = firebaseRows('users');
        $recipients = [];
        foreach ($users as $user) {
            $email = trim((string) ($user['email'] ?? ''));
            if (($user['role'] ?? 'user') === 'user' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                // BCC keeps recipients' email addresses private.
                $mail->addBCC($email);
                $recipients[] = [
                    'user_id' => (string) ($user['id'] ?? ''),
                    'username' => $user['username'] ?? 'Unknown User',
                    'email' => $email
                ];
            }
        }

        if (count($mail->getBccAddresses()) === 0) {
            throw new RuntimeException('No registered user email addresses are available.');
        }

        // --- Email Content ---
        $mail->isHTML(true);
        $mail->Subject = "⚠️ URGENT ALERT: CRITICAL AIR QUALITY";
        $mail->Body    = "
            <div style='border: 3px solid #d32f2f; padding: 25px; font-family: Arial, sans-serif; border-radius: 12px; max-width: 600px; margin: auto;'>
                <h1 style='color: #d32f2f; text-align: center;'>⚠️ CRITICAL WARNING!</h1>
                <p style='font-size: 16px;'>Magandang araw, Kababayan!</p>
                <p style='font-size: 16px;'>Ang aming monitoring sensor ay nakapagtala ng mapanganib na antas ng polusyon sa hangin (PM2.5).</p>
                
                <div style='background: #fff5f5; border: 1px solid #feb2b2; padding: 15px; text-align: center; border-radius: 8px; margin: 20px 0;'>
                    <span style='font-size: 14px; color: #702424;'>Current PM2.5 Reading:</span><br>
                    <span style='font-size: 36px; font-weight: 900; color: #d32f2f;'>$pm25 µg/m³</span>
                </div>

                <p><b>Mga dapat gawin:</b></p>
                <ul style='line-height: 1.6;'>
                    <li>Manatili sa loob ng bahay kung maaari.</li>
                    <li>Isara ang mga bintana at pintuan.</li>
                    <li>Gumamit ng face mask (N95) kung kailangang lumabas.</li>
                    <li>Iwasan ang pagsusunog ng anumang basura o dahon.</li>
                </ul>
                
                <hr style='border: 0; border-top: 1px solid #eee; margin: 20px 0;'>
                <p style='font-size: 12px; color: #666; text-align: center;'>
                    Ito ay isang automated alert mula sa <b>City Airshed Management System</b>.<br>
                    Keep safe, Calapan City!
                </p>
            </div>";

        $mail->send();
        $sentAt = gmdate('c');
        firebasePush('alert_logs', [
            'type' => 'critical_air_quality',
            'pm25' => $pm25,
            'recipient_count' => count($recipients),
            'timestamp' => $sentAt
        ]);
        foreach ($recipients as $recipient) {
            firebasePush('alert_delivery_logs', $recipient + [
                'alert_type' => 'critical_air_quality',
                'pm25' => $pm25,
                'status' => 'Sent',
                'timestamp' => $sentAt
            ]);
        }
        echo "Broadcast Successful: Alerts sent to all users.";
    } catch (Exception $e) {
        // Log the error kung sakaling mag-fail (check error log)
        echo "Email Failed: {$mail->ErrorInfo}";
    }
} else {
    echo "Air quality is normal. No alerts sent.";
}
?>
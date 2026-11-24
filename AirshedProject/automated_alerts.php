<?php
require __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;

require __DIR__ . '/../PHPMailer-master/src/Exception.php';
require __DIR__ . '/../PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer-master/src/SMTP.php';

header('Content-Type: text/plain; charset=UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('POST required.');
}

try {
    $latestReading = null;
    foreach (firebaseRows('sensor_logs', [
        'orderBy' => '"timestamp"',
        'limitToLast' => 100
    ]) as $reading) {
        if (($reading['source'] ?? '') !== 'device') {
            continue;
        }
        $timestamp = strtotime($reading['timestamp'] ?? '');
        if ($timestamp && ($latestReading === null || $timestamp > $latestReading['timestamp_unix'])) {
            $latestReading = $reading;
            $latestReading['timestamp_unix'] = $timestamp;
        }
    }

    if ($latestReading === null
        || time() - $latestReading['timestamp_unix'] < -30
        || time() - $latestReading['timestamp_unix'] > 45
        || !is_numeric($latestReading['pm25'] ?? null)) {
        echo 'No current device reading is available.';
        exit();
    }

    $pm25 = (float) $latestReading['pm25'];
    $classification = classifyPm25($pm25);
    if (!in_array($classification['level'], ['UNHEALTHY', 'HAZARDOUS'], true)) {
        echo 'No unhealthy or hazardous PM2.5 reading.';
        exit();
    }

    $lastAlertTimestamp = 0;
    foreach (firebaseRows('alert_logs', [
        'orderBy' => '"timestamp"',
        'limitToLast' => 1
    ]) as $alert) {
        $timestamp = strtotime($alert['timestamp'] ?? '');
        if ($timestamp > $lastAlertTimestamp) {
            $lastAlertTimestamp = $timestamp;
        }
    }
    if ($lastAlertTimestamp && time() - $lastAlertTimestamp < 1800) {
        echo 'Critical alert already sent within the last 30 minutes.';
        exit();
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = getenv('AIRSHED_SMTP_USERNAME');
    $mail->Password = getenv('AIRSHED_SMTP_PASSWORD');
    if (!$mail->Username || !$mail->Password) {
        throw new RuntimeException('SMTP credentials are not configured.');
    }
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    $mail->setFrom($mail->Username, 'City Airshed Alert System');

    $recipients = [];
    foreach (firebaseRows('users') as $user) {
        $email = trim((string) ($user['email'] ?? ''));
        if (($user['role'] ?? 'user') === 'user' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $mail->addBCC($email);
            $recipients[] = [
                'user_id' => (string) ($user['id'] ?? ''),
                'username' => $user['username'] ?? 'Unknown User',
                'email' => $email
            ];
        }
    }
    if (!$recipients) {
        throw new RuntimeException('No registered user email addresses are available.');
    }

    $mail->isHTML(true);
    $mail->Subject = 'URGENT ALERT: ' . $classification['label'] . ' AIR QUALITY';
    $mail->Body = "
        <div style='border: 3px solid #d32f2f; padding: 25px; font-family: Arial, sans-serif; border-radius: 12px; max-width: 600px; margin: auto;'>
            <h1 style='color: #d32f2f; text-align: center;'>AIR QUALITY ALERT</h1>
            <p>Our monitoring sensor has recorded an unhealthy PM2.5 level.</p>
            <div style='background: #fff5f5; border: 1px solid #feb2b2; padding: 15px; text-align: center; border-radius: 8px; margin: 20px 0;'>
                <span>Current PM2.5 reading:</span><br>
                <strong style='font-size: 30px; color: #d32f2f;'>" . number_format($pm25, 1) . " µg/m³ (" . htmlspecialchars($classification['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ")</strong>
            </div>
            <p>Limit prolonged outdoor activity, follow local health guidance, and use an appropriate mask if going outside.</p>
            <p style='font-size: 12px; color: #666; text-align: center;'>Automated alert from the City Airshed Management System.</p>
        </div>";

    $mail->send();
    $sentAt = gmdate('c');
    firebasePush('alert_logs', [
        'type' => 'critical_air_quality',
        'pm25' => $pm25,
        'classification' => $classification['label'],
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
    echo 'Alert sent to registered users.';
} catch (Throwable $error) {
    error_log('Automated air-quality alert failed: ' . $error->getMessage());
    http_response_code(503);
    echo 'The alert could not be sent.';
}
?>

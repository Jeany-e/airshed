# Firebase setup

## 1. Install and sign in to Firebase CLI

```powershell
npm install -g firebase-tools
firebase login
```

Run these commands from this project folder:

```powershell
firebase use airshed-4ec87
firebase deploy --only database
```

The deployed rules allow public read-only access to `sensor_logs` so the browser realtime listener works. User, report, and write access stays blocked for browsers.

## 2. Configure PHP server access

PHP writes use the server-only `FIREBASE_DATABASE_SECRET` environment variable. Do not put this value in JavaScript or commit it to the project.

For WAMP Apache, add this line to the active Apache virtual host or `httpd.conf`:

```apache
SetEnv FIREBASE_DATABASE_SECRET "YOUR_SERVER_FIREBASE_SECRET"
```

Restart Apache after changing it.

## 3. Configure authenticated device ingestion

The device must send readings to `insert_data.php` with an HTTP `POST` request
and an `X-Device-Token` header. Configure the same long, random token in the
device firmware and the PHP server environment:

```apache
SetEnv AIRSHED_DEVICE_TOKEN "YOUR_PRIVATE_DEVICE_TOKEN"
```

Send `temp`, `hum`, `co` (raw MQ ADC), and `pm25` as form fields. The endpoint
rejects requests without the token and validates sensor ranges before saving.
Do not use the former browser dashboard write endpoint; it now returns `410`.
Restart Apache after adding the token. If firmware cannot set a custom header,
update it to do so rather than placing the token in the URL.

## 4. Confirm PHP requirements

The PHP `curl` extension must be enabled. The project has already been checked with PHP and cURL enabled.

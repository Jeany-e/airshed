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

The device can keep its existing `GET` or `POST` method and sensor field names
when sending readings to `insert_data.php`. Configure the same long, random
token in the device firmware and the PHP server environment. Send it through
an `X-Device-Token` header (preferred) or as a `device_token` POST field:

```apache
SetEnv AIRSHED_DEVICE_TOKEN "YOUR_PRIVATE_DEVICE_TOKEN"
SetEnv AIRSHED_DEVICE_ID "YOUR_SENSOR_ID"
```

Send the existing `temp`/`temperature`, `hum`/`humidity`, `co`/`mq_adc`, and
`pm25` fields. The `co` value is raw MQ ADC, not calibrated CO ppm. The endpoint
rejects requests without the token and validates sensor ranges before saving.
Send `sensor_id` with each device reading when available, or configure
`AIRSHED_DEVICE_ID` as the server default so reports can filter by sensor.
Older readings without this metadata remain labeled as unidentified.
Do not put the token in the URL. The former browser dashboard write endpoint
returns `410`. Restart Apache after adding the variables.

## 4. Confirm PHP requirements

The PHP `curl` extension must be enabled. The project has already been checked with PHP and cURL enabled.

# Railway deployment

## 1. Push the project to GitHub

Create a repository containing this project folder, including `Dockerfile`,
`AirshedProject/`, and `PHPMailer-master/`. Do not commit Firebase secrets.

## 2. Create the Railway service

1. Create a new Railway project and choose **Deploy from GitHub repo**.
2. Select this repository.
3. Railway will detect the root `Dockerfile` and build the PHP/Apache service.
4. In **Variables**, add:

   `FIREBASE_DATABASE_SECRET` = your Firebase Realtime Database server secret

   `AIRSHED_DEVICE_TOKEN` = a long random token also configured on the IoT device

5. Generate a public domain under **Settings > Networking > Public Networking**.

The IoT device can keep its existing GET/POST request fields when calling
`AirshedProject/insert_data.php`, but it must send the matching token in an
`X-Device-Token` header or `device_token` POST field. Do not put the token in
the URL. Without the matching token, the endpoint rejects readings.

The Dockerfile reads Railway's `PORT` variable, so no fixed application port
needs to be configured in Railway.

## 3. Deploy Firebase rules

From the repository folder, run:

```powershell
firebase login
firebase use airshed-4ec87
firebase deploy --only database
```

## 4. Verify the deployment

Open the generated Railway URL. The root URL redirects to
`public_portal.php`. Also test registration, login, report submission, and the
public sensor readings.

## Security notes

- Add `FIREBASE_DATABASE_SECRET` only in Railway Variables.
- Store `AIRSHED_DEVICE_TOKEN` in Railway Variables and the device firmware.
- Never place the secret in JavaScript or commit it to GitHub.
- The Firebase rules file intentionally allows public reads only for
  `sensor_logs`; PHP server operations use the server-side secret.
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

5. Generate a public domain under **Settings > Networking > Public Networking**.

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
- Never place the secret in JavaScript or commit it to GitHub.
- The Firebase rules file intentionally allows public reads only for
  `sensor_logs`; PHP server operations use the server-side secret.
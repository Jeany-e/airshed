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

## 3. Confirm PHP requirements

The PHP `curl` extension must be enabled. The project has already been checked with PHP and cURL enabled.

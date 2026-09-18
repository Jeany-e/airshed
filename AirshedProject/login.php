<?php
// Session is already started inside your config.php.
include "config.php";

$error = ""; 
$success = isset($_GET['registered']) ? "Account created successfully. Please sign in." : "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim((string) ($_POST['username'] ?? ''));
    $plainPassword = (string) ($_POST['password'] ?? '');
    // Using SHA256 hash for security as per your database logic.
    $password = hash("sha256", $plainPassword);

    $matches = [];
    if ($username !== '' && $plainPassword !== '') {
        $users = firebaseRows('users');
        $matches = array_values(array_filter($users, function ($user) use ($username, $password) {
            return ($user['username'] ?? '') === $username && ($user['password'] ?? '') === $password;
        }));
    }

    if (count($matches) === 1) {
        $row = $matches[0];
        $_SESSION['user_id'] = $row['id'];
        $_SESSION['username'] = $row['username'] ?? $username;
        $_SESSION['role'] = $row['role'];
        logActivity('login', $row['id'], $row['username'] ?? $username, $row['role'] ?? 'user');

        // Redirect based on the user's role.
        if ($row['role'] == 'admin') {
            header("Location: admin_dashboard.php");
        } else {
            header("Location: user_dashboard.php");
        }
        exit();
    } else {
        $error = "Invalid username or password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | City Airshed MS</title>
    <style>
        :root {
            --primary: #174ea6;
            --blue-anim: linear-gradient(135deg, #b8e1f7, #86c6ec, #d7f0ff, #69addb);
        }

        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--blue-anim);
            background-size: 280% 280%;
            animation: gradientBG 24s ease-in-out infinite;
            overflow: hidden;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .login-container {
            background: rgba(255, 255, 255, 0.98);
            padding: 32px 40px 44px;
            box-sizing: border-box;
            border-radius: 30px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.4);
            width: 90%;
            max-width: 440px;
            text-align: center;
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.5);
            animation: authPanelFloat 10s ease-in-out infinite;
            position: relative;
        }

        @keyframes authPanelFloat {
            0%, 100% { opacity: 1; transform: translateY(0); }
            50% { opacity: 1; transform: translateY(-4px); }
        }

        /* NEW: Back Button Styling */
        .back-home {
            text-align: left;
            margin-bottom: 25px;
        }

        .back-home a {
            text-decoration: none;
            color: var(--primary);
            font-size: 14px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
            opacity: 0.7;
        }

        .back-home a:hover {
            opacity: 1;
            transform: translateX(-5px);
        }

        h2 {
            color: var(--primary);
            font-size: 36px;
            font-weight: 800;
            margin: 0 0 10px 0;
            letter-spacing: -1.5px;
        }

        .sub-text {
            color: #64748b;
            font-size: 16px;
            margin-bottom: 40px;
            display: block;
            font-weight: 500;
        }

        .input-group {
            margin-bottom: 25px;
            text-align: left;
        }

        label {
            display: block;
            margin-bottom: 10px;
            font-size: 14px;
            font-weight: 700;
            color: #334155;
            text-transform: uppercase;
        }

        input {
            width: 100%;
            padding: 18px;
            border: 2px solid #e2e8f0;
            border-radius: 15px;
            font-size: 16px;
            box-sizing: border-box;
            transition: all 0.3s ease;
            background: #f1f5f9;
        }

        input:focus {
            border-color: var(--primary);
            background: #fff;
            outline: none;
            box-shadow: 0 0 0 5px rgba(23, 78, 166, 0.15);
        }

        button {
            width: 100%;
            padding: 18px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 20px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 15px;
            box-shadow: 0 10px 20px rgba(23, 78, 166, 0.25);
        }

        button:hover {
            background: #123d82;
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(23, 78, 166, 0.4);
        }

        .error-msg {
            background: #fef2f2;
            color: #b91c1c;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 30px;
            font-size: 15px;
            font-weight: 600;
            border: 1px solid #fee2e2;
        }

        .success-msg {
            background: #f0fdf4;
            color: #15803d;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 30px;
            font-size: 15px;
            font-weight: 600;
            border: 1px solid #dcfce7;
        }

        .show-password {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            color: #64748b;
            font-size: 13px;
            cursor: pointer;
        }

        .show-password input { width: auto; }

        .footer-text {
            margin-top: 40px;
            font-size: 15px;
            color: #64748b;
        }

        .footer-text a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 800;
            transition: 0.3s;
        }

        .footer-text a:hover {
            text-decoration: underline;
        }

        @media (max-width: 520px) {
            body {
                padding: 18px 12px;
                overflow: auto;
            }

            .login-container {
                width: 90%;
                max-width: 420px;
                padding: 24px 16px 30px;
                border-radius: 22px;
            }

            h2 {
                font-size: 30px;
            }

            .sub-text {
                font-size: 14px;
                margin-bottom: 28px;
            }

            button {
                font-size: 18px;
                padding: 16px;
            }
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="back-home">
        <a href="public_portal.php">← Back to Public Portal</a>
    </div>

    <h2>Welcome Back</h2>
    <span class="sub-text">Airshed Monitoring System | Calapan City</span>

    <?php if ($error !== ""): ?>
        <div class="error-msg"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success !== ""): ?>
        <div class="success-msg"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="input-group">
            <label>Username</label>
            <input type="text" name="username" required>
        </div>
        <div class="input-group">
            <label>Password</label>
            <input type="password" name="password" required>
            <label class="show-password"><input type="checkbox" onclick="togglePassword('password')"> Show password</label>
        </div>
        <button type="submit">Sign In to Dashboard</button>
    </form>

    <div class="footer-text">
        New here? <a href="register.php">Create an Account</a>
        <br><br>
        &copy; 2026 Calapan City Capstone Project
    </div>
</div>

<script>
function togglePassword(fieldName) {
    const field = document.querySelector(`input[name="${fieldName}"]`);
    if (field) field.type = field.type === 'password' ? 'text' : 'password';
}
</script>

</body>
</html>
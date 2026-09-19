<?php
include "config.php";

$message = "";
$messageClass = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $plainPassword = $_POST['password'] ?? '';

    if ($username === '' || $email === '' || $phone === '' || trim($plainPassword) === '') {
        $message = "Please complete all required fields.";
        $messageClass = "error-msg";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageClass = "error-msg";
    }

    if ($message !== '') {
        // Keep the form visible so the user can correct the missing or invalid data.
    } else {
    // Public registration can never create an administrator account.
    $role = 'user';
    // Standard SHA256 hashing to match your login logic.
    $password = hash("sha256", $plainPassword);

    // Check if username or email already exists
    $users = firebaseRows('users');
    $exists = array_filter($users, function ($user) use ($username, $email) {
        return ($user['username'] ?? '') === $username || ($user['email'] ?? '') === $email;
    });

    if (count($exists) > 0) {
        $message = "Username or Email already exists!";
        $messageClass = "error-msg";
    } else {
        // Updated INSERT statement with Email and Phone
        $user = firebasePush('users', [
            'username' => $username,
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
            'role' => $role,
            'created_at' => gmdate('c')
        ]);

        if ($user && isset($user['name'])) {
            header('Location: login.php?registered=1');
            exit();
        } else {
            $message = "Registration failed. Please try again.";
            $messageClass = "error-msg";
        }
    }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | City Airshed MS</title>
    <style>
        :root {
            --primary: #174ea6;
            --blue-anim: linear-gradient(135deg, #78c5ef, #bce8fa, #4f9bd3);
        }

        body {
            margin: 0; padding: 0;
            font-family: 'Segoe UI', sans-serif;
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            background: var(--blue-anim);
            background-size: 240% 240%;
            animation: gradientBG 15s ease infinite;
            overflow-x: hidden;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .register-container {
            background: rgba(255, 255, 255, 0.98);
            padding: 40px 50px 50px 50px;
            border-radius: 30px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.4);
            width: 90%;
            max-width: 500px; 
            text-align: center;
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.5);
            margin: 40px 20px;
            animation: authPanelEntrance 0.65s ease-out both;
        }

        @keyframes authPanelEntrance {
            from { opacity: 0; transform: translateY(24px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Back Button Styling */
        .back-home {
            text-align: left;
            margin-bottom: 20px;
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

        h2 { color: var(--primary); font-size: 32px; font-weight: 800; margin: 0 0 5px 0; letter-spacing: -1px; }
        .sub-text { color: #64748b; font-size: 15px; margin-bottom: 30px; display: block; font-weight: 500; }

        .input-group { margin-bottom: 18px; text-align: left; }
        label { display: block; margin-bottom: 8px; font-size: 12px; font-weight: 800; color: #334155; text-transform: uppercase; }

        input, select {
            width: 100%; padding: 15px;
            border: 2px solid #e2e8f0; border-radius: 12px;
            font-size: 15px; box-sizing: border-box;
            background: #f1f5f9; transition: 0.3s;
        }

        input:focus { border-color: var(--primary); background: #fff; outline: none; box-shadow: 0 0 0 4px rgba(23, 78, 166, 0.1); }

        button {
            width: 100%; padding: 16px;
            background: var(--primary); color: white;
            border: none; border-radius: 12px;
            font-size: 18px; font-weight: 700;
            cursor: pointer; transition: 0.3s; margin-top: 15px;
            box-shadow: 0 10px 20px rgba(23, 78, 166, 0.2);
        }

        button:hover { background: #123d82; transform: translateY(-2px); box-shadow: 0 15px 25px rgba(23, 78, 166, 0.3); }

        .error-msg { background: #fef2f2; color: #b91c1c; padding: 12px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; border: 1px solid #fee2e2; font-weight: 600; }
        .success-msg { background: #f0fdf4; color: #15803d; padding: 12px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; border: 1px solid #dcfce7; font-weight: 600; }

        .show-password { display: flex; align-items: center; gap: 8px; margin-top: 9px; color: #64748b; font-size: 13px; cursor: pointer; }
        .show-password input { width: auto; }
        
        .footer-text { margin-top: 25px; font-size: 15px; color: #64748b; }
        .footer-text a { color: var(--primary); font-weight: 800; text-decoration: none; }
        .footer-text a:hover { text-decoration: underline; }

        @media (max-width: 520px) {
            body {
                padding: 18px 12px;
                overflow: auto;
            }

            .register-container {
                width: 92%;
                max-width: 440px;
                margin: 0;
                padding: 24px 16px 28px;
                border-radius: 20px;
            }

            h2 {
                font-size: 28px;
            }

            .sub-text {
                font-size: 14px;
            }

            button {
                font-size: 17px;
                padding: 14px;
            }
        }
    </style>
</head>
<body>

<div class="register-container">
    <div class="back-home">
        <a href="public_portal.php">← Back to Public Portal</a>
    </div>

    <h2>Join Airshed</h2>
    <span class="sub-text">Environmental Monitoring | Calapan City</span>

    <?php if ($message !== ""): ?>
        <div class="<?php echo $messageClass; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="input-group">
            <label>Full Username</label>
            <input type="text" name="username" required>
        </div>
        <div class="input-group">
            <label>Email Address</label>
            <input type="email" name="email" required>
        </div>
        <div class="input-group">
            <label>Phone Number</label>
            <input type="text" name="phone" required>
        </div>
        <div class="input-group">
            <label>Password</label>
            <input type="password" name="password" required>
            <label class="show-password"><input type="checkbox" onclick="togglePassword('password')"> Show password</label>
        </div>
        <button type="submit">Create My Account</button>
    </form>

    <div class="footer-text">
        Already have an account? <a href="login.php">Sign In</a>
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
<?php
/**
 * PUBLIC REPORTING PAGE - CALAPAN CITY AIRSHED MS
 * Redirects back to the dashboard after submission.
 */
include "config.php"; 

$msg = "";
$submitted = false;
$isAuthenticatedUser = isset($_SESSION['user_id']);
$returnUrl = $isAuthenticatedUser ? 'user_dashboard.php' : 'public_portal.php';
$returnLabel = $isAuthenticatedUser ? 'User Dashboard' : 'Public Portal';

if (isset($_POST['submit_report'])) {
    $type = trim($_POST['incident_type']);
    $loc = trim($_POST['location']);
    $desc = trim($_POST['description']);
    
    // Anonymous = 0, User = Session ID
    $u_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

    $saved = firebasePush('pollution_reports', [
        'user_id' => (string) $u_id,
        'incident_type' => $type,
        'location' => $loc,
        'description' => $desc,
        'status' => 'Pending',
        'reported_at' => gmdate('c')
    ]);

    if ($saved) {
        $submitted = true;
        $msg = "
        <div class='success-overlay'>
            <div class='success-card'>
                <span style='font-size: 60px;'>✅</span>
                <h3>REPORT SENT!</h3>
                <p>Thank you for your report. Returning to $returnLabel...</p>
            </div>
        </div>
        <script>
            setTimeout(function(){
                window.location.href = '$returnUrl';
            }, 3000);
        </script>";
    } else {
        $msg = "<div class='error-banner'>Error saving report.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Incident | Calapan Airshed</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #174ea6; --danger: #d32f2f; --bg: #e5f4ff; --blue-anim: linear-gradient(135deg, #78c5ef, #bce8fa, #4f9bd3); }
        body { margin: 0; font-family: 'Inter', sans-serif; background: linear-gradient(120deg, #d5efff, #f8fcff, #c6e9fb); background-size: 300% 300%; display: flex; align-items: stretch; min-height: 100vh; }

        .report-shell { width: 100%; min-height: 100vh; display: flex; align-items: stretch; }
        .report-sidebar { flex: 0 0 280px; min-height: 100vh; padding: 30px 20px; border-radius: 0; background: var(--blue-anim); box-shadow: 4px 0 24px rgba(36,104,157,.18); }
        .report-sidebar h3 { margin: 0 0 20px; padding-bottom: 20px; border-bottom: 2px solid rgba(23,78,166,.16); color: var(--primary); text-align: center; font-size: 20px; }
        .report-sidebar a { display: block; padding: 11px 13px; margin: 6px 0; border-radius: 10px; color: #17466f; text-decoration: none; font-size: 13px; font-weight: 700; }
        .report-sidebar a:hover, .report-sidebar a.active { background: rgba(255,255,255,.78); color: var(--primary); }
        .report-content { flex: 1; display: flex; align-items: center; justify-content: center; min-width: 0; padding: 40px; overflow-y: auto; }
        .mobile-menu-toggle, .sidebar-backdrop { display: none; }
        
        .report-box { 
            width: 90%; max-width: 450px; background: #fff; padding: 40px; 
            border-radius: 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.1); 
            box-sizing: border-box;
            border: 1px solid #c9e4f2;
            animation: reportPanelFloat 9s ease-in-out infinite;
        }

        @keyframes reportPanelFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-4px); }
        }
        
        h2 { color: var(--primary); font-weight: 900; text-align: center; margin-bottom: 5px; }
        .sub { text-align: center; color: #666; margin-bottom: 30px; font-size: 14px; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 800; font-size: 12px; color: #888; margin-bottom: 8px; text-transform: uppercase; }
        
        .form-group select, .form-group input, .form-group textarea {
            width: 100%; padding: 15px; border: 2px solid #eee; border-radius: 15px; font-family: inherit; font-size: 16px; box-sizing: border-box;
        }

        .btn-send {
            width: 100%; padding: 18px; background: var(--danger); color: white; border: none; border-radius: 15px;
            font-size: 18px; font-weight: 800; cursor: pointer; transition: 0.3s;
        }
        .btn-send:hover { transform: scale(1.02); background: #b71c1c; }

        .btn-cancel { display: block; text-align: center; margin-top: 20px; text-decoration: none; color: #999; font-weight: 700; font-size: 14px; }

        /* Success Overlay */
        .success-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255,255,255,0.95); display: flex; justify-content: center; align-items: center; z-index: 999;
        }
        .success-card { text-align: center; animation: pop 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes pop { from { transform: scale(0.5); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        @media (max-width: 520px) {
            body {
                padding: 0;
                align-items: flex-start;
            }

            .report-shell {
                width: 100%;
                flex-direction: column;
                align-items: stretch;
                gap: 14px;
            }

            .report-sidebar {
                flex-basis: auto;
                padding: 14px 10px;
                border-radius: 18px;
            }

            .report-sidebar h3 {
                margin-bottom: 8px;
                font-size: 16px;
            }

            .report-sidebar a {
                display: inline-block;
                width: calc(50% - 8px);
                box-sizing: border-box;
                margin: 3px;
                padding: 9px 6px;
                text-align: center;
                font-size: 11px;
            }

            .report-box {
                width: 100%;
                max-width: 430px;
                padding: 22px 16px 28px;
                border-radius: 20px;
                margin: 0 auto;
            }

            h2 {
                font-size: 28px;
            }

            .form-group select, .form-group input, .form-group textarea {
                padding: 13px 12px;
                font-size: 15px;
            }

            .btn-send {
                padding: 15px;
                font-size: 17px;
            }
        }

        @media (max-width: 760px) {
            .mobile-menu-toggle {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                position: fixed;
                top: 12px;
                left: 12px;
                z-index: 1002;
                width: 42px;
                height: 42px;
                border: 0;
                border-radius: 12px;
                background: var(--primary);
                color: #fff;
                font-size: 21px;
                cursor: pointer;
                box-shadow: 0 8px 20px rgba(23,78,166,.25);
            }

            .report-shell {
                width: 100%;
                display: block;
            }

            .report-sidebar {
                position: fixed;
                inset: 0 auto 0 0;
                z-index: 1000;
                width: min(290px, 86vw);
                height: 100vh;
                box-sizing: border-box;
                padding: 72px 20px 20px;
                border-radius: 0;
                overflow-y: auto;
                transform: translateX(-105%);
                transition: transform .25s ease;
            }

            .report-sidebar.open { transform: translateX(0); }
            .report-sidebar h3 { margin-bottom: 20px; font-size: 20px; }
            .report-sidebar a {
                display: block;
                width: 100%;
                box-sizing: border-box;
                margin: 6px 0;
                padding: 12px 14px;
                text-align: left;
                font-size: 13px;
            }

            .sidebar-backdrop {
                position: fixed;
                inset: 0;
                z-index: 999;
                background: rgba(10,18,60,.45);
            }

            .sidebar-backdrop.open { display: block; }
            .report-content {
                width: 100%;
                box-sizing: border-box;
                align-items: flex-start;
                padding: 16px 12px;
            }
        }
    </style>
</head>
<body>

<button class="mobile-menu-toggle" type="button" aria-label="Open navigation" aria-expanded="false" onclick="toggleReportSidebar()">☰</button>
<div class="sidebar-backdrop" onclick="closeReportSidebar()"></div>

<?php if ($submitted) echo $msg; ?>

<div class="report-shell">
    <aside class="report-sidebar">
        <h3>CITY AIRSHED</h3>
        <?php if ($isAuthenticatedUser): ?>
            <a href="user_dashboard.php">📊 Real-time Monitoring</a>
            <a href="report.php" class="active">🚨 Report Pollution / Burning</a>
            <a href="sensor_reports.php">📈 Sensor Reports</a>
            <a href="user_dashboard.php#v-profile">👤 My Profile</a>
            <a href="logout.php">🚪 Sign Out</a>
        <?php else: ?>
            <a href="public_portal.php">Public Portal</a>
            <a href="login.php">User Login</a>
            <a href="register.php">Create Account</a>
        <?php endif; ?>
    </aside>

    <div class="report-content">
<div class="report-box">
    <h2>🚨 Report Pollution</h2>
    <p class="sub">Your report helps the Calapan Airshed office take action.</p>

    <form method="POST">
        <div class="form-group">
            <label>Incident Type</label>
            <select name="incident_type" required>
                <option value="House Fire">🏠 House Fire / Nasusunog na Bahay</option>
                <option value="Grass or Forest Fire">🌳 Grass or Forest Fire / Sunog sa Damuhan o Gubat</option>
                <option value="Open Burning">🔥 Illegal Burning (Pagsusunog)</option>
                <option value="Garbage Burning">🗑️ Garbage Burning / Pagsusunog ng Basura</option>
                <option value="Vehicle Smoke">💨 Thick Vehicle Smoke</option>
                <option value="Industrial">🏭 Factory Emission</option>
                <option value="Heavy Smoke or Bad Odor">🌫️ Heavy Smoke or Strong Odor</option>
            </select>
        </div>

        <div class="form-group">
            <label>Location</label>
            <input type="text" name="location" placeholder="Where did this happen?" required>
        </div>

        <div class="form-group">
            <label>Details</label>
            <textarea name="description" rows="4" placeholder="Describe the incident..." required></textarea>
        </div>

        <button type="submit" name="submit_report" class="btn-send">SUBMIT REPORT</button>
        <a href="<?php echo htmlspecialchars($returnUrl, ENT_QUOTES); ?>" class="btn-cancel">← Back to <?php echo htmlspecialchars($returnLabel); ?></a>
    </form>
</div>
</div>
</div>

<script>
    function toggleReportSidebar() {
        const sidebar = document.querySelector('.report-sidebar');
        const backdrop = document.querySelector('.sidebar-backdrop');
        const toggle = document.querySelector('.mobile-menu-toggle');
        const isOpen = sidebar.classList.toggle('open');
        backdrop.classList.toggle('open', isOpen);
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    function closeReportSidebar() {
        document.querySelector('.report-sidebar').classList.remove('open');
        document.querySelector('.sidebar-backdrop').classList.remove('open');
        document.querySelector('.mobile-menu-toggle').setAttribute('aria-expanded', 'false');
    }
</script>

</body>
</html>
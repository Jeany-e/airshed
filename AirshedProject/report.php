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
        :root { --primary: #1a237e; --danger: #d32f2f; --bg: #f0f2f5; }
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg); display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        
        .report-box { 
            width: 90%; max-width: 450px; background: #fff; padding: 40px; 
            border-radius: 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.1); 
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
                padding: 20px 12px;
                align-items: flex-start;
            }

            .report-box {
                width: 92%;
                max-width: 430px;
                padding: 22px 16px 28px;
                border-radius: 20px;
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
    </style>
</head>
<body>

<?php if ($submitted) echo $msg; ?>

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

</body>
</html>
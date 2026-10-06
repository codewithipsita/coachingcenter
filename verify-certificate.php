<?php
// verify-certificate.php — Public page. Anyone who scans the certificate QR lands here.
include('db.php');

// Create table if somehow missing (defensive)
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS saved_certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cert_uid VARCHAR(64) UNIQUE NOT NULL,
    student_id INT NOT NULL,
    student_name VARCHAR(255) NOT NULL,
    course_name VARCHAR(255) NOT NULL,
    enrollment_id INT DEFAULT 0,
    bg_image VARCHAR(255) DEFAULT '',
    issue_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$cert_uid = trim($_GET['uid'] ?? '');
$cert     = null;
$valid    = false;

if (!empty($cert_uid)) {
    $uid_esc = mysqli_real_escape_string($conn, $cert_uid);
    $res = mysqli_query($conn,
        "SELECT * FROM saved_certificates WHERE cert_uid = '$uid_esc' LIMIT 1"
    );
    if ($res && mysqli_num_rows($res) > 0) {
        $cert  = mysqli_fetch_assoc($res);
        $valid = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $valid ? 'Valid Certificate' : 'Invalid Certificate'; ?> — Coaching Center</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: <?php echo $valid ? 'linear-gradient(135deg,#f0fdf4,#dcfce7,#bbf7d0)' : 'linear-gradient(135deg,#fff1f2,#ffe4e6,#fecdd3)'; ?>;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: #fff;
            border-radius: 24px;
            padding: 50px 40px;
            max-width: 520px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.12);
            animation: popIn 0.5s cubic-bezier(0.175,0.885,0.32,1.275) forwards;
        }
        @keyframes popIn {
            from { opacity:0; transform: scale(0.85) translateY(20px); }
            to   { opacity:1; transform: scale(1)    translateY(0); }
        }
        .big-icon {
            font-size: 80px;
            margin-bottom: 20px;
            display: block;
        }
        .status-title {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 10px;
            color: <?php echo $valid ? '#15803d' : '#be123c'; ?>;
        }
        .status-subtitle {
            font-size: 15px;
            color: #64748b;
            margin-bottom: 32px;
            line-height: 1.6;
        }
        .info-box {
            background: <?php echo $valid ? '#f0fdf4' : '#fff1f2'; ?>;
            border: 1px solid <?php echo $valid ? '#bbf7d0' : '#fecdd3'; ?>;
            border-radius: 14px;
            padding: 24px;
            text-align: left;
            margin-bottom: 28px;
        }
        .info-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 14px;
        }
        .info-row:last-child { margin-bottom: 0; }
        .info-label {
            font-size: 12px;
            font-weight: 700;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            min-width: 100px;
        }
        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
        }
        .divider {
            width: 60px;
            height: 4px;
            border-radius: 4px;
            background: <?php echo $valid ? '#4ade80' : '#f87171'; ?>;
            margin: 0 auto 28px;
        }
        .uid-box {
            font-size: 11px;
            color: #94a3b8;
            word-break: break-all;
            margin-top: 20px;
            padding: 10px;
            background: #f8fafc;
            border-radius: 8px;
            font-family: monospace;
        }
        .back-btn {
            display: inline-block;
            margin-top: 6px;
            padding: 10px 24px;
            background: #1e293b;
            color: #fff;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
        }
        .back-btn:hover { background: #334155; }
        .watermark {
            font-size: 11px;
            color: #cbd5e1;
            margin-top: 24px;
        }
    </style>
</head>
<body>
<div class="card">

    <?php if ($valid && $cert): ?>

        <!-- ── VALID CERTIFICATE ──────────────────────────────────── -->
        <span class="big-icon">✅</span>
        <div class="status-title">Valid Certificate</div>
        <div class="divider"></div>
        <p class="status-subtitle">
            This certificate has been officially issued by our coaching center<br>and is authentic.
        </p>

        <div class="info-box">
            <div class="info-row">
                <span class="info-label">Student</span>
                <span class="info-value"><?php echo htmlspecialchars($cert['student_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Course</span>
                <span class="info-value"><?php echo htmlspecialchars($cert['course_name']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Issued On</span>
                <span class="info-value"><?php echo date('F d, Y', strtotime($cert['issue_date'])); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Status</span>
                <span class="info-value" style="color:#16a34a;font-weight:700;">
                    <i class="fa-solid fa-circle-check"></i> Verified &amp; Authentic
                </span>
            </div>
        </div>

        <div class="uid-box">
            Certificate ID: <?php echo htmlspecialchars($cert['cert_uid']); ?>
        </div>

    <?php else: ?>

        <!-- ── INVALID CERTIFICATE ────────────────────────────────── -->
        <span class="big-icon">❌</span>
        <div class="status-title">Invalid Certificate</div>
        <div class="divider"></div>
        <p class="status-subtitle">
            This certificate could not be verified.<br>
            It may be fake, expired, or the QR code is damaged.
        </p>

        <div class="info-box">
            <div class="info-row">
                <span class="info-label">Status</span>
                <span class="info-value" style="color:#dc2626;font-weight:700;">
                    <i class="fa-solid fa-circle-xmark"></i> Not found in our records
                </span>
            </div>
            <div class="info-row">
                <span class="info-label">Action</span>
                <span class="info-value">Please contact the coaching center to verify manually.</span>
            </div>
        </div>

    <?php endif; ?>

    <p class="watermark">Coaching Center Certificate Verification System</p>
</div>
</body>
</html>

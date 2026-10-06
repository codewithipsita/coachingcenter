<?php
// student/my-certificate.php — Shows saved certificates for the logged-in student

if (session_status() === PHP_SESSION_NONE) session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

include('../db.php');

$student_id = intval($_SESSION['user_id']);

// ── Handle Delete Certificate (simple form POST) ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_cert'])) {
    $del_uid = trim($_POST['cert_uid'] ?? '');
    if ($del_uid !== '') {
        $uid_esc = mysqli_real_escape_string($conn, $del_uid);
        // Only allow student to delete their OWN certificate
        mysqli_query($conn,
            "DELETE FROM saved_certificates WHERE cert_uid = '$uid_esc' AND student_id = $student_id"
        );
    }
    header("Location: my-certificate.php");
    exit();
}

include('header.php');
include('sidebar.php');

$student_id = intval($_SESSION['user_id']);

// Fetch this student's saved certificates
$certs = [];
// Make sure table exists
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

$cq = mysqli_query($conn,
    "SELECT * FROM saved_certificates WHERE student_id = $student_id ORDER BY created_at DESC"
);
if ($cq) {
    while ($row = mysqli_fetch_assoc($cq)) {
        $certs[] = $row;
    }
}

// Build base URL dynamically — no hardcoding
$protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'];
$root_path = rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/'); // e.g. /coatchingcenter
$base_url  = $protocol . '://' . $host . $root_path;
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- QR Code JS library (no server-side needed) -->
<script src="https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs@gh-pages/qrcode.min.js"></script>

<style>
    /* ── No-scroll layout ── everything fits in one screen ── */
    html, body {
        overflow: hidden !important;   /* prevent page scroll */
        height: 100%;
    }
    .layout-wrapper {
        height: calc(100vh - 60px); /* subtract header height */
        overflow: hidden;
    }
    .main-content {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg,#f0f4ff,#f8fafc,#fdf4ff);
        height: 100%;              /* fill the wrapper */
        overflow-y: auto;          /* scroll INSIDE main-content only if needed */
        padding: 24px 32px;
        box-sizing: border-box;
    }
    .page-header {
        display: flex; align-items: center; gap: 14px;
        margin-bottom: 16px;
    }
    .page-header .icon {
        width: 42px; height: 42px; border-radius: 12px;
        background: linear-gradient(135deg,#6366f1,#8b5cf6);
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 18px;
        box-shadow: 0 4px 14px rgba(99,102,241,0.35);
        flex-shrink: 0;
    }
    .page-header h1 { font-size: 22px; font-weight: 800; color: #1e293b; margin:0; }
    .page-header p  { font-size: 13px; color:#94a3b8; margin:2px 0 0; }

    /* Empty state */
    .empty-box {
        background:#fff; border-radius:20px; padding:60px 30px; text-align:center;
        box-shadow:0 4px 20px rgba(0,0,0,0.06);
    }
    .empty-box .ei { font-size:56px; margin-bottom:14px; opacity:.6; }
    .empty-box h3 { font-size:18px; font-weight:700; color:#1e293b; margin-bottom:6px; }
    .empty-box p  { color:#94a3b8; font-size:14px; }

    /* One cert card fills the available screen */
    .cert-card {
        background:#fff;
        border-radius:18px;
        padding:18px 20px;
        box-shadow:0 4px 20px rgba(0,0,0,0.08);
        border:1px solid #e8eaf6;
        margin-bottom:20px;
        animation: fadeInUp 0.4s ease-out;
    }
    @keyframes fadeInUp {
        from { opacity:0; transform:translateY(16px); }
        to   { opacity:1; transform:translateY(0); }
    }
    .cert-card-header {
        display:flex; align-items:center; justify-content:space-between;
        margin-bottom:12px; flex-wrap:wrap; gap:8px;
    }
    .cert-card-header h2 {
        font-size:15px; font-weight:700; color:#1e293b;
        display:flex; align-items:center; gap:8px; margin:0;
    }
    .course-pill {
        background:linear-gradient(135deg,#6366f1,#8b5cf6);
        color:#fff; font-size:11px; font-weight:700;
        padding:2px 12px; border-radius:20px;
    }
    /* Canvas fills width, limited height so no scroll */
    .canvas-wrap {
        width:100%; overflow:hidden; border-radius:10px;
        border:2px solid #e2e8f0;
        box-shadow:0 4px 16px rgba(0,0,0,0.08);
        background:#f1f5f9; text-align:center;
        max-height: calc(100vh - 280px);  /* limit height so it fits on screen */
    }
    canvas {
        max-width:100%;
        max-height: calc(100vh - 284px);  /* scale down to fit */
        display:block; margin:0 auto;
        object-fit: contain;
    }
    /* Action buttons row */
    .cert-actions {
        display:flex; align-items:center; gap:10px;
        margin-top:12px; flex-wrap:wrap;
    }
    .btn-dl {
        display:inline-flex; align-items:center; gap:7px;
        padding:9px 20px;
        background:linear-gradient(135deg,#10b981,#059669);
        color:#fff; border:none; border-radius:9px;
        font-size:13px; font-weight:700;
        font-family:'Poppins',sans-serif; cursor:pointer;
        box-shadow:0 3px 10px rgba(16,185,129,0.35);
        transition:transform .2s;
    }
    .btn-dl:hover { transform:translateY(-2px); }
    .btn-del {
        display:inline-flex; align-items:center; gap:7px;
        padding:9px 20px;
        background:linear-gradient(135deg,#ef4444,#dc2626);
        color:#fff; border:none; border-radius:9px;
        font-size:13px; font-weight:700;
        font-family:'Poppins',sans-serif; cursor:pointer;
        box-shadow:0 3px 10px rgba(239,68,68,0.35);
        transition:transform .2s;
    }
    .btn-del:hover { transform:translateY(-2px); }
    .cert-meta  { font-size:12px; color:#94a3b8; }
    .cert-uid-mini { font-size:10px; color:#cbd5e1; font-family:monospace; word-break:break-all; }
</style>

<div class="main-content">

    <div class="page-header">
        <span class="icon"><i class="fa-solid fa-certificate"></i></span>
        <div>
            <h1>My Certificates</h1>
            <p>Download or manage your issued certificates</p>
        </div>
    </div>

    <?php if (empty($certs)): ?>
        <div class="empty-box">
            <div class="ei">🎓</div>
            <h3>No Certificates Yet</h3>
            <p>Your certificates will appear here once the admin generates and saves them.<br>
               Make sure your course payment is approved.</p>
        </div>
    <?php else: ?>

        <?php foreach ($certs as $i => $cert): ?>
        <?php
            $bg_path    = !empty($cert['bg_image']) ? "../admin/" . $cert['bg_image'] : "";
            $verify_url = $base_url . "/verify-certificate.php?uid=" . $cert['cert_uid'];
            $canvas_id  = "cert_canvas_" . $i;
        ?>
        <div class="cert-card">
            <div class="cert-card-header">
                <h2>
                    <i class="fa-solid fa-certificate" style="color:#6366f1;"></i>
                    <?php echo htmlspecialchars($cert['course_name']); ?>
                    <span class="course-pill">Issued</span>
                </h2>
                <span class="cert-meta"><?php echo date('d M Y', strtotime($cert['issue_date'])); ?></span>
            </div>

            <!-- Certificate canvas -->
            <div class="canvas-wrap">
                <canvas id="<?php echo $canvas_id; ?>"></canvas>
            </div>

            <!-- Action buttons -->
            <div class="cert-actions">

                <!-- Download button -->
                <button class="btn-dl"
                    onclick="downloadCert(
                        '<?php echo $canvas_id; ?>',
                        '<?php echo addslashes($cert['student_name']); ?>',
                        '<?php echo addslashes($cert['course_name']); ?>'
                    )">
                    <i class="fa-solid fa-download"></i> Download PNG
                </button>

                <!-- Delete button — simple form POST, no AJAX -->
                <form method="POST" action="my-certificate.php"
                    onsubmit="return confirm('Are you sure you want to delete this certificate? This cannot be undone.');">
                    <input type="hidden" name="delete_cert" value="1">
                    <input type="hidden" name="cert_uid"    value="<?php echo htmlspecialchars($cert['cert_uid'], ENT_QUOTES); ?>">
                    <button type="submit" class="btn-del">
                        <i class="fa-solid fa-trash"></i> Delete
                    </button>
                </form>

                <div>
                    <div class="cert-meta">Certificate ID:</div>
                    <div class="cert-uid-mini"><?php echo htmlspecialchars($cert['cert_uid']); ?></div>
                </div>
            </div>
        </div>

        <!-- JS: draw this certificate on its canvas -->
        <script>
        (function() {
            var canvasId    = "<?php echo $canvas_id; ?>";
            var bgPath      = "<?php echo addslashes($bg_path); ?>";
            var studentName = "<?php echo addslashes(htmlspecialchars($cert['student_name'])); ?>";
            var courseName  = "<?php echo addslashes(htmlspecialchars($cert['course_name'])); ?>";
            var issueDate   = "<?php echo date('F d, Y', strtotime($cert['issue_date'])); ?>";
            var verifyUrl   = "<?php echo addslashes($verify_url); ?>";
            window.addEventListener('load', function() {
                drawCertOnCanvas(canvasId, bgPath, studentName, courseName, issueDate, verifyUrl);
            });
        })();
        </script>

        <?php endforeach; ?>

    <?php endif; ?>

</div><!-- .main-content -->

<script>
// ── Draw a certificate on the given canvas ────────────────────────────────────
function drawCertOnCanvas(canvasId, bgPath, studentName, courseName, issueDate, verifyUrl) {
    var canvas = document.getElementById(canvasId);
    if (!canvas) return;
    var CERT_W = 1122, CERT_H = 794;  // Always fixed A4 landscape size
    var ctx = canvas.getContext('2d');

    // Set fixed canvas size FIRST (same size with or without background)
    canvas.width  = CERT_W;
    canvas.height = CERT_H;

    if (bgPath !== "") {
        var img = new Image();
        img.crossOrigin = "anonymous";
        img.src = bgPath;
        img.onload = function() {
            // Scale bg image to FILL the fixed A4 canvas (no stretching of canvas)
            ctx.drawImage(img, 0, 0, CERT_W, CERT_H);
            drawTextAndQR(ctx, canvas, studentName, courseName, issueDate, verifyUrl);
        };
        img.onerror = function() {
            drawFallbackBg(ctx, canvas, studentName, courseName, issueDate, verifyUrl);
        };
    } else {
        drawFallbackBg(ctx, canvas, studentName, courseName, issueDate, verifyUrl);
    }
}

// ── Draw all text + QR on canvas ─────────────────────────────────────────────
function drawTextAndQR(ctx, canvas, studentName, courseName, issueDate, verifyUrl) {
    var W = canvas.width, H = canvas.height;

    // White strip in center for readability
    ctx.save();
    ctx.fillStyle = "rgba(255,255,255,0.55)";
    ctx.fillRect(0, H * 0.28, W, H * 0.50);
    ctx.restore();

    var cx = W / 2;

    // "CERTIFICATE OF COMPLETION"
    ctx.textAlign = "center";
    ctx.font = "bold " + Math.round(W * 0.026) + "px Georgia,serif";
    ctx.fillStyle = "#6366f1";
    ctx.fillText("CERTIFICATE OF COMPLETION", cx, H * 0.33);

    // divider line
    ctx.beginPath();
    ctx.moveTo(cx - W*0.25, H*0.355); ctx.lineTo(cx + W*0.25, H*0.355);
    ctx.strokeStyle = "#c4b5fd"; ctx.lineWidth = Math.max(1, W*0.002); ctx.stroke();

    // "This is to certify that"
    ctx.font = Math.round(W * 0.020) + "px Arial,sans-serif";
    ctx.fillStyle = "#64748b";
    ctx.fillText("This is to certify that", cx, H * 0.395);

    // Student Name
    ctx.font = "bold " + Math.round(W * 0.060) + "px Georgia,'Times New Roman',serif";
    ctx.fillStyle = "#1e293b";
    ctx.fillText(studentName, cx, H * 0.485);

    // Underline
    var nw = ctx.measureText(studentName).width;
    ctx.beginPath();
    ctx.moveTo(cx - nw/2, H*0.500); ctx.lineTo(cx + nw/2, H*0.500);
    ctx.strokeStyle = "#6366f1"; ctx.lineWidth = Math.max(2, W*0.003); ctx.stroke();

    // "has successfully completed the course"
    ctx.font = Math.round(W * 0.019) + "px Arial,sans-serif";
    ctx.fillStyle = "#475569";
    ctx.fillText("has successfully completed the course", cx, H * 0.555);

    // Course Name
    ctx.font = "bold italic " + Math.round(W * 0.036) + "px Georgia,serif";
    ctx.fillStyle = "#4f46e5";
    ctx.fillText(courseName, cx, H * 0.620);

    // Sub line
    ctx.font = Math.round(W * 0.016) + "px Arial,sans-serif";
    ctx.fillStyle = "#64748b";
    ctx.fillText("with verified payment and outstanding dedication.", cx, H * 0.668);

    // Issue date (left side)
    ctx.textAlign = "left";
    ctx.font = Math.round(W * 0.016) + "px Arial,sans-serif";
    ctx.fillStyle = "#94a3b8";
    ctx.fillText("Issued on: " + issueDate, W * 0.06, H * 0.82);

    // Signature line (left)
    ctx.beginPath();
    ctx.moveTo(W*0.06, H*0.87); ctx.lineTo(W*0.28, H*0.87);
    ctx.strokeStyle = "#94a3b8"; ctx.lineWidth = 1; ctx.stroke();
    ctx.font = Math.round(W * 0.013) + "px Arial,sans-serif";
    ctx.fillStyle = "#94a3b8";
    ctx.fillText("Authorized Signature", W*0.06, H*0.895);

    // Now generate QR code and draw it bottom-right
    generateQRAndDraw(ctx, W, H, verifyUrl);
}

// ── Generate QR using qrcodejs and draw it at TOP-RIGHT of canvas ────────────
function generateQRAndDraw(ctx, W, H, verifyUrl) {
    var qrSize = Math.round(W * 0.11);               // QR size on canvas
    var qrX    = Math.round((W - qrSize) / 2);       // CENTER horizontally
    var qrY    = H - qrSize - Math.round(H * 0.15);  // Moved higher up from bottom

    // Create a temporary div, generate QR into it
    var tempDiv = document.createElement('div');
    tempDiv.style.position = 'absolute';
    tempDiv.style.left = '-9999px';
    document.body.appendChild(tempDiv);

    new QRCode(tempDiv, {
        text:          verifyUrl,
        width:         200,
        height:        200,
        correctLevel:  QRCode.CorrectLevel.H
    });

    // QRCode.js generates a canvas inside the div synchronously
    // (small timeout to be safe on all browsers)
    setTimeout(function() {
        var qrCanvas = tempDiv.querySelector('canvas');
        if (qrCanvas) {
            // Draw QR code image onto certificate canvas
            ctx.drawImage(qrCanvas, qrX, qrY, qrSize, qrSize);

            // White frame around QR
            ctx.strokeStyle = "#e2e8f0";
            ctx.lineWidth   = 3;
            ctx.strokeRect(qrX - 4, qrY - 4, qrSize + 8, qrSize + 8);

            // "Scan to Verify" label under QR
            ctx.textAlign  = "center";
            ctx.font       = Math.round(W * 0.013) + "px Arial,sans-serif";
            ctx.fillStyle  = "#94a3b8";
            ctx.fillText("Scan to Verify", qrX + qrSize/2, qrY + qrSize + Math.round(H*0.03));
        }
        document.body.removeChild(tempDiv);
    }, 100);
}

// ── Fallback beautiful certificate background ─────────────────────────────────
function drawFallbackBg(ctx, canvas, studentName, courseName, issueDate, verifyUrl) {
    var W = 1122, H = 794;  // A4 Landscape (same as CERT_W/CERT_H)
    // canvas size already set to 1122x794 before calling this function

    // Cream/gold gradient background (real certificate feel)
    var grad = ctx.createLinearGradient(0, 0, W, H);
    grad.addColorStop(0,   "#fffdf0");
    grad.addColorStop(0.5, "#fefce8");
    grad.addColorStop(1,   "#fdf6e3");
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, W, H);

    // Outer thick gold border
    ctx.strokeStyle = "#b45309"; ctx.lineWidth = 18;
    ctx.strokeRect(14, 14, W-28, H-28);

    // Inner thin gold border
    ctx.strokeStyle = "#fbbf24"; ctx.lineWidth = 4;
    ctx.strokeRect(30, 30, W-60, H-60);

    // Second inner thin border
    ctx.strokeStyle = "#d97706"; ctx.lineWidth = 1.5;
    ctx.strokeRect(40, 40, W-80, H-80);

    // Corner ornaments
    var corners = [[60,60],[W-60,60],[60,H-60],[W-60,H-60]];
    corners.forEach(function(pt) {
        ctx.beginPath(); ctx.arc(pt[0],pt[1],16,0,2*Math.PI);
        ctx.fillStyle="#b45309"; ctx.fill();
        ctx.beginPath(); ctx.arc(pt[0],pt[1],9,0,2*Math.PI);
        ctx.fillStyle="#fef3c7"; ctx.fill();
        ctx.beginPath(); ctx.arc(pt[0],pt[1],4,0,2*Math.PI);
        ctx.fillStyle="#b45309"; ctx.fill();
    });

    // Top center star/seal symbol
    ctx.textAlign="center";
    ctx.font = "bold " + Math.round(W*0.040) + "px Georgia,serif";
    ctx.fillStyle="#b45309";
    ctx.fillText("★  COACHING CENTER  ★", W/2, H*0.14);

    drawTextAndQR(ctx, canvas, studentName, courseName, issueDate, verifyUrl);
}

// ── Download the certificate canvas as PNG ────────────────────────────────────
function downloadCert(canvasId, studentName, courseName) {
    // Small delay to make sure QR is drawn
    setTimeout(function() {
        var canvas = document.getElementById(canvasId);
        var link   = document.createElement('a');
        link.download = "Certificate_" + studentName.replace(/\s+/g,'_') + "_" + courseName.replace(/\s+/g,'_') + ".png";
        link.href  = canvas.toDataURL('image/png');
        link.click();
    }, 200);
}
</script>

</div><!-- .layout-wrapper from sidebar.php -->
</body>
</html>

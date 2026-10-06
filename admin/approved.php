<?php
session_start();

// PHP Cache Control
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Kick out if not logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

include '../db.php';

// ─── HANDLE APPROVE ACTION ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_id'])) {
    $approve_id = intval($_POST['approve_id']);

    // 1. Update the status in DB
    $stmt = $conn->prepare("UPDATE enrollments SET status = 'Approved' WHERE id = ?");
    $stmt->bind_param("i", $approve_id);
    $stmt->execute();
    $stmt->close();

    // 2. Fetch student email + name for the notification
    $info_query = "SELECT e.student_name, e.course_name, sm.email
                   FROM enrollments e
                   LEFT JOIN student_master sm ON sm.id = e.student_id
                   WHERE e.id = ?";
    $info_stmt = $conn->prepare($info_query);
    $info_stmt->bind_param("i", $approve_id);
    $info_stmt->execute();
    $info_result = $info_stmt->get_result();
    $student_info = $info_result->fetch_assoc();
    $info_stmt->close();

    // 3. Send approval email via PHPMailer (using stored SMTP settings)
    $email_sent = false;
    if ($student_info && !empty($student_info['email'])) {
        // Fetch SMTP settings from DB
        $smtp_result = mysqli_query($conn, "SELECT * FROM email_settings WHERE id = 1");
        $smtp = mysqli_fetch_assoc($smtp_result);

        if ($smtp) {
            // Use PHP mail() as fallback if PHPMailer not available
            // Try PHPMailer first
            $mailer_path = '../vendor/phpmailer/phpmailer/src/PHPMailer.php';
            if (file_exists($mailer_path)) {
                require_once $mailer_path;
                require_once '../vendor/phpmailer/phpmailer/src/SMTP.php';
                require_once '../vendor/phpmailer/phpmailer/src/Exception.php';

                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $smtp['sending_email_id'];
                    $mail->Password   = $smtp['app_password'];
                    $mail->SMTPSecure = 'tls';
                    $mail->Port       = 587;

                    $mail->setFrom($smtp['sending_email_id'], $smtp['username']);
                    $mail->addAddress($student_info['email'], $student_info['student_name']);
                    $mail->isHTML(true);
                    $mail->Subject = 'Payment Approved - ' . $student_info['course_name'];
                    $mail->Body    = "
                        <h2>🎉 Payment Approved!</h2>
                        <p>Dear <strong>{$student_info['student_name']}</strong>,</p>
                        <p>Your payment for <strong>{$student_info['course_name']}</strong> has been <span style='color:green;font-weight:bold;'>approved</span> by the admin.</p>
                        <p>You can now access all course materials. Welcome aboard!</p>
                        <br>
                        <p>Regards,<br><strong>Coaching Center Team</strong></p>
                    ";
                    $mail->send();
                    $email_sent = true;
                } catch (Exception $e) {
                    // Email failed silently, continue
                }
            } else {
                // Fallback: php mail()
                $to      = $student_info['email'];
                $subject = 'Payment Approved - ' . $student_info['course_name'];
                $message = "Dear {$student_info['student_name']},\n\nYour payment for {$student_info['course_name']} has been approved.\n\nRegards,\nCoaching Center Team";
                $headers = "From: {$smtp['sending_email_id']}";
                mail($to, $subject, $message, $headers);
                $email_sent = true;
            }
        }
    }

    // 4. Redirect back with success message
    $msg = urlencode("✅ Payment approved successfully!" . ($email_sent ? " Email sent to student." : ""));
    header("Location: approved.php?success=" . $msg);
    exit();
}

// ─── FETCH PENDING ENROLLMENTS ONLY ──────────────────────────────────────────
$enrollments = [];
$query = "SELECT e.`id`, e.`student_id`, e.`student_name`, e.`course_id`, e.`course_name`,
                 e.`batch_id`, COALESCE(b.`batch_name`, e.`batch_id`) AS batch_name,
                 e.`total_amount`, e.`paid_amount`, e.`payment_type`, e.`utr_number`,
                 e.`screenshot`, e.`status`, e.`created_at`
          FROM `enrollments` e
          LEFT JOIN `batches` b ON b.`id` = e.`batch_id`
          WHERE e.`status` = 'Pending'
          ORDER BY e.`created_at` DESC";
$result = mysqli_query($conn, $query);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $enrollments[] = $row;
    }
}

include('header.php');
include('sidebar.php');
?>

<style>
    .page-header {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 28px;
    }
    .page-header h2 {
        font-size: 24px;
        color: #1e293b;
        margin: 0;
    }
    .page-header .count-pill {
        background: #fef3c7;
        color: #b45309;
        font-size: 13px;
        font-weight: 700;
        padding: 4px 14px;
        border-radius: 20px;
    }

    /* Success Alert */
    .alert-success {
        background: #d1fae5;
        color: #065f46;
        border: 1px solid #6ee7b7;
        border-radius: 8px;
        padding: 14px 20px;
        margin-bottom: 22px;
        font-weight: 600;
        font-size: 15px;
    }

    /* Table */
    .table-wrapper {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.07);
        overflow: hidden;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    thead {
        background: #1e293b;
        color: #e2e8f0;
    }
    thead th {
        padding: 14px 16px;
        text-align: left;
        font-weight: 600;
        font-size: 13px;
        letter-spacing: 0.4px;
        white-space: nowrap;
    }
    tbody tr {
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.15s;
    }
    tbody tr:hover { background: #f8fafc; }
    tbody td {
        padding: 13px 16px;
        color: #374151;
        vertical-align: middle;
    }
    .badge-pending {
        background: #fef3c7;
        color: #b45309;
        font-size: 12px;
        font-weight: 700;
        padding: 3px 12px;
        border-radius: 20px;
    }

    /* Action Buttons */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 7px 14px;
        border-radius: 7px;
        font-size: 13px;
        font-weight: 600;
        border: none;
        cursor: pointer;
        text-decoration: none;
        transition: background 0.2s;
    }
    .btn-view   { background: #3b82f6; color: #fff; }
    .btn-view:hover   { background: #2563eb; }
    .btn-approve { background: #10b981; color: #fff; }
    .btn-approve:hover { background: #059669; }

    /* No Data */
    .no-data {
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
        font-size: 16px;
    }
    .no-data span { font-size: 48px; display: block; margin-bottom: 12px; }

    /* Screenshot Modal */
    .ss-modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.7);
        z-index: 2000;
        align-items: center;
        justify-content: center;
    }
    .ss-modal-overlay.active { display: flex; }
    .ss-modal-box {
        background: #fff;
        border-radius: 14px;
        padding: 24px;
        max-width: 520px;
        width: 95%;
        position: relative;
        max-height: 85vh;
        overflow-y: auto;
    }
    .ss-modal-box h3 {
        margin: 0 0 16px 0;
        font-size: 18px;
        color: #1e293b;
    }
    .ss-modal-box img {
        width: 100%;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }
    .ss-no-image {
        text-align: center;
        padding: 30px;
        color: #94a3b8;
        font-size: 15px;
    }
    .ss-close {
        position: absolute;
        top: 14px; right: 18px;
        font-size: 22px;
        cursor: pointer;
        color: #64748b;
        background: none;
        border: none;
        line-height: 1;
    }
    .ss-close:hover { color: #e11d48; }
</style>

<main class="main-content">

    <!-- Page Title -->
    <div class="page-header">
        <h2>⏳ Pending Payment Approvals</h2>
        <span class="count-pill"><?php echo count($enrollments); ?> Pending</span>
    </div>

    <!-- Success Message -->
    <?php if (!empty($_GET['success'])): ?>
        <div class="alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
    <?php endif; ?>

    <!-- Enrollments Table -->
    <div class="table-wrapper">
        <?php if (empty($enrollments)): ?>
            <div class="no-data">
                <span>🎉</span>
                No pending payments! All enrollments are approved.
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student Name</th>
                        <th>Course</th>
                        <th>Batch Name</th>
                        <th>Total (₹)</th>
                        <th>Paid (₹)</th>
                        <th>Payment Type</th>
                        <th>UTR No.</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $sr = 1; foreach ($enrollments as $enr): ?>
                    <tr>
                        <td><?php echo $sr++; ?></td>
                        <td><strong><?php echo htmlspecialchars($enr['student_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($enr['course_name']); ?></td>
                        <td><?php echo htmlspecialchars($enr['batch_name']); ?></td>
                        <td>₹<?php echo number_format($enr['total_amount'], 2); ?></td>
                        <td>₹<?php echo number_format($enr['paid_amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($enr['payment_type']); ?></td>
                        <td><?php echo htmlspecialchars($enr['utr_number'] ?: '—'); ?></td>
                        <td><span class="badge-pending">Pending</span></td>
                        <td><?php echo date('d M Y', strtotime($enr['created_at'])); ?></td>
                        <td style="white-space:nowrap; display:flex; gap:6px;">
                            <!-- View Screenshot -->
                            <button class="btn btn-view"
                                onclick="viewScreenshot('<?php echo htmlspecialchars($enr['screenshot'] ?? '', ENT_QUOTES); ?>')">
                                🖼 View SS
                            </button>
                            <!-- Approve -->
                            <form method="POST" style="margin:0;"
                                  onsubmit="return confirm('Approve payment for <?php echo htmlspecialchars($enr['student_name'], ENT_QUOTES); ?>?');">
                                <input type="hidden" name="approve_id" value="<?php echo $enr['id']; ?>">
                                <button type="submit" class="btn btn-approve">✔ Approve</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

<!-- Screenshot Modal -->
<div class="ss-modal-overlay" id="ssModal">
    <div class="ss-modal-box">
        <button class="ss-close" onclick="closeSSModal()">✕</button>
        <h3>📄 Payment Screenshot</h3>
        <div id="ssModalBody"></div>
    </div>
</div>

<script>
function viewScreenshot(path) {
    const body = document.getElementById('ssModalBody');
    if (path && path.trim() !== '') {
        // Path stored in DB — adjust prefix if needed
        // Files are saved to admin/uploads/payments/ — so from admin/approved.php, path is just: uploads/payments/filename.jpg
        const imgSrc = path;
        body.innerHTML = '<img src="' + imgSrc + '" alt="Payment Screenshot" onerror="this.outerHTML=\'<div class=ss-no-image>❌ Image not found at path: ' + path + '</div>\'">';
    } else {
        body.innerHTML = '<div class="ss-no-image">No screenshot uploaded for this enrollment.</div>';
    }
    document.getElementById('ssModal').classList.add('active');
}

function closeSSModal() {
    document.getElementById('ssModal').classList.remove('active');
    document.getElementById('ssModalBody').innerHTML = '';
}

// Close on overlay click
document.getElementById('ssModal').addEventListener('click', function(e) {
    if (e.target === this) closeSSModal();
});
</script>

  </div> <!-- close .layout-wrapper from sidebar -->
</body>
</html>

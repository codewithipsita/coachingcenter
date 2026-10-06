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

include('../db.php');

// ── Create saved_certificates table if it doesn't exist ───────────────────────
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS saved_certificates (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    cert_uid      VARCHAR(64)  UNIQUE NOT NULL,
    student_id    INT          NOT NULL,
    student_name  VARCHAR(255) NOT NULL,
    course_name   VARCHAR(255) NOT NULL,
    enrollment_id INT          DEFAULT 0,
    bg_image      VARCHAR(255) DEFAULT '',
    issue_date    DATE         NOT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
)");

// ── Handle Save Certificate (simple form POST — no AJAX, no PDO) ──────────────
$save_success = false;
$save_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_cert'])) {
    $p_student_id    = intval($_POST['p_student_id']    ?? 0);
    $p_student_name  = trim($_POST['p_student_name']    ?? '');
    $p_course_name   = trim($_POST['p_course_name']     ?? '');
    $p_enrollment_id = intval($_POST['p_enrollment_id'] ?? 0);
    $p_bg_image      = trim($_POST['p_bg_image']        ?? '');
    $p_issue_date    = trim($_POST['p_issue_date']      ?? date('Y-m-d'));
    $p_course_id     = intval($_POST['p_course_id']     ?? 0);

    if ($p_student_id > 0 && $p_student_name !== '' && $p_course_name !== '') {
        // Escape for safe SQL
        $name_esc   = mysqli_real_escape_string($conn, $p_student_name);
        $course_esc = mysqli_real_escape_string($conn, $p_course_name);
        $bg_esc     = mysqli_real_escape_string($conn, $p_bg_image);
        $date_esc   = mysqli_real_escape_string($conn, $p_issue_date);

        // Check if already saved
        $check = mysqli_query($conn,
            "SELECT cert_uid FROM saved_certificates WHERE student_id = $p_student_id AND course_name = '$course_esc' LIMIT 1"
        );
        if ($check && mysqli_num_rows($check) > 0) {
            $save_message = 'already_saved';
        } else {
            // Generate unique certificate ID
            $cert_uid = bin2hex(random_bytes(16));
            $insert = mysqli_query($conn,
                "INSERT INTO saved_certificates (cert_uid, student_id, student_name, course_name, enrollment_id, bg_image, issue_date)
                 VALUES ('$cert_uid', $p_student_id, '$name_esc', '$course_esc', $p_enrollment_id, '$bg_esc', '$date_esc')"
            );
            $save_message = $insert ? 'saved' : 'error';
        }
        // Redirect back so refresh doesn't re-POST
        header("Location: cirtificate.php?course_id=$p_course_id&save_msg=$save_message");
        exit();
    }
}

$save_msg_param = $_GET['save_msg'] ?? '';

// ── Build verify base URL dynamically (no hardcoding) ────────────────────────
$protocol  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host      = $_SERVER['HTTP_HOST'];
// Get path two levels up from /admin/cirtificate.php  →  /coatchingcenter
$root_path = rtrim(dirname(dirname($_SERVER['PHP_SELF'])), '/');
$base_url  = $protocol . '://' . $host . $root_path;

// Fetch all courses for dropdown
$courses = [];
$course_query = "SELECT id, course_name FROM courses ORDER BY course_name ASC";
$course_result = mysqli_query($conn, $course_query);
if ($course_result) {
    while ($row = mysqli_fetch_assoc($course_result)) {
        $courses[] = $row;
    }
}

// Fetch latest certificate background
$bg_image = "";
$bg_query = "SELECT image_path FROM certificate_backgrounds ORDER BY created_at DESC LIMIT 1";
$bg_result = mysqli_query($conn, $bg_query);
if ($bg_result && $bg_row = mysqli_fetch_assoc($bg_result)) {
    $bg_image = $bg_row['image_path'];
}

// Fetch students based on selected course (with Approved payment status)
$selected_course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$selected_course_name = "";
$students = [];

if ($selected_course_id > 0) {
    // Get course name
    $cn_query = "SELECT course_name FROM courses WHERE id = $selected_course_id";
    $cn_result = mysqli_query($conn, $cn_query);
    if ($cn_result && $cn_row = mysqli_fetch_assoc($cn_result)) {
        $selected_course_name = $cn_row['course_name'];
    }

    // Fetch students who are enrolled in this course with Approved status
    $student_query = "SELECT e.id AS enrollment_id, e.student_id, e.student_name, e.course_name, e.status, e.created_at,
                            sm.email
                      FROM enrollments e
                      LEFT JOIN student_master sm ON sm.id = e.student_id
                      WHERE e.course_id = $selected_course_id AND e.status = 'Approved'
                      ORDER BY e.student_name ASC";
    $student_result = mysqli_query($conn, $student_query);
    if ($student_result) {
        while ($row = mysqli_fetch_assoc($student_result)) {
            $students[] = $row;
        }
    }
}

// Build map: student_id => cert_uid  (to know who already has a saved certificate)
$saved_map = [];
if (!empty($students)) {
    $ids     = array_map(function($s){ return intval($s['student_id']); }, $students);
    $ids_str = implode(',', $ids);
    $sc_q    = mysqli_query($conn,
        "SELECT student_id, cert_uid FROM saved_certificates WHERE student_id IN ($ids_str)"
    );
    if ($sc_q) {
        while ($sc = mysqli_fetch_assoc($sc_q)) {
            $saved_map[$sc['student_id']] = $sc['cert_uid'];
        }
    }
}

include('header.php');
include('sidebar.php');
?>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- QR Code JS library — used to embed a verify QR into the certificate -->
<script src="https://cdn.jsdelivr.net/gh/davidshimjs/qrcodejs@gh-pages/qrcode.min.js"></script>

<style> 
    /* @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(24px); }
        to   { opacity: 1; transform: translateY(0); }
    } */
    @keyframes pulse-glow {
        0%, 100% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.4); }
        50%       { box-shadow: 0 0 0 10px rgba(99, 102, 241, 0); }
    }

    .main-content {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #f0f4ff 0%, #f8fafc 60%, #fdf4ff 100%);
        min-height: 100vh;
        padding: 36px 40px;
    }

    /* Page Header */
    .cert-page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 32px;
        animation: fadeInUp 0.5s ease-out forwards;
    }
    .cert-page-header h1 {
        font-size: 28px;
        font-weight: 800;
        color: #1e293b;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .cert-page-header h1 .icon-wrap {
        width: 48px; height: 48px;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        color: #fff;
        font-size: 22px;
        box-shadow: 0 6px 20px rgba(99,102,241,0.35);
    }
    .breadcrumb {
        display: flex; align-items: center; gap: 8px;
        font-size: 13px; color: #94a3b8;
    }
    .breadcrumb a { color: #6366f1; text-decoration: none; font-weight: 500; }
    .breadcrumb a:hover { text-decoration: underline; }

    /* No Background Warning */
    .no-bg-alert {
        background: linear-gradient(135deg, #fff7ed, #fff);
        border: 1px solid #fed7aa;
        border-radius: 14px;
        padding: 18px 24px;
        display: flex; align-items: center; gap: 14px;
        margin-bottom: 24px;
        animation: fadeInUp 0.5s ease-out;
    }
    .no-bg-alert .alert-icon { font-size: 28px; }
    .no-bg-alert .alert-text { font-size: 14px; color: #92400e; }
    .no-bg-alert .alert-text a { color: #7c3aed; font-weight: 700; text-decoration: none; }
    .no-bg-alert .alert-text a:hover { text-decoration: underline; }

    /* Filter Card */
    .filter-card {
        background: #ffffff;
        border-radius: 20px;
        padding: 28px 32px;
        box-shadow: 0 4px 24px rgba(0,0,0,0.07);
        border: 1px solid #e8eaf6;
        margin-bottom: 28px;
        animation: fadeInUp 0.55s ease-out forwards;
    }
    .filter-card-title {
        font-size: 13px;
        font-weight: 700;
        color: #6366f1;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 18px;
        display: flex; align-items: center; gap: 8px;
    }
    .filter-row {
        display: flex; align-items: flex-end; gap: 16px; flex-wrap: wrap;
    }
    .filter-group {
        flex: 1; min-width: 220px;
    }
    .filter-group label {
        display: block;
        font-size: 13px; font-weight: 600; color: #475569;
        margin-bottom: 8px;
    }
    .select-styled {
        width: 100%;
        padding: 12px 18px;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        font-size: 14px; font-family: 'Poppins', sans-serif;
        color: #1e293b;
        background: #f8fafc;
        appearance: none;
        transition: border-color 0.2s, box-shadow 0.2s;
        cursor: pointer;
    }
    .select-styled:focus {
        outline: none;
        border-color: #6366f1;
        box-shadow: 0 0 0 4px rgba(99,102,241,0.12);
        background-color: #fff;
    }
    .btn-filter {
        padding: 12px 28px;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: #fff;
        border: none; border-radius: 12px;
        font-size: 14px; font-weight: 700; font-family: 'Poppins', sans-serif;
        cursor: pointer;
        display: flex; align-items: center; gap: 8px;
        transition: transform 0.2s, box-shadow 0.2s;
        box-shadow: 0 4px 16px rgba(99,102,241,0.35);
        white-space: nowrap;
    }
    .btn-filter:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(99,102,241,0.45);
    }

    /* Stats Row */
    .stats-row {
        display: flex; gap: 16px; margin-bottom: 24px; flex-wrap: wrap;
        animation: fadeInUp 0.6s ease-out forwards;
    }
    .stat-chip {
        display: flex; align-items: center; gap: 10px;
        background: #fff; border: 1px solid #e2e8f0;
        border-radius: 12px; padding: 12px 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    }
    .stat-chip .stat-icon { font-size: 20px; }
    .stat-chip .stat-info .stat-num {
        font-size: 20px; font-weight: 800; color: #1e293b;
    }
    .stat-chip .stat-info .stat-label {
        font-size: 11px; color: #94a3b8; font-weight: 500; text-transform: uppercase;
    }

    /* Table Card */
    .table-card {
        background: #ffffff;
        border-radius: 20px;
        box-shadow: 0 4px 24px rgba(0,0,0,0.07);
        border: 1px solid #e8eaf6;
        overflow: hidden;
        animation: fadeInUp 0.65s ease-out forwards;
    }
    .table-card-header {
        padding: 22px 28px;
        border-bottom: 1px solid #f1f5f9;
        display: flex; align-items: center; justify-content: space-between;
        background: linear-gradient(135deg, #f8faff, #fff);
    }
    .table-card-header h2 {
        margin: 0; font-size: 18px; font-weight: 700; color: #1e293b;
        display: flex; align-items: center; gap: 10px;
    }
    .course-badge {
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: #fff; font-size: 12px; font-weight: 700;
        padding: 4px 14px; border-radius: 20px;
    }
    .count-badge {
        background: #ede9fe; color: #7c3aed;
        font-size: 12px; font-weight: 700;
        padding: 4px 14px; border-radius: 20px;
    }

    /* Table */
    table.cert-table {
        width: 100%; border-collapse: collapse; font-size: 14px;
    }
    table.cert-table thead {
        background: linear-gradient(135deg, #1e293b, #334155);
        color: #e2e8f0;
    }
    table.cert-table thead th {
        padding: 15px 20px; text-align: left;
        font-size: 12px; font-weight: 700;
        letter-spacing: 0.8px; text-transform: uppercase;
        white-space: nowrap;
    }
    table.cert-table tbody tr {
        border-bottom: 1px solid #f1f5f9;
        transition: background 0.15s;
    }
    table.cert-table tbody tr:last-child { border-bottom: none; }
    table.cert-table tbody tr:hover { background: #f8f7ff; }
    table.cert-table tbody td {
        padding: 16px 20px; color: #374151; vertical-align: middle;
    }

    .student-avatar {
        width: 38px; height: 38px; border-radius: 50%;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        display: inline-flex; align-items: center; justify-content: center;
        color: #fff; font-weight: 700; font-size: 15px;
        margin-right: 10px; flex-shrink: 0;
    }
    .student-name-cell {
        display: flex; align-items: center;
    }
    .student-name-text { font-weight: 600; color: #1e293b; }
    .student-email { font-size: 12px; color: #94a3b8; margin-top: 2px; }

    .status-badge {
        display: inline-flex; align-items: center; gap: 5px;
        background: #d1fae5; color: #065f46;
        padding: 5px 14px; border-radius: 20px;
        font-size: 12px; font-weight: 700;
    }

    /* Generate Button */
    .btn-generate {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 9px 18px;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        color: #fff; border: none; border-radius: 10px;
        font-size: 13px; font-weight: 700; font-family: 'Poppins', sans-serif;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
        box-shadow: 0 4px 12px rgba(99,102,241,0.35);
        white-space: nowrap;
    }
    .btn-generate:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(99,102,241,0.45);
    }

    /* Empty State */
    .empty-state {
        text-align: center; padding: 70px 30px;
        animation: fadeInUp 0.5s ease-out;
    }
    .empty-state .empty-icon {
        font-size: 64px; margin-bottom: 18px; opacity: 0.6;
    }
    .empty-state h3 {
        color: #1e293b; font-size: 20px; font-weight: 700; margin-bottom: 10px;
    }
    .empty-state p { color: #94a3b8; font-size: 14px; line-height: 1.7; }

    /* Certificate Modal */
    .cert-modal-overlay {
        display: none;
        position: fixed; inset: 0;
        background: rgba(0,0,0,0.78);
        z-index: 9999;
        align-items: center; justify-content: center;
        backdrop-filter: blur(4px);
    }
    .cert-modal-overlay.active { display: flex; }
    .cert-modal-box {
        background: #fff;
        border-radius: 20px;
        padding: 28px;
        max-width: 900px; width: 96%;
        position: relative;
        max-height: 92vh; overflow-y: auto;
        box-shadow: 0 24px 60px rgba(0,0,0,0.3);
        animation: fadeInUp 0.3s ease-out;
    }
    .cert-modal-header {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 22px;
        padding-bottom: 16px;
        border-bottom: 1px solid #f1f5f9;
    }
    .cert-modal-header h3 {
        margin: 0; font-size: 20px; font-weight: 700; color: #1e293b;
        display: flex; align-items: center; gap: 10px;
    }
    .modal-close-btn {
        background: #f1f5f9; border: none; border-radius: 50%;
        width: 36px; height: 36px; cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        font-size: 18px; color: #64748b;
        transition: background 0.2s, color 0.2s;
        font-family: 'Poppins', sans-serif;
    }
    .modal-close-btn:hover { background: #fee2e2; color: #e11d48; }

    /* Canvas wrapper */
    #cert-canvas-wrap {
        width: 100%;
        border: 2px solid #e2e8f0;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 8px 32px rgba(0,0,0,0.12);
        background: #f8fafc;
        text-align: center;
    }
    #certCanvas {
        max-width: 100%;
        display: block;
        margin: 0 auto;
    }

    .cert-modal-actions {
        display: flex; gap: 12px; margin-top: 22px; justify-content: flex-end; flex-wrap: wrap;
    }
    .btn-download {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 11px 24px;
        background: linear-gradient(135deg, #10b981, #059669);
        color: #fff; border: none; border-radius: 10px;
        font-size: 14px; font-weight: 700; font-family: 'Poppins', sans-serif;
        cursor: pointer;
        transition: transform 0.2s, box-shadow 0.2s;
        box-shadow: 0 4px 14px rgba(16,185,129,0.35);
    }
    .btn-download:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(16,185,129,0.45);
    }
    .btn-download:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
    .btn-close-modal {
        display: inline-flex; align-items: center; gap: 8px;
        padding: 11px 22px;
        background: #f1f5f9; color: #475569;
        border: 1px solid #e2e8f0; border-radius: 10px;
        font-size: 14px; font-weight: 600; font-family: 'Poppins', sans-serif;
        cursor: pointer; transition: background 0.2s;
    }
    .btn-close-modal:hover { background: #e2e8f0; }
</style>

<main class="main-content">

    <!-- Page Header -->
    <div class="cert-page-header">
        <h1>
            <span class="icon-wrap"><i class="fa-solid fa-certificate"></i></span>
            Certificate Generator
        </h1>
        <div class="breadcrumb">
            <a href="welcome.php"><i class="fa-solid fa-house"></i> Dashboard</a>
            <span>&rsaquo;</span>
            <span>Certificate</span>
        </div>
    </div>

    <?php if (empty($bg_image)): ?>
    <div class="no-bg-alert">
        <div class="alert-icon">&#9888;&#65039;</div>
        <div class="alert-text">
            No certificate background image is set. Please
            <a href="cirtificate-setting.php">upload a background</a>
            in Certificate Settings before generating certificates.
        </div>
    </div>
    <?php endif; ?>

    <!-- Filter Card -->
    <div class="filter-card">
        <div class="filter-card-title">
            <i class="fa-solid fa-filter"></i> Filter by Course
        </div>
        <form method="GET" action="">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="course_id">Select Course</label>
                    <select name="course_id" id="course_id" class="select-styled">
                        <option value="">&#8212; Choose a Course &#8212;</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>"
                                <?php echo ($selected_course_id == $course['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['course_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-filter">
                    <i class="fa-solid fa-magnifying-glass"></i> Show Students
                </button>
            </div>
        </form>
    </div>

    <?php if ($selected_course_id > 0): ?>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-chip">
                <div class="stat-icon">&#127891;</div>
                <div class="stat-info">
                    <div class="stat-num"><?php echo count($students); ?></div>
                    <div class="stat-label">Verified Students</div>
                </div>
            </div>
            <div class="stat-chip">
                <div class="stat-icon">&#128216;</div>
                <div class="stat-info">
                    <div class="stat-num" style="font-size:15px;"><?php echo htmlspecialchars($selected_course_name); ?></div>
                    <div class="stat-label">Selected Course</div>
                </div>
            </div>
        </div>

        <!-- Table Card -->
        <div class="table-card">
            <div class="table-card-header">
                <h2>
                    <i class="fa-solid fa-users" style="color:#6366f1;"></i>
                    Verified Students
                    <?php if (!empty($selected_course_name)): ?>
                        <span class="course-badge"><?php echo htmlspecialchars($selected_course_name); ?></span>
                    <?php endif; ?>
                </h2>
                <span class="count-badge"><?php echo count($students); ?> Students</span>
            </div>

            <?php if (empty($students)): ?>
                <div class="empty-state">
                    <div class="empty-icon">&#128269;</div>
                    <h3>No Verified Students Found</h3>
                    <p>There are no students with <strong>Approved</strong> payment status for this course.<br>
                    Approve student payments from the <a href="approved.php" style="color:#6366f1;">Payment Approvals</a> page.</p>
                </div>
            <?php else: ?>
                <table class="cert-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student Name</th>
                            <th>Course Name</th>
                            <th>Payment Status</th>
                            <th>Enrolled On</th>
                            <th style="text-align:center;">Generate Certificate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $sr = 1; foreach ($students as $stu): ?>
                        <tr>
                            <td><?php echo $sr++; ?></td>
                            <td>
                                <div class="student-name-cell">
                                    <div class="student-avatar">
                                        <?php echo strtoupper(substr($stu['student_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="student-name-text"><?php echo htmlspecialchars($stu['student_name']); ?></div>
                                        <?php if (!empty($stu['email'])): ?>
                                            <div class="student-email"><?php echo htmlspecialchars($stu['email']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($stu['course_name']); ?></td>
                            <td><span class="status-badge">&#10003; Verified</span></td>
                            <td><?php echo date('d M Y', strtotime($stu['created_at'])); ?></td>
                            <td style="text-align:center;">
                                <?php
                                $already_saved = isset($saved_map[$stu['student_id']]);
                                $existing_uid  = $already_saved ? $saved_map[$stu['student_id']] : '';
                                ?>
                                <button class="btn-generate"
                                    data-name="<?php echo htmlspecialchars($stu['student_name'], ENT_QUOTES); ?>"
                                    data-course="<?php echo htmlspecialchars($stu['course_name'], ENT_QUOTES); ?>"
                                    data-date="<?php echo date('F d, Y', strtotime($stu['created_at'])); ?>"
                                    data-rawdate="<?php echo date('Y-m-d', strtotime($stu['created_at'])); ?>"
                                    data-student-id="<?php echo intval($stu['student_id']); ?>"
                                    data-enrollment-id="<?php echo intval($stu['enrollment_id']); ?>"
                                    data-bg="<?php echo htmlspecialchars($bg_image, ENT_QUOTES); ?>"
                                    data-saved="<?php echo $already_saved ? '1' : '0'; ?>"
                                    data-cert-uid="<?php echo htmlspecialchars($existing_uid, ENT_QUOTES); ?>">
                                    <i class="fa-solid fa-certificate"></i> Generate
                                </button>
                                <?php if ($already_saved): ?>
                                    <div style="margin-top:6px;">
                                        <span style="background:#d1fae5;color:#065f46;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;">
                                            ✔ Saved
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    <?php elseif (!isset($_GET['course_id'])): ?>
        <div class="table-card">
            <div class="empty-state">
                <div class="empty-icon">&#127891;</div>
                <h3>Select a Course to Get Started</h3>
                <p>Choose a course from the dropdown above to view eligible students<br>and generate their certificates.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="table-card">
            <div class="empty-state">
                <div class="empty-icon">&#128237;</div>
                <h3>No Verified Students</h3>
                <p>No students with approved payments found for the selected course.</p>
            </div>
        </div>
    <?php endif; ?>

</main>

<!-- Simple HTML form for saving the certificate (no AJAX, no fetch) -->
<!-- Hidden fields are filled by JS when Generate button is clicked -->
<form method="POST" action="cirtificate.php" id="save-cert-form" style="display:none;">
    <input type="hidden" name="save_cert"        value="1">
    <input type="hidden" name="p_course_id"      value="<?php echo $selected_course_id; ?>">
    <input type="hidden" id="f_student_id"    name="p_student_id"    value="">
    <input type="hidden" id="f_student_name"  name="p_student_name"  value="">
    <input type="hidden" id="f_course_name"   name="p_course_name"   value="">
    <input type="hidden" id="f_enrollment_id" name="p_enrollment_id" value="">
    <input type="hidden" id="f_bg_image"      name="p_bg_image"      value="">
    <input type="hidden" id="f_issue_date"    name="p_issue_date"    value="">
</form>

<!-- Certificate Preview Modal -->
<div class="cert-modal-overlay" id="certModal">
    <div class="cert-modal-box">
        <div class="cert-modal-header">
            <h3><i class="fa-solid fa-certificate" style="color:#6366f1;"></i> Certificate Preview</h3>
            <button class="modal-close-btn" onclick="closeCertModal()">&#10005;</button>
        </div>

        <!-- Canvas: certificate drawn here by JS -->
        <div id="cert-canvas-wrap">
            <canvas id="certCanvas"></canvas>
        </div>

        <div class="cert-modal-actions">
            <button class="btn-close-modal" onclick="closeCertModal()">
                <i class="fa-solid fa-xmark"></i> Close
            </button>
            <!-- Save button fills the hidden form then submits it normally -->
            <button id="save-cert-btn" onclick="submitSaveForm()"
                style="display:inline-flex;align-items:center;gap:8px;padding:11px 24px;
                       background:linear-gradient(135deg,#6366f1,#8b5cf6);
                       color:#fff;border:none;border-radius:10px;
                       font-size:14px;font-weight:700;font-family:'Poppins',sans-serif;
                       cursor:pointer;box-shadow:0 4px 14px rgba(99,102,241,0.35);">
                <i class="fa-solid fa-floppy-disk"></i> Save Certificate
            </button>
            <button class="btn-download" id="download-btn" onclick="downloadCertificate()">
                <i class="fa-solid fa-download"></i> Download
            </button>
        </div>
    </div>
</div>

<script>
// ── Config ─────────────────────────────────────────────────────────
var BG_IMAGE_PATH = "<?php echo addslashes($bg_image); ?>";
var BASE_URL      = "<?php echo addslashes($base_url); ?>";
var CERT_W = 1122;   // Fixed A4 landscape width  (always this size)
var CERT_H = 794;    // Fixed A4 landscape height (always this size)

// ── Current student info (set when Generate button is clicked) ─────
var currentStudentName  = "";
var currentCourseName   = "";
var currentStudentId    = 0;
var currentEnrollmentId = 0;
var currentRawDate      = "";   // Y-m-d format for form POST
var currentEnrollDate   = "";   // Human-readable date for display
var currentCertUid      = "";   // cert_uid if already saved
var currentIsSaved      = false;

// ── Listen for clicks on any Generate button ───────────────────────
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.btn-generate');
    if (!btn) return;

    // Read all data from the button's data-* attributes
    currentStudentName  = btn.getAttribute('data-name');
    currentCourseName   = btn.getAttribute('data-course');
    currentStudentId    = parseInt(btn.getAttribute('data-student-id'))    || 0;
    currentEnrollmentId = parseInt(btn.getAttribute('data-enrollment-id')) || 0;
    currentRawDate      = btn.getAttribute('data-rawdate') || '';
    currentEnrollDate   = btn.getAttribute('data-date')    || '';
    currentCertUid      = btn.getAttribute('data-cert-uid')|| '';
    currentIsSaved      = (btn.getAttribute('data-saved') === '1');

    openCertModal();
});

// ── Open the modal ─────────────────────────────────────────────────
function openCertModal() {
    // Show modal first so canvas has dimensions
    document.getElementById('certModal').classList.add('active');
    document.body.style.overflow = 'hidden';

    // Update Save button appearance
    // NOTE: we never set disabled=true so the button always stays clickable
    // PHP handles duplicate checking on the server side
    var saveBtn = document.getElementById('save-cert-btn');
    if (saveBtn) {
        if (currentIsSaved) {
            // Already saved — show green label (but still clickable; PHP will just return 'already_saved')
            saveBtn.innerHTML = '<i class="fa-solid fa-circle-check"></i> Already Saved';
            saveBtn.style.background = 'linear-gradient(135deg,#10b981,#059669)';
        } else {
            saveBtn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save Certificate';
            saveBtn.style.background = 'linear-gradient(135deg,#6366f1,#8b5cf6)';
        }
        saveBtn.disabled = false;   // ALWAYS keep enabled so modal stays functional
    }

    // Draw certificate canvas
    if (currentIsSaved && currentCertUid !== '') {
        // Already saved — draw with real QR code
        var verifyUrl = BASE_URL + '/verify-certificate.php?uid=' + currentCertUid;
        drawCertFull(verifyUrl);
    } else {
        // Not yet saved — draw with a placeholder QR code so the admin sees the layout
        var dummyVerifyUrl = BASE_URL + '/verify-certificate.php?uid=PREVIEW';
        drawCertFull(dummyVerifyUrl);
    }
}

// ── Draw the certificate (always A4 size, bg image scaled to fill) ─
// verifyUrl = null means draw without QR
function drawCertFull(verifyUrl) {
    var canvas = document.getElementById('certCanvas');
    var ctx    = canvas.getContext('2d');

    // Always fixed A4 landscape size — same whether bg image or not
    canvas.width  = CERT_W;
    canvas.height = CERT_H;

    if (BG_IMAGE_PATH !== '') {
        var img = new Image();
        img.crossOrigin = 'anonymous';
        img.src = BG_IMAGE_PATH;

        img.onload = function() {
            // Scale background image to fill the fixed A4 canvas
            ctx.drawImage(img, 0, 0, CERT_W, CERT_H);
            // Draw text on top
            drawCertText(ctx);
            // Draw QR if we have a verify URL
            if (verifyUrl) drawQR(ctx, verifyUrl);
        };

        img.onerror = function() {
            // Background failed — draw the fancy fallback design
            drawFallbackDesign(ctx);
            drawCertText(ctx);
            if (verifyUrl) drawQR(ctx, verifyUrl);
        };

    } else {
        // No background uploaded — draw the fancy fallback design
        drawFallbackDesign(ctx);
        drawCertText(ctx);
        if (verifyUrl) drawQR(ctx, verifyUrl);
    }
}

// ── Draw the decorative fallback background (purple/cream A4) ──────
function drawFallbackDesign(ctx) {
    // Cream/gold gradient background
    var grad = ctx.createLinearGradient(0, 0, CERT_W, CERT_H);
    grad.addColorStop(0,   '#fffdf0');
    grad.addColorStop(0.5, '#fefce8');
    grad.addColorStop(1,   '#fdf6e3');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, CERT_W, CERT_H);

    // Outer thick gold border
    ctx.strokeStyle = '#b45309'; ctx.lineWidth = 16;
    ctx.strokeRect(12, 12, CERT_W-24, CERT_H-24);

    // Inner thin gold border
    ctx.strokeStyle = '#fbbf24'; ctx.lineWidth = 3;
    ctx.strokeRect(28, 28, CERT_W-56, CERT_H-56);

    // Second inner thin border
    ctx.strokeStyle = '#d97706'; ctx.lineWidth = 1;
    ctx.strokeRect(38, 38, CERT_W-76, CERT_H-76);

    // Corner ornaments
    [[60,60],[CERT_W-60,60],[60,CERT_H-60],[CERT_W-60,CERT_H-60]].forEach(function(pt) {
        ctx.beginPath(); ctx.arc(pt[0],pt[1],16,0,2*Math.PI); ctx.fillStyle='#b45309'; ctx.fill();
        ctx.beginPath(); ctx.arc(pt[0],pt[1],9, 0,2*Math.PI); ctx.fillStyle='#fef3c7'; ctx.fill();
        ctx.beginPath(); ctx.arc(pt[0],pt[1],4, 0,2*Math.PI); ctx.fillStyle='#b45309'; ctx.fill();
    });

    // Top center heading
    ctx.textAlign = 'center';
    ctx.font = 'bold ' + Math.round(CERT_W*0.036) + 'px Georgia,serif';
    ctx.fillStyle = '#b45309';
    ctx.fillText('\u2605  COACHING CENTER  \u2605', CERT_W/2, CERT_H*0.13);
}

// ── Draw text content on the certificate ──────────────────────────
function drawCertText(ctx) {
    var W = CERT_W, H = CERT_H;
    var cx = W / 2;

    // Semi-transparent white strip so text is readable over any background
    ctx.save();
    ctx.fillStyle = 'rgba(255,255,255,0.55)';
    ctx.fillRect(0, H*0.26, W, H*0.52);
    ctx.restore();

    ctx.textAlign = 'center';

    // "CERTIFICATE OF COMPLETION"
    ctx.font = 'bold ' + Math.round(W*0.026) + 'px Georgia,serif';
    ctx.fillStyle = '#6366f1';
    ctx.fillText('CERTIFICATE OF COMPLETION', cx, H*0.32);

    // Decorative line under heading
    ctx.beginPath();
    ctx.moveTo(cx - W*0.22, H*0.345); ctx.lineTo(cx + W*0.22, H*0.345);
    ctx.strokeStyle = '#c4b5fd'; ctx.lineWidth = 2; ctx.stroke();

    // "This is to certify that"
    ctx.font = Math.round(W*0.020) + 'px Arial,sans-serif';
    ctx.fillStyle = '#64748b';
    ctx.fillText('This is to certify that', cx, H*0.39);

    // Student Name
    ctx.font = 'bold ' + Math.round(W*0.058) + 'px Georgia,\'Times New Roman\',serif';
    ctx.fillStyle = '#1e293b';
    ctx.fillText(currentStudentName, cx, H*0.475);

    // Purple underline under name
    var nw = ctx.measureText(currentStudentName).width;
    ctx.beginPath();
    ctx.moveTo(cx-nw/2, H*0.490); ctx.lineTo(cx+nw/2, H*0.490);
    ctx.strokeStyle = '#6366f1'; ctx.lineWidth = Math.max(2, W*0.003); ctx.stroke();

    // "has successfully completed the course"
    ctx.font = Math.round(W*0.019) + 'px Arial,sans-serif';
    ctx.fillStyle = '#475569';
    ctx.fillText('has successfully completed the course', cx, H*0.548);

    // Course Name
    ctx.font = 'bold italic ' + Math.round(W*0.034) + 'px Georgia,serif';
    ctx.fillStyle = '#4f46e5';
    ctx.fillText(currentCourseName, cx, H*0.612);

    // Sub-text
    ctx.font = Math.round(W*0.016) + 'px Arial,sans-serif';
    ctx.fillStyle = '#64748b';
    ctx.fillText('with verified payment and outstanding dedication.', cx, H*0.658);

    // Issue date (left aligned)
    ctx.textAlign = 'left';
    ctx.font = Math.round(W*0.015) + 'px Arial,sans-serif';
    ctx.fillStyle = '#94a3b8';
    ctx.fillText('Issued on: ' + currentEnrollDate, W*0.06, H*0.82);

    // Signature line
    ctx.beginPath();
    ctx.moveTo(W*0.06, H*0.87); ctx.lineTo(W*0.30, H*0.87);
    ctx.strokeStyle = '#94a3b8'; ctx.lineWidth = 1; ctx.stroke();
    ctx.font = Math.round(W*0.013) + 'px Arial,sans-serif';
    ctx.fillStyle = '#94a3b8';
    ctx.fillText('Authorized Signature', W*0.06, H*0.895);
}

// ── Draw QR code at TOP-RIGHT of the certificate ───────────────────
function drawQR(ctx, verifyUrl) {
    var W = CERT_W, H = CERT_H;
    var qrSize = Math.round(W * 0.11);               // QR image size
    var qrX    = Math.round((W - qrSize) / 2);       // CENTER horizontally
    var qrY    = H - qrSize - Math.round(H * 0.15);  // Moved higher up from bottom

    // Create a hidden div for QRCode.js to render into
    var tempDiv = document.createElement('div');
    tempDiv.style.cssText = 'position:absolute;left:-9999px;top:-9999px;';
    document.body.appendChild(tempDiv);

    new QRCode(tempDiv, {
        text:         verifyUrl,
        width:        200,
        height:       200,
        correctLevel: QRCode.CorrectLevel.H
    });

    // QRCode.js renders synchronously into a canvas element inside tempDiv
    setTimeout(function() {
        var qrCanvas = tempDiv.querySelector('canvas');
        if (qrCanvas) {
            // White background box behind QR
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(qrX-6, qrY-6, qrSize+12, qrSize+12);

            // Draw the QR code
            ctx.drawImage(qrCanvas, qrX, qrY, qrSize, qrSize);

            // Purple border around QR
            ctx.strokeStyle = '#6366f1'; ctx.lineWidth = 2;
            ctx.strokeRect(qrX-6, qrY-6, qrSize+12, qrSize+12);

            // "Scan to Verify" label BELOW the QR
            ctx.textAlign = 'center';
            ctx.font      = Math.round(W*0.012) + 'px Arial,sans-serif';
            ctx.fillStyle = '#64748b';
            ctx.fillText('Scan to Verify', qrX + qrSize/2, qrY + qrSize + Math.round(H*0.035));
        }
        document.body.removeChild(tempDiv);
    }, 150);
}

// ── Save Certificate — fills hidden form fields and submits ─────────
// Simple POST form submit — no AJAX, no fetch()
function submitSaveForm() {
    // Fill hidden form fields with current student data
    document.getElementById('f_student_id').value    = currentStudentId;
    document.getElementById('f_student_name').value  = currentStudentName;
    document.getElementById('f_course_name').value   = currentCourseName;
    document.getElementById('f_enrollment_id').value = currentEnrollmentId;
    document.getElementById('f_bg_image').value      = BG_IMAGE_PATH;
    document.getElementById('f_issue_date').value    = currentRawDate;

    // Submit the form normally (page will reload after save)
    document.getElementById('save-cert-form').submit();
}

// ── Download certificate canvas as PNG ─────────────────────────────
function downloadCertificate() {
    // Small delay to ensure QR has finished drawing
    setTimeout(function() {
        var canvas = document.getElementById('certCanvas');
        var link   = document.createElement('a');
        link.download = 'Certificate_' + currentStudentName.replace(/\s+/g,'_') + '.png';
        link.href  = canvas.toDataURL('image/png');
        link.click();
    }, 250);
}

// ── Close modal ───────────────────────────────────────────────────
function closeCertModal() {
    document.getElementById('certModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Click outside modal box to close
document.getElementById('certModal').addEventListener('click', function(e) {
    if (e.target === this) closeCertModal();
});
</script>

  </div><!-- close .layout-wrapper from sidebar -->
</body>
</html>

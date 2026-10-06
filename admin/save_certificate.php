<?php
// save_certificate.php — AJAX handler called by admin cirtificate.php
session_start();

header('Content-Type: application/json');

// Only logged-in admins can save
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'msg' => 'Not authorised']);
    exit();
}

include('../db.php');

// ── Create the saved_certificates table if it doesn't exist yet ───────────────
$create_table = "CREATE TABLE IF NOT EXISTS saved_certificates (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    cert_uid      VARCHAR(64)  UNIQUE NOT NULL,
    student_id    INT          NOT NULL,
    student_name  VARCHAR(255) NOT NULL,
    course_name   VARCHAR(255) NOT NULL,
    enrollment_id INT          DEFAULT 0,
    bg_image      VARCHAR(255) DEFAULT '',
    issue_date    DATE         NOT NULL,
    created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
)";
mysqli_query($conn, $create_table);

// ── Read POST values ──────────────────────────────────────────────────────────
$student_id    = intval($_POST['student_id']    ?? 0);
$student_name  = trim($_POST['student_name']    ?? '');
$course_name   = trim($_POST['course_name']     ?? '');
$enrollment_id = intval($_POST['enrollment_id'] ?? 0);
$bg_image      = trim($_POST['bg_image']        ?? '');
$issue_date    = trim($_POST['issue_date']      ?? date('Y-m-d'));

// Basic validation
if ($student_id <= 0 || empty($student_name) || empty($course_name)) {
    echo json_encode(['success' => false, 'msg' => 'Missing required data']);
    exit();
}

// ── Check if certificate already saved for this student + course ──────────────
$stu_name_esc  = mysqli_real_escape_string($conn, $student_name);
$course_esc    = mysqli_real_escape_string($conn, $course_name);

$check = mysqli_query($conn,
    "SELECT cert_uid FROM saved_certificates
     WHERE student_id = $student_id AND course_name = '$course_esc'
     LIMIT 1"
);
if ($check && mysqli_num_rows($check) > 0) {
    $existing = mysqli_fetch_assoc($check);
    echo json_encode([
        'success'  => true,
        'already'  => true,
        'cert_uid' => $existing['cert_uid'],
        'msg'      => 'Certificate already saved.'
    ]);
    exit();
}

// ── Generate a unique Certificate UID (32-char hex) ───────────────────────────
$cert_uid = bin2hex(random_bytes(16));

// ── Insert into DB ────────────────────────────────────────────────────────────
$bg_esc   = mysqli_real_escape_string($conn, $bg_image);
$date_esc = mysqli_real_escape_string($conn, $issue_date);

$sql = "INSERT INTO saved_certificates
        (cert_uid, student_id, student_name, course_name, enrollment_id, bg_image, issue_date)
        VALUES
        ('$cert_uid', $student_id, '$stu_name_esc', '$course_esc', $enrollment_id, '$bg_esc', '$date_esc')";

if (mysqli_query($conn, $sql)) {
    echo json_encode([
        'success'  => true,
        'already'  => false,
        'cert_uid' => $cert_uid,
        'msg'      => 'Certificate saved successfully!'
    ]);
} else {
    echo json_encode(['success' => false, 'msg' => 'DB error: ' . mysqli_error($conn)]);
}
?>

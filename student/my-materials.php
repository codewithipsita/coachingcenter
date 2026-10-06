<?php
session_start();

// Cache Control - prevent back button after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Redirect to login if not logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

include '../db.php';

$student_id = (int) $_SESSION['user_id'];

// ── Step 1: Check if this student has at least one approved enrollment ────────
// Fetch all approved enrollments for this student (course_id + batch_id pairs)
$approved_enrollments = [];
$enroll_query = "SELECT course_id, batch_id, course_name FROM enrollments
                 WHERE student_id = ? AND status = 'approved'";
$stmt = $conn->prepare($enroll_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$enroll_result = $stmt->get_result();
while ($row = $enroll_result->fetch_assoc()) {
    $approved_enrollments[] = $row;
}
$stmt->close();

// ── Step 2: Fetch materials for each approved course+batch ────────────────────
$materials = [];

if (!empty($approved_enrollments)) {
    foreach ($approved_enrollments as $enr) {
        $cid = (int)$enr['course_id'];
        $bid = (int)$enr['batch_id'];

        $mat_query = "SELECT id, file_name, file_path FROM materials
                      WHERE course_id = ? AND batch_id = ?
                      ORDER BY id DESC";
        $mstmt = $conn->prepare($mat_query);
        if ($mstmt) {
            $mstmt->bind_param("ii", $cid, $bid);
            $mstmt->execute();
            $mresult = $mstmt->get_result();
            while ($mrow = $mresult->fetch_assoc()) {
                $mrow['course_name'] = $enr['course_name']; // attach course name
                $materials[] = $mrow;
            }
            $mstmt->close();
        }
    }
}

include 'header.php';
include 'sidebar.php';
?>

<style>
    .page-title {
        font-size: 24px;
        font-weight: 700;
        color: #1e293b;
        margin-bottom: 6px;
    }
    .page-sub {
        font-size: 14px;
        color: #64748b;
        margin-bottom: 28px;
    }

    /* Access Denied Box */
    .access-denied {
        background: #fff7ed;
        border: 1px solid #fed7aa;
        border-radius: 12px;
        padding: 40px;
        text-align: center;
        max-width: 500px;
        margin: 0 auto;
    }
    .access-denied .icon { font-size: 52px; margin-bottom: 14px; }
    .access-denied h3 { color: #c2410c; font-size: 20px; margin: 0 0 10px; }
    .access-denied p { color: #78350f; font-size: 14px; margin: 0; }

    /* Materials Grid */
    .materials-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 18px;
    }

    /* One card per file */
    .material-card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.07);
        padding: 20px 22px;
        width: 280px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        border-top: 4px solid #3b82f6;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .material-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 20px rgba(59,130,246,0.12);
    }
    .material-card .file-icon { font-size: 36px; }
    .material-card .file-name {
        font-size: 14px;
        font-weight: 600;
        color: #1e293b;
        word-break: break-word;
    }
    .material-card .course-tag {
        font-size: 12px;
        color: #fff;
        background: #3b82f6;
        display: inline-block;
        padding: 2px 10px;
        border-radius: 20px;
        font-weight: 600;
    }
    .material-card .btn-download {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #10b981;
        color: #fff;
        padding: 8px 16px;
        border-radius: 7px;
        font-size: 13px;
        font-weight: 600;
        text-decoration: none;
        border: none;
        cursor: pointer;
        transition: background 0.2s;
        width: fit-content;
    }
    .material-card .btn-download:hover { background: #059669; }

    /* No materials box */
    .no-materials {
        background: #f8fafc;
        border: 1px dashed #cbd5e1;
        border-radius: 12px;
        padding: 50px;
        text-align: center;
        color: #94a3b8;
    }
    .no-materials .icon { font-size: 48px; margin-bottom: 12px; }
    .no-materials p { font-size: 15px; margin: 0; }
</style>

<main class="main-content">
    <div class="page-title">📚 My Course Materials</div>
    <div class="page-sub">Download materials for your approved courses below.</div>

    <?php if (empty($approved_enrollments)): ?>
        <!-- Student has no approved payment yet -->
        <div class="access-denied">
            <div class="icon">🔒</div>
            <h3>Access Restricted</h3>
            <p>Your payment is not yet approved by admin.<br>
               Materials will be available once your payment is approved.</p>
        </div>

    <?php elseif (empty($materials)): ?>
        <!-- approved but no materials uploaded yet -->
        <div class="no-materials">
            <div class="icon">📂</div>
            <p>No materials uploaded yet for your course.<br>Please check back later.</p>
        </div>

    <?php else: ?>
        <!-- Show materials -->
        <div class="materials-grid">
            <?php foreach ($materials as $mat): ?>
                <?php
                    // Determine file icon based on extension
                    $ext = strtolower(pathinfo($mat['file_name'], PATHINFO_EXTENSION));
                    $icons = [
                        'pdf'  => '📄',
                        'doc'  => '📝', 'docx' => '📝',
                        'ppt'  => '📊', 'pptx' => '📊',
                        'xls'  => '📋', 'xlsx' => '📋',
                        'zip'  => '🗜️', 'rar'  => '🗜️',
                        'mp4'  => '🎬', 'avi'  => '🎬',
                        'jpg'  => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️',
                    ];
                    $icon = $icons[$ext] ?? '📁';

                    // file_path in DB is: "uploads/materials/filename" (relative to admin/ folder)
                    // From student/ folder, path becomes: ../admin/uploads/materials/filename
                    $file_path = '../admin/' . $mat['file_path'];
                ?>
                <div class="material-card">
                    <div class="file-icon"><?php echo $icon; ?></div>
                    <div class="file-name"><?php echo htmlspecialchars($mat['file_name']); ?></div>
                    <span class="course-tag"><?php echo htmlspecialchars($mat['course_name']); ?></span>
                    <a href="<?php echo htmlspecialchars($file_path); ?>" download class="btn-download">
                        ⬇ Download
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

  </div> <!-- close .layout-wrapper from sidebar -->
</body>
</html>

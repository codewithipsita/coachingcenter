<?php
session_start();

// If not logged in, redirect to login page
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

include('../db.php');

$student_id = $_SESSION['user_id'];
$today = date('Y-m-d'); // Today's date like 2026-09-13

// --- Handle Form Submission (when student clicks Present or Absent) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Get the status from the button clicked (present or absent)
    $status = $_POST['status'];

    // Get the course_id from the form
    $course_id = (int)$_POST['course_id'];

    // Only allow 'present' or 'absent' to be saved (security check)
    if ($status === 'present' || $status === 'absent') {

        // Check if attendance was already marked today for this course
        $check = mysqli_prepare($conn, "SELECT id FROM attendance WHERE student_id = ? AND course_id = ? AND attendance_date = ?");
        mysqli_stmt_bind_param($check, "iis", $student_id, $course_id, $today);
        mysqli_stmt_execute($check);
        mysqli_stmt_store_result($check);

        if (mysqli_stmt_num_rows($check) === 0) {
            // Not marked yet, so insert it now
            $insert = mysqli_prepare($conn, "INSERT INTO attendance (student_id, course_id, attendance_date, status) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($insert, "iiss", $student_id, $course_id, $today, $status);
            mysqli_stmt_execute($insert);
            mysqli_stmt_close($insert);
        }

        mysqli_stmt_close($check);
    }

    // Redirect to same page to avoid form resubmit on refresh
    header("Location: mark_attendance.php");
    exit();
}

// --- Get the student's enrolled course ---
// We look up which course this student is enrolled in
$course_query = mysqli_prepare($conn, "SELECT e.course_id, c.course_name FROM enrollments e JOIN courses c ON e.course_id = c.id WHERE e.student_id = ? LIMIT 1");
mysqli_stmt_bind_param($course_query, "i", $student_id);
mysqli_stmt_execute($course_query);
$course_result = mysqli_stmt_get_result($course_query);
$enrolled_course = mysqli_fetch_assoc($course_result);
mysqli_stmt_close($course_query);

// --- Check if attendance already marked today ---
$already_marked = false;
$marked_status = '';

if ($enrolled_course) {
    $course_id = $enrolled_course['course_id'];

    $att_check = mysqli_prepare($conn, "SELECT status FROM attendance WHERE student_id = ? AND course_id = ? AND attendance_date = ?");
    mysqli_stmt_bind_param($att_check, "iis", $student_id, $course_id, $today);
    mysqli_stmt_execute($att_check);
    $att_result = mysqli_stmt_get_result($att_check);
    $att_row = mysqli_fetch_assoc($att_result);
    mysqli_stmt_close($att_check);

    if ($att_row) {
        $already_marked = true;
        $marked_status = $att_row['status'];
    }
}

include('header.php');
include('sidebar.php');
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<main class="main-content" style="padding: 30px;">

    <h2 class="fw-bold mb-4" style="color: #4a3341;">&#128197; Mark Your Attendance</h2>

    <?php if (!$enrolled_course): ?>
        <!-- Student is not enrolled in any course -->
        <div class="alert alert-warning">
            <strong>Not Enrolled!</strong> You are not enrolled in any course. Please enroll first.
        </div>

    <?php else: ?>

        <!-- Show the attendance card -->
        <div class="card shadow-sm" style="max-width: 500px; border-radius: 15px; border: none;">
            <div class="card-body p-4">

                <!-- Today's Date -->
                <div class="mb-3">
                    <span class="text-muted">Today's Date:</span>
                    <h4 class="fw-bold mt-1" style="color: #4a3341;">
                        <?php echo date('d F Y'); // e.g. 13 September 2026 ?>
                    </h4>
                </div>

                <!-- Course Name -->
                <div class="mb-4">
                    <span class="text-muted">Course:</span>
                    <h5 class="fw-bold mt-1" style="color: #1e293b;">
                        <?php echo htmlspecialchars($enrolled_course['course_name']); ?>
                    </h5>
                </div>

                <hr>

                <?php if ($already_marked): ?>
                    <!-- Attendance already marked - show result, no buttons -->
                    <div class="text-center mt-3">
                        <p class="text-muted mb-2">Your attendance for today:</p>

                        <?php if ($marked_status === 'present'): ?>
                            <div class="alert alert-success fs-5 fw-bold">
                                &#10003; Present
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger fs-5 fw-bold">
                                &#10007; Absent
                            </div>
                        <?php endif; ?>

                        <p class="text-muted mt-2" style="font-size: 13px;">
                            <i class="bi bi-lock-fill"></i> Attendance is locked and cannot be changed.
                        </p>
                    </div>

                <?php else: ?>
                    <!-- Not marked yet - show Present and Absent buttons -->
                    <p class="text-muted mb-3">Mark your attendance for today:</p>

                    <form method="POST" action="mark_attendance.php">
                        <!-- Send the course_id as hidden field -->
                        <input type="hidden" name="course_id" value="<?php echo $course_id; ?>">

                        <div class="d-flex gap-3">
                            <!-- Present Button -->
                            <button type="submit" name="status" value="present"
                                class="btn btn-success btn-lg flex-fill fw-bold"
                                style="border-radius: 10px; padding: 15px;">
                                &#10003; Present
                            </button>

                            <!-- Absent Button -->
                            <button type="submit" name="status" value="absent"
                                class="btn btn-danger btn-lg flex-fill fw-bold"
                                style="border-radius: 10px; padding: 15px;">
                                &#10007; Absent
                            </button>
                        </div>
                    </form>

                <?php endif; ?>

            </div>
        </div>

    <?php endif; ?>

</main>

</div> <!-- closes .layout-wrapper from sidebar.php -->
</body>
</html>

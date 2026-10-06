<?php
session_start();

// If not logged in as admin, redirect to admin login page
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

include('../db.php');

$today = date('Y-m-d');

// --- Get all courses for the dropdown ---
$courses_result = mysqli_query($conn, "SELECT id, course_name FROM courses ORDER BY course_name ASC");

// --- Selected course ---
$selected_course_id   = 0;
$selected_course_name = '';

// --- Date filter: default to today ---
$selected_date = $today;
if (isset($_GET['filter_date']) && !empty($_GET['filter_date'])) {
    $selected_date = $_GET['filter_date'];
}

// --- Status filter ---
$selected_status = 'all';
if (isset($_GET['filter_status']) && in_array($_GET['filter_status'], ['all', 'present', 'absent', 'not_marked'])) {
    $selected_status = $_GET['filter_status'];
}

if (isset($_GET['course_id']) && $_GET['course_id'] > 0) {
    $selected_course_id = (int)$_GET['course_id'];

    $cn_query = mysqli_prepare($conn, "SELECT course_name FROM courses WHERE id = ?");
    mysqli_stmt_bind_param($cn_query, "i", $selected_course_id);
    mysqli_stmt_execute($cn_query);
    $cn_result = mysqli_stmt_get_result($cn_query);
    $cn_row    = mysqli_fetch_assoc($cn_result);
    mysqli_stmt_close($cn_query);

    if ($cn_row) {
        $selected_course_name = $cn_row['course_name'];
    }
}

include('header.php');
include('sidebar.php');
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<style>
    .filter-card {
        background: linear-gradient(135deg, #ffffff, #f8f4f8);
        border-radius: 16px;
        border: 1px solid #e8dde8;
        box-shadow: 0 4px 20px rgba(74,51,65,0.08);
    }
    .filter-strip {
        background: linear-gradient(135deg, #4a3341, #6b4a60);
        border-radius: 14px 14px 0 0;
        padding: 14px 20px;
        color: white;
        font-weight: 600;
        font-size: 15px;
    }
    .form-control:focus, .form-select:focus {
        border-color: #4a3341;
        box-shadow: 0 0 0 3px rgba(74,51,65,0.12);
    }
    .btn-filter {
        background: linear-gradient(135deg, #4a3341, #6b4a60);
        color: white;
        border: none;
        border-radius: 10px;
        padding: 10px 20px;
        font-weight: 600;
        transition: all .2s;
        white-space: nowrap;
    }
    .btn-filter:hover {
        background: linear-gradient(135deg, #3a2531, #5a3a50);
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(74,51,65,0.3);
    }
    .btn-reset {
        border: 2px solid #4a3341;
        color: #4a3341;
        background: transparent;
        border-radius: 10px;
        padding: 10px 14px;
        font-weight: 600;
        transition: all .2s;
        text-decoration: none;
        text-align: center;
    }
    .btn-reset:hover { background: #4a3341; color: white; }

    .summary-card {
        border-radius: 14px;
        border: none;
        padding: 18px 24px;
        text-align: center;
        min-width: 130px;
        transition: transform .2s, box-shadow .2s;
        cursor: default;
    }
    .summary-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,.12); }
    .summary-card .cnt  { font-size: 2rem; font-weight: 700; }
    .summary-card .lbl  { font-size: 12px; color: #64748b; font-weight: 500; margin-top: 2px; }

    .stab {
        border-radius: 20px;
        padding: 5px 14px;
        font-size: 13px;
        font-weight: 600;
        border: 2px solid transparent;
        transition: all .2s;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
    }
    .stab-default  { background:#f8f4f8; border-color:#e0d5e0; color:#4a3341; }
    .stab-all      { background:#ede9fe; color:#4c1d95; border-color:#a78bfa; }
    .stab-present  { background:#d1fae5; color:#065f46; border-color:#34d399; }
    .stab-absent   { background:#fee2e2; color:#991b1b; border-color:#f87171; }
    .stab-not_marked { background:#f1f5f9; color:#475569; border-color:#94a3b8; }

    .status-pill {
        font-size: 13px; padding: 6px 14px; border-radius: 20px;
        font-weight: 600; display: inline-flex; align-items: center; gap: 5px;
    }
    .date-badge {
        display: inline-flex; align-items: center; gap: 8px;
        background: linear-gradient(135deg, #4a3341, #6b4a60);
        color: white; padding: 6px 16px; border-radius: 30px;
        font-size: 14px; font-weight: 600;
    }
    .table tbody tr:hover { background-color: #fdf8fd; }
    .table thead th { font-size: 13px; letter-spacing: .4px; text-transform: uppercase; }
</style>

<main class="main-content" style="padding: 30px;">

    <!-- Page heading -->
    <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
        <h2 class="fw-bold mb-0" style="color:#4a3341;">&#128197; Attendance Report</h2>
        <?php if ($selected_course_id > 0): ?>
            <span class="date-badge">
                <i class="bi bi-calendar-check"></i>
                <?php echo date('d F Y', strtotime($selected_date)); ?>
                <?php if ($selected_date === $today): ?><span style="font-size:11px;opacity:.8;">(Today)</span><?php endif; ?>
            </span>
        <?php endif; ?>
    </div>

    <!-- ===== FILTER CARD ===== -->
    <div class="filter-card mb-4">
        <div class="filter-strip">
            <i class="bi bi-funnel-fill me-2"></i> Filter Attendance
        </div>
        <div class="p-4">
            <form method="GET" action="attendance.php">
                <div class="row g-3 align-items-end">

                    <!-- Course -->
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-semibold text-muted" style="font-size:13px;">
                            <i class="bi bi-book me-1"></i> Course
                        </label>
                        <select name="course_id" class="form-select" style="border-radius:10px; border:1.5px solid #ddd;">
                            <option value="0">-- Select a Course --</option>
                            <?php
                            mysqli_data_seek($courses_result, 0);
                            while ($course = mysqli_fetch_assoc($courses_result)) {
                                $sel = ($course['id'] == $selected_course_id) ? 'selected' : '';
                                echo "<option value='{$course['id']}' {$sel}>" . htmlspecialchars($course['course_name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Date Picker -->
                    <div class="col-12 col-md-3">
                        <label class="form-label fw-semibold text-muted" style="font-size:13px;">
                            <i class="bi bi-calendar3 me-1"></i> Date
                        </label>
                        <input type="date" name="filter_date" id="filter_date"
                               class="form-control"
                               style="border-radius:10px; border:1.5px solid #ddd;"
                               value="<?php echo htmlspecialchars($selected_date); ?>"
                               max="<?php echo $today; ?>">
                    </div>

                    <!-- Status Filter -->
                    <div class="col-12 col-md-3">
                        <label class="form-label fw-semibold text-muted" style="font-size:13px;">
                            <i class="bi bi-person-check me-1"></i> Status
                        </label>
                        <select name="filter_status" class="form-select" style="border-radius:10px; border:1.5px solid #ddd;">
                            <option value="all"        <?php echo $selected_status==='all'        ? 'selected':'' ?>>All Students</option>
                            <option value="present"    <?php echo $selected_status==='present'    ? 'selected':'' ?>>Present Only</option>
                            <option value="absent"     <?php echo $selected_status==='absent'     ? 'selected':'' ?>>Absent Only</option>
                            <option value="not_marked" <?php echo $selected_status==='not_marked' ? 'selected':'' ?>>Not Marked</option>
                        </select>
                    </div>

                    <!-- Action Buttons -->
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn-filter flex-fill">
                            <i class="bi bi-search me-1"></i> View
                        </button>
                        <a href="attendance.php" class="btn-reset flex-fill">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    </div>

                </div>
            </form>

            <!-- Quick Date Shortcuts -->
            <div class="mt-3 d-flex flex-wrap gap-2 align-items-center">
                <span class="text-muted" style="font-size:12px;">Quick:</span>
                <?php
                $shortcuts = [
                    'Today'     => $today,
                    'Yesterday' => date('Y-m-d', strtotime('-1 day')),
                    'Last 7 days' => date('Y-m-d', strtotime('-6 days')),
                ];
                foreach ($shortcuts as $lbl => $dv):
                    $url  = "attendance.php?course_id={$selected_course_id}&filter_date={$dv}&filter_status={$selected_status}";
                    $active = ($selected_date === $dv && $selected_course_id > 0);
                ?>
                <a href="<?php echo $url; ?>"
                   class="stab <?php echo $active ? 'stab-present' : 'stab-default'; ?>">
                    <?php echo $lbl; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <!-- ===== END FILTER CARD ===== -->

    <?php if ($selected_course_id > 0): ?>

        <h5 class="fw-bold mb-3" style="color:#1e293b;">
            <i class="bi bi-people-fill me-2" style="color:#4a3341;"></i>
            <?php echo htmlspecialchars($selected_course_name); ?>
            <span class="text-muted fw-normal" style="font-size:15px;">
                &mdash; <?php echo date('d F Y', strtotime($selected_date)); ?>
                <?php if ($selected_date === $today): ?>
                    <span class="badge bg-warning text-dark ms-1" style="font-size:11px;">Today</span>
                <?php endif; ?>
            </span>
        </h5>

        <?php
        // Status filter HAVING clause
        $having = '';
        if ($selected_status === 'present')    $having = "HAVING attendance_status = 'present'";
        elseif ($selected_status === 'absent') $having = "HAVING attendance_status = 'absent'";
        elseif ($selected_status === 'not_marked') $having = "HAVING attendance_status IS NULL";

        $students_query = mysqli_prepare($conn, "
            SELECT
                sm.id   AS student_id,
                sm.name AS student_name,
                sm.email,
                a.status AS attendance_status
            FROM enrollments e
            JOIN student_master sm ON e.student_id = sm.id
            LEFT JOIN attendance a  ON a.student_id  = sm.id
                                   AND a.course_id   = e.course_id
                                   AND a.attendance_date = ?
            WHERE e.course_id = ?
            GROUP BY sm.id
            {$having}
            ORDER BY sm.name ASC
        ");
        mysqli_stmt_bind_param($students_query, "si", $selected_date, $selected_course_id);
        mysqli_stmt_execute($students_query);
        $students_result = mysqli_stmt_get_result($students_query);

        $temp_rows = [];
        while ($row = mysqli_fetch_assoc($students_result)) {
            $temp_rows[] = $row;
        }
        mysqli_stmt_close($students_query);
        $total_filtered = count($temp_rows);

        // Total enrolled (for summary cards, always full set)
        $tq = mysqli_prepare($conn, "SELECT COUNT(DISTINCT sm.id) AS total FROM enrollments e JOIN student_master sm ON e.student_id=sm.id WHERE e.course_id=?");
        mysqli_stmt_bind_param($tq, "i", $selected_course_id);
        mysqli_stmt_execute($tq);
        $total_enrolled = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($tq))['total'];
        mysqli_stmt_close($tq);

        // Full summary counts for selected date
        // Use COUNT(DISTINCT sm.id) per status so duplicate enrollment rows
        // don't cause the same student to be counted more than once.
        $aq = mysqli_prepare($conn, "
            SELECT
                COUNT(DISTINCT CASE WHEN a.status = 'present' THEN sm.id ELSE NULL END) AS p_cnt,
                COUNT(DISTINCT CASE WHEN a.status = 'absent'  THEN sm.id ELSE NULL END) AS a_cnt
            FROM (SELECT DISTINCT student_id FROM enrollments WHERE course_id = ?) AS de
            JOIN student_master sm ON de.student_id = sm.id
            LEFT JOIN attendance a ON a.student_id = sm.id
                                  AND a.course_id  = ?
                                  AND a.attendance_date = ?
        ");
        mysqli_stmt_bind_param($aq, "iis", $selected_course_id, $selected_course_id, $selected_date);
        mysqli_stmt_execute($aq);
        $ares = mysqli_stmt_get_result($aq);
        $arow = mysqli_fetch_assoc($ares);
        mysqli_stmt_close($aq);

        $sum_present    = $arow ? (int)$arow['p_cnt'] : 0;
        $sum_absent     = $arow ? (int)$arow['a_cnt'] : 0;
        $sum_not_marked = $total_enrolled - $sum_present - $sum_absent;
        ?>

        <?php if ($total_enrolled > 0): ?>

            <!-- Summary Cards -->
            <div class="d-flex gap-3 mb-4 flex-wrap">
                <div class="summary-card" style="background:#ede9fe;">
                    <div class="cnt" style="color:#4c1d95;"><?php echo $total_enrolled; ?></div>
                    <div class="lbl">Total Enrolled</div>
                </div>
                <div class="summary-card" style="background:#d1fae5;">
                    <div class="cnt" style="color:#065f46;">&#10003; <?php echo $sum_present; ?></div>
                    <div class="lbl">Present</div>
                </div>
                <div class="summary-card" style="background:#fee2e2;">
                    <div class="cnt" style="color:#991b1b;">&#10007; <?php echo $sum_absent; ?></div>
                    <div class="lbl">Absent</div>
                </div>
                <div class="summary-card" style="background:#f1f5f9;">
                    <div class="cnt" style="color:#475569;">— <?php echo $sum_not_marked; ?></div>
                    <div class="lbl">Not Marked</div>
                </div>
            </div>

            <!-- Status Quick-filter tabs -->
            <div class="d-flex flex-wrap gap-2 mb-3">
                <?php
                $tabs = [
                    'all'        => "All ({$total_enrolled})",
                    'present'    => "&#10003; Present ({$sum_present})",
                    'absent'     => "&#10007; Absent ({$sum_absent})",
                    'not_marked' => "— Not Marked ({$sum_not_marked})",
                ];
                foreach ($tabs as $val => $tlabel):
                    $turl   = "attendance.php?course_id={$selected_course_id}&filter_date={$selected_date}&filter_status={$val}";
                    $isAct  = ($selected_status === $val);
                ?>
                <a href="<?php echo $turl; ?>"
                   class="stab <?php echo $isAct ? "stab-{$val}" : 'stab-default'; ?>">
                    <?php echo $tlabel; ?>
                </a>
                <?php endforeach; ?>
            </div>

            <?php if ($total_filtered > 0): ?>
                <!-- Students Table -->
                <div class="table-responsive">
                    <table class="table table-bordered align-middle" style="border-radius:12px; overflow:hidden;">
                        <thead style="background-color:#4a3341; color:white;">
                            <tr>
                                <th style="padding:13px; width:50px;">#</th>
                                <th style="padding:13px;">Student Name</th>
                                <th style="padding:13px;">Email</th>
                                <th style="padding:13px; text-align:center;">Attendance Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($temp_rows as $i => $student): ?>
                            <tr>
                                <td style="padding:12px; color:#94a3b8; font-size:13px;"><?php echo $i + 1; ?></td>
                                <td style="padding:12px; font-weight:600;">
                                    <i class="bi bi-person-circle me-2" style="color:#4a3341;"></i>
                                    <?php echo htmlspecialchars($student['student_name']); ?>
                                </td>
                                <td style="padding:12px; color:#64748b; font-size:13px;">
                                    <?php echo htmlspecialchars($student['email']); ?>
                                </td>
                                <td style="padding:12px; text-align:center;">
                                    <?php if ($student['attendance_status'] === 'present'): ?>
                                        <span class="status-pill" style="background:#d1fae5; color:#065f46;">
                                            <i class="bi bi-check-circle-fill"></i> Present
                                        </span>
                                    <?php elseif ($student['attendance_status'] === 'absent'): ?>
                                        <span class="status-pill" style="background:#fee2e2; color:#991b1b;">
                                            <i class="bi bi-x-circle-fill"></i> Absent
                                        </span>
                                    <?php else: ?>
                                        <span class="status-pill" style="background:#f1f5f9; color:#64748b;">
                                            <i class="bi bi-dash-circle"></i> Not Marked
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            <?php else: ?>
                <div class="alert alert-info d-flex align-items-center gap-2" style="border-radius:12px;">
                    <i class="bi bi-info-circle-fill fs-5"></i>
                    No students found with status
                    <strong>"<?php echo ucfirst(str_replace('_',' ', $selected_status)); ?>"</strong>
                    on <?php echo date('d F Y', strtotime($selected_date)); ?>.
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="alert alert-info" style="border-radius:12px;">No students are enrolled in this course yet.</div>
        <?php endif; ?>

    <?php endif; ?>

</main>

</div> <!-- closes .layout-wrapper from sidebar.php -->
</body>
</html>

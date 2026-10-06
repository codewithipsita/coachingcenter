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

// 1. Include database connection FIRST
include('../db.php');

// ==========================================
// 2. HANDLE DELETE ACTION
// ==========================================
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    // BUG FIX: Use prepared statement to fetch photo path
    $photo_stmt = mysqli_prepare($conn, "SELECT profile_photo FROM student_master WHERE id = ?");
    $photo_path = "";
    if ($photo_stmt) {
        mysqli_stmt_bind_param($photo_stmt, "i", $delete_id);
        mysqli_stmt_execute($photo_stmt);
        mysqli_stmt_bind_result($photo_stmt, $photo_path);
        mysqli_stmt_fetch($photo_stmt);
        mysqli_stmt_close($photo_stmt);
    }

    // BUG FIX: Use prepared statement for DELETE
    $del_stmt = mysqli_prepare($conn, "DELETE FROM student_master WHERE id = ?");
    if ($del_stmt) {
        mysqli_stmt_bind_param($del_stmt, "i", $delete_id);
        if (mysqli_stmt_execute($del_stmt)) {
            if (!empty($photo_path) && file_exists("../" . $photo_path)) {
                unlink("../" . $photo_path);
            }
            $_SESSION['success'] = "Student and photo deleted successfully!";
        } else {
            $_SESSION['error'] = "Failed to delete student: " . mysqli_error($conn);
        }
        mysqli_stmt_close($del_stmt);
    }

    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit();
}

// ==========================================
// 3. HANDLE UPDATE ACTION
// ==========================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_student'])) {
    $student_id    = intval($_POST['student_id']);
    $name          = trim($_POST['name']);
    $email         = trim($_POST['email']);
    $contact_no    = trim($_POST['contact_no']);
    $gender        = trim($_POST['gender']);
    $status        = trim($_POST['status']);
    $profile_photo = trim($_POST['existing_photo']);

    // Handle new photo upload
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $target_dir = "uploads/student/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_name   = time() . "_" . basename($_FILES["profile_photo"]["name"]);
        $target_file = $target_dir . $file_name;
        if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $target_file)) {
            $new_profile_photo = "admin/" . $target_file;
            if (!empty($profile_photo) && file_exists("../" . $profile_photo)) {
                unlink("../" . $profile_photo);
            }
            $profile_photo = $new_profile_photo;
        }
    }

    // BUG FIX: Use prepared statement for UPDATE to prevent SQL injection
    $upd_stmt = mysqli_prepare($conn,
        "UPDATE student_master SET name=?, email=?, contact_no=?, gender=?, status=?, profile_photo=? WHERE id=?"
    );
    if ($upd_stmt) {
        mysqli_stmt_bind_param($upd_stmt, "ssssssi",
            $name, $email, $contact_no, $gender, $status, $profile_photo, $student_id
        );
        if (mysqli_stmt_execute($upd_stmt)) {
            // Also sync the status into the enrollments table so the display column updates
            $enr_upd = mysqli_prepare($conn, "UPDATE enrollments SET status=? WHERE student_id=?");
            if ($enr_upd) {
                mysqli_stmt_bind_param($enr_upd, "si", $status, $student_id);
                mysqli_stmt_execute($enr_upd);
                mysqli_stmt_close($enr_upd);
            }
            $_SESSION['success'] = "Student updated successfully!";
        } else {
            $_SESSION['error'] = "Failed to update student: " . mysqli_error($conn);
        }
        mysqli_stmt_close($upd_stmt);
    }

    header("Location: " . basename($_SERVER['PHP_SELF']));
    exit();
}

// ==========================================
// 4. INCLUDE HTML LAYOUT (Header & Sidebar)
// ==========================================
include('header.php');
include('sidebar.php');

// ==========================================
// 5. FETCH ALL STUDENTS
// ==========================================
// Join enrollments to get the real payment/approval status dynamically
$query = "SELECT s.id, s.name, s.email, s.contact_no, s.gender, s.profile_photo, s.status,
                 COALESCE(e.status, 'pending') AS enroll_status
          FROM student_master s
          LEFT JOIN enrollments e ON e.student_id = s.id
          GROUP BY s.id
          ORDER BY s.id DESC";
$result = mysqli_query($conn, $query);
?>

<style>
    .main-content { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8fafc; padding: 30px; }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; }
    .page-header h2 { margin: 0; color: #1e293b; font-size: 24px; }
    .alert { padding: 14px 20px; border-radius: 6px; margin-bottom: 20px; font-weight: 500; font-size: 15px; }
    .alert-success { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .alert-error { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
    
    .table-container { background: #ffffff; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow-x: auto; }
    .student-table { width: 100%; border-collapse: collapse; }
    .student-table thead { background-color: #0f172a; }
    .student-table thead th { color: #e2e8f0; padding: 14px 16px; text-align: left; font-size: 13px; font-weight: 600; text-transform: uppercase; white-space: nowrap; }
    .student-table tbody tr { border-bottom: 1px solid #f1f5f9; transition: background-color 0.2s; }
    .student-table tbody tr:hover { background-color: #f8fafc; }
    .student-table tbody td { padding: 14px 16px; font-size: 14px; color: #334155; vertical-align: middle; }
    .profile-img { width: 45px; height: 45px; object-fit: cover; border-radius: 50%; border: 1px solid #e2e8f0; }
    .status-badge { padding: 4px 12px; border-radius: 20px; font-weight: 600; font-size: 13px; white-space: nowrap; }
    .status-unpaid  { background-color: #fee2e2; color: #991b1b; }
    .status-paid    { background-color: #dcfce7; color: #166534; }
    .status-pending  { background-color: #fef9c3; color: #854d0e; }
    .status-approved { background-color: #dcfce7; color: #166534; }
    
    .action-btns { display: flex; gap: 8px; }
    .btn-edit, .btn-delete { padding: 7px 14px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; }
    .btn-edit { background-color: #dbeafe; color: #1d4ed8; }
    .btn-edit:hover { background-color: #bfdbfe; }
    .btn-delete { background-color: #fee2e2; color: #dc2626; }
    .btn-delete:hover { background-color: #fecaca; }

    /* Modal Overlay */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 1000; justify-content: center; align-items: center; animation: fadeIn 0.25s ease; }
    .modal-overlay.active { display: flex; }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes slideUp { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    
    .modal-box { background: #ffffff; border-radius: 14px; width: 600px; max-width: 92%; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 60px rgba(0,0,0,0.25); animation: slideUp 0.3s ease; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 22px 28px; border-bottom: 1px solid #e2e8f0; }
    .modal-header h3 { margin: 0; font-size: 20px; color: #1e293b; }
    .modal-close { background: #f1f5f9; border: none; width: 36px; height: 36px; border-radius: 50%; font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: #64748b; transition: all 0.2s; }
    .modal-close:hover { background: #e2e8f0; color: #1e293b; }
    
    .modal-body { padding: 28px; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #475569; font-size: 14px; }
    .form-group input[type="text"], .form-group input[type="email"], .form-group select, .form-group input[type="file"] { width: 100%; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
    .form-group input:focus, .form-group select:focus { border-color: #3b82f6; outline: none; }
    .current-img { width: 60px; height: 60px; object-fit: cover; border-radius: 50%; margin-top: 8px; border: 1px solid #e2e8f0;}
    
    .modal-footer { display: flex; justify-content: flex-end; gap: 12px; padding: 18px 28px; border-top: 1px solid #e2e8f0; background: #f8fafc; border-radius: 0 0 14px 14px; }
    .btn-cancel { padding: 10px 22px; border: 1px solid #cbd5e1; background: #ffffff; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; color: #475569; }
    .btn-update { padding: 10px 22px; background-color: #2563eb; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: 600; }
</style>

<main class="main-content">
    <div class="page-header">
        <h2>All Students</h2>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="table-container">
        <table class="student-table">
            <thead>
                <tr>
                    <th>S.N.</th>
                    <th>Photo</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Contact No</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php $sn = 1; while ($row = mysqli_fetch_assoc($result)):
                        // Use enrollment status if available, else show 'No Enrollment'
                        $enroll_status = !empty($row['enroll_status']) ? strtolower($row['enroll_status']) : 'no enrollment';
                        if ($enroll_status === 'approved') {
                            $statusLabel = 'Approved';
                            $statusClass = 'status-approved';
                        } elseif ($enroll_status === 'pending') {
                            $statusLabel = 'Pending';
                            $statusClass = 'status-pending';
                        } else {
                            $statusLabel = 'No Enrollment';
                            $statusClass = 'status-unpaid';
                        }
                    ?>
                        <tr>
                            <td><?php echo $sn++; ?></td>
                            <td>
                                <img src="../<?php echo htmlspecialchars($row['profile_photo']); ?>" alt="Profile" class="profile-img">
                            </td>
                            <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['contact_no']); ?></td>
                            <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusLabel); ?></span></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-edit" onclick="openEditModal(
                                        <?php echo $row['id']; ?>,
                                        '<?php echo addslashes(htmlspecialchars($row['name'])); ?>',
                                        '<?php echo addslashes(htmlspecialchars($row['email'])); ?>',
                                        '<?php echo addslashes(htmlspecialchars($row['contact_no'])); ?>',
                                        '<?php echo addslashes(htmlspecialchars($row['gender'])); ?>',
                                        '<?php echo addslashes(htmlspecialchars($row['status'])); ?>',
                                        '<?php echo addslashes(htmlspecialchars($row['profile_photo'])); ?>'
                                    )">✏️ Edit</button>
                                    
                                    <a href="<?php echo basename($_SERVER['PHP_SELF']); ?>?delete_id=<?php echo $row['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this student?');">🗑️ Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">
                            No students found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- ===== EDIT STUDENT MODAL ===== -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>✏️ Edit Student</h3>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <!-- FIXED: Form action points exactly to this file -->
        <form action="<?php echo htmlspecialchars(basename($_SERVER['PHP_SELF'])); ?>" method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="student_id" id="edit_student_id">

                <div class="form-group">
                    <label for="edit_name">Student Name</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="edit_email">Email</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="edit_contact">Contact No</label>
                    <input type="text" id="edit_contact" name="contact_no" required>
                </div>

                <div class="form-group">
                    <label for="edit_gender">Gender</label>
                    <select id="edit_gender" name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <!-- Status field -->
                <div class="form-group">
                    <label for="edit_status">Payment Status</label>
                    <select id="edit_status" name="status" required>
                        <option value="Not Payment">Not Payment</option>
                        <option value="Paid">Paid</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Current Profile Photo</label>
                    <br>
                    <img id="edit_current_img" src="" class="current-img" alt="Current Photo">
                    <input type="hidden" name="existing_photo" id="edit_existing_photo">
                </div>

                <div class="form-group">
                    <label for="edit_profile_photo">Change Photo (optional)</label>
                    <input type="file" id="edit_profile_photo" name="profile_photo" accept="image/*">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" name="update_student" class="btn-update">Update Student</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditModal(id, name, email, contact, gender, status, photo) {
        document.getElementById('edit_student_id').value = id;
        document.getElementById('edit_name').value = name;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_contact').value = contact;
        document.getElementById('edit_gender').value = gender;
        document.getElementById('edit_status').value = status;
        
        document.getElementById('edit_current_img').src = '../' + photo;
        document.getElementById('edit_existing_photo').value = photo;

        document.getElementById('editModal').classList.add('active');
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.remove('active');
    }

    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeEditModal();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeEditModal();
        }
    });
</script>

  </div> <!-- close .layout-wrapper -->
</body>
</html>
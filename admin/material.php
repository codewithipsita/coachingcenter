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

include('../db.php'); // Include DB connection

// ===== Handle DELETE Material =====
if (isset($_GET['delete_id'])) {
    $del_id = intval($_GET['delete_id']);
    // Fetch file path before deleting
    $del_res = mysqli_query($conn, "SELECT file_path FROM materials WHERE id = $del_id");
    if ($del_res && $del_row = mysqli_fetch_assoc($del_res)) {
        // Delete physical file
        if (!empty($del_row['file_path']) && file_exists($del_row['file_path'])) {
            unlink($del_row['file_path']);
        }
    }
    mysqli_query($conn, "DELETE FROM materials WHERE id = $del_id");
    $_SESSION['success'] = "Material deleted successfully!";
    header("Location: material.php");
    exit();
}

// ===== Handle EDIT Material (rename display name + optional new file) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_material_id'])) {
    $edit_id        = intval($_POST['edit_material_id']);
    $new_course_id  = intval($_POST['edit_course_id']);
    $new_batch_id   = intval($_POST['edit_batch_id']);
    $new_label      = mysqli_real_escape_string($conn, trim($_POST['edit_file_label']));

    // Fetch current record
    $cur_res = mysqli_query($conn, "SELECT file_name, file_path FROM materials WHERE id = $edit_id");
    $cur     = mysqli_fetch_assoc($cur_res);

    $final_file_name = $cur['file_name'];
    $final_file_path = $cur['file_path'];

    // If admin uploaded a replacement file
    if (isset($_FILES['edit_file']) && $_FILES['edit_file']['error'] === 0) {
        $ext          = pathinfo($_FILES['edit_file']['name'], PATHINFO_EXTENSION);
        $clean        = preg_replace('/[^A-Za-z0-9\-]/', '_', pathinfo($_FILES['edit_file']['name'], PATHINFO_FILENAME));
        $new_filename = $clean . '_' . time() . '_' . rand(1000,9999) . '.' . $ext;
        $new_path     = 'uploads/materials/' . $new_filename;

        if (move_uploaded_file($_FILES['edit_file']['tmp_name'], $new_path)) {
            // Delete old file
            if (!empty($cur['file_path']) && file_exists($cur['file_path'])) {
                unlink($cur['file_path']);
            }
            $final_file_name = mysqli_real_escape_string($conn, $new_filename);
            $final_file_path = mysqli_real_escape_string($conn, $new_path);
        }
    } elseif (!empty($new_label)) {
        // Admin just renamed the label (no new file)
        $final_file_name = $new_label;
    }

    mysqli_query($conn, "UPDATE materials
                         SET course_id='$new_course_id', batch_id='$new_batch_id',
                             file_name='$final_file_name', file_path='$final_file_path'
                         WHERE id=$edit_id");
    $_SESSION['success'] = "Material updated successfully!";
    header("Location: material.php");
    exit();
}

// ===== Handle Form Submission (Upload Materials) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $course_id = mysqli_real_escape_string($conn, trim($_POST['course_id']));
    $batch_id  = mysqli_real_escape_string($conn, trim($_POST['batch_id']));
    
    $upload_dir = 'uploads/materials/';
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $upload_success = true;

    if (isset($_FILES['materials']) && !empty($_FILES['materials']['name'][0])) {
        $total_files = count($_FILES['materials']['name']);
        
        for ($i = 0; $i < $total_files; $i++) {
            if ($_FILES['materials']['error'][$i] === 0) {
                $ext = pathinfo($_FILES['materials']['name'][$i], PATHINFO_EXTENSION);
                $original_name = basename($_FILES['materials']['name'][$i], ".$ext");
                $clean_name = preg_replace('/[^A-Za-z0-9\-]/', '_', $original_name);
                $new_filename = $clean_name . '_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['materials']['tmp_name'][$i], $upload_path)) {
                    $db_file_name = mysqli_real_escape_string($conn, $new_filename);
                    $db_file_path = mysqli_real_escape_string($conn, $upload_path);
                    
                    $query = "INSERT INTO materials (course_id, batch_id, file_name, file_path) 
                              VALUES ('$course_id', '$batch_id', '$db_file_name', '$db_file_path')";
                    
                    if (!mysqli_query($conn, $query)) {
                        $upload_success = false;
                    }
                } else {
                    $upload_success = false;
                }
            }
        }

        if ($upload_success) {
            $_SESSION['success'] = "Materials uploaded successfully!";
        } else {
            $_SESSION['error'] = "Some materials failed to upload or save to the database.";
        }
    } else {
        $_SESSION['error'] = "No files were selected.";
    }

    header("Location: material.php");
    exit();
}

// ===== Fetch data for Dropdowns =====
$courses_query = "SELECT `id`, `course_name` FROM `courses` WHERE 1";
$courses_result = mysqli_query($conn, $courses_query);
$courses = [];
if (mysqli_num_rows($courses_result) > 0) {
    while($row = mysqli_fetch_assoc($courses_result)) {
        $courses[] = $row;
    }
}

$batches_query = "SELECT `id`, `course_id`, `batch_name` FROM `batches` WHERE 1";
$batches_result = mysqli_query($conn, $batches_query);
$batches = [];
if (mysqli_num_rows($batches_result) > 0) {
    while($row = mysqli_fetch_assoc($batches_result)) {
        $batches[] = $row;
    }
}

// ===== Fetch all uploaded materials for the table =====
$all_materials = [];
$mat_query = "SELECT m.id, m.file_name, m.file_path, m.course_id, m.batch_id,
                     c.course_name, b.batch_name
              FROM materials m
              LEFT JOIN courses c ON c.id = m.course_id
              LEFT JOIN batches b ON b.id = m.batch_id
              ORDER BY m.id DESC";
$mat_result = mysqli_query($conn, $mat_query);
if ($mat_result) {
    while ($row = mysqli_fetch_assoc($mat_result)) {
        $all_materials[] = $row;
    }
}

include('header.php');
include('sidebar.php');
?>

<style>
    .main-content {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f1f5f9;
        padding: 30px;
    }
    .card {
        background: #ffffff;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        width: 100%; 
        box-sizing: border-box;
        margin-bottom: 30px;
    }
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 15px;
        margin-bottom: 25px;
    }
    .card-header h2 {
        margin: 0;
        color: #1e293b;
        font-size: 20px;
    }
    
    /* Form Styles */
    .form-row {
        display: flex;
        gap: 20px;
        align-items: flex-end;
        margin-bottom: 20px;
    }
    .form-group {
        flex: 1;
        margin-bottom: 0;
    }
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #475569;
        font-size: 14px;
    }
    .form-group input[type="text"],
    .form-group input[type="file"],
    .form-group select {
        width: 100%;
        padding: 12px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        box-sizing: border-box;
        font-size: 15px;
        transition: border-color 0.3s;
        background-color: #fff;
    }
    .form-group input:focus,
    .form-group select:focus {
        border-color: #3b82f6;
        outline: none;
    }
    
    /* Dynamic File Row Styles */
    .material-wrapper { margin-top: 15px; }
    .material-row {
        display: flex;
        gap: 15px;
        align-items: center;
        margin-bottom: 15px;
    }
    .material-row input[type="file"] { flex: 1; }
    
    .btn-action-circle {
        width: 45px;
        height: 45px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 22px;
        font-weight: bold;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: opacity 0.3s;
    }
    .btn-action-circle:hover { opacity: 0.8; }
    .btn-add { background-color: #10b981; }
    .btn-remove { background-color: #ef4444; }

    .btn-save {
        background-color: #2563eb;
        color: white;
        padding: 12px 28px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 15px;
        font-weight: 600;
        transition: background-color 0.3s;
        height: 45px;
        width: 100%;
        margin-top: 10px;
    }
    .btn-save:hover { background-color: #1d4ed8; }

    /* Alerts */
    .alert {
        padding: 14px 20px;
        border-radius: 6px;
        margin-bottom: 20px;
        font-weight: 500;
        font-size: 15px;
    }
    .alert-success { background-color: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .alert-error { background-color: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

    @media (max-width: 768px) {
        .form-row { flex-direction: column; align-items: stretch; }
        .form-row .form-group { margin-bottom: 15px; }
    }

    /* ── Materials Table ── */
    .mat-table-wrapper { overflow-x: auto; }
    .mat-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    .mat-table thead { background: #1e293b; color: #e2e8f0; }
    .mat-table thead th {
        padding: 13px 16px;
        text-align: left;
        font-weight: 600;
        font-size: 13px;
        white-space: nowrap;
    }
    .mat-table tbody tr { border-bottom: 1px solid #f1f5f9; transition: background 0.15s; }
    .mat-table tbody tr:hover { background: #f8fafc; }
    .mat-table tbody td { padding: 12px 16px; color: #374151; vertical-align: middle; }
    .mat-table .file-name-cell { max-width: 220px; word-break: break-word; font-weight: 600; color: #1e293b; }
    .tbl-btn {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 6px 13px; border-radius: 6px; font-size: 12px;
        font-weight: 600; border: none; cursor: pointer; text-decoration: none;
        transition: background 0.2s;
    }
    .tbl-btn-edit   { background: #f59e0b; color: #fff; }
    .tbl-btn-edit:hover   { background: #d97706; }
    .tbl-btn-delete { background: #ef4444; color: #fff; }
    .tbl-btn-delete:hover { background: #dc2626; }
    .tbl-btn-view   { background: #3b82f6; color: #fff; }
    .tbl-btn-view:hover   { background: #2563eb; }

    /* ── Edit Modal ── */
    .edit-overlay {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,0.55); z-index: 3000;
        align-items: center; justify-content: center;
    }
    .edit-overlay.open { display: flex; }
    .edit-box {
        background: #fff; border-radius: 14px; padding: 30px;
        width: 480px; max-width: 95%; position: relative;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    }
    .edit-box h3 { margin: 0 0 22px; font-size: 18px; color: #1e293b; }
    .edit-box .form-group { margin-bottom: 16px; }
    .edit-box .form-group label { display:block; font-weight:600; color:#475569; font-size:13px; margin-bottom:6px; }
    .edit-box .form-group input,
    .edit-box .form-group select {
        width:100%; padding:10px 12px; border:1px solid #cbd5e1;
        border-radius:7px; font-size:14px; box-sizing:border-box;
    }
    .edit-box .form-group input:focus,
    .edit-box .form-group select:focus { border-color:#3b82f6; outline:none; }
    .edit-close { position:absolute; top:14px; right:18px; background:none; border:none; font-size:22px; cursor:pointer; color:#64748b; }
    .edit-close:hover { color:#e11d48; }
    .btn-update { background:#10b981; color:#fff; padding:10px 24px; border:none; border-radius:7px; font-size:14px; font-weight:600; cursor:pointer; transition:background 0.2s; }
    .btn-update:hover { background:#059669; }
    .no-materials-row td { text-align:center; color:#94a3b8; font-size:15px; padding:40px; }
</style>

<main class="main-content">
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- UPLOAD MATERIAL CARD -->
    <div class="card">
        <div class="card-header">
            <h2>Upload Course Materials</h2>
        </div>
        
        <form action="material.php" method="POST" enctype="multipart/form-data">
            <div class="form-row">
                <div class="form-group">
                    <label for="course_id">Select Course</label>
                    <select id="course_id" name="course_id" required onchange="loadBatches()">
                        <option value="" disabled selected>-- Choose a Course --</option>
                        <?php 
                        foreach($courses as $course) {
                            echo '<option value="'.$course['id'].'">' . htmlspecialchars($course['course_name']) . '</option>';
                        }
                        if (empty($courses)) { echo '<option value="" disabled>No courses available</option>'; }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="batch_id">Select Batch</label>
                    <select id="batch_id" name="batch_id" required>
                        <option value="" disabled selected>-- Choose a Batch --</option>
                    </select>
                </div>
            </div>

            <div class="form-group material-wrapper">
                <label>Upload Files</label>
                <div id="materials-container">
                    <div class="material-row">
                        <input type="file" name="materials[]" required>
                        <button type="button" class="btn-action-circle btn-add" onclick="addMaterialField()">+</button>
                    </div>
                </div>
            </div>

            <div style="max-width: 200px;">
                <button type="submit" class="btn-save">Upload Materials</button>
            </div>
        </form>
    </div>

    <!-- ── Uploaded Materials Table ── -->
    <div class="card">
        <div class="card-header">
            <h2>📁 Uploaded Materials</h2>
            <span style="font-size:13px;color:#64748b;"><?php echo count($all_materials); ?> file(s) total</span>
        </div>
        <div class="mat-table-wrapper">
            <table class="mat-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>File Name</th>
                        <th>Course</th>
                        <th>Batch</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($all_materials)): ?>
                        <tr class="no-materials-row">
                            <td colspan="5">No materials uploaded yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php $sr = 1; foreach ($all_materials as $mat): ?>
                        <tr>
                            <td><?php echo $sr++; ?></td>
                            <td class="file-name-cell"><?php echo htmlspecialchars($mat['file_name']); ?></td>
                            <td><?php echo htmlspecialchars($mat['course_name'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($mat['batch_name'] ?? '—'); ?></td>
                            <td style="white-space:nowrap; display:flex; gap:6px;">
                                <!-- View / Download -->
                                <a href="<?php echo htmlspecialchars($mat['file_path']); ?>" target="_blank" class="tbl-btn tbl-btn-view">👁 View</a>
                                <!-- Edit -->
                                <button class="tbl-btn tbl-btn-edit"
                                    onclick="openEditModal(
                                        <?php echo $mat['id']; ?>,
                                        '<?php echo addslashes(htmlspecialchars($mat['file_name'])); ?>',
                                        <?php echo (int)$mat['course_id']; ?>,
                                        <?php echo (int)$mat['batch_id']; ?>
                                    )">✏ Edit</button>
                                <!-- Delete -->
                                <a href="material.php?delete_id=<?php echo $mat['id']; ?>"
                                   class="tbl-btn tbl-btn-delete"
                                   onclick="return confirm('Delete this file permanently?')">🗑 Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>

<!-- ── Edit Material Modal ── -->
<div class="edit-overlay" id="editOverlay">
    <div class="edit-box">
        <button class="edit-close" onclick="closeEditModal()">✕</button>
        <h3>✏ Edit Material</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="edit_material_id" id="edit_material_id">

            <div class="form-group">
                <label>File Label / Display Name</label>
                <input type="text" name="edit_file_label" id="edit_file_label" placeholder="e.g. Chapter 1 Notes">
            </div>

            <div class="form-group">
                <label>Replace File <span style="font-weight:400;color:#94a3b8;">(optional — leave blank to keep current)</span></label>
                <input type="file" name="edit_file">
            </div>

            <div class="form-group">
                <label>Course</label>
                <select name="edit_course_id" id="edit_course_id" required onchange="loadEditBatches()">
                    <option value="" disabled>-- Select Course --</option>
                    <?php foreach ($courses as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['course_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Batch</label>
                <select name="edit_batch_id" id="edit_batch_id" required>
                    <option value="" disabled selected>-- Select Batch --</option>
                </select>
            </div>

            <button type="submit" class="btn-update">✔ Save Changes</button>
        </form>
    </div>
</div>

<script>
    // Pass PHP batches array securely to JavaScript
    const batches = <?php echo json_encode($batches); ?>;

    // Load dynamic dependent dropdown
    function loadBatches() {
        const courseId = document.getElementById('course_id').value;
        const batchSelect = document.getElementById('batch_id');
        
        // Reset batch dropdown
        batchSelect.innerHTML = '<option value="" disabled selected>-- Choose a Batch --</option>';
        
        // Filter and append corresponding batches
        const filteredBatches = batches.filter(batch => batch.course_id == courseId);
        filteredBatches.forEach(batch => {
            const option = document.createElement('option');
            option.value = batch.id;
            option.textContent = batch.batch_name;
            batchSelect.appendChild(option);
        });

        if(filteredBatches.length === 0) {
            batchSelect.innerHTML = '<option value="" disabled selected>No batches found for this course</option>';
        }
    }

    // Add and Remove dynamic file inputs
    function addMaterialField() {
        const container = document.getElementById('materials-container');
        const row = document.createElement('div');
        row.className = 'material-row';
        
        row.innerHTML = `
            <input type="file" name="materials[]" required>
            <button type="button" class="btn-action-circle btn-remove" onclick="this.parentElement.remove()">-</button>
        `;
        
        container.appendChild(row);
    }

    // ── Edit Modal Functions ──────────────────────────────
    function openEditModal(id, fileName, courseId, batchId) {
        document.getElementById('edit_material_id').value = id;
        document.getElementById('edit_file_label').value  = fileName;

        // Set course dropdown
        const courseSelect = document.getElementById('edit_course_id');
        courseSelect.value = courseId;

        // Load batches for that course, then set batch
        loadEditBatches(batchId);

        document.getElementById('editOverlay').classList.add('open');
    }

    function closeEditModal() {
        document.getElementById('editOverlay').classList.remove('open');
    }

    // Close modal when clicking outside the box
    document.getElementById('editOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });

    function loadEditBatches(selectBatchId = null) {
        const courseId  = document.getElementById('edit_course_id').value;
        const batchSel  = document.getElementById('edit_batch_id');

        batchSel.innerHTML = '<option value="" disabled selected>-- Select Batch --</option>';

        const filtered = batches.filter(b => b.course_id == courseId);
        filtered.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b.id;
            opt.textContent = b.batch_name;
            if (selectBatchId && b.id == selectBatchId) opt.selected = true;
            batchSel.appendChild(opt);
        });

        if (filtered.length === 0) {
            batchSel.innerHTML = '<option value="" disabled selected>No batches for this course</option>';
        }
    }
</script>

  </div> <!-- close .layout-wrapper from sidebar/header -->
</body>
</html>
<?php 
// Close DB connection
mysqli_close($conn); 
?>
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

// ===== Handle Delete Action =====
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $delete_query = "DELETE FROM batches WHERE id = $delete_id";
    if (mysqli_query($conn, $delete_query)) {
        $_SESSION['success'] = "Batch deleted successfully!";
    } else {
        $_SESSION['error'] = "Failed to delete batch: " . mysqli_error($conn);
    }
    header("Location: batch.php");
    exit();
}

// ===== Handle Form Submission (Add & Update) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $course_id  = mysqli_real_escape_string($conn, trim($_POST['course_id']));
    $batch_name = mysqli_real_escape_string($conn, trim($_POST['batch_name']));
    $batch_id   = isset($_POST['batch_id']) ? intval($_POST['batch_id']) : 0;

    if ($batch_id > 0) {
        // UPDATE existing batch
        $query = "UPDATE batches SET course_id = '$course_id', batch_name = '$batch_name' WHERE id = $batch_id";
        $success_msg = "Batch updated successfully!";
    } else {
        // INSERT new batch
        $query = "INSERT INTO batches (course_id, batch_name) VALUES ('$course_id', '$batch_name')";
        $success_msg = "Batch added successfully!";
    }

    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = $success_msg;
        header("Location: batch.php");
        exit();
    } else {
        $_SESSION['error'] = "Database error: " . mysqli_error($conn);
        header("Location: batch.php");
        exit();
    }
}

// ===== Fetch data for Dropdown & Table =====
$courses_query = "SELECT `id`, `course_name` FROM `courses` WHERE 1";
$courses_result = mysqli_query($conn, $courses_query);

// Store courses in an array so we can reuse them in the modal
$courses = [];
if (mysqli_num_rows($courses_result) > 0) {
    while($row = mysqli_fetch_assoc($courses_result)) {
        $courses[] = $row;
    }
}

// Fetch all batches (added b.course_id so JS can pre-select the dropdown in the modal)
$batches_query = "SELECT b.id, b.course_id, b.batch_name, c.course_name 
                  FROM batches b 
                  JOIN courses c ON b.course_id = c.id 
                  ORDER BY b.id DESC";
$batches_result = mysqli_query($conn, $batches_query);

include('header.php');
include('sidebar.php');
?>

<!-- Modern styling for full-width cards and modal -->
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
    }
    .btn-save:hover { background-color: #1d4ed8; }

    /* Table Styles */
    .table-responsive {
        overflow-x: auto;
    }
    .data-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }
    .data-table th, .data-table td {
        padding: 15px;
        border-bottom: 1px solid #e2e8f0;
        color: #334155;
    }
    .data-table th {
        background-color: #f8fafc;
        font-weight: 600;
        color: #0f172a;
    }
    .data-table tr:hover { background-color: #f1f5f9; }
    
    /* Action Buttons */
    .action-btn {
        padding: 6px 12px;
        border-radius: 4px;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        display: inline-block;
        margin-right: 5px;
        color: #fff;
        transition: opacity 0.2s;
        border: none;
        cursor: pointer;
    }
    .action-btn:hover { opacity: 0.8; color: #fff;}
    .btn-edit { background-color: #f59e0b; } 
    .btn-delete { background-color: #ef4444; } 

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

    /* Modal Styles */
    .modal {
        display: none; 
        position: fixed; 
        z-index: 1000; 
        left: 0; 
        top: 0; 
        width: 100%; 
        height: 100%; 
        background-color: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
    }
    .modal-content {
        background-color: #fff;
        margin: 5% auto;
        padding: 30px;
        border-radius: 12px;
        width: 100%;
        max-width: 500px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        position: relative;
        animation: slideDown 0.3s ease-out;
    }
    @keyframes slideDown {
        from { transform: translateY(-30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 15px;
    }
    .modal-header h2 { margin: 0; font-size: 20px; color: #1e293b; }
    .close-btn {
        font-size: 28px;
        font-weight: bold;
        color: #94a3b8;
        cursor: pointer;
        line-height: 1;
    }
    .close-btn:hover { color: #334155; }
    
    /* Adjust modal form layout */
    .modal .form-group { margin-bottom: 20px; }
    
    @media (max-width: 768px) {
        .form-row { flex-direction: column; align-items: stretch; }
        .form-row .form-group { margin-bottom: 15px; }
    }
</style>

<main class="main-content">
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <!-- ADD NEW BATCH CARD -->
    <div class="card">
        <div class="card-header">
            <h2>Add New Batch</h2>
        </div>
        
        <form action="batch.php" method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="course_id">Select Course</label>
                    <select id="course_id" name="course_id" required>
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
                    <label for="batch_name">Batch Name</label>
                    <input type="text" id="batch_name" name="batch_name" required placeholder="e.g., Morning Batch A">
                </div>

                <div class="form-group" style="flex: 0 0 auto;">
                    <button type="submit" class="btn-save">Save Batch</button>
                </div>
            </div>
        </form>
    </div>

    <!-- DATA TABLE CARD -->
    <div class="card">
        <div class="card-header">
            <h2>Manage Batches</h2>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th width="10%">Sl No</th>
                        <th width="35%">Course Name</th>
                        <th width="35%">Batch Name</th>
                        <th width="20%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (mysqli_num_rows($batches_result) > 0) {
                        $count = 1;
                        while($row = mysqli_fetch_assoc($batches_result)) {
                            echo '<tr>';
                            echo '<td>' . $count++ . '</td>';
                            echo '<td>' . htmlspecialchars($row['course_name']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['batch_name']) . '</td>';
                            echo '<td>
                                    <button class="action-btn btn-edit" 
                                            data-id="' . $row['id'] . '" 
                                            data-course="' . $row['course_id'] . '" 
                                            data-batch="' . htmlspecialchars($row['batch_name']) . '"
                                            onclick="openEditModal(this)">Edit</button>
                                    <a href="batch.php?delete_id=' . $row['id'] . '" class="action-btn btn-delete" onclick="return confirm(\'Are you sure you want to delete this batch?\');">Delete</a>
                                  </td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="4" style="text-align:center;">No batches found. Create one above!</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- EDIT POPUP MODAL -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Batch</h2>
                <span class="close-btn" onclick="closeEditModal()">&times;</span>
            </div>
            
            <form action="batch.php" method="POST">
                <input type="hidden" id="edit_batch_id" name="batch_id">
                
                <div class="form-group">
                    <label for="edit_course_id">Select Course</label>
                    <select id="edit_course_id" name="course_id" required>
                        <?php 
                        foreach($courses as $course) {
                            echo '<option value="'.$course['id'].'">' . htmlspecialchars($course['course_name']) . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="edit_batch_name">Batch Name</label>
                    <input type="text" id="edit_batch_name" name="batch_name" required>
                </div>

                <div class="form-group" style="margin-top: 25px;">
                    <button type="submit" class="btn-save">Update Batch</button>
                </div>
            </form>
        </div>
    </div>

</main>

<script>
    // Modal script
    var modal = document.getElementById("editModal");

    // Open modal and populate data
    function openEditModal(button) {
        // Get data from data- attributes on the clicked button
        var id = button.getAttribute('data-id');
        var course_id = button.getAttribute('data-course');
        var batch_name = button.getAttribute('data-batch');

        // Populate the modal form inputs
        document.getElementById('edit_batch_id').value = id;
        document.getElementById('edit_course_id').value = course_id;
        document.getElementById('edit_batch_name').value = batch_name;

        // Show the modal
        modal.style.display = "block";
    }

    // Close modal function
    function closeEditModal() {
        modal.style.display = "none";
    }

    // Close modal if user clicks outside of the modal content
    window.onclick = function(event) {
        if (event.target == modal) {
            closeEditModal();
        }
    }
</script>

  </div> <!-- close .layout-wrapper -->
</body>
</html>
<?php 
// Close DB connection
mysqli_close($conn); 
?>
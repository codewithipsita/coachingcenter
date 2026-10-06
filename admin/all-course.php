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
include('header.php');
include('sidebar.php');

// Include database connection
include('../db.php');

// Fetch all courses (including total_enroled)
$query = "SELECT id, course_name, course_image, course_duration, course_price, capacity, course_details, total_enroled, created_at FROM courses ORDER BY id DESC";
$result = mysqli_query($conn, $query);
?>

<!-- CKEditor 5 CDN for modal editor -->
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>

<style>
    .main-content {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f8fafc;
        padding: 30px;
    }
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }
    .page-header h2 {
        margin: 0;
        color: #1e293b;
        font-size: 24px;
    }
    .btn-add {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background-color: #2563eb;
        color: white;
        padding: 11px 22px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 15px;
        font-weight: 600;
        text-decoration: none;
        transition: background-color 0.3s, box-shadow 0.3s;
        box-shadow: 0 2px 6px rgba(37, 99, 235, 0.25);
    }
    .btn-add:hover {
        background-color: #1d4ed8;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
    }
    .alert {
        padding: 14px 20px;
        border-radius: 6px;
        margin-bottom: 20px;
        font-weight: 500;
        font-size: 15px;
    }
    .alert-success {
        background-color: #dcfce7;
        color: #166534;
        border: 1px solid #bbf7d0;
    }
    .alert-error {
        background-color: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    .table-container {
        background: #ffffff;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        overflow-x: auto;
    }
    .course-table {
        width: 100%;
        border-collapse: collapse;
    }
    .course-table thead {
        background-color: #0f172a;
    }
    .course-table thead th {
        color: #e2e8f0;
        padding: 14px 16px;
        text-align: left;
        font-size: 13px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
    }
    .course-table tbody tr {
        border-bottom: 1px solid #f1f5f9;
        transition: background-color 0.2s;
    }
    .course-table tbody tr:hover {
        background-color: #f8fafc;
    }
    .course-table tbody td {
        padding: 14px 16px;
        font-size: 14px;
        color: #334155;
        vertical-align: middle;
    }
    .course-table tbody tr:last-child {
        border-bottom: none;
    }
    .course-img {
        width: 60px;
        height: 42px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
    }
    .price-badge {
        background-color: #eff6ff;
        color: #1d4ed8;
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 13px;
        white-space: nowrap;
    }
    .capacity-badge {
        background-color: #f0fdf4;
        color: #166534;
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 13px;
    }
    .enrolled-badge {
        background-color: #fef3c7;
        color: #92400e;
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 13px;
    }
    .available-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 13px;
    }
    .available-badge.has-seats {
        background-color: #ecfdf5;
        color: #065f46;
    }
    .available-badge.full {
        background-color: #fee2e2;
        color: #991b1b;
    }
    .sn-col {
        width: 50px;
    }
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
    }
    .empty-state span {
        font-size: 48px;
        display: block;
        margin-bottom: 12px;
    }
    .empty-state p {
        font-size: 16px;
        margin: 0;
    }

    /* Action buttons */
    .action-btns {
        display: flex;
        gap: 8px;
    }
    .btn-edit, .btn-delete {
        padding: 7px 14px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.2s;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .btn-edit {
        background-color: #dbeafe;
        color: #1d4ed8;
    }
    .btn-edit:hover {
        background-color: #bfdbfe;
    }
    .btn-delete {
        background-color: #fee2e2;
        color: #dc2626;
    }
    .btn-delete:hover {
        background-color: #fecaca;
    }

    /* ===== MODAL OVERLAY ===== */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(4px);
        z-index: 1000;
        justify-content: center;
        align-items: center;
        animation: fadeIn 0.25s ease;
    }
    .modal-overlay.active {
        display: flex;
    }
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    @keyframes slideUp {
        from { transform: translateY(30px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
    .modal-box {
        background: #ffffff;
        border-radius: 14px;
        width: 720px;
        max-width: 92%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 25px 60px rgba(0,0,0,0.25);
        animation: slideUp 0.3s ease;
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 22px 28px;
        border-bottom: 1px solid #e2e8f0;
    }
    .modal-header h3 {
        margin: 0;
        font-size: 20px;
        color: #1e293b;
    }
    .modal-close {
        background: #f1f5f9;
        border: none;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        font-size: 18px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        transition: all 0.2s;
    }
    .modal-close:hover {
        background: #e2e8f0;
        color: #1e293b;
    }
    .modal-body {
        padding: 28px;
    }
    .modal-body .form-group {
        margin-bottom: 20px;
    }
    .modal-body .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #475569;
        font-size: 14px;
    }
    .modal-body .form-group input[type="text"],
    .modal-body .form-group input[type="number"],
    .modal-body .form-group input[type="file"] {
        width: 100%;
        padding: 11px 14px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        box-sizing: border-box;
        font-size: 14px;
        transition: border-color 0.3s;
    }
    .modal-body .form-group input:focus {
        border-color: #3b82f6;
        outline: none;
    }
    .modal-body .form-group input[type="file"] {
        padding: 9px;
        background-color: #f1f5f9;
        cursor: pointer;
    }
    .modal-body .grid-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    .modal-body .current-img {
        width: 80px;
        height: 56px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #e2e8f0;
        margin-top: 8px;
    }
    .modal-body .ck-editor__editable_inline {
        min-height: 160px;
    }
    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding: 18px 28px;
        border-top: 1px solid #e2e8f0;
        background: #f8fafc;
        border-radius: 0 0 14px 14px;
    }
    .btn-cancel {
        padding: 10px 22px;
        border: 1px solid #cbd5e1;
        background: #ffffff;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        color: #475569;
        transition: all 0.2s;
    }
    .btn-cancel:hover {
        background: #f1f5f9;
    }
    .btn-update {
        padding: 10px 22px;
        background-color: #2563eb;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: background-color 0.3s;
        box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
    }
    .btn-update:hover {
        background-color: #1d4ed8;
    }
</style>

<main class="main-content">

    <div class="page-header">
        <h2>All Courses</h2>
        <a href="add-course.php" class="btn-add">➕ Add New Course</a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="table-container">
        <table class="course-table">
            <thead>
                <tr>
                    <th class="sn-col">S.N.</th>
                    <th>Image</th>
                    <th>Course Name</th>
                    <th>Duration</th>
                    <th>Price</th>
                    <th>Capacity</th>
                    <th>Enrolled</th>
                    <th>Available</th>
                    <th>Created At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($result) > 0): ?>
                    <?php $sn = 1; while ($row = mysqli_fetch_assoc($result)):
                        $available = $row['capacity'] - $row['total_enroled'];
                    ?>
                        <tr>
                            <td><?php echo $sn++; ?></td>
                            <td>
                                <img src="<?php echo htmlspecialchars($row['course_image']); ?>" alt="<?php echo htmlspecialchars($row['course_name']); ?>" class="course-img">
                            </td>
                            <td><strong><?php echo htmlspecialchars($row['course_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($row['course_duration']); ?></td>
                            <td><span class="price-badge">₹<?php echo number_format($row['course_price'], 2); ?></span></td>
                            <td><span class="capacity-badge"><?php echo htmlspecialchars($row['capacity']); ?> seats</span></td>
                            <td><span class="enrolled-badge"><?php echo htmlspecialchars($row['total_enroled']); ?></span></td>
                            <td>
                                <span class="available-badge <?php echo ($available > 0) ? 'has-seats' : 'full'; ?>">
                                    <?php echo ($available > 0) ? $available . ' seats' : 'Full'; ?>
                                </span>
                            </td>
                            <td><?php echo date('d M Y', strtotime($row['created_at'])); ?></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-edit" onclick="openEditModal(
                                        <?php echo $row['id']; ?>,
                                        '<?php echo addslashes(htmlspecialchars($row['course_name'])); ?>',
                                        '<?php echo addslashes(htmlspecialchars($row['course_image'])); ?>',
                                        '<?php echo addslashes(htmlspecialchars($row['course_duration'])); ?>',
                                        '<?php echo $row['course_price']; ?>',
                                        '<?php echo $row['capacity']; ?>',
                                        '<?php echo addslashes($row['course_details']); ?>'
                                    )">✏️ Edit</button>
                                    <a href="delete_course.php?id=<?php echo $row['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this course?');">🗑️ Delete</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10">
                            <div class="empty-state">
                                <span>📚</span>
                                <p>No courses found. Click "Add New Course" to get started!</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</main>

<!-- ===== EDIT COURSE MODAL ===== -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>✏️ Edit Course</h3>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form action="update_course.php" method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="course_id" id="edit_course_id">

                <div class="form-group">
                    <label for="edit_course_name">Course Name</label>
                    <input type="text" id="edit_course_name" name="course_name" required>
                </div>

                <div class="form-group">
                    <label>Current Image</label>
                    <br>
                    <img id="edit_current_img" src="" class="current-img" alt="Current image">
                    <input type="hidden" name="existing_image" id="edit_existing_image">
                </div>

                <div class="form-group">
                    <label for="edit_course_image">Change Image (optional)</label>
                    <input type="file" id="edit_course_image" name="course_image" accept="image/*">
                </div>

                <div class="grid-row">
                    <div class="form-group">
                        <label for="edit_course_duration">Course Duration</label>
                        <input type="text" id="edit_course_duration" name="course_duration" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_course_price">Course Price</label>
                        <input type="number" id="edit_course_price" name="course_price" step="0.01" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="edit_capacity">Seat Capacity</label>
                    <input type="number" id="edit_capacity" name="capacity" min="1" required>
                </div>

                <div class="form-group">
                    <label for="edit_course_details">Course Details</label>
                    <textarea id="edit_course_details" name="course_details"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-update">Update Course</button>
            </div>
        </form>
    </div>
</div>

<script>
    let editEditorInstance = null;

    function openEditModal(id, name, image, duration, price, capacity, details) {
        document.getElementById('edit_course_id').value = id;
        document.getElementById('edit_course_name').value = name;
        document.getElementById('edit_current_img').src = image;
        document.getElementById('edit_existing_image').value = image;
        document.getElementById('edit_course_duration').value = duration;
        document.getElementById('edit_course_price').value = price;
        document.getElementById('edit_capacity').value = capacity;

        document.getElementById('editModal').classList.add('active');

        // Destroy previous CKEditor instance if exists
        if (editEditorInstance) {
            editEditorInstance.destroy().then(() => {
                initEditCKEditor(details);
            });
        } else {
            initEditCKEditor(details);
        }
    }

    function initEditCKEditor(details) {
        ClassicEditor
            .create(document.querySelector('#edit_course_details'))
            .then(editor => {
                editEditorInstance = editor;
                editor.setData(details);
            })
            .catch(error => {
                console.error(error);
            });
    }

    function closeEditModal() {
        document.getElementById('editModal').classList.remove('active');
        if (editEditorInstance) {
            editEditorInstance.destroy();
            editEditorInstance = null;
        }
    }

    // Close modal when clicking outside the box
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeEditModal();
        }
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeEditModal();
        }
    });
</script>

  </div> <!-- close .layout-wrapper -->
</body>
</html>

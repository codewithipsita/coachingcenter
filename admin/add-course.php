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

// ===== Handle form submission =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include('../db.php');

    // Collect form data
    $course_name    = mysqli_real_escape_string($conn, trim($_POST['course_name']));
    $course_duration = mysqli_real_escape_string($conn, trim($_POST['course_duration']));
    $course_price   = mysqli_real_escape_string($conn, trim($_POST['course_price']));
    $capacity       = mysqli_real_escape_string($conn, trim($_POST['capacity']));
    $course_details = mysqli_real_escape_string($conn, trim($_POST['course_details']));

    // Handle image upload
    $course_image = '';
    if (isset($_FILES['course_image']) && $_FILES['course_image']['error'] === 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['course_image']['type'];

        if (!in_array($file_type, $allowed_types)) {
            $_SESSION['error'] = "Invalid image format. Only JPG, PNG, GIF, and WEBP are allowed.";
            header("Location: add-course.php");
            exit();
        }

        // Max file size: 5MB
        if ($_FILES['course_image']['size'] > 5 * 1024 * 1024) {
            $_SESSION['error'] = "Image size must be less than 5MB.";
            header("Location: add-course.php");
            exit();
        }

        // Generate a unique filename to avoid overwrites
        $ext = pathinfo($_FILES['course_image']['name'], PATHINFO_EXTENSION);
        $new_filename = 'course_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
        $upload_dir = 'uploads/courses/';
        $upload_path = $upload_dir . $new_filename;

        if (move_uploaded_file($_FILES['course_image']['tmp_name'], $upload_path)) {
            $course_image = $upload_path;
        } else {
            $_SESSION['error'] = "Failed to upload image. Please try again.";
            header("Location: add-course.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "Please select a course image.";
        header("Location: add-course.php");
        exit();
    }

    // Insert into courses table (without total_enroled)
    $query = "INSERT INTO courses (course_name, course_image, course_duration, course_price, capacity, course_details) 
              VALUES ('$course_name', '$course_image', '$course_duration', '$course_price', '$capacity', '$course_details')";

    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Course added successfully!";
        header("Location: all-course.php");
        exit();
    } else {
        $_SESSION['error'] = "Failed to add course: " . mysqli_error($conn);
        header("Location: add-course.php");
        exit();
    }

    mysqli_close($conn);
}

include('header.php');
include('sidebar.php');
?>

<!-- Modern styling for a professional look -->
<style>
    .main-content {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f8fafc;
        padding: 30px;
    }
    .form-container {
        background: #ffffff;
        padding: 35px;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        max-width: 800px;
        margin: 0 auto;
    }
    .form-container h2 {
        margin-top: 0;
        color: #1e293b;
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 15px;
        margin-bottom: 25px;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #475569;
    }
    .form-group input[type="text"],
    .form-group input[type="number"],
    .form-group input[type="file"] {
        width: 100%;
        padding: 12px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        box-sizing: border-box;
        font-size: 15px;
        transition: border-color 0.3s;
    }
    .form-group input:focus {
        border-color: #3b82f6;
        outline: none;
    }
    .form-group input[type="file"] {
        padding: 9px;
        background-color: #f1f5f9;
        cursor: pointer;
    }
    .grid-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    .btn-save {
        background-color: #2563eb;
        color: white;
        padding: 12px 28px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 16px;
        font-weight: 600;
        transition: background-color 0.3s;
        box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
    }
    .btn-save:hover {
        background-color: #1d4ed8;
    }
    /* CKEditor height fix */
    .ck-editor__editable_inline {
        min-height: 250px;
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
</style>

<!-- CKEditor 5 CDN -->
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>

<main class="main-content">
    <div class="form-container">
        <h2>Add New Course</h2>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <!-- enctype="multipart/form-data" is strictly required for image uploads -->
        <form action="add-course.php" method="POST" enctype="multipart/form-data">
            
            <div class="form-group">
                <label for="course_name">Course Name</label>
                <input type="text" id="course_name" name="course_name" required placeholder="Enter the course title">
            </div>

            <div class="form-group">
                <label for="course_image">Course Cover Image</label>
                <input type="file" id="course_image" name="course_image" accept="image/*" required>
            </div>

            <div class="grid-row">
                <div class="form-group">
                    <label for="course_duration">Course Duration</label>
                    <input type="text" id="course_duration" name="course_duration" required placeholder="e.g., 3 Months, 12 Weeks">
                </div>

                <div class="form-group">
                    <label for="course_price">Course Price</label>
                    <input type="number" id="course_price" name="course_price" step="0.01" min="0" required placeholder="0.00">
                </div>
            </div>

            <div class="form-group">
                <label for="capacity">Seat Capacity</label>
                <input type="number" id="capacity" name="capacity" min="1" required placeholder="Maximum students">
            </div>

            <div class="form-group">
                <label for="course_details">Course Details</label>
                <textarea id="course_details" name="course_details"></textarea>
            </div>

            <div class="form-group" style="text-align: right; margin-top: 30px;">
                <button type="submit" class="btn-save">Save Course</button>
            </div>

        </form>
    </div>
</main>

<!-- Initialize CKEditor -->
<script>
    ClassicEditor
        .create(document.querySelector('#course_details'))
        .catch(error => {
            console.error(error);
        });
</script>

  </div> <!-- close .layout-wrapper -->
</body>
</html>
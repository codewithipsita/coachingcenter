<?php
session_start();

// Kick out if not logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: all-course.php");
    exit();
}

// Include database connection
include('../db.php');

// Collect form data
$course_id       = mysqli_real_escape_string($conn, trim($_POST['course_id']));
$course_name     = mysqli_real_escape_string($conn, trim($_POST['course_name']));
$course_duration = mysqli_real_escape_string($conn, trim($_POST['course_duration']));
$course_price    = mysqli_real_escape_string($conn, trim($_POST['course_price']));
$capacity        = mysqli_real_escape_string($conn, trim($_POST['capacity']));
$course_details  = mysqli_real_escape_string($conn, trim($_POST['course_details']));
$existing_image  = mysqli_real_escape_string($conn, trim($_POST['existing_image']));

// Handle image upload (optional — keep existing if no new one uploaded)
$course_image = $existing_image;

if (isset($_FILES['course_image']) && $_FILES['course_image']['error'] === 0) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $file_type = $_FILES['course_image']['type'];

    if (!in_array($file_type, $allowed_types)) {
        $_SESSION['error'] = "Invalid image format. Only JPG, PNG, GIF, and WEBP are allowed.";
        header("Location: all-course.php");
        exit();
    }

    if ($_FILES['course_image']['size'] > 5 * 1024 * 1024) {
        $_SESSION['error'] = "Image size must be less than 5MB.";
        header("Location: all-course.php");
        exit();
    }

    $ext = pathinfo($_FILES['course_image']['name'], PATHINFO_EXTENSION);
    $new_filename = 'course_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
    $upload_dir = 'uploads/courses/';
    $upload_path = $upload_dir . $new_filename;

    if (move_uploaded_file($_FILES['course_image']['tmp_name'], $upload_path)) {
        // Delete old image if it exists
        if (!empty($existing_image) && file_exists($existing_image)) {
            unlink($existing_image);
        }
        $course_image = $upload_path;
    } else {
        $_SESSION['error'] = "Failed to upload new image.";
        header("Location: all-course.php");
        exit();
    }
}

// Update the course in the database
$query = "UPDATE courses SET 
            course_name = '$course_name', 
            course_image = '$course_image', 
            course_duration = '$course_duration', 
            course_price = '$course_price', 
            capacity = '$capacity', 
            course_details = '$course_details' 
          WHERE id = '$course_id'";

if (mysqli_query($conn, $query)) {
    $_SESSION['success'] = "Course updated successfully!";
} else {
    $_SESSION['error'] = "Failed to update course: " . mysqli_error($conn);
}

header("Location: all-course.php");
exit();

mysqli_close($conn);
?>

<?php
session_start();

// Kick out if not logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

// Include database connection
include('../db.php');

// Get course ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Invalid course ID.";
    header("Location: all-course.php");
    exit();
}

$course_id = mysqli_real_escape_string($conn, $_GET['id']);

// First fetch the course to get the image path
$query = "SELECT course_image FROM courses WHERE id = '$course_id'";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = "Course not found.";
    header("Location: all-course.php");
    exit();
}

$course = mysqli_fetch_assoc($result);

// Delete the course image file if it exists
if (!empty($course['course_image']) && file_exists($course['course_image'])) {
    unlink($course['course_image']);
}

// Delete the course from the database
$delete_query = "DELETE FROM courses WHERE id = '$course_id'";

if (mysqli_query($conn, $delete_query)) {
    $_SESSION['success'] = "Course deleted successfully!";
} else {
    $_SESSION['error'] = "Failed to delete course: " . mysqli_error($conn);
}

header("Location: all-course.php");
exit();

mysqli_close($conn);
?>

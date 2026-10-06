<?php
include('db.php'); // Ensure $conn is defined in this file

$message = "";

// Helper function to resize and compress images if they exceed 500KB
function processAndSaveImage($source, $destination, $size) {
    $info = getimagesize($source);
    if (!$info) return move_uploaded_file($source, $destination); // Fallback for unknown types

    $mime = $info['mime'];
    
    // Create image resource based on type
    switch ($mime) {
        // BUG FIX: MIME type 'image/jpg' is non-standard — correct is 'image/jpeg'
        case 'image/jpg': $image = imagecreatefromjpg($source); break;
        case 'image/png': $image = imagecreatefrompng($source); break;
        case 'image/webp': $image = imagecreatefromwebp($source); break;
        default: return move_uploaded_file($source, $destination); // Fallback for gif or others
    }

    // If image is larger than 500KB, resize width to 600px while maintaining aspect ratio
    if ($size > 500 * 1024) {
        $width = $info[0];
        $height = $info[1];
        $new_width = 600; 
        $new_height = floor($height * ($new_width / $width));
        
        $new_image = imagecreatetruecolor($new_width, $new_height);

        // Preserve transparency for PNGs
        if ($mime == 'image/png') {
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
        }

        imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        $image = $new_image;
    }

    // Save compressed image
    $success = false;
    switch ($mime) {
        case 'image/jpeg': $success = imagejpeg($image, $destination, 75); break; // 75% quality
        case 'image/png': $success = imagepng($image, $destination, 7); break; // 0-9 compression level
        case 'image/webp': $success = imagewebp($image, $destination, 80); break; // 80% quality
    }
    
    imagedestroy($image);
    return $success;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $contact_no = $_POST['contact_no'];
    $gender = $_POST['gender'];
    $password = $_POST['password'];
    $cpassword = $_POST['cpassword'];
    $profile_photo = "";

    if ($password !== $cpassword) {
        $message = "<script>alert('Passwords do not match!');</script>";
    } else {
        // Check if email exists
        $check_sql = "SELECT id FROM student_master WHERE email = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "s", $email);
        mysqli_stmt_execute($check_stmt);
        mysqli_stmt_store_result($check_stmt);

        if (mysqli_stmt_num_rows($check_stmt) > 0) {
            $message = "<script>alert('Email already registered.');</script>";
        } else {
            // Handle Profile Photo Upload
            if (isset($_FILES['new_photo']) && $_FILES['new_photo']['error'] == UPLOAD_ERR_OK) {
                $upload_dir = 'admin/uploads/student/';
                
                // Create directory if it doesn't exist
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $filename = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "_", basename($_FILES['new_photo']['name']));
                $target_file = $upload_dir . $filename;
                
                // Process, compress if > 500KB, and save
                if (processAndSaveImage($_FILES['new_photo']['tmp_name'], $target_file, $_FILES['new_photo']['size'])) {
                    $profile_photo = $target_file;
                }
            }

            // Insert into Database
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $sql = "INSERT INTO student_master (name, email, contact_no, gender, password, profile_photo) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ssssss", $name, $email, $contact_no, $gender, $hashed_password, $profile_photo);
                
                if (mysqli_stmt_execute($stmt)) {
                    $message = "<script>alert('Registration successful!');</script>";
                } else {
                    $message = "<script>alert('Error saving data.');</script>";
                }
                mysqli_stmt_close($stmt);
            } else {
                $message = "<script>alert('Database error.');</script>";
            }
        }
        mysqli_stmt_close($check_stmt);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Glassmorphism</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <div class="container">
        <h2>Create Account</h2>
        
        <?php echo $message; ?>
        
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="input-box">
                <i class="fa-solid fa-user"></i>
                <input type="text" name="name" placeholder="Enter Your Name" required>
            </div>

            <div class="input-box">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" name="email" placeholder="Email Address" required>
            </div>

            <div class="input-box">
                <i class="fa-solid fa-phone"></i>
                <input type="number" name="contact_no" placeholder="Contact Number" required>
            </div>

            <div class="input-box">
                <i class="fa-solid fa-image"></i>
                <input type="file" name="new_photo" accept="image/*" required style="padding-top: 15px;">
            </div>

            <div class="radio-group">
                <label><input type="radio" name="gender" value="Male" required> Male</label>
                <label><input type="radio" name="gender" value="Female" required> Female</label>
                <label><input type="radio" name="gender" value="Other" required> Other</label>
            </div>

            <div class="input-box">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="password" placeholder="Enter Password" required>
            </div>

            <div class="input-box">
                <i class="fa-solid fa-shield-halved"></i>
                <input type="password" name="cpassword" placeholder="Confirm Password" required>
            </div>

            <button type="submit">Register</button>

            <div class="links" style="justify-content: center;">
                <a href="index.php">Already have an account? Login here</a>
            </div>
        </form>
    </div>

</body>
</html>
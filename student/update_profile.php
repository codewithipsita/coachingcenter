<?php
session_start();

// PHP Cache Control
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Kick out if not logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

include('../db.php');

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$message = "";
$msg_class = "";

// Directory for profile photos updated to the new path
$upload_dir = "../admin/uploads/student/"; 

// --- FUNCTION: Auto Resize/Compress Image if > 500KB ---
function processAndSaveImage($temp_file, $target_file, $max_bytes = 512000) {
    $file_size = filesize($temp_file);
    if ($file_size <= $max_bytes) {
        move_uploaded_file($temp_file, $target_file);
        return $target_file;
    }
    
    $info = getimagesize($temp_file);
    if (!$info) return false;
    
    $mime = $info['mime'];
    if ($mime == 'image/jpeg') $image = imagecreatefromjpeg($temp_file);
    elseif ($mime == 'image/png') $image = imagecreatefrompng($temp_file);
    elseif ($mime == 'image/gif') $image = imagecreatefromgif($temp_file);
    else return false;

    $width = $info[0];
    $height = $info[1];
    $new_width = 800;
    $new_height = ($height / $width) * $new_width;
    $tmp = imagecreatetruecolor((int)$new_width, (int)$new_height);
    
    $target_file = preg_replace('/\.(png|gif|jpeg)$/i', '.jpg', $target_file);
    $bg = imagecolorallocate($tmp, 255, 255, 255);
    imagefill($tmp, 0, 0, $bg);
    imagecopyresampled($tmp, $image, 0, 0, 0, 0, (int)$new_width, (int)$new_height, $width, $height);
    
    $success = imagejpeg($tmp, $target_file, 75);
    imagedestroy($image);
    imagedestroy($tmp);
    
    return $success ? $target_file : false;
}

// --- POST REQUEST: HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $contact_no = trim($_POST['contact_no']);
    $gender = trim($_POST['gender']);
    
    $old_photo_query = "SELECT `profile_photo` FROM `student_master` WHERE `id` = ?";
    $stmt_old = mysqli_prepare($conn, $old_photo_query);
    mysqli_stmt_bind_param($stmt_old, "i", $user_id);
    mysqli_stmt_execute($stmt_old);
    mysqli_stmt_bind_result($stmt_old, $old_photo);
    mysqli_stmt_fetch($stmt_old);
    mysqli_stmt_close($stmt_old);

    $new_photo_path = $old_photo; 

    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true); 
        
        $tmp_name = $_FILES['profile_photo']['tmp_name'];
        $file_name = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "", basename($_FILES['profile_photo']['name']));
        $target_file = $upload_dir . $file_name;
        
        $processed_file = processAndSaveImage($tmp_name, $target_file);
        
        if ($processed_file) {
            // BUG FIX: Store the full relative path (matching registration flow)
            // Registration stores: admin/uploads/student/filename
            // We must store the same format, not just basename
            $relative_path = 'admin/uploads/student/' . basename($processed_file);
            $new_photo_path = $relative_path;
            if (!empty($old_photo) && file_exists('../' . $old_photo)) {
                unlink('../' . $old_photo);
            }
        } else {
            $message = "Failed to process the uploaded image.";
            $msg_class = "alert-error";
        }
    }

    if (empty($msg_class)) {
        $update_query = "UPDATE `student_master` SET `name`=?, `email`=?, `contact_no`=?, `gender`=?, `profile_photo`=? WHERE `id`=?";
        $stmt_upd = mysqli_prepare($conn, $update_query);
        if ($stmt_upd) {
            mysqli_stmt_bind_param($stmt_upd, "sssssi", $name, $email, $contact_no, $gender, $new_photo_path, $user_id);
            if (mysqli_stmt_execute($stmt_upd)) {
                $message = "Profile successfully updated!";
                $msg_class = "alert-success";
            } else {
                $message = "Database update failed.";
                $msg_class = "alert-error";
            }
            mysqli_stmt_close($stmt_upd);
        }
    }
}

// --- GET REQUEST: FETCH CURRENT DATA ---
$current_data = ['name'=>'', 'email'=>'', 'contact_no'=>'', 'gender'=>'', 'profile_photo'=>''];
$query = "SELECT `name`, `email`, `contact_no`, `gender`, `profile_photo` FROM `student_master` WHERE `id` = ?";
$stmt = mysqli_prepare($conn, $query);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $current_data['name'], $current_data['email'], $current_data['contact_no'], $current_data['gender'], $current_data['profile_photo']);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);
}

$user_name = !empty($current_data['name']) ? $current_data['name'] : "Guest"; 

// --- FIXED IMAGE CHECK LOGIC ---
$photo_filename = basename($current_data['profile_photo'] ?? ''); // Extracts JUST the filename
$image_url_path = $upload_dir . $photo_filename; // Path for the HTML <img> tag

// Safely get the absolute server path for PHP's file_exists() to evaluate properly
$server_path = __DIR__ . '/' . $image_url_path; 
$image_exists = !empty($current_data['profile_photo']) && file_exists($server_path);

// Include Layout
include('header.php');
include('sidebar.php');
?>

<style>
    /* Main wrapper to hold the form cleanly */
    .main-content {
        padding: 40px;
        background-color: #f4f6f9;
        font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        min-height: 100vh;
        box-sizing: border-box;
    }
    
    .profile-card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        padding: 40px;
        max-width: 800px; /* Constrained width for readability */
        margin: 0 auto; /* Centers the card horizontally */
        width: 100%;
    }

    .profile-card h2 {
        margin-top: 0;
        color: #2c3e50;
        font-size: 22px;
        font-weight: 600;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid #edf2f7;
    }

    /* Grid layout for form fields to utilize width elegantly */
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    /* Full width spanning for specific elements */
    .full-width {
        grid-column: 1 / -1;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        color: #4a5568;
        margin-bottom: 8px;
        font-size: 14px;
    }

    .form-control {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid #cbd5e1;
        border-radius: 6px;
        font-size: 15px;
        color: #1e293b;
        transition: border-color 0.2s, box-shadow 0.2s;
        box-sizing: border-box;
        background-color: #f8fafc;
    }

    .form-control:focus {
        outline: none;
        border-color: #3b82f6;
        background-color: #ffffff;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
    }

    .btn-submit {
        background-color: #2563eb;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 6px;
        font-size: 15px;
        font-weight: 600;
        cursor: pointer;
        transition: background-color 0.2s;
        margin-top: 10px;
        display: inline-block;
        
    }

    .btn-submit:hover {
        background-color: #1d4ed8;
    }

    /* Modern Photo Upload Area */
    .photo-upload-section {
        display: flex;
        align-items: center;
        gap: 25px;
        margin-bottom: 30px;
        padding: 20px;
        background-color: #f8fafc;
        border-radius: 8px;
        border: 1px dashed #cbd5e1;
    }

    .img-preview {
        width: 90px;
        height: 90px;
        object-fit: cover;
        border-radius: 50%;
        border: 4px solid #ffffff;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        display: block;
    }

    .img-placeholder {
        width: 90px;
        height: 90px;
        border-radius: 50%;
        background-color: #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #64748b;
        font-size: 14px;
        font-weight: 500;
        border: 4px solid #ffffff;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    .upload-details {
        flex-grow: 1;
    }

    .help-text {
        display: block;
        font-size: 13px;
        color: #64748b;
        margin-top: 8px;
    }
    
    .alert {
        padding: 16px;
        border-radius: 6px;
        margin-bottom: 24px;
        font-size: 14px;
        font-weight: 500;
    }
    
    .alert-success { 
        background-color: #ecfdf5; 
        color: #065f46; 
        border: 1px solid #a7f3d0; 
    }
    
    .alert-error { 
        background-color: #fef2f2; 
        color: #991b1b; 
        border: 1px solid #fecaca; 
    }

    /* Responsive adjustment for smaller screens */
    @media (max-width: 600px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

    <!-- Main Content Area -->
    <main class="main-content">
        <div class="profile-card">
            <h2>Update Profile, <?php echo htmlspecialchars($user_name); ?>!</h2>
            
            <?php if(!empty($message)): ?>
                <div class="alert <?php echo $msg_class; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" enctype="multipart/form-data">
                
                <!-- Profile Photo Upload Area -->
                <div class="photo-upload-section">
                    <div>
                        <!-- FIXED IMAGE PREVIEW LOGIC -->
                        <?php if($image_exists): ?>
                            <img src="<?php echo htmlspecialchars($image_url_path); ?>" alt="Profile" class="img-preview">
                        <?php else: ?>
                            <div class="img-placeholder">No Photo</div>
                        <?php endif; ?>
                    </div>
                    <div class="upload-details">
                        <label style="font-weight: 600; color: #4a5568; display: block; margin-bottom: 8px;">Update Profile Picture</label>
                        <input type="file" name="profile_photo" accept="image/*" class="form-control" style="padding: 8px; background: white;">
                        <span class="help-text">JPG, PNG or GIF. Max 500KB (auto-resizes if larger).</span>
                    </div>
                </div>

                <!-- Form Fields Grid -->
                <div class="form-grid">
                    <!-- Name -->
                    <div class="form-group full-width">
                        <label>Full Name</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($current_data['name']); ?>" required>
                    </div>

                    <!-- Email -->
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($current_data['email']); ?>" required>
                    </div>

                    <!-- Contact Number -->
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" name="contact_no" class="form-control" value="<?php echo htmlspecialchars($current_data['contact_no']); ?>" required>
                    </div>

                    <!-- Gender -->
                    <div class="form-group full-width">
                        <label>Gender</label>
                        <select name="gender" class="form-control" required style="max-width: 50%;">
                            <option value="" disabled>Select Gender</option>
                            <option value="Male" <?php if($current_data['gender'] == 'Male') echo 'selected'; ?>>Male</option>
                            <option value="Female" <?php if($current_data['gender'] == 'Female') echo 'selected'; ?>>Female</option>
                            <option value="Other" <?php if($current_data['gender'] == 'Other') echo 'selected'; ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div style="text-align: right; margin-top: 10px;">
                   <center><button type="submit" class="btn-submit">Save Changes</button></center> 
                </div>
            </form>
        </div>
    </main>

  </div> <!-- closes .layout-wrapper from sidebar.php -->
</body>
</html>
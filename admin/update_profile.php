<?php
session_start();
include('../db.php'); 

$message = "";

// Assuming the logged-in admin's ID is stored in a session. 
$admin_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 1;

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = mysqli_real_escape_string($conn, trim($_POST['fullname']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    
    $update_image_query = "";
    
    // Handle Profile Image Upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $target_dir = "uploads/";
        
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES["profile_image"]["name"], PATHINFO_EXTENSION);
        $new_file_name = time() . "_" . uniqid() . "." . $file_extension;
        $target_file = $target_dir . $new_file_name;
        
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array(strtolower($file_extension), $allowed_types)) {
            if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                $update_image_query = ", profile_image = '$target_file'";
            } else {
                $message = "Error uploading image.";
            }
        } else {
            $message = "Only JPG, JPEG, PNG, and GIF files are allowed.";
        }
    }

    // Update Database
    if (empty($message)) {
        $sql = "UPDATE admin SET fullname = '$fullname', email = '$email' $update_image_query WHERE id = '$admin_id'";
        if (mysqli_query($conn, $sql)) {
            $message = "Profile updated successfully!";
        } else {
            $message = "Error updating profile: " . mysqli_error($conn);
        }
    }
}

// Fetch Current Data
$sql = "SELECT id, fullname, email, profile_image FROM admin WHERE id = '$admin_id'";
$result = mysqli_query($conn, $sql);
$admin = mysqli_fetch_assoc($result);

// Include standard layout files
include('header.php');
include('sidebar.php');
?>

<style>
    /* Scoped CSS for the Profile Card - width: 100% removed to protect sidebar */
    .profile-content-area {
        padding: 40px 20px;
        font-family: 'Poppins', sans-serif;
        flex-grow: 1; /* Allows it to fill remaining space next to sidebar */
        overflow-x: hidden;
    }

    .profile-card { 
        background: #ffffff; 
        max-width: 500px; 
        margin: 0 auto; 
        padding: 40px; 
        border-radius: 12px; 
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); 
        border: 1px solid #e5e7eb;
    }

    .profile-card h2 { 
        text-align: center; 
        font-size: 1.5rem; 
        font-weight: 600; 
        color: #111827; 
        margin-bottom: 30px; 
    }
    
    .profile-preview { text-align: center; margin-bottom: 25px; }
    .profile-preview img { 
        width: 120px; 
        height: 120px; 
        border-radius: 50%; 
        object-fit: cover; 
        border: 3px solid #3b82f6; 
        padding: 3px;
    }
    .profile-preview .placeholder { 
        width: 120px; 
        height: 120px; 
        border-radius: 50%; 
        background: #f3f4f6; 
        display: inline-flex; 
        justify-content: center; 
        align-items: center; 
        font-size: 3rem; 
        color: #9ca3af; 
        border: 3px solid #3b82f6; 
    }

    .input-group { margin-bottom: 20px; }
    .input-group label { 
        display: block; 
        font-size: 0.9rem; 
        color: #4b5563; 
        margin-bottom: 8px; 
        font-weight: 500;
    }
    .input-group input[type="text"], 
    .input-group input[type="email"] { 
        width: 100%; 
        padding: 12px 15px; 
        border: 1px solid #d1d5db; 
        border-radius: 8px; 
        font-size: 1rem; 
        color: #1f2937;
        box-sizing: border-box;
        transition: border-color 0.2s;
    }
    .input-group input:focus { 
        outline: none; 
        border-color: #3b82f6; 
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); 
    }
    
    .file-input-wrapper { margin-bottom: 25px; text-align: center; }
    .file-input-wrapper input[type="file"] { display: none; }
    .file-input-wrapper label { 
        cursor: pointer; 
        padding: 8px 16px; 
        background: #f3f4f6; 
        color: #4b5563;
        border-radius: 6px; 
        font-size: 0.9rem; 
        font-weight: 500;
        display: inline-block; 
        border: 1px solid #d1d5db;
        transition: all 0.2s;
    }
    .file-input-wrapper label:hover { background: #e5e7eb; color: #111827; }

    .update-btn { 
        width: 100%; 
        padding: 12px; 
        background: #3b82f6; 
        border: none; 
        border-radius: 8px; 
        color: #fff; 
        font-size: 1rem; 
        font-weight: 600; 
        cursor: pointer; 
        transition: background 0.3s; 
    }
    .update-btn:hover { background: #2563eb; }
</style>

<!-- Main Form Container -->
<main class="main-content">
<div class="profile-content-area">
    <div class="profile-card">
        <h2>Update Profile</h2>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="profile-preview">
                <?php if (!empty($admin['profile_image']) && file_exists($admin['profile_image'])): ?>
                    <img src="<?php echo htmlspecialchars($admin['profile_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="Profile">
                <?php else: ?>
                    <div class="placeholder"><i class="fa-solid fa-user"></i></div>
                <?php endif; ?>
            </div>

            <div class="file-input-wrapper">
                <label for="profile_image"><i class="fa-solid fa-camera"></i> Upload New Image</label>
                <input type="file" id="profile_image" name="profile_image" accept="image/png, image/jpeg, image/jpg, image/gif">
            </div>

            <div class="input-group">
                <label>Full Name</label>
                <input type="text" name="fullname" value="<?php echo htmlspecialchars($admin['fullname'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="input-group">
                <label>Email Address</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($admin['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <button type="submit" class="update-btn">Save Changes</button>
        </form>
    </div>
</div>
</main>

  </div> <!-- close .layout-wrapper -->

<?php if(!empty($message)): ?>
<script>
    alert("<?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>");
</script>
<?php endif; ?>

</body>
</html>
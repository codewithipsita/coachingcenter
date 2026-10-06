<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Adjust this path if your db.php is located elsewhere
include('../db.php');

$hasImage = false;
$imagePath = "";

// Changed 'student_id' to 'user_id' to match your login script
if (isset($conn) && isset($_SESSION['user_id'])) {
    $student_id = $_SESSION['user_id'];
    
    // Using a prepared statement for security
    $stmt = $conn->prepare("SELECT profile_photo FROM student_master WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $student = $result->fetch_assoc()) {
        if (!empty($student['profile_photo'])) {
            // Extract just the filename to be safe
            $filename = basename($student['profile_photo']);
            
            // Reconstruct the correct relative path to the image
            $imagePath = "../admin/uploads/student/" . htmlspecialchars($filename);
            
            // Check if file actually exists on the server to prevent broken icons
            if(file_exists(__DIR__ . "/../admin/uploads/student/" . $filename)) {
                $hasImage = true;
            }
        }
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Header Dropdown</title>
  <style>
    body { margin: 0; font-family: sans-serif; background: #f4f5f7; }
    header { display: flex; justify-content: space-between; padding: 15px 40px; background: #0f172a; color: white; }
    
    .profile-container { position: relative; }
    .profile-icon { 
      width: 44px; height: 44px; border-radius: 50%; cursor: pointer; 
      border: 2px solid #334155; background: #1e293b; overflow: hidden;
      display: flex; justify-content: center; align-items: center; padding: 0;
    }
    .profile-icon img { width: 100%; height: 100%; object-fit: cover; }
    
    .profile-dropdown-menu {
      display: none; position: absolute; right: 0; top: 55px; 
      background: white; min-width: 200px; border-radius: 8px; 
      box-shadow: 0 4px 15px rgba(0,0,0,0.2); padding: 8px; z-index: 100;
    }
    .profile-dropdown-menu.show { display: block; }
    .profile-dropdown-menu a { 
      display: block; padding: 10px; text-decoration: none; color: #334155; 
      border-radius: 4px; 
    }
    .profile-dropdown-menu a:hover { background: #f1f5f9; }
    .profile-dropdown-menu a.logout { color: #e11d48; border-top: 1px solid #e2e8f0; margin-top: 5px; }
  </style>
</head>
<body>

  <header>
    <div class="logo"></div>
    <div class="profile-container">
      <button class="profile-icon" id="profileBtn">
        <?php if ($hasImage): ?>
          <img src="<?php echo $imagePath; ?>" alt="Profile">
        <?php else: ?>
          🧑‍💻
        <?php endif; ?>
      </button>
      
      <div class="profile-dropdown-menu" id="dropdownMenu">
        <a href="update_profile.php">⚙️ Update Profile</a>
        <a href="password.php">🔐 Change Password</a>
        <a href="logout.php" class="logout">🚪 Logout</a>
      </div>
    </div>
  </header>

  <script>
    const btn = document.getElementById('profileBtn');
    const menu = document.getElementById('dropdownMenu');

    btn.addEventListener('click', (e) => {
      menu.classList.toggle('show');
      e.stopPropagation();
    });

    window.addEventListener('click', (e) => {
      if (!e.target.closest('.profile-container')) {
        menu.classList.remove('show');
      }
    });
  </script>
</body>
</html>
<?php
session_start();
include('../db.php'); 

$message = "";

// BUG FIX: Use the logged-in admin's ID from session, not a hardcoded 1
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}
$admin_id = (int)$_SESSION['user_id'];

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // 1. Check if passwords match
    if ($new_password !== $confirm_password) {
        $message = "Error: Passwords do not match. Please try again.";
    } else {
        // 2. Hash the password for security (Highly Recommended)
        // Note: If you are currently storing plain text passwords, you can skip hashing by replacing 
        // the line below with: $final_password = mysqli_real_escape_string($conn, trim($new_password));
        // However, using password_hash() is the standard security practice.
        $final_password = password_hash($new_password, PASSWORD_DEFAULT);

        // 3. Update Database using Prepared Statements to prevent SQL Injection
        $sql = "UPDATE admin SET password = ? WHERE id = ?";
        
        if ($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "si", $final_password, $admin_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $message = "Password updated successfully!";
            } else {
                $message = "Error updating password: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $message = "Database error: Unable to prepare statement.";
        }
    }
}

// Include standard layout files
include('header.php');
include('sidebar.php');
?>

<style>
    /* Scoped CSS adapted from your reference */
    .settings-content-area {
        padding: 40px 20px;
        font-family: 'Poppins', sans-serif;
        flex-grow: 1; 
        overflow-x: hidden;
    }

    .settings-card { 
        background: #ffffff; 
        max-width: 500px; 
        margin: 0 auto; 
        padding: 40px; 
        border-radius: 12px; 
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); 
        border: 1px solid #e5e7eb;
    }

    .settings-card h2 { 
        text-align: center; 
        font-size: 1.5rem; 
        font-weight: 600; 
        color: #111827; 
        margin-bottom: 30px; 
    }

    .input-group { margin-bottom: 20px; }
    
    .input-group label { 
        display: block; 
        font-size: 0.9rem; 
        color: #4b5563; 
        margin-bottom: 8px; 
        font-weight: 500;
    }
    
    .input-group input[type="password"] { 
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

<main class="main-content">
<div class="settings-content-area">
    <div class="settings-card">
        <h2>Change Password</h2>
        
        <form method="POST" action="">
            <div class="input-group">
                <label>New Password</label>
                <input type="password" name="new_password" required placeholder="Enter new password">
            </div>

            <div class="input-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required placeholder="Confirm new password">
            </div>

            <button type="submit" class="update-btn">Update Password</button>
        </form>
    </div>
</div>
</main>

  </div> <?php if(!empty($message)): ?>
<script>
    alert("<?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>");
</script>
<?php endif; ?>

</body>
</html>
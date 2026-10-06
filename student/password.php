<?php
session_start();
include('../db.php'); 

$message = "";

// BUG FIX: Redirect instead of die() — consistent with all other student pages
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

if($_SERVER["REQUEST_METHOD"] == "POST"){
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // 2. Use 'user_id' to match your login.php file
    $id = $_SESSION['user_id']; 

    if($new_password !== $confirm_password){
         $message = "Error: Passwords do not match. Please try again.";
    } else {
        // Hash the password for security
        $final_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update Database
        $sql = "UPDATE student_master SET password = ? WHERE id = ?"; 
        
        if($stmt = mysqli_prepare($conn, $sql)){
            mysqli_stmt_bind_param($stmt, "si", $final_password, $id);
            
            if (mysqli_stmt_execute($stmt)) {
                // Check if a row was ACTUALLY updated
                if (mysqli_stmt_affected_rows($stmt) > 0) {
                    $message = "Password updated successfully!";
                } else {
                    $message = "Error: Could not update the password. Please try again.";
                }
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
    .settings-content-area {
        padding: 60px 20px;
        font-family: 'Inter', 'Poppins', sans-serif;
        flex-grow: 1; 
        display: flex;
        justify-content: center;
        align-items: flex-start;
        background: #f8fafc;
        min-height: 80vh;
    }

    .settings-card { 
        background: #ffffff; 
        width: 100%;
        max-width: 480px; 
        margin-top: 40px;
        padding: 50px 40px; 
        border-radius: 20px; 
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.04), 0 1px 3px rgba(0,0,0,0.05); 
        border: 1px solid #f1f5f9;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .settings-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 25px 50px rgba(0, 0, 0, 0.08);
    }

    .settings-card h2 { 
        text-align: center; 
        font-size: 1.75rem; 
        font-weight: 700; 
        color: #0f172a; 
        margin-bottom: 8px; 
    }

    .settings-card p.subtitle {
        text-align: center;
        color: #64748b;
        font-size: 0.95rem;
        margin-bottom: 30px;
    }

    .custom-alert {
        padding: 12px 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 0.9rem;
        font-weight: 500;
        text-align: center;
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

    .input-group { margin-bottom: 24px; }
    
    .input-group label { 
        display: block; 
        font-size: 0.9rem; 
        color: #334155; 
        margin-bottom: 8px; 
        font-weight: 600;
    }
    
    .input-group input[type="password"] { 
        width: 100%; 
        padding: 14px 16px; 
        background: #f8fafc;
        border: 1px solid #e2e8f0; 
        border-radius: 10px; 
        font-size: 1rem; 
        color: #0f172a;
        box-sizing: border-box;
        transition: all 0.3s ease;
    }
    
    .input-group input[type="password"]:focus { 
        outline: none; 
        border-color: #3b82f6; 
        background: #ffffff;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15); 
    }

    .update-btn { 
        width: 100%; 
        padding: 14px; 
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); 
        border: none; 
        border-radius: 10px; 
        color: #fff; 
        font-size: 1.05rem; 
        font-weight: 600; 
        cursor: pointer; 
        transition: all 0.3s ease; 
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
    }
    
    .update-btn:hover { 
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(37, 99, 235, 0.35);
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); 
    }

    .update-btn:active {
        transform: translateY(1px);
    }
</style>

<main class="main-content">
<div class="settings-content-area">
    <div class="settings-card">
        <h2>Change Password</h2>
        <p class="subtitle">Secure your account with a new password</p>
        
        <?php if(!empty($message)): ?>
            <?php $alertClass = strpos($message, 'Error') !== false ? 'alert-error' : 'alert-success'; ?>
            <div class="custom-alert <?php echo $alertClass; ?>">
                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

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

</body>
</html>
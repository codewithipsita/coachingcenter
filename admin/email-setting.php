<?php
session_start();
include('../db.php'); 

$message = "";

// Assuming the email settings is a single global record with ID = 1.
$settings_id = 1;

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $receive_email_id = mysqli_real_escape_string($conn, trim($_POST['receive_email_id']));
    $sending_email_id = mysqli_real_escape_string($conn, trim($_POST['sending_email_id']));
    $app_password = mysqli_real_escape_string($conn, trim($_POST['app_password']));

    // Update Database
    $sql = "UPDATE email_settings SET 
            username = '$username', 
            receive_email_id = '$receive_email_id', 
            sending_email_id = '$sending_email_id', 
            app_password = '$app_password',
            updated_at = NOW() 
            WHERE id = '$settings_id'";
            
    if (mysqli_query($conn, $sql)) {
        $message = "Email settings updated successfully!";
    } else {
        $message = "Error updating email settings: " . mysqli_error($conn);
    }
}

// Fetch Current Data
$sql = "SELECT id, username, receive_email_id, sending_email_id, app_password FROM email_settings WHERE id = '$settings_id'";
$result = mysqli_query($conn, $sql);
$settings = mysqli_fetch_assoc($result);

// Include standard layout files
include('header.php');
include('sidebar.php');
?>

<style>
    /* Scoped CSS for the Settings Card - width: 100% removed to protect sidebar */
    .settings-content-area {
        padding: 40px 20px;
        font-family: 'Poppins', sans-serif;
        flex-grow: 1; /* Allows it to fill remaining space next to sidebar */
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
    .input-group input[type="text"], 
    .input-group input[type="email"],
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
        <h2>Email Settings</h2>
        
        <form method="POST" action="">

            <div class="input-group">
                <label>Username (SMTP User)</label>
                <input type="text" name="username" value="<?php echo htmlspecialchars($settings['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="input-group">
                <label>Receive Email ID</label>
                <input type="email" name="receive_email_id" value="<?php echo htmlspecialchars($settings['receive_email_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="input-group">
                <label>Sending Email ID (From Address)</label>
                <input type="email" name="sending_email_id" value="<?php echo htmlspecialchars($settings['sending_email_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <div class="input-group">
                <label>App Password</label>
                <input type="text" name="app_password" value="<?php echo htmlspecialchars($settings['app_password'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <button type="submit" class="update-btn">Save Settings</button>
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
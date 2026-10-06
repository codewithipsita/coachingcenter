<?php
session_start();
include('db.php');

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize input
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Prepare SQL to find the user by email
    $sql = "SELECT id, name, password FROM student_master WHERE email = ?";
    $stmt = mysqli_prepare($conn, $sql);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        
        // Check if the email exists in the database
        if (mysqli_stmt_num_rows($stmt) > 0) {
            mysqli_stmt_bind_result($stmt, $id, $name, $hashed_password);
            mysqli_stmt_fetch($stmt);
            
            // Verify the entered password against the hashed password in the database
            if (password_verify($password, $hashed_password)) {
                // Password is correct, set the exact session variables welcome.php expects
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id'] = $id;
                $_SESSION['user_name'] = $name;
                
                // Redirect to the student welcome page
                header("Location: student/welcome.php");
                exit();
            } else {
                // Incorrect password
                $message = "<script>alert('Invalid email or password!');</script>";
            }
        } else {
            // Email not found
            $message = "<script>alert('Invalid email or password!');</script>";
        }
        mysqli_stmt_close($stmt);
    } else {
        $message = "<script>alert('Database error. Please try again later.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Glassmorphism</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

    <div class="container">
        <h2>Welcome Back</h2>
        
        <!-- Render the alert message if there is an error -->
        <?php echo $message; ?>
        
        <form action="" method="POST">
            <div class="input-box">
                <i class="fa-solid fa-envelope"></i>
                <input type="email" name="email" placeholder="Email Address" required>
            </div>

            <div class="input-box">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <button type="submit">Login</button>

            <div class="links">
                <a href="register.php">Register as a student</a>
                <a href="#">Forgot Password?</a>
            </div>
        </form>
    </div>

</body>
</html>
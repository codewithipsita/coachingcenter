<?php
session_start();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: welcome.php");
    exit();
}

include('../db.php');
$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name     = trim($_POST['name']);
    $password = $_POST['password'];

    // BUG FIX: Use prepared statement to prevent SQL injection
    $stmt = mysqli_prepare($conn, "SELECT id, username, password FROM admin WHERE username = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "s", $name);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            mysqli_stmt_bind_result($stmt, $admin_id, $admin_username, $hashed_password);
            mysqli_stmt_fetch($stmt);
            mysqli_stmt_close($stmt);

            if (password_verify($password, $hashed_password)) {
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id']   = $admin_id;
                $_SESSION['username']  = $admin_username;
                header("Location: welcome.php");
                exit();
            } else {
                $message = "Incorrect password.";
            }
        } else {
            mysqli_stmt_close($stmt);
            $message = "Username not found.";
        }
    } else {
        $message = "Database error. Please try again.";
    }
}
?>
<!-- Keep your exact HTML/CSS from the previous login page here -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Glassmorphism</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #0f172a; overflow: hidden; }
        .blob { position: absolute; filter: blur(60px); z-index: 0; animation: animateLiquid 10s ease-in-out infinite alternate; }
        .shape-1 { width: 400px; height: 400px; background: linear-gradient(135deg, #47B5FF, #256D85); top: -100px; left: -100px; border-radius: 40% 60% 70% 30% / 40% 50% 60% 50%; }
        .shape-2 { width: 350px; height: 350px; background: linear-gradient(135deg, #DFF6FF, #06283D); bottom: -100px; right: -50px; border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%; animation-delay: -5s; }
        @keyframes animateLiquid {
            0% { transform: translateY(0) scale(1) rotate(0deg); border-radius: 40% 60% 70% 30% / 40% 50% 60% 50%; }
            100% { transform: translateY(-50px) scale(1.1) rotate(20deg); border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%; }
        }
        .container { position: relative; z-index: 1; width: 400px; padding: 50px 40px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px; backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2); color: #fff; }
        .container h2 { text-align: center; font-size: 2rem; font-weight: 500; letter-spacing: 2px; margin-bottom: 30px; }
        .input-box { position: relative; margin-bottom: 25px; }
        .input-box i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); font-size: 1.2rem; color: rgba(255, 255, 255, 0.7); }
        .input-box input { width: 100%; padding: 15px 15px 15px 45px; background: rgba(255, 255, 255, 0.1); border: 1px solid transparent; outline: none; border-radius: 30px; color: #fff; font-size: 1rem; transition: 0.3s ease; }
        .input-box input::placeholder { color: rgba(255, 255, 255, 0.5); }
        .input-box input:focus { background: rgba(255, 255, 255, 0.2); border: 1px solid rgba(71, 181, 255, 0.5); box-shadow: 0 0 10px rgba(71, 181, 255, 0.2); }
        button { width: 100%; padding: 12px; background: #47B5FF; border: none; outline: none; border-radius: 30px; color: #fff; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: 0.3s; box-shadow: 0 5px 15px rgba(71, 181, 255, 0.3); }
        button:hover { background: #256D85; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(71, 181, 255, 0.4); }
        .links { text-align: center; margin-top: 20px; }
        .links a { color: rgba(255, 255, 255, 0.7); text-decoration: none; font-size: 0.9rem; transition: 0.3s; }
        .links a:hover { color: #fff; text-shadow: 0 0 5px rgba(255, 255, 255, 0.5); }
    </style>
</head>
<body>
    <div class="blob shape-1"></div>
    <div class="blob shape-2"></div>
    <div class="container">
        <h2>LOGIN</h2>
        <form method="POST" action="">
            <div class="input-box">
                <i class="fa-solid fa-envelope"></i>
                <input type="text" name="name" placeholder="User Name" required>
            </div>
            <div class="input-box">
                <i class="fa-solid fa-lock"></i>
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit">Login</button>
            <div class="links">
                <a href="#">Forgot Password?</a>
            </div>
        </form>
    </div>
    <?php if(!empty($message)): ?>
    <script>
        alert("<?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>");
    </script>
    <?php endif; ?>
</body>
</html>
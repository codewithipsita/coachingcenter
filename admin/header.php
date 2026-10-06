<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Professional Header Dropdown</title>
  <style>
    /* Reset and Base Styles */
    body {
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
      background-color: #f4f5f7;
    }

    /* Professional Dark Header */
    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      background-color: #0f172a; /* Deep slate blue */
      padding: 15px 40px;
      box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
    }

    .logo {
      font-size: 22px;
      font-weight: 700;
      color: #ffffff;
      letter-spacing: 0.5px;
    }

    /* Profile Container */
    .profile-container {
      position: relative;
    }

    /* Profile Button matching the dark header */
    .profile-icon {
      background-color: #1e293b;
      border: 2px solid #334155;
      border-radius: 50%;
      width: 44px;
      height: 44px;
      font-size: 20px;
      cursor: pointer;
      display: flex;
      justify-content: center;
      align-items: center;
      transition: all 0.2s ease;
    }

    .profile-icon:hover {
      background-color: #334155;
      border-color: #475569;
    }

    .profile-icon:focus {
      outline: none;
      border-color: #3b82f6;
    }

    /* Clean, Light Dropdown Menu */
    .dropdown-menu {
      visibility: hidden;
      opacity: 0;
      position: absolute;
      right: 0;
      top: 60px;
      background-color: #ffffff;
      min-width: 220px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.15);
      border: 1px solid #e2e8f0;
      border-radius: 12px;
      padding: 8px;
      transform: translateY(-10px);
      transition: opacity 0.3s ease, transform 0.3s ease, visibility 0.3s;
      z-index: 100;
    }

    /* Active Class for Animation */
    .dropdown-menu.show {
      visibility: visible;
      opacity: 1;
      transform: translateY(0);
    }

    /* Dropdown Links */
    .dropdown-menu a {
      color: #334155;
      padding: 10px 12px;
      text-decoration: none;
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 14px;
      font-weight: 500;
      border-radius: 8px;
      transition: background-color 0.2s, color 0.2s;
    }

    .dropdown-menu a:hover {
      background-color: #f1f5f9;
      color: #0f172a;
    }

    /* Divider */
    .divider {
      height: 1px;
      background-color: #e2e8f0;
      margin: 6px 0;
    }

    /* Special styling for Logout */
    .dropdown-menu a.logout {
      color: #e11d48;
    }

    .dropdown-menu a.logout:hover {
      background-color: #fff1f2;
      color: #be123c;
    }
  </style>
</head>
<body>

  <header>
    <div class="logo"></div>
    
    <div class="profile-container">
      <button class="profile-icon" id="profileBtn" aria-label="Profile">🧑‍💻</button>
      
      <!-- Dropdown Menu -->
      <div class="dropdown-menu" id="dropdownMenu">
        <a href="update_profile.php"><span>⚙️</span> Update Profile</a>
        <a href="email-setting.php"><span>✉️</span> Email Settings</a>
        <a href="password.php"><span>🔐</span> Change Password</a>
        <div class="divider"></div>
        <a href="logout.php" class="logout"><span>🚪</span> Logout</a>
      </div>
    </div>
  </header>

  <script>
    const profileBtn = document.getElementById('profileBtn');
    const dropdownMenu = document.getElementById('dropdownMenu');

    // Toggle dropdown
    profileBtn.addEventListener('click', function(event) {
      dropdownMenu.classList.toggle('show');
      event.stopPropagation();
    });

    // Close when clicking outside
    window.addEventListener('click', function(event) {
      if (!event.target.closest('.profile-container')) {
        dropdownMenu.classList.remove('show');
      }
    });
  </script>

</body>
</html>
<style>
    /* 1. Lock the body to the full screen height and use a column flexbox */
    body {
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
      height: 100vh;
      display: flex;
      flex-direction: column; 
      background-color: #f4f5f7;
    }

    /* 2. Header stays at the top and doesn't shrink */
    header {
      background-color: #0f172a;
      color: white;
      padding: 15px 40px;
      display: flex;
      justify-content: space-between;
      flex-shrink: 0; 
      z-index: 10;
    }

    /* 3. Wrap the sidebar and main content in a row flexbox */
    .layout-wrapper {
      display: flex;
      flex: 1; 
      overflow: hidden; 
    }

    /* 4. Sidebar fixed width & spacing */
    .sidebar {
      width: 260px;
      background-color: #1e293b;
      padding: 24px 16px;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .nav-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 16px;
      color: #94a3b8;
      text-decoration: none;
      font-size: 15px;
      font-weight: 500;
      border-radius: 8px;
      transition: all 0.2s ease-in-out;
      cursor: pointer;
    }

    /* Dropdown button reset to match links */
    .dropdown-btn {
      background: none;
      border: none;
      width: 100%;
      text-align: left;
      font-family: inherit;
    }

    /* Push the arrow to the right edge */
    .dropdown-btn .arrow {
      margin-left: auto;
      font-size: 12px;
      transition: transform 0.2s;
    }

    .nav-item:hover {
      background-color: #334155;
      color: #f8fafc;
    }

    .nav-item.active {
      background-color: #3b82f6;
      color: #ffffff;
      box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.3);
    }

    /* Container for sub-items */
    .dropdown-container {
      display: none; /* Hidden by default */
      flex-direction: column;
      gap: 4px;
      padding-left: 36px; /* Indent the sub-menu */
    }

    .sub-item {
      padding: 10px 16px;
      font-size: 14px;
    }

    /* 5. Main content fills the rest of the space */
    .main-content {
      flex: 1; 
      padding: 30px 40px;
      overflow-y: auto; 
    }

    .widget {
      background: white;
      padding: 20px;
      border-radius: 8px;
      border: 1px solid #e2e8f0;
    }
  </style>
<!-- BOTTOM: Wrapper for Sidebar and Content -->
  <div class="layout-wrapper">
    
    <!-- LEFT: Sidebar -->
    <nav class="sidebar">
      <a href="welcome.php" class="nav-item active" onclick="window.location.reload();">
         Dashboard
      </a>

      <!-- Dropdown Trigger -->
      <button class="nav-item dropdown-btn" onclick="toggleMenu('courseMenu', 'courseArrow')">
  <span>📚</span> Course
  <span class="arrow" id="courseArrow">▼</span>
</button>

      <!-- Sub-menu Items -->
      <div class="dropdown-container" id="courseMenu">
        <a href="add-course.php" class="nav-item sub-item">Add New Course</a>
        <a href="all-course.php" class="nav-item sub-item">All Courses</a>
      </div>

      <a href="batch.php" class="nav-item">
        <span>👨‍🎓</span></span> Batch
      </a>

       <a href="material.php" class="nav-item">
        <span>🪙</span></span> Material
      </a>

      <a href="student.php" class="nav-item">
        <span>👨</span> Students
      </a>

      <!-- <a href="enrollments.php" class="nav-item">
        <span>📋</span> Enrollments
      </a> -->

      <a href="attendance.php" class="nav-item">
        <span>📅</span> Attendance
      </a>

       <!-- Dropdown Trigger -->
      <button class="nav-item dropdown-btn" onclick="toggleMenu('cirtificateMenu', 'cirtificateArrow')">
  <span>📄</span> Certificate
  <span class="arrow" id="cirtificateArrow">▼</span>
</button>

      <!-- Sub-menu Items -->
      <div class="dropdown-container" id="cirtificateMenu">
        <a href="cirtificate.php" class="nav-item sub-item">Cirtificate</a>
        <a href="cirtificate-setting.php" class="nav-item sub-item">Cirtificcate Setting</a>
      </div>

        <button class="nav-item dropdown-btn" onclick="toggleMenu('settingMenu', 'settingArrow')">
  <span>🔅</span> Settings
  <span class="arrow" id="settingArrow">▼</span>
</button>

      <!-- Sub-menu Items -->
      <div class="dropdown-container" id="settingMenu">
        <a href="qr-setting.php" class="nav-item sub-item">QR Setting</a>
        <a href="half_payment.php" class="nav-item sub-item">Half Payment</a>
        <a href="full_payment.php" class="nav-item sub-item">Full Payment</a>
      </div>


    </nav>

    <!-- RIGHT: Main Dashboard Content goes here -->
    <!-- Each page must close: </div> (layout-wrapper), </body>, </html> -->

    <script>
  function toggleMenu(menuId, arrowId) {
    const menu = document.getElementById(menuId);
    const arrow = document.getElementById(arrowId);
    
    if (menu.style.display === "flex") {
      menu.style.display = "none";
      arrow.style.transform = "rotate(0deg)";
    } else {
      menu.style.display = "flex";
      arrow.style.transform = "rotate(180deg)";
    }
  }
</script>
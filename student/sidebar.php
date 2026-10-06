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
      <a href="welcome.php" class="nav-item active">
         Dashboard
      </a>
      <!-- <a href="course.php" class="nav-item">
        <span>📚</span> Course
      </a>
      <a href="receipts.php" class="nav-item">
        <span>🧾</span> Receipts
      </a> -->
      <a href="my-materials.php" class="nav-item">
        <span>📚</span> My Materials
      </a>
      <a href="my-certificate.php" class="nav-item">
        <span>🎓</span> My Certificate
      </a>
      <a href="mark_attendance.php" class="nav-item">
        <span>📅</span> Attendance
      </a>
    </nav> <!-- This closing tag was previously missing -->

   
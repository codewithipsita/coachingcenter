<?php
session_start();

// PHP Cache Control
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Kick out if not logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit();
}

include '../db.php';
include('header.php');
include('sidebar.php');

// Count pending payment students
$pending_count = 0;
$pending_result = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM enrollments WHERE status = 'Pending'");
if ($pending_result) {
    $row = mysqli_fetch_assoc($pending_result);
    $pending_count = $row['cnt'];
}
?>

<style>
    .dashboard-header { margin-bottom: 30px; }
    .dashboard-header h2 { font-size: 26px; color: #1e293b; margin: 0 0 6px 0; }
    .dashboard-header p { color: #64748b; margin: 0; font-size: 15px; }

    .summary-cards { display: flex; flex-wrap: wrap; gap: 24px; margin-top: 10px; }

    .summary-card {
        background: #ffffff;
        border-radius: 14px;
        box-shadow: 0 4px 18px rgba(0,0,0,0.08);
        padding: 28px 32px;
        min-width: 220px;
        cursor: pointer;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 22px;
        border-left: 6px solid #f59e0b;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        position: relative;
        overflow: hidden;
    }
    .summary-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(245, 158, 11, 0.2);
    }
    .summary-card::before {
        content: '';
        position: absolute;
        top: -40px; right: -30px;
        width: 120px; height: 120px;
        background: rgba(245, 158, 11, 0.08);
        border-radius: 50%;
    }
    .card-icon { font-size: 40px; line-height: 1; flex-shrink: 0; }
    .card-info { display: flex; flex-direction: column; gap: 4px; }
    .card-info .card-count { font-size: 38px; font-weight: 800; color: #f59e0b; line-height: 1; }
    .card-info .card-label { font-size: 14px; font-weight: 600; color: #475569; }
    .card-info .card-sub { font-size: 12px; color: #94a3b8; }
    .pending-badge {
        position: absolute; top: 14px; right: 16px;
        background: #fef3c7; color: #b45309;
        font-size: 11px; font-weight: 700;
        padding: 3px 10px; border-radius: 20px;
        text-transform: uppercase; letter-spacing: 0.5px;
    }
</style>

<main class="main-content">
    <div class="dashboard-header">
        <h2>👋 Welcome, Admin!</h2>
        <p>Here's a quick overview of your coaching center.</p>
    </div>

    <div class="summary-cards">
        <!-- Pending Payment Card — click to go to approved.php -->
        <a href="approved.php" class="summary-card" title="Click to manage pending payments">
            <div class="card-icon">💳</div>
            <div class="card-info">
                <span class="card-count"><?php echo $pending_count; ?></span>
                <span class="card-label">Pending Payments</span>
                <span class="card-sub">Click to review &amp; approve</span>
            </div>
            <span class="pending-badge">⚠ Action Needed</span>
        </a>
    </div>
</main>

  </div> <!-- close .layout-wrapper from sidebar -->
</body>
</html>
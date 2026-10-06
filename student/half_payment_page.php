<?php
// Include your existing files
include '../db.php';       
include 'header.php';
include 'sidebar.php';

$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$batch_id = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : 0;

$course_name = "Unknown Course";
$total_amount = 0;

if ($course_id > 0) {
    $stmt = $conn->prepare("SELECT course_name, course_price FROM courses WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $course_name = $row['course_name'];
            $total_amount = floatval($row['course_price']);
        }
        $stmt->close();
    }
}

$student_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$already_paid = false;
$table_exists = $conn->query("SHOW TABLES LIKE 'enrollments'");
if ($table_exists && $table_exists->num_rows > 0 && $student_id > 0 && $course_id > 0) {
    $check_q = "SELECT id FROM enrollments WHERE student_id = ? AND course_id = ? LIMIT 1";
    if ($check_stmt = $conn->prepare($check_q)) {
        $check_stmt->bind_param("ii", $student_id, $course_id);
        $check_stmt->execute();
        $check_res = $check_stmt->get_result();
        if ($check_res->num_rows > 0) {
            $already_paid = true;
        }
        $check_stmt->close();
    }
}

$half_amount = $total_amount / 2;
?>

<style>
    .payment-wrapper {
        flex: 1;
        overflow-y: auto;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        min-height: calc(100vh - 80px);
        padding: 40px 20px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f8fafc; 
        box-sizing: border-box;
        width: 100%; 
    }

    .payment-card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1); 
        padding: 40px;
        width: 100%; 
        max-width: 600px;
        margin: 0 auto;
        border-top: 5px solid #2980b9;
        transition: transform 0.3s ease-out, box-shadow 0.3s ease-out;
    }

    .payment-card:hover {
        transform: translateX(8px);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
    }
    
    .payment-title {
        color: #1a252f;
        font-size: 22px;
        border-bottom: 2px solid #e1e8ed;
        padding-bottom: 15px;
        margin-bottom: 20px;
        margin-top: 0;
        text-align: center;
    }
    
    .payment-details {
        margin-bottom: 25px;
    }

    .detail-row {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid #f1f1f1;
        font-size: 16px;
    }

    .detail-label {
        color: #7f8c8d;
        font-weight: 600;
    }

    .detail-value {
        color: #2c3e50;
        font-weight: bold;
    }

    .half-amount {
        color: #e67e22;
        font-size: 18px;
    }
    
    .btn-paynow {
        display: block;
        width: 100%;
        background-color: #2980b9;
        color: #fff;
        padding: 12px 20px;
        text-decoration: none;
        border-radius: 5px;
        font-weight: bold;
        font-size: 16px;
        text-align: center;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        box-shadow: 0 3px 5px rgba(41, 128, 185, 0.2);
    }
    
    .btn-paynow:hover {
        background-color: #1c5980;
    }

    /* Modal Popup CSS Styling */
    .method-modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        align-items: center;
        justify-content: center;
    }

    .method-modal-content {
        background: #ffffff;
        padding: 30px;
        border-radius: 10px;
        text-align: center;
        width: 100%;
        max-width: 400px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        position: relative;
    }

    .method-modal-close {
        position: absolute;
        top: 12px;
        right: 15px;
        font-size: 22px;
        font-weight: bold;
        color: #aaa;
        cursor: pointer;
        background: none;
        border: none;
        padding: 0;
        line-height: 1;
    }

    .method-modal-close:hover {
        color: #000;
    }

    .method-modal-content h3 {
        margin-top: 0;
        color: #1a252f;
    }

    .method-btns {
        margin-top: 25px;
        display: flex;
        gap: 15px;
        justify-content: center;
    }

    .btn-method {
        padding: 10px 20px;
        border-radius: 5px;
        text-decoration: none;
        font-weight: 600;
        color: #fff;
        font-size: 14px;
        flex: 1;
    }

    .btn-cash {
        background-color: #27ae60;
    }

    .btn-cash:hover {
        background-color: #219653;
    }

    .btn-online {
        background-color: #8e44ad;
    }

    .btn-online:hover {
        background-color: #732d91;
    }
</style>

<div class="payment-wrapper">
    <div class="payment-card">
        <h2 class="payment-title">Half Payment Details</h2>

        <div class="payment-details">
            <div class="detail-row">
                <span class="detail-label">Course Name</span>
                <span class="detail-value"><?php echo htmlspecialchars($course_name); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Total Amount</span>
                <span class="detail-value">Rs. <?php echo number_format($total_amount, 2); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Half Amount to Pay</span>
                <span class="detail-value half-amount">Rs. <?php echo number_format($half_amount, 2); ?></span>
            </div>
        </div>
        
        <?php if ($already_paid): ?>
            <div style="background: #fdf5e6; color: #d35400; padding: 15px; border-radius: 8px; font-weight: bold; text-align: center; border-left: 5px solid #d35400; margin-top: 15px;">
                <i class="fas fa-info-circle"></i> You have already initiated or completed payment for this course.
            </div>
        <?php else: ?>
            <button type="button" class="btn-paynow" onclick="openMethodModal()">Pay Now</button>
        <?php endif; ?>

    </div>
</div>

<!-- Payment Method Modal Popup -->
<div id="methodModal" class="method-modal">
    <div class="method-modal-content">
        <button type="button" class="method-modal-close" onclick="closeMethodModal()">&times;</button>
        <h3>Select Payment Method</h3>
        <p>How would you like to pay your half payment?</p>
        <div class="method-btns">
            <a href="cash_payment.php?type=half&batch_id=<?php echo $batch_id; ?>&course_id=<?php echo $course_id; ?>&amount=<?php echo $half_amount; ?>" class="btn-method btn-cash">Cash</a>
            <a href="online_payment.php?type=half&batch_id=<?php echo $batch_id; ?>&course_id=<?php echo $course_id; ?>&amount=<?php echo $half_amount; ?>" class="btn-method btn-online">Online</a>
        </div>
    </div>
</div>

<script>
    function openMethodModal() {
        document.getElementById('methodModal').style.display = 'flex';
    }

    function closeMethodModal() {
        document.getElementById('methodModal').style.display = 'none';
    }
</script>

  </div> <!-- closes .layout-wrapper from sidebar.php -->
</body>
</html>

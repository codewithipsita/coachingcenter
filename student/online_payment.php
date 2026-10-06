<?php
// BUG FIX: Add session_start() explicitly + proper auth guard
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

// Include essential files
include '../db.php';
include 'header.php';
include 'sidebar.php';

// Check if user is logged in
$student_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$student_name = "Unknown Student";

// Fetch Student Name
if ($student_id > 0) {
    $stmt = $conn->prepare("SELECT name FROM student_master WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $student_name = $row['name'];
        }
        $stmt->close();
    }
}

// Get URL parameters
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
$batch_id = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : 0;
$payment_amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;
$payment_type = isset($_GET['type']) ? $_GET['type'] : 'online';

$course_name = "Unknown Course";
$total_amount = 0;

// Fetch Course Details
if ($course_id > 0) {
    $stmt = $conn->prepare("SELECT course_name, course_price FROM courses WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $course_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $course_name = $row['course_name'];
            $total_amount = floatval($row['course_price']);
        }
        $stmt->close();
    }
}

// Fetch active QR Code from qr_settings table
$qr_image = "";
$qr_query = "SELECT qr_image FROM qr_settings LIMIT 1";
$qr_result = mysqli_query($conn, $qr_query);
if ($qr_result && mysqli_num_rows($qr_result) > 0) {
    $qr_row = mysqli_fetch_assoc($qr_result);
    // Add '../admin/' because the image path is relative to the admin folder
    $qr_image = '../admin/' . $qr_row['qr_image'];
}

// Create the enrollments table automatically if it doesn't exist
$table_sql = "
CREATE TABLE IF NOT EXISTS enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    student_name VARCHAR(255) NOT NULL,
    course_id INT NOT NULL,
    course_name VARCHAR(255) NOT NULL,
    batch_id INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    paid_amount DECIMAL(10,2) NOT NULL,
    payment_type VARCHAR(50) NOT NULL,
    utr_number VARCHAR(100) NOT NULL,
    screenshot VARCHAR(255) NOT NULL,
    status VARCHAR(50) DEFAULT 'Pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
mysqli_query($conn, $table_sql);

// Check duplicate payment
$already_paid = false;
if ($student_id > 0 && $course_id > 0) {
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

// Handle Form Submission
$receipt_generated = false;
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_payment'])) {
    $utr_number = mysqli_real_escape_string($conn, $_POST['utr_number']);
    
    if ($already_paid) {
        $error_message = "Duplicate entry blocked. You have already completed the payment for this course.";
    } else {
        // File upload directory handling
        $target_dir = "../admin/uploads/payments/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $screenshot_path = "";
    if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] == 0) {
        $file_name = time() . "_" . basename($_FILES["screenshot"]["name"]);
        $target_file = $target_dir . $file_name;
        
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        if (in_array($imageFileType, ['jpg', 'jpeg', 'png', 'webp'])) {
            if (move_uploaded_file($_FILES["screenshot"]["tmp_name"], $target_file)) {
                $screenshot_path = "uploads/payments/" . $file_name; // Store relative path for database
            } else {
                $error_message = "Failed to upload the screenshot. Check folder permissions.";
            }
        } else {
            $error_message = "Only JPG, JPEG, PNG, and WEBP files are allowed for the screenshot.";
        }
    } else {
        $error_message = "Please upload a screenshot of your payment.";
    }

    // Insert into database if there are no errors
    if (empty($error_message) && !empty($screenshot_path)) {
        $insert_query = "INSERT INTO enrollments 
                         (student_id, student_name, course_id, course_name, batch_id, total_amount, paid_amount, payment_type, utr_number, screenshot) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        if ($stmt = $conn->prepare($insert_query)) {
            // Label the payment type appropriately
            $type_label = ($payment_type === 'half') ? 'Half Payment (Online)' : 'Online Payment';
            
            $stmt->bind_param("isisiddsss", $student_id, $student_name, $course_id, $course_name, $batch_id, $total_amount, $payment_amount, $type_label, $utr_number, $screenshot_path);
            
            if ($stmt->execute()) {
                $receipt_generated = true;
            } else {
                $error_message = "Database Error: Could not save your enrollment.";
            }
            $stmt->close();
            } else {
                $error_message = "Database statement preparation failed.";
            }
        }
    }
}
?>

<!-- Include html2pdf for downloading the receipt -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<!-- Include FontAwesome and Bootstrap if not loaded by header -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .online-pay-wrapper {
        flex: 1;
        overflow-y: auto;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        min-height: calc(100vh - 80px);
        padding: 40px 20px;
        background-color: #f4f7fc;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        width: 100%;
    }

    .form-card {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        padding: 40px;
        width: 100%;
        max-width: 800px;
        margin: 0 auto;
        border-top: 5px solid #8e44ad;
    }

    .section-title {
        color: #2c3e50;
        font-weight: 700;
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 10px;
        margin-bottom: 25px;
    }

    .readonly-field {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        color: #475569;
        font-weight: 500;
    }

    .qr-box {
        border: 2px dashed #cbd5e1;
        border-radius: 10px;
        padding: 15px;
        text-align: center;
        background: #f8fafc;
    }

    .qr-box img {
        max-width: 200px;
        height: auto;
        border-radius: 8px;
    }

    .btn-save {
        background-color: #8e44ad;
        border: none;
        padding: 12px 30px;
        font-weight: 600;
        font-size: 16px;
        border-radius: 8px;
        color: white;
        transition: all 0.3s ease;
        width: 100%;
    }

    .btn-save:hover {
        background-color: #732d91;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(142, 68, 173, 0.3);
    }

    /* Receipt Overlay Styles */
    .receipt-overlay {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.7);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    }
    
    .receipt-card {
        background: white;
        padding: 40px;
        border-radius: 15px;
        text-align: center;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    @keyframes popIn {
        0% { transform: scale(0.8); opacity: 0; }
        100% { transform: scale(1); opacity: 1; }
    }

    .receipt-card i.success-icon {
        color: #27ae60;
        font-size: 60px;
        margin-bottom: 20px;
    }

    .receipt-content {
        text-align: left;
        background: #f8fafc;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        margin-bottom: 20px;
    }

    .receipt-actions .btn {
        margin: 0 5px;
    }

    @media print {
        body * {
            visibility: hidden;
        }
        #printableReceipt, #printableReceipt * {
            visibility: visible;
        }
        #printableReceipt {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }
        .receipt-actions, .spinner-border, .redirect-text {
            display: none !important;
        }
    }
</style>

<div class="online-pay-wrapper">
    
    <?php if ($receipt_generated): ?>
        <!-- Success Receipt Popup -->
        <div class="receipt-overlay">
            <div class="receipt-card">
                
                <div id="printableReceipt">
                    <i class="fas fa-check-circle success-icon"></i>
                    <h3 class="fw-bold mb-3">Payment Receipt</h3>
                    <p class="text-muted mb-3">Your payment is currently <strong class="text-warning">Pending</strong> verification.</p>
                    
                    <div class="receipt-content">
                        <p><strong>Student:</strong> <?php echo htmlspecialchars($student_name); ?></p>
                        <p><strong>Course:</strong> <?php echo htmlspecialchars($course_name); ?></p>
                        <p><strong>Batch ID:</strong> <?php echo htmlspecialchars($batch_id); ?></p>
                        <p><strong>Amount Paid:</strong> Rs. <?php echo number_format($payment_amount, 2); ?></p>
                        <p><strong>UTR:</strong> <?php echo htmlspecialchars($utr_number); ?></p>
                        <p><strong>Date:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                    </div>
                </div>

                <div class="receipt-actions mb-4">
                    <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Print</button>
                    <button onclick="downloadReceipt()" class="btn btn-success"><i class="fas fa-download"></i> Download</button>
                </div>

                <div class="spinner-border text-primary" role="status" style="width: 1.2rem; height: 1.2rem;">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 small text-secondary redirect-text">Redirecting to dashboard in <span id="countdown">6</span> seconds...</p>
            </div>
        </div>

        <script>
            // Download functionality using html2pdf
            function downloadReceipt() {
                var element = document.getElementById('printableReceipt');
                var opt = {
                    margin:       1,
                    filename:     'Payment_Receipt.pdf',
                    image:        { type: 'jpeg', quality: 0.98 },
                    html2canvas:  { scale: 2 },
                    jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
                };
                html2pdf().set(opt).from(element).save();
            }

            // Countdown and Redirect logic
            var timeLeft = 6;
            var countdownTimer = setInterval(function() {
                timeLeft--;
                document.getElementById('countdown').textContent = timeLeft;
                if (timeLeft <= 0) {
                    clearInterval(countdownTimer);
                    window.location.href = 'welcome.php';
                }
            }, 1000);
        </script>
    <?php else: ?>

        <div class="form-card">
            <h3 class="section-title"><i class="fas fa-laptop-code text-primary me-2"></i> Online Payment Enrollment</h3>

            <?php if ($already_paid): ?>
                <div class="alert alert-warning">
                    <h4><i class="fas fa-exclamation-triangle me-2"></i> Payment Already Received</h4>
                    <p>You have already enrolled in and paid for this course. You cannot submit another payment for the same course.</p>
                    <a href="welcome.php" class="btn btn-outline-primary mt-2">Return to Dashboard</a>
                </div>
            <?php else: ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error_message); ?></div>
                <?php endif; ?>

                <form action="" method="POST" enctype="multipart/form-data">
                
                <div class="row mb-4">
                    <!-- Left Column: Details -->
                    <div class="col-md-7">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Student Name</label>
                            <input type="text" class="form-control readonly-field" value="<?php echo htmlspecialchars($student_name); ?>" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold">Course Name</label>
                            <input type="text" class="form-control readonly-field" value="<?php echo htmlspecialchars($course_name); ?>" readonly>
                        </div>
                        
                        <div class="row">
                            <div class="col-sm-6 mb-3">
                                <label class="form-label fw-bold">Batch ID</label>
                                <input type="text" class="form-control readonly-field" value="<?php echo htmlspecialchars($batch_id); ?>" readonly>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <label class="form-label fw-bold">Total Amount</label>
                                <input type="text" class="form-control readonly-field" value="Rs. <?php echo number_format($total_amount, 2); ?>" readonly>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-success">Now Payment Amount</label>
                            <input type="text" class="form-control readonly-field border-success text-success fw-bold" value="Rs. <?php echo number_format($payment_amount, 2); ?>" readonly>
                        </div>
                    </div>

                    <!-- Right Column: QR Code -->
                    <div class="col-md-5 d-flex flex-column justify-content-center">
                        <label class="form-label fw-bold text-center">Scan & Pay</label>
                        <div class="qr-box shadow-sm">
                            <?php if (!empty($qr_image) && file_exists($qr_image)): ?>
                                <img src="<?php echo htmlspecialchars($qr_image); ?>" alt="Admin QR Code">
                            <?php else: ?>
                                <div class="p-4 text-muted">
                                    <i class="fas fa-qrcode fa-3x mb-2 d-block"></i>
                                    QR Code not set by Admin
                                </div>
                            <?php endif; ?>
                        </div>
                        <p class="text-center text-muted small mt-2">Scan this QR to make the exact payment shown above.</p>
                    </div>
                </div>

                <hr class="mb-4 text-muted">

                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Enter UTR / Transaction No. <span class="text-danger">*</span></label>
                        <input type="text" name="utr_number" class="form-control" placeholder="e.g. 123456789012" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Upload Payment Screenshot <span class="text-danger">*</span></label>
                        <input type="file" name="screenshot" class="form-control" accept="image/*" required>
                        <div class="form-text">Supported formats: JPG, PNG, WEBP.</div>
                    </div>
                </div>

                <button type="submit" name="submit_payment" class="btn btn-save"><i class="fas fa-check-circle me-2"></i> Save Payment & Enroll</button>

            </form>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</div>

</div> <!-- Closes .layout-wrapper from sidebar.php -->
</body>
</html>

<?php
// BUG FIX: Add session_start() explicitly + proper auth guard
session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

// Include your existing files
include '../db.php';
include 'header.php';
include 'sidebar.php';

// Initialize variable to hold course data
$course_data = null;
$batch_id_val = 0;

// Check if batch_id is passed in the URL
if (isset($_GET['batch_id']) && !empty($_GET['batch_id'])) {
    $batch_id = intval($_GET['batch_id']);
    $batch_id_val = $batch_id;

    // SQL query to join courses and batches based on the batch_id
    $sql = "SELECT c.id, c.course_name, c.course_image, c.course_duration, c.course_price, 
                   c.capacity, c.course_details, c.total_enroled, c.created_at 
            FROM courses c
            JOIN batches b ON c.id = b.course_id
            WHERE b.id = ?";

    // Prepare and execute the statement
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $batch_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $course_data = $result->fetch_assoc();
        }
        $stmt->close();
    }
}

// Fetch payment descriptions
$half_payment_desc = "";
$full_payment_desc = "";
$payment_res = mysqli_query($conn, "SELECT payment_type, description FROM payments WHERE payment_type IN ('Half', 'Full') ORDER BY id DESC");
$seen = [];
if ($payment_res) {
    while ($row = mysqli_fetch_assoc($payment_res)) {
        if (!isset($seen[$row['payment_type']])) {
            if ($row['payment_type'] === 'Half') {
                $half_payment_desc = $row['description'];
            } else {
                $full_payment_desc = $row['description'];
            }
            $seen[$row['payment_type']] = true;
        }
    }
}

// Handle form submission to trigger the payment modal
$show_modal = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_enrollment'])) {
    $show_modal = true;
    $batch_id_val = intval($_POST['batch_id']);
    $course_id_val = intval($_POST['course_id']);
}
?>

<style>
    .enroll-wrapper {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: calc(100vh - 80px);
        padding: 20px;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background-color: #f4f7f6; 
        box-sizing: border-box;
        width: 100%; 
    }

    .enroll-card {
        background: #ffffff;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08); 
        padding: 20px 25px;
        width: 100%; 
        margin: 0;
    }
    
    .enroll-title {
        color: #1a252f;
        font-size: 20px;
        border-bottom: 2px solid #e1e8ed;
        padding-bottom: 10px;
        margin-bottom: 15px;
        margin-top: 0;
    }
    
    .details-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .details-table th, .details-table td {
        padding: 10px 12px;
        border-bottom: 1px solid #edf1f2;
        text-align: left;
        vertical-align: middle;
    }
    
    .details-table th {
        color: #7f8c8d;
        width: 28%;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 0.5px;
    }
    
    .details-table td {
        color: #2c3e50;
        font-size: 14px;
        line-height: 1.4;
    }
    
    .details-table tr:hover td {
        background-color: #fcfcfc;
    }
    
    .course-img {
        width: 65px;
        height: 65px;
        border-radius: 50%;
        object-fit: cover;
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        display: block;
        border: 2px solid #ffffff;
    }
    
    .btn-confirm {
        display: inline-block;
        background-color: #2980b9;
        color: #fff;
        padding: 10px 22px;
        text-decoration: none;
        border-radius: 5px;
        font-weight: 600;
        font-size: 14px;
        margin-top: 15px;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        box-shadow: 0 3px 5px rgba(41, 128, 185, 0.2);
    }
    
    .btn-confirm:hover {
        background-color: #1c5980;
    }
    
    .error-msg {
        color: #c0392b;
        background: #f9ebea;
        padding: 15px;
        border-radius: 6px;
        border-left: 4px solid #e74c3c;
        font-size: 14px;
    }

    /* Modal Popup CSS Styling */
    .payment-modal {
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

    .modal-content {
        background: #ffffff;
        padding: 30px;
        border-radius: 10px;
        text-align: center;
        width: 100%;
        max-width: 400px;
        box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        position: relative; /* Needed for positioning the close cross */
    }

    /* Close Cross Button Style */
    .modal-close {
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

    .modal-close:hover {
        color: #000;
    }

    .modal-content h3 {
        margin-top: 0;
        color: #1a252f;
    }

    .modal-btns {
        margin-top: 25px;
        display: flex;
        gap: 15px;
        justify-content: center;
    }

    .btn-payment {
        padding: 10px 20px;
        border-radius: 5px;
        text-decoration: none;
        font-weight: 600;
        color: #fff;
        font-size: 14px;
    }

    .btn-half {
        background-color: #e67e22;
    }

    .btn-half:hover {
        background-color: #d35400;
    }

    .btn-full {
        background-color: #27ae60;
    }

    .btn-full:hover {
        background-color: #219653;
    }
</style>

<div class="enroll-wrapper">
    <div class="enroll-card">
        <h2 class="enroll-title">Course Enrollment Details</h2>

        <?php if ($course_data): ?>
            <table class="details-table">
                <tr>
                    <th>Course Image</th>
                    <td>
                        <img src="../admin/<?php echo htmlspecialchars($course_data['course_image']); ?>" alt="Course Image" class="course-img">
                    </td>
                </tr>
                <tr>
                    <th>Course Name</th>
                    <td><strong><?php echo htmlspecialchars($course_data['course_name']); ?></strong></td>
                </tr>
                <tr>
                    <th>Duration</th>
                    <td><?php echo htmlspecialchars($course_data['course_duration']); ?></td>
                </tr>
                <tr>
                    <th>Price</th>
                    <td>Rs. <?php echo number_format($course_data['course_price'], 2); ?></td>
                </tr>
                <tr>
                    <th>Available Capacity</th>
                    <td>
                        <?php 
                        $available_seats = $course_data['capacity'] - $course_data['total_enroled'];
                        echo htmlspecialchars($available_seats) . ' seats left (Total: ' . htmlspecialchars($course_data['capacity']) . ')'; 
                        ?>
                    </td>
                </tr>
                <tr>
                    <th>Description</th>
                    <td><?php echo html_entity_decode($course_data['course_details']); ?></td>
                </tr>
            </table>
            
            <?php
            $student_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
            $already_enrolled = false;
            
            // Check if the enrollments table exists (suppress error if it doesn't)
            $table_exists = $conn->query("SHOW TABLES LIKE 'enrollments'");
            if ($table_exists && $table_exists->num_rows > 0 && $student_id > 0 && isset($course_data['id'])) {
                $check_course_id = $course_data['id'];
                $check_q = "SELECT id FROM enrollments WHERE student_id = ? AND course_id = ? LIMIT 1";
                if ($check_stmt = $conn->prepare($check_q)) {
                    $check_stmt->bind_param("ii", $student_id, $check_course_id);
                    $check_stmt->execute();
                    $check_res = $check_stmt->get_result();
                    if ($check_res->num_rows > 0) {
                        $already_enrolled = true;
                    }
                    $check_stmt->close();
                }
            }
            ?>
            <div style="text-align: right;">
                <?php if ($already_enrolled): ?>
                    <div style="background: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; border-left: 4px solid #ffeeba; text-align: left; margin-bottom: 15px;">
                        <strong><i class="fas fa-exclamation-circle"></i> Already Enrolled:</strong> You are already enrolled in a batch for this course.
                    </div>
                <?php else: ?>
                    <form action="" method="POST">
                        <input type="hidden" name="batch_id" value="<?php echo htmlspecialchars($batch_id_val); ?>">
                        <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($course_data['id']); ?>">
                        <input type="hidden" name="confirm_enrollment" value="1">
                        <button type="submit" class="btn-confirm">Confirm Enrollment</button>
                    </form>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="error-msg">
                <strong>Error:</strong> No course details found for the selected batch. Please ensure you clicked a valid link.
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- Payment Selection Modal Popup -->
<div id="paymentModal" class="payment-modal" style="display: <?php echo $show_modal ? 'flex' : 'none'; ?>;">
    <div class="modal-content">
        <button type="button" class="modal-close" onclick="closePaymentModal()">&times;</button>
        
        <div id="paymentSelectionArea">
            <h3>Select Payment Type</h3>
            <p>Choose how you would like to proceed with your payment.</p>
            <div class="modal-btns">
                <button onclick="showPaymentDesc('half')" class="btn-payment btn-half" style="border: none; cursor: pointer;">Half Payment</button>
                <button onclick="showPaymentDesc('full')" class="btn-payment btn-full" style="border: none; cursor: pointer;">Full Payment</button>
            </div>
        </div>

        <div id="paymentDescriptionArea" style="display: none; text-align: left;">
            <h3 id="paymentDescTitle">Payment Details</h3>
            <div id="paymentDescContent" style="margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 5px; font-size: 14px; max-height: 300px; overflow-y: auto;">
                <!-- Description will be loaded here -->
            </div>
            <div style="text-align: center; margin-top: 20px;">
                <a href="#" id="paymentOkBtn" class="btn-confirm" style="display: inline-block;">OK, Proceed</a>
            </div>
        </div>

    </div>
</div>

<script>
    var halfDesc = <?php echo json_encode($half_payment_desc); ?>;
    var fullDesc = <?php echo json_encode($full_payment_desc); ?>;
    var halfUrl = "half_payment_page.php?batch_id=<?php echo $batch_id_val; ?>&course_id=<?php echo $course_data['id'] ?? ''; ?>";
    var fullUrl = "full_payment_page.php?batch_id=<?php echo $batch_id_val; ?>&course_id=<?php echo $course_data['id'] ?? ''; ?>";

    function showPaymentDesc(type) {
        document.getElementById('paymentSelectionArea').style.display = 'none';
        document.getElementById('paymentDescriptionArea').style.display = 'block';
        
        if (type === 'half') {
            document.getElementById('paymentDescTitle').innerText = 'Half Payment Details';
            document.getElementById('paymentDescContent').innerHTML = halfDesc || "No details provided.";
            document.getElementById('paymentOkBtn').href = halfUrl;
        } else {
            document.getElementById('paymentDescTitle').innerText = 'Full Payment Details';
            document.getElementById('paymentDescContent').innerHTML = fullDesc || "No details provided.";
            document.getElementById('paymentOkBtn').href = fullUrl;
        }
    }

    function closePaymentModal() {
        document.getElementById('paymentModal').style.display = 'none';
        // Reset modal state after a short delay so the transition is hidden
        setTimeout(function() {
            document.getElementById('paymentSelectionArea').style.display = 'block';
            document.getElementById('paymentDescriptionArea').style.display = 'none';
        }, 300);
    }
</script>

<?php 
// include 'footer.php'; 
?>
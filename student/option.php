<?php
// Include your existing header and sidebar files
// Adjust the file paths if they are located in a different directory (e.g., '../header.php')
include 'header.php';
include 'sidebar.php';
?>

<!-- Main Content Area -->
<div class="main-content" style="padding: 20px; margin-left: 250px;">
    <h2>Select Payment Option</h2>
    <p>Please choose how you would like to proceed with your payment.</p>

    <div class="button-container" style="margin-top: 30px;">
        <!-- Half Payment Button -->
        <a href="half_payment.php" style="
            display: inline-block;
            padding: 12px 24px;
            margin-right: 15px;
            background-color: #ff9800; /* Orange */
            color: white;
            text-decoration: none;
            font-weight: bold;
            border-radius: 5px;
            text-align: center;
        ">Half Payment</a>

        <!-- Full Payment Button -->
        <a href="full_payment.php" style="
            display: inline-block;
            padding: 12px 24px;
            background-color: #4CAF50; /* Green */
            color: white;
            text-decoration: none;
            font-weight: bold;
            border-radius: 5px;
            text-align: center;
        ">Full Payment</a>
    </div>
</div>

<?php
// Include your footer if you have one
// include 'footer.php';
?>
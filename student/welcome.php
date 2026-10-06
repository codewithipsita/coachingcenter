<?php
session_start();

// PHP Cache Control
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Kick out if not logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ../index.php");
    exit();
}

include('../db.php');

$user_name = "Guest"; 

if (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id']; 
    
    $query = "SELECT `name` FROM `student_master` WHERE `id` = ?";
    $stmt = mysqli_prepare($conn, $query);
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $fetched_name);
        
        if (mysqli_stmt_fetch($stmt)) {
            $user_name = $fetched_name;
        }
        mysqli_stmt_close($stmt);
    }
}

// Include Layout
include('header.php');
include('sidebar.php');
?>

<!-- Include Bootstrap for Modal and Grid system to work -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<!-- Custom CSS for Animations and Hover Effects -->
<style>
    @keyframes slideDownFade {
        from {
            opacity: 0;
            transform: translateY(-40px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-top-down {
        animation: slideDownFade 0.6s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards;
        opacity: 0; 
    }

    .course-card-overlay {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border-radius: 15px;
        cursor: pointer;
        background: linear-gradient(135deg, #f4e8df 0%, #e8d5c4 100%);
        overflow: hidden;
        height: 100%;
        min-height: 220px;
    }

    .course-card-overlay:hover {
        transform: translateY(-6px) scale(1.01);
        box-shadow: 0 20px 35px rgba(0,0,0,0.2) !important;
    }

    .course-card-overlay:hover .bg-img {
        transform: scale(1.08) !important;
    }

    .card-title-custom {
        color: #4a3341;
        font-weight: 900;
        font-size: 1.5rem;
        line-height: 1.2;
        text-transform: uppercase;
        margin-bottom: 5px;
    }

    .card-subtitle-custom {
        color: #4a3341;
        font-family: Georgia, 'Times New Roman', Times, serif;
        font-style: italic;
        font-size: 1.05rem;
        margin-bottom: 20px;
    }

    .price-badge {
        background-color: #4a3341; 
        color: #ffffff;
        padding: 6px 14px;
        font-size: 1.15rem;
        font-weight: 800;
        border-radius: 6px;
        display: inline-block;
        box-shadow: 0 4px 10px rgba(74, 51, 65, 0.3);
    }
</style>

<!-- Main Content Area -->
<main class="main-content" style="padding: 20px;">
    
    <div class="d-flex justify-content-between align-items-center mb-4 animate-top-down">
        <h2 class="fw-bold m-0" style="color: #4a3341;">Welcome to the Dashboard, <?php echo htmlspecialchars($user_name); ?>!</h2>
    </div>

    <!-- Mark Attendance Button -->
    <div class="mb-4 animate-top-down">
        <a href="mark_attendance.php" class="btn btn-lg text-white fw-bold px-4 py-3" 
           style="background-color: #4a3341; border-radius: 12px; text-decoration: none; font-size: 16px; box-shadow: 0 4px 12px rgba(74,51,65,0.3);">
            &#128197; Mark Attendance
        </a>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4 animate-top-down">
        <h3 class="fw-bold m-0 fs-4" style="color: #4a3341;">All Available Courses</h3>
    </div>
    
    <div class="row">
        <?php
        $courses_query = mysqli_query($conn, "SELECT * FROM `courses` WHERE 1");
        
        if (mysqli_num_rows($courses_query) > 0) {
            $delay = 0; 
            
            while ($course = mysqli_fetch_assoc($courses_query)) {
                $capacity = $course['capacity'] > 0 ? $course['capacity'] : 1; 
                $active_batch_index = floor($course['total_enroled'] / $capacity);
                ?>
                <div class="col-lg-4 col-md-6 mb-4 animate-top-down" style="animation-delay: <?php echo $delay; ?>s;">
                    <div class="card border-0 shadow-sm course-card-overlay" data-bs-toggle="modal" data-bs-target="#courseModal<?php echo $course['id']; ?>" style="position: relative; height: 280px; border-radius: 15px; overflow: hidden; cursor: pointer; transition: transform 0.3s ease, box-shadow 0.3s ease;">
                        <img src="../admin/<?php echo !empty($course['course_image']) ? htmlspecialchars($course['course_image']) : 'uploads/courses/default-course.jpg'; ?>" 
                             alt="<?php echo htmlspecialchars($course['course_name']); ?>" style="width: 100%; height: 100%; object-fit: cover; position: absolute; top: 0; left: 0; z-index: 1; transition: transform 0.5s ease;" class="bg-img">
                        <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.1) 60%, rgba(0,0,0,0) 100%); z-index: 2;"></div>
                        <div class="card-body d-flex flex-column justify-content-end p-4" style="position: relative; z-index: 3; height: 100%;">
                            <h3 class="card-title-custom text-white mb-1" style="font-size: 1.6rem; font-weight: 800; text-shadow: 1px 1px 3px rgba(0,0,0,0.5);"><?php echo htmlspecialchars($course['course_name']); ?></h3>
                            <p class="card-subtitle-custom text-white-50 mb-3" style="font-family: inherit; font-style: normal; font-size: 0.95rem; text-shadow: 1px 1px 2px rgba(0,0,0,0.8);"><i class="bi bi-clock"></i> <?php echo htmlspecialchars($course['course_duration']); ?> duration</p>
                            <div class="mt-auto d-flex flex-column align-items-start gap-2">
                                <span class="price-badge" style="background-color: #ffc107; color: #000; font-size: 1.1rem; padding: 6px 16px; border-radius: 30px; font-weight: 800; box-shadow: 0 4px 10px rgba(0,0,0,0.3);">₹<?php echo htmlspecialchars($course['course_price']); ?></span>
                                <span class="badge" style="background-color: #0d6efd; color: #fff; font-size: 0.85rem; font-weight: 600; padding: 6px 12px; border-radius: 20px; letter-spacing: 0.5px; text-transform: uppercase; box-shadow: 0 4px 8px rgba(13,110,253,0.4); border: 1px solid #0a58ca;">View Details <i class="bi bi-arrow-right-circle-fill ms-1"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
                $delay += 0.1; 
            }
        } else {
            echo "<div class='col-12'><div class='alert alert-info text-center'>No courses found. Check back later!</div></div>";
        }
        ?>
    </div>
</main>

<!-- Render all Modals outside of the main layout -->
<?php
$courses_query_modal = mysqli_query($conn, "SELECT * FROM `courses` WHERE 1");
if (mysqli_num_rows($courses_query_modal) > 0) {
    while ($course = mysqli_fetch_assoc($courses_query_modal)) {
        $capacity = $course['capacity'] > 0 ? $course['capacity'] : 1; 
        $active_batch_index = floor($course['total_enroled'] / $capacity);
?>
    <div class="modal fade" id="courseModal<?php echo $course['id']; ?>" tabindex="-1" aria-labelledby="courseModalLabel<?php echo $course['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content" style="border-radius: 15px; border: none; overflow: hidden;">
                <div class="modal-header text-white" style="background-color: #4a3341;">
                    <h5 class="modal-title fw-bold" id="courseModalLabel<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['course_name']); ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0" style="background-color: #fcfdfd;">
                    <div style="background-color: #e9ecef; text-align: center; width: 100%; border-bottom: 1px solid #dee2e6;">
                        <img src="../admin/<?php echo !empty($course['course_image']) ? htmlspecialchars($course['course_image']) : 'uploads/courses/default-course.jpg'; ?>" 
                             style="max-width: 100%; max-height: 400px; height: auto; object-fit: contain; display: inline-block;" alt="Course Image">
                    </div>
                    
                    <div class="p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="fw-bold mb-0" style="color: #4a3341;">Price: ₹<?php echo htmlspecialchars($course['course_price']); ?></h4>
                            <span class="badge bg-info text-dark px-3 py-2 fs-6">Total Enrolled: <?php echo htmlspecialchars($course['total_enroled']); ?></span>
                        </div>
                        
                        <div class="row mb-3 text-muted">
                            <div class="col-sm-6">
                                <p class="mb-1"><i class="bi bi-clock"></i> <strong>Duration:</strong> <?php echo htmlspecialchars($course['course_duration']); ?></p>
                            </div>
                            <div class="col-sm-6">
                                <p class="mb-1"><i class="bi bi-people"></i> <strong>Batch Capacity:</strong> <?php echo htmlspecialchars($course['capacity']); ?></p>
                            </div>
                        </div>
                        
                        <hr>
                        <h5 class="fw-bold" style="color: #4a3341;">Course Details:</h5>
                        <div class="text-muted"><?php echo $course['course_details']; ?></div>
                        
                        <h5 class="mt-4 mb-3 fw-bold" style="color: #4a3341;">Available Batches</h5>
                        <div class="table-responsive bg-light rounded shadow-sm">
                            <table class="table table-hover mb-0 align-middle">
                                <thead style="background-color: #e8d5c4; color: #4a3341;">
                                    <tr>
                                        <th class="py-3">SN.</th>
                                        <th class="py-3">Batch Name</th>
                                        <th class="py-3">Created At</th>
                                        <th class="py-3">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // BUG FIX: Use prepared statement instead of raw string interpolation
                                    $batch_stmt = mysqli_prepare($conn, "SELECT id, batch_name, created_at FROM batches WHERE course_id = ? ORDER BY id ASC");
                                    if ($batch_stmt) {
                                        mysqli_stmt_bind_param($batch_stmt, "i", $course['id']);
                                        mysqli_stmt_execute($batch_stmt);
                                        $batches_query = mysqli_stmt_get_result($batch_stmt);
                                    } else {
                                        $batches_query = false;
                                    }

                                    if ($batches_query && mysqli_num_rows($batches_query) > 0) {
                                        $index = 0;
                                        while ($batch = mysqli_fetch_assoc($batches_query)) {
                                            echo "<tr>";
                                            echo "<td class='fw-bold'>" . ($index + 1) . "</td>";
                                            echo "<td>" . htmlspecialchars($batch['batch_name']) . "</td>";
                                            echo "<td>" . date('d M, Y', strtotime($batch['created_at'])) . "</td>";

                                            echo "<td>";
                                            if ($index < $active_batch_index) {
                                                echo "<button class='btn btn-danger btn-sm px-3 rounded-pill' disabled>Full</button>";
                                            } elseif ($index == $active_batch_index) {
                                                echo "<a href='enroll.php?course_id={$course['id']}&batch_id={$batch['id']}' class='btn btn-sm px-3 rounded-pill text-white shadow-sm' style='background-color: #4a3341;'>Enroll</a>";
                                            } else {
                                                echo "<button class='btn btn-secondary btn-sm px-3 rounded-pill' disabled>Waiting</button>";
                                            }
                                            echo "</td>";
                                            echo "</tr>";
                                            $index++;
                                        }
                                        if ($batch_stmt) mysqli_stmt_close($batch_stmt);
                                    } else {
                                        echo "<tr><td colspan='4' class='text-center py-4 text-muted'>No batches available.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background-color: #f8f9fa;">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
<?php
    }
}
?>

</div> <!-- closes .layout-wrapper from sidebar.php -->
</body>
</html>
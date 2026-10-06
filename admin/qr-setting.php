<?php
// Include database connection
include '../db.php';

$message = "";
$msg_type = "";

// Handle Form Submission (Both from new upload or selecting existing gallery path)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $selected_image_path = "";

    // Check if an image was picked from the server gallery
    if (!empty($_POST['selected_gallery_image'])) {
        $selected_image_path = $_POST['selected_gallery_image'];
    } 
    // Check if a new file was uploaded via file input
    elseif (isset($_FILES['qr_image']) && $_FILES['qr_image']['error'] == 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0755, true);
        }
        $file_name = time() . "_" . basename($_FILES["qr_image"]["name"]);
        $target_file = $target_dir . $file_name;
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
        
        $check = getimagesize($_FILES["qr_image"]["tmp_name"]);
        if ($check !== false) {
            $allowed_types = array("jpg", "jpeg", "png", "webp");
            if (in_array($imageFileType, $allowed_types)) {
                if (move_uploaded_file($_FILES["qr_image"]["tmp_name"], $target_file)) {
                    $selected_image_path = $target_file;
                } else {
                    $message = "Error uploading your file.";
                    $msg_type = "danger";
                }
            } else {
                $message = "Only JPG, JPEG, PNG & WEBP files are allowed.";
                $msg_type = "danger";
            }
        } else {
            $message = "File is not a valid image.";
            $msg_type = "danger";
        }
    }

    // Save/Update path in database if we have a valid image path
    if (!empty($selected_image_path)) {
        $check_query = "SELECT id FROM qr_settings LIMIT 1";
        $result = mysqli_query($conn, $check_query);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $id = $row['id'];
            $update = "UPDATE qr_settings SET qr_image = '$selected_image_path' WHERE id = $id";
            mysqli_query($conn, $update);
        } else {
            $insert = "INSERT INTO qr_settings (qr_image) VALUES ('$selected_image_path')";
            mysqli_query($conn, $insert);
        }
        $message = "QR Code updated successfully!";
        $msg_type = "success";
    }
}

// Fetch current QR Code configuration
$qr_query = "SELECT qr_image FROM qr_settings LIMIT 1";
$qr_result = mysqli_query($conn, $qr_query);
$current_qr = ($qr_result && mysqli_num_rows($qr_result) > 0) ? mysqli_fetch_assoc($qr_result)['qr_image'] : '';

// Function to recursively get all images from uploads directory and its child folders
function getGalleryImages($dir = 'uploads') {
    $images = array();
    if (is_dir($dir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
                if (in_array($ext, array('jpg', 'jpeg', 'png', 'webp'))) {
                    $images[] = str_replace('\\', '/', $file->getPathname());
                }
            }
        }
    }
    return $images;
}
$gallery_images = getGalleryImages('uploads');
?>

<?php include 'header.php'; ?>
<?php include 'sidebar.php'; ?>

<!-- Bootstrap 5 CSS & FontAwesome CDN -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    .card-custom {
        border: none;
        border-radius: 16px;
        box-shadow: 0 0.5rem 1.5rem rgba(18, 38, 63, 0.05);
        background: #ffffff;
        transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
        width: 100%;
        max-width: 100%;
    }
    .card-custom:hover {
        transform: translateY(-10px);
        box-shadow: 0 1rem 2.5rem rgba(18, 38, 63, 0.15);
    }
    .qr-preview-box {
        width: 200px;
        height: 200px;
        border: 2px dashed #cbd5e1;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        background: #f8fafc;
        margin: 0 auto;
    }
    .qr-preview-box img {
        width: 100%;
        height: 100%;
        object-fit: contain;
    }
    .upload-trigger-btn {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        color: #1e293b;
        padding: 12px 20px;
        border-radius: 10px;
        font-weight: 500;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        width: 100%;
        justify-content: space-between;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .upload-trigger-btn:hover {
        border-color: #3b82f6;
        background: #f8fafc;
    }
    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
        gap: 12px;
        max-height: 380px;
        overflow-y: auto;
        padding: 5px;
    }
    .gallery-item {
        position: relative;
        height: 110px;
        border-radius: 10px;
        overflow: hidden;
        border: 2px solid #e2e8f0;
        cursor: pointer;
        transition: all 0.2s ease;
        background: #fff;
    }
    .gallery-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .gallery-item input[type="radio"] {
        position: absolute;
        top: 8px;
        left: 8px;
        accent-color: #2563eb;
        transform: scale(1.2);
        z-index: 2;
    }
    .gallery-item.selected {
        border-color: #2563eb;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
    }
</style>

<!-- Main Content Area -->
<div class="main-content">
    <div class="container-fluid px-0">
                
                <!-- Page Title Header -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h2 class="fw-bold text-dark mb-1">QR Code Settings</h2>
                        <p class="text-muted mb-0">Seamlessly manage, select from gallery, or upload new platform QR codes.</p>
                    </div>
                </div>

                <!-- Feedback Alert Message -->
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show shadow-sm border-0" role="alert">
                        <i class="fas fa-info-circle me-2"></i> <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Settings Card Container -->
                <div class="row">
                    <div class="col-12">
                        <div class="card card-custom p-4 p-md-5">
                            <form action="" method="POST" enctype="multipart/form-data" id="qrForm">
                                
                                <!-- Current QR View -->
                                <div class="mb-4 text-center">
                                    <label class="form-label fw-semibold text-secondary d-block mb-3">Active Display QR Code</label>
                                    <div class="qr-preview-box shadow-sm">
                                        <?php if (!empty($current_qr) && file_exists($current_qr)): ?>
                                            <img src="<?php echo $current_qr; ?>" alt="Current QR Code" id="activePreviewImg">
                                        <?php else: ?>
                                            <span class="text-muted small" id="noQrText">
                                                <i class="fas fa-qrcode fa-2x mb-2 d-block text-black-50"></i>No QR Uploaded
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Interactive Professional Upload Trigger Field -->
                                <div class="mb-4">
                                    <label class="form-label fw-semibold text-dark mb-2">Select QR Source</label>
                                    <div>
                                        <button type="button" class="upload-trigger-btn" data-bs-toggle="modal" data-bs-target="#sourceSelectModal">
                                            <span class="d-flex align-items-center gap-2">
                                                <i class="fas fa-folder-open text-primary"></i> 
                                                <span id="selectedSourceLabel" class="text-secondary">Choose File or Select from Gallery</span>
                                            </span>
                                            <i class="fas fa-chevron-right text-muted small"></i>
                                        </button>
                                    </div>
                                    <input type="hidden" name="selected_gallery_image" id="selectedGalleryInput">
                                    <div class="form-text mt-2 text-muted">Recommended dimension: 500x500px. Supports PNG, JPG, WEBP.</div>
                                </div>

                                <!-- Submit Button -->
                                <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                                    <button type="submit" class="btn btn-primary px-4 py-2 fw-semibold shadow-sm">
                                        <i class="fas fa-save me-2"></i> Save Changes
                                    </button>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    <!-- Choice Selection Modal (Popup) -->
    <div class="modal fade" id="sourceSelectModal" tabindex="-1" aria-labelledby="sourceSelectModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="sourceSelectModalLabel">Choose Upload Method</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <p class="text-muted mb-4">How would you like to provide your QR code image?</p>
                    <div class="d-flex justify-content-center gap-3">
                        <button type="button" class="btn btn-outline-primary px-4 py-3 fw-semibold rounded-3 flex-fill" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#galleryModal">
                            <i class="fas fa-images fa-lg d-block mb-2"></i> Choose from Gallery
                        </button>
                        <button type="button" class="btn btn-primary px-4 py-3 fw-semibold rounded-3 flex-fill" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#uploadNewModal">
                            <i class="fas fa-cloud-upload-alt fa-lg d-block mb-2"></i> Upload New File
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Gallery Selection Modal -->
    <div class="modal fade" id="galleryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold"><i class="fas fa-photo-film me-2 text-primary"></i> Select from Uploads Gallery</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <?php if (!empty($gallery_images)): ?>
                        <p class="text-muted small mb-3">Select an image found in your uploads repository or subfolders:</p>
                        <div class="gallery-grid">
                            <?php foreach ($gallery_images as $img): ?>
                                <div class="gallery-item" onclick="selectGalleryImage('<?php echo $img; ?>', this)">
                                    <input type="radio" name="gallery_radio" value="<?php echo $img; ?>">
                                    <img src="<?php echo $img; ?>" alt="Gallery Image">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-folder-open fa-3x mb-3 text-black-50"></i>
                            <p class="mb-0">No images found in the 'uploads' directory or its subfolders.</p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer border-top bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary px-4" id="confirmGalleryBtn" data-bs-dismiss="modal" disabled>Confirm Selection</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload New File Modal -->
    <div class="modal fade" id="uploadNewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold"><i class="fas fa-upload me-2 text-primary"></i> Upload New QR Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="modal_qr_image" class="form-label fw-semibold">Choose file from device</label>
                        <input class="form-control" type="file" id="modal_qr_image" name="qr_image" accept="image/*" form="qrForm">
                        <div class="form-text mt-2">Select a clear QR code image from your computer or phone.</div>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light rounded-bottom-4">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary px-4" id="confirmUploadBtn" data-bs-dismiss="modal" disabled>Attach File</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedPath = "";

        function selectGalleryImage(path, element) {
            document.querySelectorAll('.gallery-item').forEach(item => item.classList.remove('selected'));
            element.classList.add('selected');
            
            let radio = element.querySelector('input[type="radio"]');
            radio.checked = true;
            
            selectedPath = path;
            document.getElementById('confirmGalleryBtn').removeAttribute('disabled');
        }

        document.getElementById('confirmGalleryBtn').addEventListener('click', function() {
            if (selectedPath) {
                document.getElementById('selectedGalleryInput').value = selectedPath;
                document.getElementById('modal_qr_image').value = '';
                
                let fileName = selectedPath.split('/').pop();
                document.getElementById('selectedSourceLabel').innerText = "Gallery: " + fileName;
                document.getElementById('selectedSourceLabel').classList.remove('text-secondary');
                document.getElementById('selectedSourceLabel').classList.add('text-dark', 'fw-medium');
                
                let previewBox = document.querySelector('.qr-preview-box');
                previewBox.innerHTML = `<img src="${selectedPath}" alt="Selected QR Code">`;
            }
        });

        const fileInput = document.getElementById('modal_qr_image');
        const confirmUploadBtn = document.getElementById('confirmUploadBtn');

        fileInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                confirmUploadBtn.removeAttribute('disabled');
            } else {
                confirmUploadBtn.setAttribute('disabled', 'true');
            }
        });

        confirmUploadBtn.addEventListener('click', function() {
            if (fileInput.files && fileInput.files[0]) {
                let fileName = fileInput.files[0].name;
                document.getElementById('selectedGalleryInput').value = '';
                
                document.getElementById('selectedSourceLabel').innerText = "File: " + fileName;
                document.getElementById('selectedSourceLabel').classList.remove('text-secondary');
                document.getElementById('selectedSourceLabel').classList.add('text-dark', 'fw-medium');
                
                let reader = new FileReader();
                reader.onload = function(e) {
                    let previewBox = document.querySelector('.qr-preview-box');
                    previewBox.innerHTML = `<img src="${e.target.result}" alt="New QR Preview">`;
                }
                reader.readAsDataURL(fileInput.files[0]);
            }
        });
    </script>
    </div> <!-- close .layout-wrapper from sidebar.php -->
</body>
</html>
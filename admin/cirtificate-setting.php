<?php
session_start();
include('../db.php'); 

$message = "";

// Ensure uploads directory exists
$target_dir = "uploads/";
if (!is_dir($target_dir)) {
    mkdir($target_dir, 0777, true);
}

// Function to get all images from a directory and its subdirectories
function getGalleryImages($dir) {
    $images = [];
    if (is_dir($dir)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $images[] = str_replace('\\', '/', $file->getPathname());
                }
            }
        }
    }
    return $images;
}

$gallery_images = getGalleryImages($target_dir);

// Handle Form Submissions (Upload & Delete)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- DELETE LOGIC ---
    if (isset($_POST['delete_id'])) {
        $delete_id = mysqli_real_escape_string($conn, $_POST['delete_id']);
        
        $get_path_sql = "SELECT image_path FROM certificate_backgrounds WHERE id = '$delete_id'";
        $result = mysqli_query($conn, $get_path_sql);
        
        if ($row = mysqli_fetch_assoc($result)) {
            $file_to_delete = $row['image_path'];
            
            $delete_sql = "DELETE FROM certificate_backgrounds WHERE id = '$delete_id'";
            if (mysqli_query($conn, $delete_sql)) {
                if (file_exists($file_to_delete) && strpos($file_to_delete, 'uploads/') === 0) {
                    unlink($file_to_delete);
                }
                $message = "Certificate background deleted successfully!";
            } else {
                $message = "Error deleting from database: " . mysqli_error($conn);
            }
        }
    } 
    // --- UPLOAD LOGIC ---
    else {
        $final_image_path = "";
        
        if (isset($_FILES['new_image']) && $_FILES['new_image']['error'] == 0) {
            $file_extension = pathinfo($_FILES["new_image"]["name"], PATHINFO_EXTENSION);
            $new_file_name = time() . "_" . uniqid() . "." . $file_extension;
            $target_file = $target_dir . $new_file_name;
            
            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array(strtolower($file_extension), $allowed_types)) {
                if (move_uploaded_file($_FILES["new_image"]["tmp_name"], $target_file)) {
                    $final_image_path = $target_file;
                } else {
                    $message = "Error uploading new image.";
                }
            } else {
                $message = "Only JPG, JPEG, PNG, and GIF files are allowed.";
            }
        } 
        elseif (!empty($_POST['gallery_image'])) {
            $final_image_path = mysqli_real_escape_string($conn, $_POST['gallery_image']);
        } else {
            $message = "Please select or upload a background image.";
        }

        if (empty($message) && !empty($final_image_path)) {
            $sql = "INSERT INTO certificate_backgrounds (image_path) VALUES ('$final_image_path')";
            if (mysqli_query($conn, $sql)) {
                $message = "Certificate background saved successfully!";
            } else {
                $message = "Error saving to database: " . mysqli_error($conn);
            }
        }
    }
}

// Fetch all saved backgrounds
$saved_backgrounds = [];
$fetch_sql = "SELECT id, image_path, created_at FROM certificate_backgrounds ORDER BY created_at DESC";
$fetch_result = mysqli_query($conn, $fetch_sql);
if ($fetch_result) {
    while ($row = mysqli_fetch_assoc($fetch_result)) {
        $saved_backgrounds[] = $row;
    }
}

include('header.php');
include('sidebar.php');
?>

<style>
    @keyframes fadeInUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .content-area {
        padding: 40px 20px;
        font-family: 'Poppins', sans-serif;
        flex-grow: 1; 
        overflow-x: hidden;
    }

    /* --- MAIN UPLOAD CARD --- */
    .cert-card { 
        background: #ffffff; 
        max-width: 650px; 
        margin: 0 auto; 
        padding: 40px; 
        border-radius: 12px; 
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08); 
        border: 1px solid #e5e7eb;
        animation: fadeInUp 0.5s ease-out forwards;
    }

    .cert-card h2 { 
        text-align: center; 
        font-size: 1.5rem; 
        font-weight: 600; 
        color: #111827; 
        margin-bottom: 30px; 
    }

    .image-selection-wrapper {
        border: 2px dashed #d1d5db;
        border-radius: 8px;
        padding: 20px;
        text-align: center;
        margin-bottom: 20px;
        background: #f9fafb;
    }
    
    .image-preview {
        max-width: 100%;
        max-height: 250px;
        display: none;
        margin: 10px auto 20px auto;
        border-radius: 8px;
        border: 2px solid #3b82f6;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }

    .choose-btn {
        padding: 10px 20px;
        background: #f3f4f6;
        color: #4b5563;
        border-radius: 6px;
        font-size: 0.9rem;
        font-weight: 500;
        cursor: pointer;
        border: 1px solid #d1d5db;
        transition: all 0.2s;
    }
    .choose-btn:hover { background: #e5e7eb; color: #111827; }

    .submit-btn { 
        width: 100%; 
        padding: 12px; 
        background: #3b82f6; 
        border: none; 
        border-radius: 8px; 
        color: #fff; 
        font-size: 1rem; 
        font-weight: 600; 
        cursor: pointer; 
        transition: background 0.3s; 
    }
    .submit-btn:hover { background: #2563eb; }

    /* --- SAVED BACKGROUNDS LIST (INSIDE MAIN CARD) --- */
    .saved-backgrounds-container {
        margin-top: 40px;
        padding-top: 25px;
        border-top: 1px solid #e5e7eb;
    }
    .saved-backgrounds-container h3 {
        font-size: 1.1rem;
        color: #374151;
        margin-bottom: 15px;
        font-weight: 600;
    }
    .bg-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .bg-list-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f9fafb;
        padding: 12px 18px;
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        transition: background 0.2s, border-color 0.2s;
    }
    .bg-list-item:hover {
        background: #fff;
        border-color: #d1d5db;
        box-shadow: 0 2px 5px rgba(0,0,0,0.02);
    }
    .bg-name {
        font-weight: 500;
        color: #4b5563;
        font-size: 0.95rem;
    }
    .action-buttons-list {
        display: flex;
        gap: 8px;
    }
    .action-buttons-list button {
        padding: 6px 12px;
        border: none;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        color: #fff;
        transition: background 0.2s;
    }
    .btn-preview { background: #10b981; }
    .btn-preview:hover { background: #059669; }
    
    .btn-delete { background: #ef4444; }
    .btn-delete:hover { background: #dc2626; }

    /* --- MODALS --- */
    .modal-overlay {
        display: none; 
        position: fixed; 
        z-index: 9999; 
        left: 0; top: 0; 
        width: 100%; height: 100%; 
        background-color: rgba(0,0,0,0.5);
    }
    .modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 80%;
        max-width: 700px;
        border-radius: 12px;
    }
    .modal-header {
        display: flex; justify-content: space-between; align-items: center;
        border-bottom: 1px solid #eee; padding-bottom: 10px; margin-bottom: 20px;
    }
    .close-modal { cursor: pointer; font-size: 1.5rem; font-weight: bold; color: #aaa; }
    .close-modal:hover { color: #000; }
    .modal-tabs { display: flex; gap: 10px; margin-bottom: 20px; }
    .tab-btn {
        padding: 8px 16px; border: none; background: #e5e7eb; cursor: pointer;
        border-radius: 6px; font-weight: 600; color: #4b5563;
    }
    .tab-btn.active { background: #3b82f6; color: #fff; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    
    .gallery-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 15px;
        max-height: 400px; overflow-y: auto; padding: 10px;
    }
    .gallery-item {
        width: 100%; height: 120px; object-fit: cover; border-radius: 8px;
        cursor: pointer; border: 2px solid transparent; transition: border 0.2s;
    }
    .gallery-item.selected { border-color: #3b82f6; box-shadow: 0 0 10px rgba(59,130,246,0.5); }
    
    .upload-box { text-align: center; padding: 40px; border: 2px dashed #d1d5db; border-radius: 8px; }

    /* --- PROFESSIONAL CARD PREVIEW MODAL (UPDATED FOR SIZING) --- */
    #cardPreviewModal {
        display: none; 
        position: fixed;
        z-index: 10000;
        left: 0; top: 0;
        width: 100%; height: 100%;
        background-color: rgba(0,0,0,0.6);
        align-items: center;
        justify-content: center;
    }
    .preview-card-inner {
        background: #ffffff;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.25);
        width: 90%;
        max-width: 650px; 
        position: relative;
        animation: fadeInUp 0.3s ease-out forwards;
        text-align: center; /* Centers the image inside the card */
    }
    .preview-card-inner h4 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #374151;
        font-size: 1.1rem;
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 10px;
        text-align: left;
    }
    .preview-card-inner img {
        max-width: 100%; 
        max-height: 400px; /* Forces the image to never exceed this height */
        object-fit: contain; /* Keeps the proportions accurate without stretching */
        border-radius: 4px;
        border: 1px solid #d1d5db;
        display: inline-block;
    }
    .close-card-preview {
        position: absolute;
        top: 20px;
        right: 25px;
        font-size: 24px;
        line-height: 1;
        color: #9ca3af;
        cursor: pointer;
        transition: color 0.2s;
    }
    .close-card-preview:hover { color: #111827; }
</style>

<main class="main-content">
<div class="content-area">
    
    <!-- Upload Section & Saved List -->
    <div class="cert-card">
        <h2>Set Certificate Background</h2>
        
        <form method="POST" action="" enctype="multipart/form-data" id="certForm">
            <!-- Image Selection Area -->
            <div class="image-selection-wrapper">
                <img id="main-preview" class="image-preview" src="" alt="Background Preview">
                <input type="hidden" name="gallery_image" id="gallery_image_input">
                <button type="button" class="choose-btn" onclick="openModal()"><i class="fa-solid fa-image"></i> Choose / Upload Background</button>
            </div>

            <button type="submit" class="submit-btn">Save Background</button>
            <div id="file-input-container" style="display:none;"></div>
        </form>

        <!-- Saved Backgrounds integrated inside the card -->
        <div class="saved-backgrounds-container">
            <h3>Saved Backgrounds</h3>
            
            <?php if (empty($saved_backgrounds)): ?>
                <p style="text-align:center; color:#6b7280; font-size: 0.9rem;">No certificate backgrounds have been saved yet.</p>
            <?php else: ?>
                <div class="bg-list">
                    <?php foreach ($saved_backgrounds as $bg): ?>
                        <div class="bg-list-item">
                            <span class="bg-name">
                                <i class="fa-solid fa-file-image" style="color: #9ca3af; margin-right: 8px;"></i>
                                Background #<?php echo $bg['id']; ?> 
                                <small style="color: #9ca3af; font-weight: normal; margin-left: 5px;">
                                    (<?php echo date('M d, Y', strtotime($bg['created_at'])); ?>)
                                </small>
                            </span>
                            
                            <div class="action-buttons-list">
                                <!-- Preview Button -->
                                <button type="button" class="btn-preview" onclick="openCardPreview('<?php echo htmlspecialchars($bg['image_path'], ENT_QUOTES, 'UTF-8'); ?>')">
                                    <i class="fa-solid fa-eye"></i> Preview
                                </button>
                                
                                <!-- Delete Button -->
                                <form method="POST" action="" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this background?');">
                                    <input type="hidden" name="delete_id" value="<?php echo $bg['id']; ?>">
                                    <button type="submit" class="btn-delete">
                                        <i class="fa-solid fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>
</main>

<!-- Modal for Image Selection -->
<div id="imageModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Select Background Image</h3>
            <span class="close-modal" onclick="closeModal()">&times;</span>
        </div>
        
        <div class="modal-tabs">
            <button class="tab-btn active" onclick="switchTab('gallery')">Choose from Gallery</button>
            <button class="tab-btn" onclick="switchTab('upload')">Upload New</button>
        </div>
        
        <div id="tab-gallery" class="tab-content active">
            <?php if (empty($gallery_images)): ?>
                <p style="text-align:center; color:#6b7280;">No images found in uploads folder.</p>
            <?php else: ?>
                <div class="gallery-grid">
                    <?php foreach ($gallery_images as $img): ?>
                        <img src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" class="gallery-item" onclick="selectGalleryImage(this, '<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>')">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div id="tab-upload" class="tab-content">
            <div class="upload-box">
                <label for="modal_file_input" style="cursor:pointer; display:block; padding:20px; background:#f3f4f6; border-radius:8px;">
                    <i class="fa-solid fa-upload" style="font-size:2rem; color:#9ca3af; margin-bottom:10px;"></i>
                    <br>Click to browse files
                </label>
                <input type="file" id="modal_file_input" accept="image/png, image/jpeg, image/jpg, image/gif" style="display:none;" onchange="handleNewUpload(this)">
            </div>
        </div>
    </div>
</div>

<!-- Professional Card Size Preview Modal -->
<div id="cardPreviewModal" onclick="closeCardPreview(event)">
    <div class="preview-card-inner" onclick="event.stopPropagation()">
        <span class="close-card-preview" onclick="closeCardPreview(event)">&times;</span>
        <h4>Certificate Layout Preview</h4>
        <img id="card-preview-image-tag" src="" alt="Certificate Preview">
    </div>
</div>

<?php if(!empty($message)): ?>
<script>
    alert("<?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>");
</script>
<?php endif; ?>

<script>
    const modal = document.getElementById('imageModal');
    const mainPreview = document.getElementById('main-preview');
    const galleryInput = document.getElementById('gallery_image_input');
    const fileContainer = document.getElementById('file-input-container');
    const cardPreviewModal = document.getElementById('cardPreviewModal');
    const cardPreviewImageTag = document.getElementById('card-preview-image-tag');

    function openModal() { modal.style.display = "block"; }
    function closeModal() { modal.style.display = "none"; }

    function switchTab(tabName) {
        document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.getElementById('tab-' + tabName).classList.add('active');
        event.target.classList.add('active');
    }

    function selectGalleryImage(element, imagePath) {
        document.querySelectorAll('.gallery-item').forEach(item => item.classList.remove('selected'));
        element.classList.add('selected');
        galleryInput.value = imagePath;
        mainPreview.src = imagePath;
        mainPreview.style.display = "block";
        fileContainer.innerHTML = '';
        closeModal();
    }

    function handleNewUpload(input) {
        if (input.files && input.files[0]) {
            let reader = new FileReader();
            reader.onload = function(e) {
                mainPreview.src = e.target.result;
                mainPreview.style.display = "block";
                galleryInput.value = '';
                document.querySelectorAll('.gallery-item').forEach(item => item.classList.remove('selected'));
                const clone = input.cloneNode(true);
                clone.name = "new_image"; 
                fileContainer.innerHTML = ''; 
                fileContainer.appendChild(clone);
                closeModal();
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    // --- Card Preview Functions ---
    function openCardPreview(imagePath) {
        cardPreviewImageTag.src = imagePath;
        cardPreviewModal.style.display = "flex"; 
    }

    function closeCardPreview(event) {
        if (event.target === cardPreviewModal || event.target.classList.contains('close-card-preview')) {
            cardPreviewModal.style.display = "none";
        }
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            closeModal();
        }
    }
</script>

</body>
</html>
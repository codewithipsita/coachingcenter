<?php
include('../db.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $query = "INSERT INTO payments (payment_type, description) VALUES ('Half', '$description')";
    if (mysqli_query($conn, $query)) {
        echo "<script>alert('Half payment description saved successfully!');</script>";
    }
}
?>

    <title>Half Payment</title>
    <script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>


     <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --bg-main: #f9fafb;
            --surface: #ffffff;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --border: #e5e7eb;
            --radius: 8px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-main);
            color: var(--text-main);
            margin: 0;
            padding: 0;
        }

        .main-content {
            max-width: 900px;
            margin: 40px auto;
            background: var(--surface);
            padding: 32px;
            border-radius: var(--radius);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }

        .main-content h2 {
            margin-top: 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 24px;
            border-bottom: 2px solid var(--border);
            padding-bottom: 12px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--text-main);
        }

        /* CKEditor 5 Professional Styling Adjustments */
        .ck-editor__editable_inline {
            min-height: 250px;
            border-bottom-left-radius: var(--radius) !important;
            border-bottom-right-radius: var(--radius) !important;
            border-color: var(--border) !important;
            padding: 0 16px !important;
        }
        
        .ck.ck-toolbar {
            border-top-left-radius: var(--radius) !important;
            border-top-right-radius: var(--radius) !important;
            border-color: var(--border) !important;
            background-color: #f3f4f6 !important;
        }

        .ck-editor__editable_inline:focus {
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
        }

        button[type="submit"] {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 12px 24px;
            font-size: 0.95rem;
            font-weight: 500;
            border-radius: var(--radius);
            cursor: pointer;
            transition: background-color 0.2s ease, box-shadow 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        button[type="submit"]:hover {
            background-color: var(--primary-hover);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
        }
    </style>
</head>
</head>
<body>
    <?php include('header.php'); ?>
    <?php include('sidebar.php'); ?>

    <div class="main-content">
        <h2>Half Payment Description</h2>
        <form action="" method="POST">
            <div class="form-group">
                <label for="description">Description:</label>
                <textarea name="description" id="editor_half"></textarea>
            </div>
            <button type="submit">Save Half Payment</button>
        </form>
    </div>

    <script>
        ClassicEditor
            .create(document.querySelector('#editor_half'))
            .catch(error => { console.error(error); });
    </script>
</body>
</html>
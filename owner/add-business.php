<?php
// header.php includes session_start() and database.php
include 'includes/header.php'; // This also includes $conn

// Access Control: Check if owner is logged in
if (!isset($_SESSION['owner_id'])) {
    header("Location: login.php");
    exit;
}

$owner_id = $_SESSION['owner_id'];
$errors = [];
$success_message = '';

// Initialize form field variables
$business_name = '';
$description = '';
$category_id_selected = '';
$subcategory_id_selected = '';
$contact = '';
$location = '';
$image_path_for_db = null; 
$posted_custom_fields = []; // To repopulate custom fields on error

// Fetch categories for the dropdown
$categories = [];
$category_sql = "SELECT id, name FROM categories ORDER BY name ASC";
$category_result = $conn->query($category_sql);
if ($category_result) {
    while ($row = $category_result->fetch_assoc()) {
        $categories[] = $row;
    }
} else {
    $errors['categories_load'] = "Error loading categories: " . htmlspecialchars($conn->error);
}


// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve and sanitize inputs
    $business_name = trim($_POST['business_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id_selected = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $subcategory_id_selected = filter_input(INPUT_POST, 'subcategory_id', FILTER_VALIDATE_INT);
    $contact = trim($_POST['contact'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $posted_custom_fields = $_POST['custom_field'] ?? [];


    // --- Validation ---
    if (empty($business_name)) {
        $errors['business_name'] = "Business name is required.";
    }
    if (!$category_id_selected) {
        $errors['category_id'] = "Please select a valid category.";
    }
    if (!$subcategory_id_selected) {
        $errors['subcategory_id'] = "Please select a valid subcategory.";
    }
    // Basic validation for custom fields (e.g. required if made so in admin panel - not implemented yet)
    // For now, we accept them as they are.

    // --- Image Upload Handling ---
    if (isset($_FILES['business_image']) && $_FILES['business_image']['error'] == UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../assets/uploads/businesses/'; 
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                 $errors['image_upload'] = "Upload directory does not exist and could not be created.";
            }
        }
        if (!is_writable($upload_dir) && !isset($errors['image_upload'])) {
             $errors['image_upload'] = "Upload directory is not writable.";
        }

        if (empty($errors['image_upload'])) { 
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_file_size = 5 * 1024 * 1024; // 5 MB

            $file_type = $_FILES['business_image']['type'];
            $file_size = $_FILES['business_image']['size'];
            $file_tmp_name = $_FILES['business_image']['tmp_name'];
            $file_name = $_FILES['business_image']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            if (!in_array($file_type, $allowed_types)) {
                $errors['image_upload'] = "Invalid file type. Only JPG, PNG, GIF, WEBP are allowed.";
            } elseif ($file_size > $max_file_size) {
                $errors['image_upload'] = "File size exceeds the limit of 5MB.";
            } else {
                $unique_filename = uniqid('biz_', true) . '.' . $file_ext;
                $destination_path = $upload_dir . $unique_filename;
                $image_path_for_db = 'assets/uploads/businesses/' . $unique_filename; 

                if (!move_uploaded_file($file_tmp_name, $destination_path)) {
                    $errors['image_upload'] = "Failed to move uploaded file. Check permissions.";
                    $image_path_for_db = null; 
                }
            }
        }
    } elseif (isset($_FILES['business_image']) && $_FILES['business_image']['error'] != UPLOAD_ERR_NO_FILE) {
        $errors['image_upload'] = "Error uploading image. Code: " . $_FILES['business_image']['error'];
    }


    // --- Database Insertion ---
    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $sql_insert_business = "INSERT INTO businesses (owner_id, category_id, subcategory_id, name, description, contact, image, location, status, created_at) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
            $stmt_insert = $conn->prepare($sql_insert_business);

            if (!$stmt_insert) {
                throw new Exception("Error preparing business insert statement: " . $conn->error);
            }
            $stmt_insert->bind_param("iiisssss", 
                $owner_id, $category_id_selected, $subcategory_id_selected, 
                $business_name, $description, $contact, 
                $image_path_for_db, $location
            );

            if (!$stmt_insert->execute()) {
                throw new Exception("Failed to add business: " . $stmt_insert->error);
            }
            $new_business_id = $stmt_insert->insert_id;
            $stmt_insert->close();

            // --- Save Custom Field Data ---
            if (!empty($posted_custom_fields) && is_array($posted_custom_fields) && $new_business_id) {
                $sql_insert_custom = "INSERT INTO custom_form_data (business_id, field_name, field_value) VALUES (?, ?, ?)";
                $stmt_custom = $conn->prepare($sql_insert_custom);
                if (!$stmt_custom) {
                     throw new Exception("Error preparing custom data insert statement: " . $conn->error);
                }

                foreach ($posted_custom_fields as $custom_field_id => $value) {
                    // Fetch field_name from custom_fields table based on $custom_field_id
                    $stmt_fetch_fname = $conn->prepare("SELECT field_name, field_type FROM custom_fields WHERE id = ? AND subcategory_id = ?");
                    if(!$stmt_fetch_fname) throw new Exception("Error preparing field name fetch: " . $conn->error);
                    $stmt_fetch_fname->bind_param("ii", $custom_field_id, $subcategory_id_selected);
                    $stmt_fetch_fname->execute();
                    $result_fname = $stmt_fetch_fname->get_result();
                    
                    if ($field_meta = $result_fname->fetch_assoc()) {
                        $actual_field_name = $field_meta['field_name'];
                        $actual_field_type = $field_meta['field_type'];
                        $field_value_to_save = is_array($value) ? implode(', ', $value) : trim($value);

                        // Handle checkbox: if not in POST, it means unchecked. Store '0' or empty.
                        // This logic might need refinement based on how checkbox data is expected.
                        // If the field type is checkbox and it's not in $posted_custom_fields, it means it was unchecked.
                        // However, the loop `foreach ($posted_custom_fields as ...)` will only iterate over submitted values.
                        // A more robust way for checkboxes is to check ALL defined checkboxes for the subcategory.
                        // For now, this handles submitted values. If a checkbox `custom_field[X]` is not in POST, it's not saved.
                        // If it IS in POST, its value ('1' usually) is saved.

                        if ($actual_field_type === 'checkbox' && !isset($posted_custom_fields[$custom_field_id])) {
                            // This specific condition is hard to meet here due to loop structure.
                            // It's better to iterate all defined fields for the subcategory later if this becomes an issue.
                            // For now, if a checkbox is submitted, its value is saved. If not, it's not.
                            // To store "0" for unchecked, you'd iterate defined fields and check presence in POST.
                            // For simplicity here, we only save what's submitted.
                            // If a checkbox is submitted, it will have a value (e.g. "1").
                        }
                        
                        $stmt_custom->bind_param("iss", $new_business_id, $actual_field_name, $field_value_to_save);
                        if (!$stmt_custom->execute()) {
                            throw new Exception("Failed to save custom field data for '$actual_field_name': " . $stmt_custom->error);
                        }
                    }
                    $stmt_fetch_fname->close();
                }
                $stmt_custom->close();
            }
            
            $conn->commit();
            $success_message = "Business added successfully! It is now pending approval by an administrator.";
            $business_name = $description = $contact = $location = '';
            $category_id_selected = $subcategory_id_selected = null;
            $posted_custom_fields = []; // Clear custom fields too

        } catch (Exception $e) {
            $conn->rollback();
            $errors['db'] = $e->getMessage();
            if ($image_path_for_db && file_exists($upload_dir . basename($image_path_for_db))) {
                unlink($upload_dir . basename($image_path_for_db));
            }
        }
    }
}
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-md-10">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h2 class="mb-0 text-center">Add New Business Listing</h2>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $success_message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <?php 
                        $displayable_errors = $errors; // Use a copy
                        if (!empty($displayable_errors)): 
                    ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <strong>Please correct the following errors:</strong><br>
                            <?php foreach ($displayable_errors as $error_key => $error_msg): ?>
                                - <?php echo htmlspecialchars($error_msg); ?><br>
                            <?php endforeach; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form action="add-business.php" method="POST" enctype="multipart/form-data" novalidate>
                        <div class="mb-3">
                            <label for="business_name" class="form-label">Business Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?php echo isset($errors['business_name']) ? 'is-invalid' : ''; ?>" id="business_name" name="business_name" value="<?php echo htmlspecialchars($business_name); ?>" required>
                            <?php if (isset($errors['business_name'])): ?><div class="invalid-feedback"><?php echo $errors['business_name']; ?></div><?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control <?php echo isset($errors['description']) ? 'is-invalid' : ''; ?>" id="description" name="description" rows="4"><?php echo htmlspecialchars($description); ?></textarea>
                            <?php if (isset($errors['description'])): ?><div class="invalid-feedback"><?php echo $errors['description']; ?></div><?php endif; ?>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                                <select class="form-select <?php echo isset($errors['category_id']) ? 'is-invalid' : ''; ?>" id="category_id" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php echo ($category_id_selected == $category['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['category_id'])): ?><div class="invalid-feedback"><?php echo $errors['category_id']; ?></div><?php endif; ?>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="subcategory_id" class="form-label">Subcategory <span class="text-danger">*</span></label>
                                <select class="form-select <?php echo isset($errors['subcategory_id']) ? 'is-invalid' : ''; ?>" id="subcategory_id" name="subcategory_id" required>
                                    <option value="">Select Category First</option>
                                </select>
                                <?php if (isset($errors['subcategory_id'])): ?><div class="invalid-feedback"><?php echo $errors['subcategory_id']; ?></div><?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Custom Fields Container -->
                        <div id="custom-fields-container" class="mb-3">
                            <!-- Custom fields will be loaded here by JavaScript -->
                        </div>

                        <div class="mb-3">
                            <label for="contact" class="form-label">Contact (Phone/Email)</label>
                            <input type="text" class="form-control <?php echo isset($errors['contact']) ? 'is-invalid' : ''; ?>" id="contact" name="contact" value="<?php echo htmlspecialchars($contact); ?>">
                            <?php if (isset($errors['contact'])): ?><div class="invalid-feedback"><?php echo $errors['contact']; ?></div><?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="location" class="form-label">Location / Address</label>
                            <input type="text" class="form-control <?php echo isset($errors['location']) ? 'is-invalid' : ''; ?>" id="location" name="location" value="<?php echo htmlspecialchars($location); ?>">
                            <?php if (isset($errors['location'])): ?><div class="invalid-feedback"><?php echo $errors['location']; ?></div><?php endif; ?>
                        </div>
                        
                        <div class="mb-4">
                            <label for="business_image" class="form-label">Business Image (Optional)</label>
                            <input class="form-control <?php echo isset($errors['image_upload']) ? 'is-invalid' : ''; ?>" type="file" id="business_image" name="business_image">
                            <div class="form-text">Max 5MB. Allowed types: JPG, PNG, GIF, WEBP.</div>
                            <?php if (isset($errors['image_upload'])): ?><div class="invalid-feedback d-block"><?php echo $errors['image_upload']; ?></div><?php endif; ?>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">Add Business</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const categorySelect = document.getElementById('category_id');
    const subcategorySelect = document.getElementById('subcategory_id');
    const customFieldsContainer = document.getElementById('custom-fields-container');
    const initialSubcategoryId = <?php echo json_encode($subcategory_id_selected ?? null); ?>;
    // Repopulate custom fields if there was a POST error
    const existingCustomFieldValues = <?php echo json_encode($posted_custom_fields ?? []); ?>;

    function fetchSubcategories(categoryId, selectedSubcategoryId = null) {
        customFieldsContainer.innerHTML = ''; // Clear custom fields when category changes
        if (!categoryId) {
            subcategorySelect.innerHTML = '<option value="">Select Category First</option>';
            subcategorySelect.disabled = true;
            return;
        }

        fetch('owner_ajax_get_subcategories.php?category_id=' + categoryId)
            .then(response => response.json())
            .then(data => {
                subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
                if (data.error) {
                    console.error(data.error);
                } else if (data.length === 0) {
                     subcategorySelect.innerHTML = '<option value="">No subcategories found</option>';
                } else {
                    data.forEach(subcategory => {
                        const option = document.createElement('option');
                        option.value = subcategory.id;
                        option.textContent = subcategory.name;
                        if (selectedSubcategoryId && parseInt(subcategory.id) === parseInt(selectedSubcategoryId)) {
                            option.selected = true;
                        }
                        subcategorySelect.appendChild(option);
                    });
                }
                subcategorySelect.disabled = false;
                // If a subcategory was pre-selected (e.g. form error), trigger custom field loading
                if (selectedSubcategoryId) {
                    fetchCustomFields(selectedSubcategoryId, existingCustomFieldValues);
                }
            })
            .catch(error => {
                console.error('Error fetching subcategories:', error);
                subcategorySelect.innerHTML = '<option value="">Error loading subcategories</option>';
                subcategorySelect.disabled = true;
            });
    }

    function fetchCustomFields(subcategoryId, existingValues = {}) {
        customFieldsContainer.innerHTML = ''; // Clear previous fields
        if (!subcategoryId) return;

        fetch('owner_ajax_get_custom_fields.php?subcategory_id=' + subcategoryId)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error(data.error);
                    customFieldsContainer.innerHTML = '<p class="text-danger">Error loading custom fields.</p>';
                } else if (data.length === 0) {
                    customFieldsContainer.innerHTML = '<p class="text-muted">No custom fields for this subcategory.</p>';
                } else {
                    data.forEach(field => {
                        const formGroup = document.createElement('div');
                        formGroup.classList.add('mb-3');

                        const label = document.createElement('label');
                        label.classList.add('form-label');
                        label.setAttribute('for', 'custom_field_' + field.id);
                        label.textContent = field.field_name;
                        formGroup.appendChild(label);

                        let inputElement;
                        const fieldName = 'custom_field[' + field.id + ']';
                        // Get existing value for this field, if any
                        const existingValue = existingValues[field.id] || '';


                        switch (field.field_type) {
                            case 'text':
                                inputElement = document.createElement('input');
                                inputElement.type = 'text';
                                inputElement.value = existingValue;
                                break;
                            case 'number':
                                inputElement = document.createElement('input');
                                inputElement.type = 'number';
                                inputElement.value = existingValue;
                                break;
                            case 'textarea':
                                inputElement = document.createElement('textarea');
                                inputElement.rows = 3;
                                inputElement.textContent = existingValue;
                                break;
                            case 'checkbox':
                                inputElement = document.createElement('input');
                                inputElement.type = 'checkbox';
                                inputElement.value = '1'; // Value when checked
                                if (existingValue === '1') {
                                    inputElement.checked = true;
                                }
                                // Checkbox needs a wrapper for proper label alignment or specific styling
                                const checkboxWrapper = document.createElement('div');
                                checkboxWrapper.classList.add('form-check');
                                inputElement.classList.add('form-check-input');
                                inputElement.id = 'custom_field_' + field.id; // Assign ID to input for label 'for'
                                label.classList.remove('form-label'); // Remove default label class
                                label.classList.add('form-check-label'); // Add form-check label class
                                
                                checkboxWrapper.appendChild(inputElement);
                                checkboxWrapper.appendChild(label); // Label after input for form-check
                                formGroup.innerHTML = ''; // Clear previous label
                                formGroup.appendChild(checkboxWrapper);
                                customFieldsContainer.appendChild(formGroup);
                                inputElement.name = fieldName; // Set name after potential wrapper modifications
                                continue; // Skip common element setup for checkbox
                            case 'select':
                                inputElement = document.createElement('select');
                                const defaultOption = document.createElement('option');
                                defaultOption.value = '';
                                defaultOption.textContent = 'Select ' + field.field_name;
                                inputElement.appendChild(defaultOption);
                                if (field.field_options) {
                                    field.field_options.split(',').forEach(opt => {
                                        const option = document.createElement('option');
                                        option.value = opt.trim();
                                        option.textContent = opt.trim();
                                        if (existingValue === opt.trim()) {
                                            option.selected = true;
                                        }
                                        inputElement.appendChild(option);
                                    });
                                }
                                break;
                            default:
                                inputElement = document.createElement('input');
                                inputElement.type = 'text'; // Fallback
                                inputElement.value = existingValue;
                        }
                        
                        inputElement.classList.add('form-control');
                        inputElement.id = 'custom_field_' + field.id;
                        inputElement.name = fieldName;
                        formGroup.appendChild(inputElement);
                        customFieldsContainer.appendChild(formGroup);
                    });
                }
            })
            .catch(error => {
                console.error('Error fetching custom fields:', error);
                customFieldsContainer.innerHTML = '<p class="text-danger">Error loading custom fields.</p>';
            });
    }

    categorySelect.addEventListener('change', function() {
        fetchSubcategories(this.value);
        customFieldsContainer.innerHTML = ''; // Clear on category change immediately
    });

    subcategorySelect.addEventListener('change', function() {
        fetchCustomFields(this.value, existingCustomFieldValues); // Pass existing values on subcat change too
    });

    // Initial load logic
    if (categorySelect.value) {
        fetchSubcategories(categorySelect.value, initialSubcategoryId);
    } else {
        subcategorySelect.disabled = true;
    }
});
</script>

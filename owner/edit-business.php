<?php
// header.php includes session_start() and database.php
include 'includes/header.php'; // This also includes $conn

// Access Control: Check if owner is logged in
if (!isset($_SESSION['owner_id'])) {
    header("Location: login.php");
    exit;
}

$owner_id = $_SESSION['owner_id'];
$business_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

$errors = [];
$success_message = ''; // Not used directly due to redirect, but kept for structure

// Initialize form field variables
$business_name = '';
$description = '';
$category_id_selected = '';
$subcategory_id_selected = ''; // This will be fetched from business_data
$contact = '';
$location = '';
$current_image_path = null;
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

// Verify business ID and ownership, then fetch data
if (!$business_id) {
    $errors['load'] = "Invalid business ID specified.";
} else {
    $stmt_fetch_biz = $conn->prepare("SELECT * FROM businesses WHERE id = ? AND owner_id = ?");
    if (!$stmt_fetch_biz) {
        $errors['load'] = "Error preparing to fetch business data: " . htmlspecialchars($conn->error);
    } else {
        $stmt_fetch_biz->bind_param("ii", $business_id, $owner_id);
        $stmt_fetch_biz->execute();
        $result_biz = $stmt_fetch_biz->get_result();
        if ($result_biz->num_rows === 1) {
            $business_data = $result_biz->fetch_assoc();
            $business_name = $business_data['name'];
            $description = $business_data['description'];
            $category_id_selected = $business_data['category_id'];
            $subcategory_id_selected = $business_data['subcategory_id']; // Crucial for JS
            $contact = $business_data['contact'];
            $location = $business_data['location'];
            $current_image_path = $business_data['image'];
        } else {
            $errors['load'] = "Business not found or you do not have permission to edit it.";
            $business_id = null; 
        }
        $stmt_fetch_biz->close();
    }
}


// Handle Form Submission for Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && $business_id && empty($errors['load'])) {
    $new_business_name = trim($_POST['business_name'] ?? '');
    $new_description = trim($_POST['description'] ?? '');
    $new_category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $new_subcategory_id = filter_input(INPUT_POST, 'subcategory_id', FILTER_VALIDATE_INT);
    $new_contact = trim($_POST['contact'] ?? '');
    $new_location = trim($_POST['location'] ?? '');
    $new_image_path_for_db = $current_image_path; 
    $posted_custom_fields = $_POST['custom_field'] ?? [];


    if (empty($new_business_name)) $errors['business_name'] = "Business name is required.";
    if (!$new_category_id) $errors['category_id'] = "Please select a valid category.";
    if (!$new_subcategory_id) { // Subcategory might change, ensure it's valid
        $errors['subcategory_id'] = "Please select a valid subcategory.";
    } else {
        // If subcategory changed, $subcategory_id_selected needs to be updated for custom field processing
        $subcategory_id_selected = $new_subcategory_id; 
    }


    if (isset($_FILES['business_image']) && $_FILES['business_image']['error'] == UPLOAD_ERR_OK) {
        $upload_dir_abs = __DIR__ . '/../assets/uploads/businesses/'; 
        if (!is_dir($upload_dir_abs)) {
            if (!mkdir($upload_dir_abs, 0755, true)) {
                 $errors['image_upload'] = "Upload directory does not exist and could not be created.";
            }
        }
        if (!is_writable($upload_dir_abs) && !isset($errors['image_upload'])) {
             $errors['image_upload'] = "Upload directory is not writable.";
        }

        if (empty($errors['image_upload'])) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_file_size = 5 * 1024 * 1024; 

            if (!in_array($_FILES['business_image']['type'], $allowed_types) || $_FILES['business_image']['size'] > $max_file_size) {
                $errors['image_upload'] = "Invalid file type or size (Max 5MB: JPG, PNG, GIF, WEBP).";
            } else {
                $file_ext = strtolower(pathinfo($_FILES['business_image']['name'], PATHINFO_EXTENSION));
                $unique_filename = uniqid('biz_', true) . '.' . $file_ext;
                $destination_path_abs = $upload_dir_abs . $unique_filename;
                
                if (move_uploaded_file($_FILES['business_image']['tmp_name'], $destination_path_abs)) {
                    if ($current_image_path && file_exists(__DIR__ . '/../' . $current_image_path)) {
                        unlink(__DIR__ . '/../' . $current_image_path);
                    }
                    $new_image_path_for_db = 'assets/uploads/businesses/' . $unique_filename; 
                } else {
                    $errors['image_upload'] = "Failed to move uploaded file.";
                }
            }
        }
    } elseif (isset($_FILES['business_image']) && $_FILES['business_image']['error'] != UPLOAD_ERR_NO_FILE) {
        $errors['image_upload'] = "Error uploading image. Code: " . $_FILES['business_image']['error'];
    }

    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            $sql_update_business = "UPDATE businesses SET 
                                    category_id = ?, subcategory_id = ?, name = ?, 
                                    description = ?, contact = ?, image = ?, location = ?, 
                                    status = 'pending' 
                                    WHERE id = ? AND owner_id = ?";
            $stmt_update = $conn->prepare($sql_update_business);

            if (!$stmt_update) throw new Exception("Error preparing business update: " . $conn->error);
            
            $stmt_update->bind_param("iisssssii", 
                $new_category_id, $new_subcategory_id, $new_business_name,
                $new_description, $new_contact, $new_image_path_for_db, $new_location,
                $business_id, $owner_id
            );

            if (!$stmt_update->execute()) throw new Exception("Failed to update business: " . $stmt_update->error);
            $stmt_update->close();

            // Delete existing custom form data
            $stmt_delete_custom = $conn->prepare("DELETE FROM custom_form_data WHERE business_id = ?");
            if (!$stmt_delete_custom) throw new Exception("Error preparing to delete old custom data: " . $conn->error);
            $stmt_delete_custom->bind_param("i", $business_id);
            if (!$stmt_delete_custom->execute()) throw new Exception("Failed to delete old custom data: " . $stmt_delete_custom->error);
            $stmt_delete_custom->close();

            // Insert new custom field data
            if (!empty($posted_custom_fields) && is_array($posted_custom_fields)) {
                $sql_insert_custom = "INSERT INTO custom_form_data (business_id, field_name, field_value) VALUES (?, ?, ?)";
                $stmt_custom_insert = $conn->prepare($sql_insert_custom);
                 if (!$stmt_custom_insert) throw new Exception("Error preparing custom data insert: " . $conn->error);

                foreach ($posted_custom_fields as $custom_field_id => $value) {
                    $stmt_fetch_fname = $conn->prepare("SELECT field_name FROM custom_fields WHERE id = ? AND subcategory_id = ?");
                    if(!$stmt_fetch_fname) throw new Exception("Error preparing field name fetch for update: " . $conn->error);
                    
                    // Use $new_subcategory_id if subcategory was changed, otherwise original $subcategory_id_selected
                    $current_processing_subcategory_id = $new_subcategory_id ? $new_subcategory_id : $business_data['subcategory_id'];

                    $stmt_fetch_fname->bind_param("ii", $custom_field_id, $current_processing_subcategory_id);
                    $stmt_fetch_fname->execute();
                    $result_fname = $stmt_fetch_fname->get_result();
                    
                    if ($field_meta = $result_fname->fetch_assoc()) {
                        $actual_field_name = $field_meta['field_name'];
                        $field_value_to_save = is_array($value) ? implode(', ', $value) : trim($value);
                        
                        $stmt_custom_insert->bind_param("iss", $business_id, $actual_field_name, $field_value_to_save);
                        if (!$stmt_custom_insert->execute()) {
                            throw new Exception("Failed to save custom field data for '$actual_field_name': " . $stmt_custom_insert->error);
                        }
                    }
                    $stmt_fetch_fname->close();
                }
                $stmt_custom_insert->close();
            }

            $conn->commit();
            header("Location: businesses.php?edit_success=1");
            exit;

        } catch (Exception $e) {
            $conn->rollback();
            $errors['db'] = $e->getMessage();
            // If image was newly uploaded in this failed transaction, attempt to delete it
            if ($new_image_path_for_db !== $current_image_path && file_exists(__DIR__ . '/../' . $new_image_path_for_db)) {
                unlink(__DIR__ . '/../' . $new_image_path_for_db);
            }
        }
    }
    // Repopulate form fields with new (but un-saved) values on error
    $business_name = $new_business_name;
    $description = $new_description;
    $category_id_selected = $new_category_id;
    // $subcategory_id_selected is already updated above if changed
    $contact = $new_contact;
    $location = $new_location;
    $current_image_path = $new_image_path_for_db; 
}
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-md-10">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h2 class="mb-0 text-center">Edit Business Listing</h2>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($errors['load'])): ?>
                        <div class="alert alert-danger">
                            <?php echo $errors['load']; ?>
                            <p><a href="businesses.php" class="alert-link">Return to My Businesses</a></p>
                        </div>
                    <?php elseif ($business_id): ?>
                        <?php 
                        $displayable_errors = array_filter($errors, function($key) {
                            return !in_array($key, ['load', 'categories_load']);
                        }, ARRAY_FILTER_USE_KEY);

                        if (!empty($displayable_errors)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <strong>Please correct the following errors:</strong><br>
                                <?php foreach ($displayable_errors as $error_msg): ?>
                                    - <?php echo htmlspecialchars($error_msg); ?><br>
                                <?php endforeach; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>
                    
                        <form action="edit-business.php?id=<?php echo $business_id; ?>" method="POST" enctype="multipart/form-data" novalidate>
                            <div class="mb-3">
                                <label for="business_name" class="form-label">Business Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php echo isset($errors['business_name']) ? 'is-invalid' : ''; ?>" id="business_name" name="business_name" value="<?php echo htmlspecialchars($business_name); ?>" required>
                                <?php if (isset($errors['business_name'])): ?><div class="invalid-feedback"><?php echo $errors['business_name']; ?></div><?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($description); ?></textarea>
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
                                <input type="text" class="form-control" id="contact" name="contact" value="<?php echo htmlspecialchars($contact); ?>">
                            </div>

                            <div class="mb-3">
                                <label for="location" class="form-label">Location / Address</label>
                                <input type="text" class="form-control" id="location" name="location" value="<?php echo htmlspecialchars($location); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label for="business_image" class="form-label">Business Image</label>
                                <?php if ($current_image_path): ?>
                                    <div class="mb-2">
                                        <img src="../<?php echo htmlspecialchars($current_image_path); ?>" alt="Current Business Image" style="max-height: 150px; border-radius: 0.25rem;">
                                        <p class="form-text mb-0">Current image. Upload a new image below to replace it.</p>
                                    </div>
                                <?php else: ?>
                                     <p class="form-text mb-0">No current image. Upload one below.</p>
                                <?php endif; ?>
                                <input class="form-control <?php echo isset($errors['image_upload']) ? 'is-invalid' : ''; ?>" type="file" id="business_image" name="business_image">
                                <div class="form-text">Max 5MB. Allowed types: JPG, PNG, GIF, WEBP. Leave blank to keep current image.</div>
                                <?php if (isset($errors['image_upload'])): ?><div class="invalid-feedback d-block"><?php echo $errors['image_upload']; ?></div><?php endif; ?>
                            </div>

                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-primary btn-lg">Update Business</button>
                                <a href="businesses.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </form>
                    <?php endif; ?>
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
    
    const initialCategoryId = <?php echo json_encode($category_id_selected ?? null); ?>;
    const initialSubcategoryId = <?php echo json_encode($subcategory_id_selected ?? null); ?>;
    const businessIdForCustomData = <?php echo json_encode($business_id ?? null); ?>;
    // For repopulating custom fields if POST had errors
    const postedCustomFieldValues = <?php echo json_encode($posted_custom_fields ?? []); ?>;


    function fetchSubcategories(categoryId, selectedSubcategoryId = null, callback = null) {
        customFieldsContainer.innerHTML = ''; 
        if (!categoryId) {
            subcategorySelect.innerHTML = '<option value="">Select Category First</option>';
            subcategorySelect.disabled = true;
            if (callback) callback();
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
                if (callback) callback(); 
            })
            .catch(error => {
                console.error('Error fetching subcategories:', error);
                subcategorySelect.innerHTML = '<option value="">Error loading subcategories</option>';
                subcategorySelect.disabled = true;
                if (callback) callback();
            });
    }

    function fetchAndPopulateCustomFields(subcategoryId) {
        customFieldsContainer.innerHTML = '';
        if (!subcategoryId || !businessIdForCustomData) return;

        // First, get custom field definitions
        fetch('owner_ajax_get_custom_fields.php?subcategory_id=' + subcategoryId)
            .then(response => response.json())
            .then(definitions => {
                if (definitions.error) {
                    console.error(definitions.error);
                    customFieldsContainer.innerHTML = '<p class="text-danger">Error loading custom field definitions.</p>';
                    return;
                }
                if (definitions.length === 0) {
                    customFieldsContainer.innerHTML = '<p class="text-muted">No custom fields for this subcategory.</p>';
                    return;
                }

                // Then, get existing data for these fields for the current business
                fetch('owner_ajax_get_business_custom_data.php?business_id=' + businessIdForCustomData)
                    .then(response => response.json())
                    .then(existingData => {
                        if (existingData.error) {
                            console.error(existingData.error);
                            // Proceed to render fields anyway, just without pre-filled data
                        }
                        
                        // Use posted values if available (from form error), otherwise use fetched existingData
                        const valuesToUse = Object.keys(postedCustomFieldValues).length > 0 ? postedCustomFieldValues : existingData;

                        definitions.forEach(field => {
                            const formGroup = document.createElement('div');
                            formGroup.classList.add('mb-3');

                            const label = document.createElement('label');
                            label.classList.add('form-label');
                            label.setAttribute('for', 'custom_field_' + field.id);
                            label.textContent = field.field_name;
                            formGroup.appendChild(label);

                            let inputElement;
                            const fieldNameAttr = 'custom_field[' + field.id + ']';
                            // Value for this field: check POST errors first, then fetched existing data
                            const value = valuesToUse[field.id] || '';


                            switch (field.field_type) {
                                case 'text':
                                    inputElement = document.createElement('input');
                                    inputElement.type = 'text';
                                    inputElement.value = value;
                                    break;
                                case 'number':
                                    inputElement = document.createElement('input');
                                    inputElement.type = 'number';
                                    inputElement.value = value;
                                    break;
                                case 'textarea':
                                    inputElement = document.createElement('textarea');
                                    inputElement.rows = 3;
                                    inputElement.textContent = value;
                                    break;
                                case 'checkbox':
                                    inputElement = document.createElement('input');
                                    inputElement.type = 'checkbox';
                                    inputElement.value = '1'; 
                                    if (value === '1') {
                                        inputElement.checked = true;
                                    }
                                    const checkboxWrapper = document.createElement('div');
                                    checkboxWrapper.classList.add('form-check');
                                    inputElement.classList.add('form-check-input');
                                    inputElement.id = 'custom_field_' + field.id;
                                    label.classList.remove('form-label');
                                    label.classList.add('form-check-label');
                                    
                                    checkboxWrapper.appendChild(inputElement);
                                    checkboxWrapper.appendChild(label);
                                    formGroup.innerHTML = ''; 
                                    formGroup.appendChild(checkboxWrapper);
                                    customFieldsContainer.appendChild(formGroup);
                                    inputElement.name = fieldNameAttr;
                                    continue; 
                                case 'select':
                                    inputElement = document.createElement('select');
                                    const defaultOption = document.createElement('option');
                                    defaultOption.value = '';
                                    defaultOption.textContent = 'Select ' + field.field_name;
                                    inputElement.appendChild(defaultOption);
                                    if (field.field_options) {
                                        field.field_options.split(',').forEach(optText => {
                                            const opt = optText.trim();
                                            const option = document.createElement('option');
                                            option.value = opt;
                                            option.textContent = opt;
                                            if (value === opt) {
                                                option.selected = true;
                                            }
                                            inputElement.appendChild(option);
                                        });
                                    }
                                    break;
                                default:
                                    inputElement = document.createElement('input');
                                    inputElement.type = 'text'; 
                                    inputElement.value = value;
                            }
                            
                            inputElement.classList.add('form-control');
                            inputElement.id = 'custom_field_' + field.id;
                            inputElement.name = fieldNameAttr;
                            formGroup.appendChild(inputElement);
                            customFieldsContainer.appendChild(formGroup);
                        });
                    })
                    .catch(error => {
                         console.error('Error fetching existing custom data:', error);
                         // Still render fields from definitions, but without pre-filled data
                         definitions.forEach(field => { /* ... render empty fields ... */ });
                    });
            })
            .catch(error => {
                console.error('Error fetching custom field definitions:', error);
                customFieldsContainer.innerHTML = '<p class="text-danger">Error loading custom fields.</p>';
            });
    }

    categorySelect.addEventListener('change', function() {
        fetchSubcategories(this.value, null, function() {
            // After subcategories are loaded (or failed), if a subcategory IS selected, then load its fields
            if (subcategorySelect.value) {
                fetchAndPopulateCustomFields(subcategorySelect.value);
            } else {
                customFieldsContainer.innerHTML = ''; // Clear if no subcategory selected
            }
        });
    });

    subcategorySelect.addEventListener('change', function() {
        fetchAndPopulateCustomFields(this.value);
    });

    // Initial load for edit page
    if (initialCategoryId) {
        fetchSubcategories(initialCategoryId, initialSubcategoryId, function() {
            // This callback ensures that fetchAndPopulateCustomFields is called
            // only after subcategories (including the initial one) are loaded and selected.
            if (initialSubcategoryId) {
                fetchAndPopulateCustomFields(initialSubcategoryId);
            }
        });
    } else {
        subcategorySelect.disabled = true;
    }
});
</script>

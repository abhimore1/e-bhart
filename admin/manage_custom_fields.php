<?php
// header.php includes session_start() and database.php
include 'includes/header.php';

// Access Control: Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$errors = [];
$success_message = '';

$selected_subcategory_id = filter_input(INPUT_GET, 'subcategory_id', FILTER_VALIDATE_INT);

// Form state variables
$edit_mode = false;
$field_id_to_edit = null;
$field_name_value = '';
$field_type_value = '';
$field_options_value = '';

// --- Fetch Subcategories for Dropdown ---
$subcategories_for_select = [];
$sql_subcategories = "SELECT sc.id, sc.name AS subcategory_name, c.name AS category_name 
                      FROM subcategories sc 
                      JOIN categories c ON sc.category_id = c.id 
                      ORDER BY c.name ASC, sc.name ASC";
$result_subcategories = $conn->query($sql_subcategories);
if ($result_subcategories) {
    while ($row = $result_subcategories->fetch_assoc()) {
        $subcategories_for_select[] = $row;
    }
} else {
    $errors['load_subcategories'] = "Error fetching subcategories: " . htmlspecialchars($conn->error);
}


// --- Handle Add/Edit Custom Field (Submission) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_custom_field'])) {
    $posted_subcategory_id = filter_input(INPUT_POST, 'subcategory_id_for_field', FILTER_VALIDATE_INT);
    $field_name = trim($_POST['field_name'] ?? '');
    $field_type = trim($_POST['field_type'] ?? '');
    $field_options = trim($_POST['field_options'] ?? '');
    $custom_field_id_to_save = filter_input(INPUT_POST, 'custom_field_id', FILTER_VALIDATE_INT); 

    $selected_subcategory_id = $posted_subcategory_id; 

    if (!$posted_subcategory_id) {
        $errors['form'] = "Subcategory ID is missing from the form submission.";
    }
    if (empty($field_name)) {
        $errors['field_name'] = "Field name is required.";
    }
    if (empty($field_type)) {
        $errors['field_type'] = "Field type is required.";
    }
    if (in_array($field_type, ['select', 'radio']) && empty($field_options)) {
        $errors['field_options'] = "Field options are required for 'select' or 'radio' types (comma-separated).";
    }
    if (!in_array($field_type, ['text', 'number', 'textarea', 'checkbox', 'select', 'radio'])) { 
        $errors['field_type'] = "Invalid field type selected.";
    }

    if (empty($errors) && $posted_subcategory_id) {
        $sql_check_unique = "SELECT id FROM custom_fields WHERE field_name = ? AND subcategory_id = ?";
        $params_check_unique = [$field_name, $posted_subcategory_id];
        if ($custom_field_id_to_save) { 
            $sql_check_unique .= " AND id != ?";
            $params_check_unique[] = $custom_field_id_to_save;
        }
        $stmt_check_unique = $conn->prepare($sql_check_unique);
        if (!$stmt_check_unique) {
             $errors['db'] = "Error preparing uniqueness check: " . htmlspecialchars($conn->error);
        } else {
            $types_check = str_repeat('s', count($params_check_unique) -1 ) . 'i'; 
            if(count($params_check_unique) == 3) $types_check = 'sii'; else $types_check = 'si';

            $stmt_check_unique->bind_param($types_check, ...$params_check_unique);
            $stmt_check_unique->execute();
            if ($stmt_check_unique->get_result()->num_rows > 0) {
                $errors['field_name'] = "Field name already exists for this subcategory.";
            }
            $stmt_check_unique->close();
        }
    }

    if (empty($errors) && $posted_subcategory_id) {
        if ($custom_field_id_to_save) { 
            $sql_save = "UPDATE custom_fields SET field_name = ?, field_type = ?, field_options = ?, subcategory_id = ? WHERE id = ?";
            $stmt_save = $conn->prepare($sql_save);
            if (!$stmt_save) {
                $errors['db'] = "Error preparing update statement: " . htmlspecialchars($conn->error);
            } else {
                $stmt_save->bind_param("sssii", $field_name, $field_type, $field_options, $posted_subcategory_id, $custom_field_id_to_save);
                if ($stmt_save->execute()) {
                    $success_message = "Custom field updated successfully!";
                } else {
                    $errors['db'] = "Failed to update custom field: " . htmlspecialchars($stmt_save->error);
                }
                $stmt_save->close();
            }
        } else { 
            $sql_save = "INSERT INTO custom_fields (subcategory_id, field_name, field_type, field_options) VALUES (?, ?, ?, ?)";
            $stmt_save = $conn->prepare($sql_save);
             if (!$stmt_save) {
                $errors['db'] = "Error preparing insert statement: " . htmlspecialchars($conn->error);
            } else {
                $stmt_save->bind_param("isss", $posted_subcategory_id, $field_name, $field_type, $field_options);
                if ($stmt_save->execute()) {
                    $success_message = "Custom field added successfully!";
                } else {
                    $errors['db'] = "Failed to add custom field: " . htmlspecialchars($stmt_save->error);
                }
                $stmt_save->close();
            }
        }
        if(empty($errors['db']) && !$custom_field_id_to_save) {
             $field_name_value = $field_type_value = $field_options_value = '';
        } elseif(empty($errors['db']) && $custom_field_id_to_save){ 
            $edit_mode = false;
            $field_id_to_edit = null;
            $field_name_value = $field_type_value = $field_options_value = '';
        }

    }
    if(!empty($errors)){
        $edit_mode = (bool)$custom_field_id_to_save; 
        $field_id_to_edit = $custom_field_id_to_save;
        $field_name_value = $field_name;
        $field_type_value = $field_type;
        $field_options_value = $field_options;
    }
}


// --- Handle Delete Custom Field ---
if (isset($_GET['delete_field_id']) && $selected_subcategory_id) {
    $field_id_to_delete = filter_input(INPUT_GET, 'delete_field_id', FILTER_VALIDATE_INT);
    if ($field_id_to_delete) {
        $stmt_delete = $conn->prepare("DELETE FROM custom_fields WHERE id = ? AND subcategory_id = ?"); 
        if (!$stmt_delete) {
            $errors['db'] = "Error preparing delete statement: " . htmlspecialchars($conn->error);
        } else {
            $stmt_delete->bind_param("ii", $field_id_to_delete, $selected_subcategory_id);
            if ($stmt_delete->execute()) {
                $success_message = "Custom field deleted successfully!";
            } else {
                $errors['db'] = "Failed to delete custom field: " . htmlspecialchars($stmt_delete->error);
            }
            $stmt_delete->close();
        }
    }
}

// --- Handle Load Edit Form (via GET parameter 'edit_field_id') ---
if (isset($_GET['edit_field_id']) && $selected_subcategory_id && !($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_custom_field']))) {
    $field_id_to_edit_get = filter_input(INPUT_GET, 'edit_field_id', FILTER_VALIDATE_INT);
    if ($field_id_to_edit_get) {
        $stmt_fetch_edit = $conn->prepare("SELECT * FROM custom_fields WHERE id = ? AND subcategory_id = ?");
        if(!$stmt_fetch_edit){
            $errors['db'] = "Error preparing to fetch field for edit: " . htmlspecialchars($conn->error);
        } else {
            $stmt_fetch_edit->bind_param("ii", $field_id_to_edit_get, $selected_subcategory_id);
            $stmt_fetch_edit->execute();
            $result_edit = $stmt_fetch_edit->get_result();
            if ($result_edit->num_rows === 1) {
                $edit_mode = true;
                $field_data = $result_edit->fetch_assoc();
                $field_id_to_edit = $field_data['id'];
                $field_name_value = $field_data['field_name'];
                $field_type_value = $field_data['field_type'];
                $field_options_value = $field_data['field_options'];
            } else {
                $errors['db'] = "Custom field not found for this subcategory or ID is invalid.";
            }
            $stmt_fetch_edit->close();
        }
    }
}


// --- Fetch Existing Custom Fields for Selected Subcategory ---
$custom_fields_list = [];
if ($selected_subcategory_id && empty($errors['load_subcategories'])) {
    $sql_fields = "SELECT * FROM custom_fields WHERE subcategory_id = ? ORDER BY field_name ASC";
    $stmt_fields = $conn->prepare($sql_fields);
    if (!$stmt_fields) {
        $errors['load_fields'] = "Error preparing to fetch custom fields: " . htmlspecialchars($conn->error);
    } else {
        $stmt_fields->bind_param("i", $selected_subcategory_id);
        $stmt_fields->execute();
        $result_fields = $stmt_fields->get_result();
        while ($row = $result_fields->fetch_assoc()) {
            $custom_fields_list[] = $row;
        }
        $stmt_fields->close();
    }
}

?>

<div class="container mt-4">
    <h1 class="mb-4">Manage Custom Fields</h1>

    <!-- Display Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php 
        $all_errors_display = $errors; 
        if (!empty($all_errors_display)): 
    ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Please correct the following errors:</strong><br>
            <?php foreach ($all_errors_display as $error_key => $error_msg): ?>
                - <?php echo htmlspecialchars($error_msg); ?><br>
            <?php endforeach; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Subcategory Selection Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-header navbar-admin text-white"> {/* Changed card header class */}
            <h3 class="mb-0">Select Subcategory to Manage Fields</h3>
        </div>
        <div class="card-body">
            <form action="manage_custom_fields.php" method="GET" class="row g-3 align-items-center">
                <div class="col-md-8">
                    <label for="subcategory_id_select" class="visually-hidden">Subcategory:</label>
                    <select class="form-select form-select-lg" id="subcategory_id_select" name="subcategory_id" required>
                        <option value="">-- Select a Subcategory --</option>
                        <?php foreach ($subcategories_for_select as $sub): ?>
                            <option value="<?php echo $sub['id']; ?>" <?php echo ($selected_subcategory_id == $sub['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sub['category_name'] . ' &raquo; ' . $sub['subcategory_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100 btn-lg">Load Fields</button>
                </div>
            </form>
        </div>
    </div>


    <?php if ($selected_subcategory_id && empty($errors['load_subcategories'])): ?>
        <!-- Add/Edit Custom Field Form -->
        <div class="card shadow-sm mb-4">
            <div class="card-header <?php echo $edit_mode ? 'bg-secondary text-white' : 'navbar-admin text-white'; ?>"> {/* Changed card header class */}
                <h3 class="mb-0"><?php echo $edit_mode ? 'Edit Custom Field' : 'Add New Custom Field'; ?></h3>
            </div>
            <div class="card-body">
                <form action="manage_custom_fields.php?subcategory_id=<?php echo $selected_subcategory_id; ?>" method="POST">
                    <input type="hidden" name="subcategory_id_for_field" value="<?php echo htmlspecialchars($selected_subcategory_id); ?>">
                    <?php if ($edit_mode && $field_id_to_edit): ?>
                        <input type="hidden" name="custom_field_id" value="<?php echo htmlspecialchars($field_id_to_edit); ?>">
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="field_name" class="form-label">Field Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?php echo isset($errors['field_name']) ? 'is-invalid' : ''; ?>" id="field_name" name="field_name" value="<?php echo htmlspecialchars($field_name_value); ?>" required>
                            <?php if (isset($errors['field_name'])): ?><div class="invalid-feedback"><?php echo $errors['field_name']; ?></div><?php endif; ?>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="field_type" class="form-label">Field Type <span class="text-danger">*</span></label>
                            <select class="form-select <?php echo isset($errors['field_type']) ? 'is-invalid' : ''; ?>" id="field_type" name="field_type" required>
                                <option value="">Select Type</option>
                                <option value="text" <?php echo ($field_type_value == 'text') ? 'selected' : ''; ?>>Text (Single Line)</option>
                                <option value="number" <?php echo ($field_type_value == 'number') ? 'selected' : ''; ?>>Number</option>
                                <option value="textarea" <?php echo ($field_type_value == 'textarea') ? 'selected' : ''; ?>>Textarea (Multi-line)</option>
                                <option value="checkbox" <?php echo ($field_type_value == 'checkbox') ? 'selected' : ''; ?>>Checkbox</option>
                                <option value="select" <?php echo ($field_type_value == 'select') ? 'selected' : ''; ?>>Select (Dropdown)</option>
                            </select>
                            <?php if (isset($errors['field_type'])): ?><div class="invalid-feedback"><?php echo $errors['field_type']; ?></div><?php endif; ?>
                        </div>
                        
                        <div class="col-md-4 mb-3" id="field_options_container" style="<?php echo in_array($field_type_value, ['select', 'radio']) ? '' : 'display:none;'; ?>">
                            <label for="field_options" class="form-label">Field Options (comma-separated)</label>
                            <textarea class="form-control <?php echo isset($errors['field_options']) ? 'is-invalid' : ''; ?>" id="field_options" name="field_options" rows="2"><?php echo htmlspecialchars($field_options_value); ?></textarea>
                            <?php if (isset($errors['field_options'])): ?><div class="invalid-feedback"><?php echo $errors['field_options']; ?></div><?php endif; ?>
                        </div>
                    </div>
                    
                    <button type="submit" name="save_custom_field" class="btn btn-success"><?php echo $edit_mode ? 'Update Field' : 'Add Field'; ?></button>
                    <?php if ($edit_mode): ?>
                        <a href="manage_custom_fields.php?subcategory_id=<?php echo $selected_subcategory_id; ?>" class="btn btn-outline-secondary">Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- List Existing Custom Fields -->
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h3 class="mb-0">Existing Fields for "<?php 
                    $current_sub_name = '';
                    foreach($subcategories_for_select as $sub) { if($sub['id'] == $selected_subcategory_id) {$current_sub_name = $sub['category_name'] . ' &raquo; ' . $sub['subcategory_name']; break;}}
                    echo htmlspecialchars($current_sub_name);
                ?>"</h3>
            </div>
            <div class="card-body">
                <?php if (empty($custom_fields_list) && empty($errors['load_fields'])): ?>
                    <div class="alert alert-info">No custom fields defined for this subcategory yet.</div>
                <?php elseif (!empty($custom_fields_list)): ?>
                    <div class="table-responsive"> {/* Added table-responsive wrapper */}
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Field Name</th>
                                    <th>Field Type</th>
                                    <th>Options</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($custom_fields_list as $field): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($field['id']); ?></td>
                                        <td><?php echo htmlspecialchars($field['field_name']); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($field['field_type'])); ?></td>
                                        <td><?php echo !empty($field['field_options']) ? htmlspecialchars($field['field_options']) : 'N/A'; ?></td>
                                        <td class="text-center">
                                            <a href="manage_custom_fields.php?subcategory_id=<?php echo $selected_subcategory_id; ?>&edit_field_id=<?php echo $field['id']; ?>" class="btn btn-sm btn-outline-primary mb-1 mb-md-0" title="Edit Field">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </a>
                                            <a href="manage_custom_fields.php?subcategory_id=<?php echo $selected_subcategory_id; ?>&delete_field_id=<?php echo $field['id']; ?>" class="btn btn-sm btn-outline-danger ms-md-1" title="Delete Field" onclick="return confirm('Are you sure you want to delete the field \'<?php echo htmlspecialchars(addslashes($field['field_name'])); ?>\'? This might affect existing business listings using this field.');">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif(isset($_GET['subcategory_id']) && !empty($_GET['subcategory_id']) && !empty($errors['load_subcategories'])): ?>
        <div class="alert alert-warning">Could not load details for the selected subcategory. Please try selecting again.</div>
    <?php elseif(isset($_GET['subcategory_id']) && !empty($_GET['subcategory_id']) && empty($errors['load_subcategories'])): ?>
         <div class="alert alert-info">Please select a valid subcategory to manage its custom fields.</div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
<!-- Include Bootstrap Icons CSS if not already global -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

<script>
document.addEventListener('DOMContentLoaded', function() {
    const fieldTypeSelect = document.getElementById('field_type');
    const fieldOptionsContainer = document.getElementById('field_options_container');

    function toggleFieldOptions() {
        if (fieldTypeSelect && fieldOptionsContainer) {
            const selectedType = fieldTypeSelect.value;
            if (selectedType === 'select' || selectedType === 'radio') {
                fieldOptionsContainer.style.display = '';
            } else {
                fieldOptionsContainer.style.display = 'none';
            }
        }
    }

    if (fieldTypeSelect) {
        fieldTypeSelect.addEventListener('change', toggleFieldOptions);
        toggleFieldOptions(); 
    }
});
</script>

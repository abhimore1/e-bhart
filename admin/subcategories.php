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
$edit_mode = false;
$edit_subcategory_id = null;
$edit_subcategory_name = '';
$edit_parent_category_id = null;

// Fetch Parent Categories for dropdowns
$parent_categories = [];
$stmt_fetch_parents = $conn->prepare("SELECT id, name FROM categories ORDER BY name ASC");
if (!$stmt_fetch_parents) {
    $errors['db'] = "Error preparing to fetch parent categories: " . htmlspecialchars($conn->error);
} else {
    $stmt_fetch_parents->execute();
    $result_parents = $stmt_fetch_parents->get_result();
    while($row = $result_parents->fetch_assoc()){
        $parent_categories[] = $row;
    }
    $stmt_fetch_parents->close();
    if(empty($parent_categories) && $_SERVER["REQUEST_METHOD"] != "POST"){ 
         $errors['no_parent_categories'] = "No parent categories found. Please <a href='categories.php' class='alert-link'>add a parent category</a> first.";
    }
}


// --- Handle Add Subcategory ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_subcategory'])) {
    $subcategory_name = trim($_POST['subcategory_name'] ?? '');
    $parent_category_id = filter_input(INPUT_POST, 'parent_category_id', FILTER_VALIDATE_INT);

    if (empty($subcategory_name)) $errors['add_subcategory_name'] = "Subcategory name is required.";
    if (!$parent_category_id) $errors['add_parent_category_id'] = "A parent category must be selected.";

    if (empty($errors) && $parent_category_id) {
        $stmt_check = $conn->prepare("SELECT id FROM subcategories WHERE name = ? AND category_id = ?");
        if (!$stmt_check) {
            $errors['db'] = "Error preparing uniqueness check: " . htmlspecialchars($conn->error);
        } else {
            $stmt_check->bind_param("si", $subcategory_name, $parent_category_id);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                $errors['add_subcategory_name'] = "Subcategory name already exists under this parent category.";
            }
            $stmt_check->close();
        }
    }

    if (empty($errors) && $parent_category_id) {
        $stmt_insert = $conn->prepare("INSERT INTO subcategories (category_id, name) VALUES (?, ?)");
        if (!$stmt_insert) {
             $errors['db'] = "Error preparing insert statement: " . htmlspecialchars($conn->error);
        } else {
            $stmt_insert->bind_param("is", $parent_category_id, $subcategory_name);
            if ($stmt_insert->execute()) {
                $success_message = "Subcategory '" . htmlspecialchars($subcategory_name) . "' added successfully!";
            } else {
                $errors['db'] = "Failed to add subcategory: " . htmlspecialchars($stmt_insert->error);
            }
            $stmt_insert->close();
        }
    }
}

// --- Handle Edit Subcategory (Submission) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_subcategory'])) {
    $edit_subcategory_id_post = filter_input(INPUT_POST, 'edit_subcategory_id', FILTER_VALIDATE_INT);
    $new_subcategory_name = trim($_POST['edit_subcategory_name'] ?? '');
    $new_parent_category_id = filter_input(INPUT_POST, 'edit_parent_category_id', FILTER_VALIDATE_INT);

    if (empty($new_subcategory_name)) $errors['edit_subcategory_name'] = "Subcategory name cannot be empty.";
    if (!$new_parent_category_id) $errors['edit_parent_category_id'] = "A parent category must be selected for the subcategory.";
    if (!$edit_subcategory_id_post) $errors['db'] = "Invalid subcategory ID for edit.";

    if (empty($errors) && $new_parent_category_id && $edit_subcategory_id_post) {
        $stmt_check_edit = $conn->prepare("SELECT id FROM subcategories WHERE name = ? AND category_id = ? AND id != ?");
        if (!$stmt_check_edit) {
            $errors['db'] = "Error preparing edit uniqueness check: " . htmlspecialchars($conn->error);
        } else {
            $stmt_check_edit->bind_param("sii", $new_subcategory_name, $new_parent_category_id, $edit_subcategory_id_post);
            $stmt_check_edit->execute();
            if ($stmt_check_edit->get_result()->num_rows > 0) {
                $errors['edit_subcategory_name'] = "Another subcategory with this name already exists under the selected parent category.";
            }
            $stmt_check_edit->close();
        }
    }

    if (empty($errors) && $new_parent_category_id && $edit_subcategory_id_post) {
        $stmt_update = $conn->prepare("UPDATE subcategories SET name = ?, category_id = ? WHERE id = ?");
        if (!$stmt_update) {
            $errors['db'] = "Error preparing update statement: " . htmlspecialchars($conn->error);
        } else {
            $stmt_update->bind_param("sii", $new_subcategory_name, $new_parent_category_id, $edit_subcategory_id_post);
            if ($stmt_update->execute()) {
                $success_message = "Subcategory updated successfully to '" . htmlspecialchars($new_subcategory_name) . "'!";
            } else {
                $errors['db'] = "Failed to update subcategory: " . htmlspecialchars($stmt_update->error);
            }
            $stmt_update->close();
        }
    } else { 
        $edit_mode = true;
        $edit_subcategory_id = $edit_subcategory_id_post; 
        $edit_subcategory_name = $new_subcategory_name;   
        $edit_parent_category_id = $new_parent_category_id; 
    }
}

// --- Handle Load Edit Form (via GET) ---
if (isset($_GET['edit_id']) && !($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_subcategory']))) {
    $edit_id_get = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if ($edit_id_get) {
        $stmt_fetch_edit = $conn->prepare("SELECT name, category_id FROM subcategories WHERE id = ?");
        if (!$stmt_fetch_edit) {
            $errors['db'] = "Error preparing to fetch subcategory: " . htmlspecialchars($conn->error);
        } else {
            $stmt_fetch_edit->bind_param("i", $edit_id_get);
            $stmt_fetch_edit->execute();
            $result_fetch_edit = $stmt_fetch_edit->get_result();
            if ($result_fetch_edit->num_rows === 1) {
                $edit_mode = true;
                $edit_data = $result_fetch_edit->fetch_assoc();
                $edit_subcategory_id = $edit_id_get;
                $edit_subcategory_name = $edit_data['name'];
                $edit_parent_category_id = $edit_data['category_id'];
            } else {
                $errors['db'] = "Subcategory not found for editing.";
            }
            $stmt_fetch_edit->close();
        }
    }
}

// --- Handle Delete Subcategory ---
if (isset($_GET['delete_id'])) {
    $delete_id = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT);
    if ($delete_id) {
        $stmt_check_biz = $conn->prepare("SELECT COUNT(*) as biz_count FROM businesses WHERE subcategory_id = ?");
        if (!$stmt_check_biz) {
             $errors['db'] = "Error preparing business check: " . htmlspecialchars($conn->error);
        } else {
            $stmt_check_biz->bind_param("i", $delete_id);
            $stmt_check_biz->execute();
            $biz_count = $stmt_check_biz->get_result()->fetch_assoc()['biz_count'];
            $stmt_check_biz->close();

            if ($biz_count > 0) {
                $errors['delete'] = "Cannot delete subcategory. It has " . $biz_count . " businesses associated. Please delete or reassign them first.";
            } else {
                $stmt_delete = $conn->prepare("DELETE FROM subcategories WHERE id = ?");
                if (!$stmt_delete) {
                    $errors['db'] = "Error preparing delete statement: " . htmlspecialchars($conn->error);
                } else {
                    $stmt_delete->bind_param("i", $delete_id);
                    if ($stmt_delete->execute()) {
                        $success_message = "Subcategory deleted successfully!";
                    } else {
                        $errors['db'] = "Failed to delete subcategory: " . htmlspecialchars($stmt_delete->error);
                    }
                    $stmt_delete->close();
                }
            }
        }
    }
}

// --- Fetch All Subcategories for Display (with parent category name) ---
$subcategories_list = [];
$sql_list_sub = "SELECT sc.id, sc.name AS subcategory_name, c.name AS parent_category_name,
                    (SELECT COUNT(*) FROM businesses b WHERE b.subcategory_id = sc.id) as business_count
                 FROM subcategories sc
                 JOIN categories c ON sc.category_id = c.id
                 ORDER BY c.name ASC, sc.name ASC";
$result_list_sub = $conn->query($sql_list_sub);
if ($result_list_sub) {
    while ($row = $result_list_sub->fetch_assoc()) {
        $subcategories_list[] = $row;
    }
} else {
    $errors['db_fetch_list'] = "Error fetching subcategories list: " . htmlspecialchars($conn->error);
}

?>

<div class="container mt-4">
    <h1 class="mb-4">Manage Subcategories</h1>

    <!-- Display Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php
        $all_errors_display = [];
        if(isset($errors['db'])) $all_errors_display[] = $errors['db'];
        if(isset($errors['db_fetch_list'])) $all_errors_display[] = $errors['db_fetch_list'];
        if(isset($errors['delete'])) $all_errors_display[] = $errors['delete'];
        if(isset($errors['no_parent_categories'])) $all_errors_display[] = $errors['no_parent_categories'];

        if(!$edit_mode){ 
            if(isset($errors['add_subcategory_name'])) $all_errors_display[] = $errors['add_subcategory_name'];
            if(isset($errors['add_parent_category_id'])) $all_errors_display[] = $errors['add_parent_category_id'];
        } else { 
            if(isset($errors['edit_subcategory_name'])) $all_errors_display[] = $errors['edit_subcategory_name'];
            if(isset($errors['edit_parent_category_id'])) $all_errors_display[] = $errors['edit_parent_category_id'];
        }
        
        if (!empty($all_errors_display)): 
    ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Please correct the following errors:</strong><br>
            <?php foreach ($all_errors_display as $error_msg): ?>
                - <?php echo $error_msg; ?> <br>
            <?php endforeach; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>


    <!-- Add/Edit Subcategory Form -->
    <?php if (empty($parent_categories) && !$edit_mode && !isset($errors['no_parent_categories'])): ?>
         <div class="alert alert-warning">Cannot add subcategories because no parent categories exist. Please <a href="categories.php" class="alert-link">create a parent category</a> first.</div>
    <?php else: ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header <?php echo $edit_mode ? 'bg-secondary text-white' : 'navbar-admin text-white'; ?>"> {/* Changed card header class */}
            <h3 class="mb-0"><?php echo $edit_mode ? 'Edit Subcategory' : 'Add New Subcategory'; ?></h3>
        </div>
        <div class="card-body">
            <form action="subcategories.php<?php echo $edit_mode ? '?edit_id=' . htmlspecialchars($edit_subcategory_id) : ''; ?>" method="POST">
                <?php if ($edit_mode): ?>
                    <input type="hidden" name="edit_subcategory_id" value="<?php echo htmlspecialchars($edit_subcategory_id ?? ''); ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label for="subcategory_name" class="form-label">Subcategory Name</label>
                    <input type="text" class="form-control <?php echo (isset($errors['add_subcategory_name']) || isset($errors['edit_subcategory_name'])) ? 'is-invalid' : ''; ?>" 
                           id="subcategory_name" name="<?php echo $edit_mode ? 'edit_subcategory_name' : 'subcategory_name'; ?>" 
                           value="<?php echo htmlspecialchars($edit_mode ? $edit_subcategory_name : ($_POST['subcategory_name'] ?? '')); ?>" required>
                    <?php if (!$edit_mode && isset($errors['add_subcategory_name'])): ?><div class="invalid-feedback"><?php echo $errors['add_subcategory_name']; ?></div><?php endif; ?>
                    <?php if ($edit_mode && isset($errors['edit_subcategory_name'])): ?><div class="invalid-feedback"><?php echo $errors['edit_subcategory_name']; ?></div><?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="parent_category_id" class="form-label">Parent Category</label>
                    <select class="form-select <?php echo (isset($errors['add_parent_category_id']) || isset($errors['edit_parent_category_id'])) ? 'is-invalid' : ''; ?>" 
                            id="parent_category_id" name="<?php echo $edit_mode ? 'edit_parent_category_id' : 'parent_category_id'; ?>" required>
                        <option value="">Select Parent Category</option>
                        <?php foreach ($parent_categories as $p_category): ?>
                            <option value="<?php echo $p_category['id']; ?>" 
                                <?php 
                                    $selected_val = $edit_mode ? $edit_parent_category_id : ($_POST['parent_category_id'] ?? null);
                                    echo ($selected_val == $p_category['id']) ? 'selected' : ''; 
                                ?>>
                                <?php echo htmlspecialchars($p_category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$edit_mode && isset($errors['add_parent_category_id'])): ?><div class="invalid-feedback"><?php echo $errors['add_parent_category_id']; ?></div><?php endif; ?>
                    <?php if ($edit_mode && isset($errors['edit_parent_category_id'])): ?><div class="invalid-feedback"><?php echo $errors['edit_parent_category_id']; ?></div><?php endif; ?>
                </div>
                
                <?php if ($edit_mode): ?>
                    <button type="submit" name="edit_subcategory" class="btn btn-success">Update Subcategory</button>
                    <a href="subcategories.php" class="btn btn-outline-secondary">Cancel Edit</a>
                <?php else: ?>
                    <button type="submit" name="add_subcategory" class="btn btn-primary" <?php if(empty($parent_categories)) echo 'disabled';?>>Add Subcategory</button>
                <?php endif; ?>
            </form>
        </div>
    </div>
    <?php endif; ?>


    <!-- List Existing Subcategories -->
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h3 class="mb-0">Existing Subcategories</h3>
        </div>
        <div class="card-body">
            <?php if (empty($subcategories_list) && empty($errors['db_fetch_list'])): ?>
                <div class="alert alert-info">No subcategories found. Add one above!</div>
            <?php elseif (!empty($subcategories_list)): ?>
                <div class="table-responsive"> {/* Added table-responsive wrapper */}
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Subcategory Name</th>
                                <th>Parent Category</th>
                                <th class="text-center">Businesses</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subcategories_list as $subcategory): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($subcategory['id']); ?></td>
                                    <td><?php echo htmlspecialchars($subcategory['subcategory_name']); ?></td>
                                    <td><?php echo htmlspecialchars($subcategory['parent_category_name']); ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars($subcategory['business_count']); ?></td>
                                    <td class="text-center">
                                        <a href="subcategories.php?edit_id=<?php echo $subcategory['id']; ?>" class="btn btn-sm btn-outline-primary mb-1 mb-md-0" title="Edit Subcategory">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </a>
                                        <a href="subcategories.php?delete_id=<?php echo $subcategory['id']; ?>" class="btn btn-sm btn-outline-danger ms-md-1" title="Delete Subcategory" onclick="return confirm('Are you sure you want to delete the subcategory \'<?php echo htmlspecialchars(addslashes($subcategory['subcategory_name'])); ?>\'? This action cannot be undone if there are no businesses associated.');">
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
</div>

<?php include 'includes/footer.php'; ?>
<!-- Include Bootstrap Icons CSS if not already global -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

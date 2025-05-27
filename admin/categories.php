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
$edit_category_id = null;
$edit_category_name = '';

// --- Handle Add Category ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name'] ?? '');

    if (empty($category_name)) {
        $errors['add_category_name'] = "Category name is required.";
    } else {
        // Check for uniqueness
        $stmt_check = $conn->prepare("SELECT id FROM categories WHERE name = ?");
        if (!$stmt_check) {
            $errors['db'] = "Error preparing uniqueness check: " . htmlspecialchars($conn->error);
        } else {
            $stmt_check->bind_param("s", $category_name);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result();
            if ($result_check->num_rows > 0) {
                $errors['add_category_name'] = "Category name already exists.";
            }
            $stmt_check->close();
        }
    }

    if (empty($errors)) {
        $stmt_insert = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
        if (!$stmt_insert) {
            $errors['db'] = "Error preparing insert statement: " . htmlspecialchars($conn->error);
        } else {
            $stmt_insert->bind_param("s", $category_name);
            if ($stmt_insert->execute()) {
                $success_message = "Category '" . htmlspecialchars($category_name) . "' added successfully!";
            } else {
                $errors['db'] = "Failed to add category: " . htmlspecialchars($stmt_insert->error);
            }
            $stmt_insert->close();
        }
    }
}

// --- Handle Edit Category (Submission) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_category'])) {
    $edit_category_id = filter_input(INPUT_POST, 'edit_category_id', FILTER_VALIDATE_INT);
    $new_category_name = trim($_POST['edit_category_name'] ?? '');

    if (empty($new_category_name)) {
        $errors['edit_category_name'] = "Category name cannot be empty.";
    } elseif (!$edit_category_id) {
        $errors['db'] = "Invalid category ID for edit.";
    } else {
        // Check for uniqueness (against other categories)
        $stmt_check_edit = $conn->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
         if (!$stmt_check_edit) {
            $errors['db'] = "Error preparing uniqueness check for edit: " . htmlspecialchars($conn->error);
        } else {
            $stmt_check_edit->bind_param("si", $new_category_name, $edit_category_id);
            $stmt_check_edit->execute();
            $result_check_edit = $stmt_check_edit->get_result();
            if ($result_check_edit->num_rows > 0) {
                $errors['edit_category_name'] = "Another category with this name already exists.";
            }
            $stmt_check_edit->close();
        }
    }

    if (empty($errors)) {
        $stmt_update = $conn->prepare("UPDATE categories SET name = ? WHERE id = ?");
        if (!$stmt_update) {
            $errors['db'] = "Error preparing update statement: " . htmlspecialchars($conn->error);
        } else {
            $stmt_update->bind_param("si", $new_category_name, $edit_category_id);
            if ($stmt_update->execute()) {
                $success_message = "Category updated successfully to '" . htmlspecialchars($new_category_name) . "'!";
            } else {
                $errors['db'] = "Failed to update category: " . htmlspecialchars($stmt_update->error);
            }
            $stmt_update->close();
        }
    } else { 
        $edit_mode = true; 
        $edit_category_name = $new_category_name; 
    }
}


// --- Handle Load Edit Form (via GET) ---
// Check if NOT a POST request for 'edit_category' to prevent re-fetching on own POST error
if (isset($_GET['edit_id']) && !($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_category']))) { 
    $edit_category_id_get = filter_input(INPUT_GET, 'edit_id', FILTER_VALIDATE_INT);
    if ($edit_category_id_get) {
        $stmt_fetch_edit = $conn->prepare("SELECT name FROM categories WHERE id = ?");
        if (!$stmt_fetch_edit) {
            $errors['db'] = "Error preparing to fetch category for edit: " . htmlspecialchars($conn->error);
        } else {
            $stmt_fetch_edit->bind_param("i", $edit_category_id_get);
            $stmt_fetch_edit->execute();
            $result_fetch_edit = $stmt_fetch_edit->get_result();
            if ($result_fetch_edit->num_rows === 1) {
                $edit_mode = true;
                $edit_category_data = $result_fetch_edit->fetch_assoc();
                $edit_category_id = $edit_category_id_get; // Set for the form action and hidden field
                $edit_category_name = $edit_category_data['name']; // For form value
            } else {
                $errors['db'] = "Category not found for editing.";
            }
            $stmt_fetch_edit->close();
        }
    }
}

// --- Handle Delete Category ---
if (isset($_GET['delete_id'])) {
    $delete_id = filter_input(INPUT_GET, 'delete_id', FILTER_VALIDATE_INT);
    if ($delete_id) {
        // Check for associated subcategories
        $stmt_check_sub = $conn->prepare("SELECT COUNT(*) as sub_count FROM subcategories WHERE category_id = ?");
        if (!$stmt_check_sub) {
             $errors['db'] = "Error preparing subcategory check: " . htmlspecialchars($conn->error);
        } else {
            $stmt_check_sub->bind_param("i", $delete_id);
            $stmt_check_sub->execute();
            $result_sub_count = $stmt_check_sub->get_result()->fetch_assoc()['sub_count'];
            $stmt_check_sub->close();

            if ($result_sub_count > 0) {
                $errors['delete'] = "Cannot delete category. It has " . $result_sub_count . " subcategories. Please delete or reassign them first.";
            } else {
                // Proceed with deletion
                $stmt_delete = $conn->prepare("DELETE FROM categories WHERE id = ?");
                if (!$stmt_delete) {
                    $errors['db'] = "Error preparing delete statement: " . htmlspecialchars($conn->error);
                } else {
                    $stmt_delete->bind_param("i", $delete_id);
                    if ($stmt_delete->execute()) {
                        $success_message = "Category deleted successfully!";
                    } else {
                        $errors['db'] = "Failed to delete category: " . htmlspecialchars($stmt_delete->error);
                    }
                    $stmt_delete->close();
                }
            }
        }
    }
}


// --- Fetch All Categories for Display ---
$categories_list = [];
$sql_list = "SELECT c.id, c.name, 
                (SELECT COUNT(*) FROM subcategories sc WHERE sc.category_id = c.id) as subcategory_count,
                (SELECT COUNT(*) FROM businesses b JOIN subcategories s_on_b ON b.subcategory_id = s_on_b.id WHERE s_on_b.category_id = c.id) as business_count
             FROM categories c 
             ORDER BY c.name ASC";
$result_list = $conn->query($sql_list);
if ($result_list) {
    while ($row = $result_list->fetch_assoc()) {
        $categories_list[] = $row;
    }
} else {
    $errors['db_fetch_list'] = "Error fetching categories list: " . htmlspecialchars($conn->error);
}

?>

<div class="container mt-4">
    <h1 class="mb-4">Manage Categories</h1>

    <!-- Display Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php 
        $all_errors = [];
        if(isset($errors['db'])) $all_errors[] = $errors['db'];
        if(isset($errors['db_fetch_list'])) $all_errors[] = $errors['db_fetch_list'];
        if(isset($errors['delete'])) $all_errors[] = $errors['delete'];
        
        if ($edit_mode && isset($errors['edit_category_name'])) {
             $all_errors[] = $errors['edit_category_name'];
        } elseif (!$edit_mode && isset($errors['add_category_name'])) {
             $all_errors[] = $errors['add_category_name'];
        }


        if (!empty($all_errors)): 
    ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Please correct the following errors:</strong><br>
            <?php foreach ($all_errors as $error_msg): ?>
                - <?php echo $error_msg; ?><br>
            <?php endforeach; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>


    <!-- Add/Edit Category Form -->
    <div class="card shadow-sm mb-4">
        <div class="card-header <?php echo $edit_mode ? 'bg-secondary text-white' : 'navbar-admin text-white'; ?>"> {/* Changed card header class */}
            <h3 class="mb-0"><?php echo $edit_mode ? 'Edit Category' : 'Add New Category'; ?></h3>
        </div>
        <div class="card-body">
            <form action="categories.php<?php echo $edit_mode ? '?edit_id=' . htmlspecialchars($edit_category_id) : ''; ?>" method="POST">
                <?php if ($edit_mode): ?>
                    <input type="hidden" name="edit_category_id" value="<?php echo htmlspecialchars($edit_category_id); ?>">
                    <div class="mb-3">
                        <label for="edit_category_name" class="form-label">Category Name</label>
                        <input type="text" class="form-control <?php echo isset($errors['edit_category_name']) ? 'is-invalid' : ''; ?>" id="edit_category_name" name="edit_category_name" value="<?php echo htmlspecialchars($edit_category_name); ?>" required>
                        <?php if (isset($errors['edit_category_name'])): ?><div class="invalid-feedback"><?php echo $errors['edit_category_name']; ?></div><?php endif; ?>
                    </div>
                    <button type="submit" name="edit_category" class="btn btn-success">Update Category</button>
                    <a href="categories.php" class="btn btn-outline-secondary">Cancel Edit</a>
                <?php else: ?>
                    <div class="mb-3">
                        <label for="category_name" class="form-label">Category Name</label>
                        <input type="text" class="form-control <?php echo isset($errors['add_category_name']) ? 'is-invalid' : ''; ?>" id="category_name" name="category_name" value="<?php echo isset($_POST['category_name']) && isset($errors['add_category_name']) ? htmlspecialchars($_POST['category_name']) : ''; ?>" required>
                        <?php if (isset($errors['add_category_name'])): ?><div class="invalid-feedback"><?php echo $errors['add_category_name']; ?></div><?php endif; ?>
                    </div>
                    <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- List Existing Categories -->
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h3 class="mb-0">Existing Categories</h3>
        </div>
        <div class="card-body">
            <?php if (empty($categories_list) && empty($errors['db_fetch_list'])): ?>
                <div class="alert alert-info">No categories found. Add one above!</div>
            <?php elseif (!empty($categories_list)): ?>
                <div class="table-responsive"> {/* Added table-responsive wrapper */}
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Category Name</th>
                                <th class="text-center">Subcategories</th>
                                <th class="text-center">Businesses</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories_list as $category): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['id']); ?></td>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars($category['subcategory_count']); ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars($category['business_count']); ?></td>
                                    <td class="text-center">
                                        <a href="categories.php?edit_id=<?php echo $category['id']; ?>" class="btn btn-sm btn-outline-primary mb-1 mb-md-0" title="Edit Category">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </a>
                                        <a href="categories.php?delete_id=<?php echo $category['id']; ?>" class="btn btn-sm btn-outline-danger ms-md-1" title="Delete Category" onclick="return confirm('Are you sure you want to delete the category \'<?php echo htmlspecialchars(addslashes($category['name'])); ?>\'? This action cannot be undone if there are no subcategories.');">
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

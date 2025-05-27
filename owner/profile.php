<?php
// header.php includes session_start() and database.php
include 'includes/header.php'; // This already calls session_start() and includes database.php

// Access Control: Check if owner is logged in
if (!isset($_SESSION['owner_id'])) {
    header("Location: login.php");
    exit;
}

$owner_id = $_SESSION['owner_id'];
$errors = [];
$success_message = '';

// Initialize variables for form values
$current_name = '';
$current_email = '';
$current_profile_img = '';

// Fetch current owner data for the form
$stmt_fetch = $conn->prepare("SELECT name, email, profile_img FROM owners WHERE id = ?");
if (!$stmt_fetch) {
    $errors['db_fetch'] = "Error preparing to fetch data: " . htmlspecialchars($conn->error);
} else {
    $stmt_fetch->bind_param("i", $owner_id);
    $stmt_fetch->execute();
    $result = $stmt_fetch->get_result();
    if ($result->num_rows === 1) {
        $owner_data = $result->fetch_assoc();
        $current_name = $owner_data['name'];
        $current_email = $owner_data['email'];
        $current_profile_img = $owner_data['profile_img'];
    } else {
        $errors['db_fetch'] = "Could not retrieve your profile data. This could be due to an invalid session or if the account no longer exists. Please try logging out and back in.";
        // Optional: Destroy session and redirect if owner data is critical and not found
        // session_unset(); session_destroy();
        // header("Location: login.php?error=profile_data_missing");
        // exit;
    }
    $stmt_fetch->close();
}


// Handle Profile Update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_name = trim($_POST['name'] ?? '');
    $new_email = trim($_POST['email'] ?? '');
    $new_profile_img = trim($_POST['profile_img'] ?? ''); // Allow empty string

    // Validation
    if (empty($new_name)) {
        $errors['name'] = "Name is required.";
    }
    if (empty($new_email)) {
        $errors['email'] = "Email is required.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format.";
    }

    // If no basic validation errors, proceed with more checks
    if (empty($errors)) {
        // Check if email is being changed and if the new email already exists for another owner
        if (strtolower($new_email) !== strtolower($current_email)) {
            $email_check_stmt = $conn->prepare("SELECT id FROM owners WHERE email = ? AND id != ?");
            if(!$email_check_stmt) {
                 $errors['db'] = "Error preparing email check: " . htmlspecialchars($conn->error);
            } else {
                $email_check_stmt->bind_param("si", $new_email, $owner_id);
                $email_check_stmt->execute();
                $email_result = $email_check_stmt->get_result();
                if ($email_result->num_rows > 0) {
                    $errors['email'] = "This email address is already in use by another account.";
                }
                $email_check_stmt->close();
            }
        }

        // If no errors after email uniqueness check, proceed with update
        if (empty($errors)) {
            $update_stmt = $conn->prepare("UPDATE owners SET name = ?, email = ?, profile_img = ? WHERE id = ?");
            if(!$update_stmt){
                $errors['db'] = "Error preparing update: " . htmlspecialchars($conn->error);
            } else {
                $update_stmt->bind_param("sssi", $new_name, $new_email, $new_profile_img, $owner_id);
                if ($update_stmt->execute()) {
                    $success_message = "Profile updated successfully!";
                    
                    // Update session variables if changed
                    if ($_SESSION['owner_name'] !== $new_name) {
                         $_SESSION['owner_name'] = $new_name;
                    }
                    // Update session email if it's stored and used (it is, as per previous tasks)
                    if (isset($_SESSION['owner_email']) && $_SESSION['owner_email'] !== $new_email) {
                        $_SESSION['owner_email'] = $new_email;
                    }

                    // Refresh current data for the form to show updated values
                    $current_name = $new_name;
                    $current_email = $new_email;
                    $current_profile_img = $new_profile_img;
                } else {
                    $errors['db'] = "Failed to update profile. Please try again. Error: " . htmlspecialchars($update_stmt->error);
                }
                $update_stmt->close();
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-7 col-md-9">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white"> <!-- Changed to bg-dark for consistency with other owner panel pages -->
                <h3 class="card-title mb-0 text-center">Profile Settings</h3>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors['db_fetch'])): ?>
                    <div class="alert alert-danger"><?php echo $errors['db_fetch']; ?></div>
                <?php elseif (empty($owner_data) && empty($errors['db_fetch']) && $_SERVER["REQUEST_METHOD"] !== "POST") : // Show only if not a POST request that failed for other reasons ?>
                    <div class="alert alert-danger">Could not load profile data. Please try again later or contact support.</div>
                <?php else: ?>

                    <?php if (!empty($errors['db'])): ?>
                         <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $errors['db']; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <form action="profile.php" method="POST" novalidate>
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" id="name" name="name" value="<?php echo htmlspecialchars($current_name); ?>" required>
                            <?php if (isset($errors['name'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['name']; ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($current_email); ?>" required>
                             <div class="form-text text-muted small">If you change your email, you might need to re-verify it in the future (re-verification not yet implemented).</div>
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-4"> <!-- Increased margin bottom for spacing -->
                            <label for="profile_img" class="form-label">Profile Image URL (Optional)</label>
                            <input type="url" class="form-control <?php echo isset($errors['profile_img']) ? 'is-invalid' : ''; ?>" id="profile_img" name="profile_img" value="<?php echo htmlspecialchars($current_profile_img); ?>" placeholder="e.g., https://example.com/path/to/image.png">
                             <div class="form-text text-muted small">Enter the full URL of your profile image. Leave blank to remove or if you don't have one.</div>
                            <?php if (isset($errors['profile_img'])): // For future server-side validation of URL if any ?>
                                <div class="invalid-feedback"><?php echo $errors['profile_img']; ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">Update Profile</button>
                            <a href="dashboard.php" class="btn btn-outline-secondary">Cancel and Back to Dashboard</a>
                        </div>
                    </form>
                <?php endif; // End of check for $owner_data or $errors['db_fetch'] ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

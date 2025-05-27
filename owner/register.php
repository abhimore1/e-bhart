<?php
// No session_start() here, it's already in header.php, which will be included later.
// header.php will also include config/database.php making $conn available.

$errors = [];
$success_message = '';
$name = '';
$email = '';
$profile_img_url = ''; // Initialize profile_img_url

// This script block handles form submission. $conn is needed here.
// If header.php isn't included yet, $conn won't be set.
// We ensure $conn is available for THIS processing block.
// The actual HTML output including header.php comes later.
if (!isset($conn)) {
    // This path assumes register.php is in owner/ and config/ is one level up from owner's parent.
    // So, if project root is /app, then owner is /app/owner, config is /app/config
    require_once __DIR__ . '/../config/database.php';
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $profile_img_url = trim($_POST['profile_img'] ?? ''); // Get profile image URL

    // Validation
    if (empty($name)) {
        $errors['name'] = "Name is required.";
    }
    if (empty($email)) {
        $errors['email'] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format.";
    }
    if (empty($password)) {
        $errors['password'] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors['password'] = "Password must be at least 8 characters long.";
    }
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = "Passwords do not match.";
    }

    // If no validation errors, proceed
    if (empty($errors)) {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM owners WHERE email = ?");
        if (!$stmt) {
            $errors['db'] = "Database error (prepare select): " . htmlspecialchars($conn->error);
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $errors['email'] = "Email already registered. Please <a href='login.php'>login</a>.";
            } else {
                // Hash the password
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                if (!$hashed_password) {
                    $errors['db'] = "Error hashing password.";
                } else {
                    // Insert new owner
                    $insert_stmt = $conn->prepare("INSERT INTO owners (name, email, password, profile_img) VALUES (?, ?, ?, ?)");
                    if (!$insert_stmt) {
                        $errors['db'] = "Database error (prepare insert): " . htmlspecialchars($conn->error);
                    } else {
                        // Bind the profile_img_url, can be empty if not provided
                        $insert_stmt->bind_param("ssss", $name, $email, $hashed_password, $profile_img_url);
                        if ($insert_stmt->execute()) {
                            $success_message = "Registration successful! You can now <a href='login.php' class='alert-link'>login</a>.";
                            // Clear form fields on success
                            $name = '';
                            $email = '';
                            $profile_img_url = '';
                        } else {
                            $errors['db'] = "Registration failed. Please try again. Error: " . htmlspecialchars($insert_stmt->error);
                        }
                        $insert_stmt->close();
                    }
                }
            }
            $stmt->close();
        }
    }
}

// Now include the header. session_start() is in header.php.
// $conn (database connection) is also included via header.php.
include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-6 col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <h3 class="card-title mb-0 text-center">Owner Registration</h3>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success"><?php echo $success_message; ?></div>
                <?php endif; ?>

                <?php if (!empty($errors['db'])): ?>
                    <div class="alert alert-danger"><?php echo $errors['db']; ?></div>
                <?php endif; ?>

                <form action="register.php" method="POST" novalidate>
                    <div class="mb-3">
                        <label for="name" class="form-label">Full Name</label>
                        <input type="text" class="form-control <?php echo isset($errors['name']) ? 'is-invalid' : ''; ?>" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                        <?php if (isset($errors['name'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['name']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        <?php if (isset($errors['email'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" required>
                        <div class="form-text">Password must be at least 8 characters long.</div>
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" id="confirm_password" name="confirm_password" required>
                        <?php if (isset($errors['confirm_password'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label for="profile_img" class="form-label">Profile Image URL (Optional)</label>
                        <input type="url" class="form-control <?php echo isset($errors['profile_img']) ? 'is-invalid' : ''; ?>" id="profile_img" name="profile_img" value="<?php echo htmlspecialchars($profile_img_url); ?>" placeholder="https://example.com/image.png">
                        <?php if (isset($errors['profile_img'])): // Though not currently validated on server-side beyond trim ?>
                            <div class="invalid-feedback"><?php echo $errors['profile_img']; ?></div>
                        <?php endif; ?>
                        <div class="form-text">Enter the full URL of your profile image (e.g., from a hosting service).</div>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Register</button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center py-3">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </div>
    </div>
</div>

<?php 
include 'includes/footer.php'; 
// $conn might have been closed by footer.php if it was included there,
// or if header.php included it and footer.php closes it.
// For safety, only close if $conn is still a valid resource.
// However, the standard practice adopted is to open in header and close in footer for page scripts.
// Script-specific connections like this one (if $conn wasn't set before POST processing)
// should be managed carefully. The current header/footer setup should handle $conn from config/database.php.
?>

<?php
session_start(); // START SESSION AT THE VERY TOP!

// No session_start() here, it's in header.php
// header.php also includes config/database.php, making $conn available.

$errors = [];
$email_value = ''; // To repopulate email field on failed login attempt

// --- Initial Admin Setup ---
// This block should ideally run only once or be handled by a separate setup script.
// For this task, it's included here.
// Ensure $conn is available for this script block if header.php isn't included yet.
if (!isset($conn)) {
    require_once __DIR__ . '/../config/database.php'; // Adjusted path
}

$admin_email_to_check = 'admin@citydepository.com';
$stmt_check_admin = $conn->prepare("SELECT id FROM admins WHERE email = ?");
if (!$stmt_check_admin) {
    $errors['db_setup'] = "Error preparing admin check: " . htmlspecialchars($conn->error);
} else {
    $stmt_check_admin->bind_param("s", $admin_email_to_check);
    $stmt_check_admin->execute();
    $result_check_admin = $stmt_check_admin->get_result();
    if ($result_check_admin->num_rows === 0) {
        // Admin does not exist, create one
        $default_admin_pass = 'admin123';
        $hashed_password = password_hash($default_admin_pass, PASSWORD_DEFAULT);
        $default_admin_role = 'superadmin'; // or 'admin'

        if (!$hashed_password) {
            $errors['db_setup'] = "Error hashing default admin password.";
        } else {
            $stmt_insert_admin = $conn->prepare("INSERT INTO admins (email, password, role) VALUES (?, ?, ?)");
            if (!$stmt_insert_admin) {
                $errors['db_setup'] = "Error preparing admin insert: " . htmlspecialchars($conn->error);
            } else {
                $stmt_insert_admin->bind_param("sss", $admin_email_to_check, $hashed_password, $default_admin_role);
                if (!$stmt_insert_admin->execute()) {
                    $errors['db_setup'] = "Failed to create default admin: " . htmlspecialchars($stmt_insert_admin->error);
                }
                // Optionally, add a success message here if needed, though it's usually silent
                $stmt_insert_admin->close();
            }
        }
    }
    $stmt_check_admin->close();
}


// --- Login Handling ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '');
    $email_value = $email; // Repopulate email field

    // Validation
    if (empty($email)) {
        $errors['email'] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format.";
    }
    if (empty($password)) {
        $errors['password'] = "Password is required.";
    }

    if (empty($errors)) {
        // Fetch admin from database
        $stmt = $conn->prepare("SELECT id, email, password, role FROM admins WHERE email = ?");
        if (!$stmt) {
            $errors['db'] = "Database error (prepare login select): " . htmlspecialchars($conn->error);
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $admin = $result->fetch_assoc();
                // Verify password
                if (password_verify($password, $admin['password'])) {
                    // Password is correct. Session is started in header.php.
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_email'] = $admin['email'];
                    $_SESSION['admin_role'] = $admin['role'];

                    header("Location: dashboard.php");
                    exit; 
                } else {
                    $errors['credentials'] = "Invalid email or password.";
                }
            } else {
                $errors['credentials'] = "Invalid email or password.";
            }
            $stmt->close();
        }
    }
}

// Include header.php which starts the session and HTML output.
// The session_start() in header.php will not cause an error if one is already started.
include 'includes/header.php';
?>

<div class="row justify-content-center mt-5">
    <div class="col-lg-4 col-md-6">
        <div class="card shadow">
            <div class="card-header bg-danger text-white"> <!-- Changed to bg-danger -->
                <h3 class="card-title mb-0 text-center">Admin Panel Login</h3>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($errors['db_setup'])): ?>
                    <div class="alert alert-warning"><?php echo $errors['db_setup']; ?></div>
                <?php endif; ?>
                <?php if (!empty($errors['db'])): ?>
                    <div class="alert alert-danger"><?php echo $errors['db']; ?></div>
                <?php endif; ?>
                <?php if (!empty($errors['credentials'])): ?>
                    <div class="alert alert-danger"><?php echo $errors['credentials']; ?></div>
                <?php endif; ?>

                <form action="login.php" method="POST" novalidate>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email address</label>
                        <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo htmlspecialchars($email_value); ?>" required autofocus>
                        <?php if (isset($errors['email'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" id="password" name="password" required>
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-danger btn-lg">Login</button> <!-- Changed to btn-danger -->
                    </div>
                </form>
            </div>
            <div class="card-footer text-center py-3">
                <small class="text-muted">Access restricted to authorized personnel.</small>
            </div>
        </div>
    </div>
</div>

<?php 
include 'includes/footer.php'; 
// $conn would be closed in footer.php if it was opened in header.php
?>

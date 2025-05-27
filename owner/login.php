<?php
session_start(); // START SESSION AT THE VERY TOP!

// No session_start() here, it's in header.php which is included later.
// header.php also includes config/database.php, making $conn available.

$errors = [];
$email = '';

// This block handles form submission. $conn is needed here.
// If header.php isn't included yet, $conn won't be set.
// We ensure $conn is available for THIS processing block by including it directly if needed.
if (!isset($conn)) {
    // Path assumes login.php is in owner/ and config/ is one level up from owner's parent.
    require_once __DIR__ . '/../config/database.php';
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

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
        // Fetch owner from database
        $stmt = $conn->prepare("SELECT id, name, email, password FROM owners WHERE email = ?");
        if (!$stmt) {
            $errors['db'] = "Database error (prepare): " . htmlspecialchars($conn->error);
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $owner = $result->fetch_assoc();
                // Verify password
                if (password_verify($password, $owner['password'])) {
                    // Password is correct. Session is started in header.php.
                    // We can set session variables here.
                    $_SESSION['owner_id'] = $owner['id'];
                    $_SESSION['owner_name'] = $owner['name'];
                    $_SESSION['owner_email'] = $owner['email'];

                    // Redirect to dashboard
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
// $conn is also available via header.php's inclusion of database.php.
// The session_start() in header.php will not cause an error if one is already started.
include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-5 col-md-7">
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white">
                <h3 class="card-title mb-0 text-center">Owner Login</h3>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($errors['db'])): ?>
                    <div class="alert alert-danger"><?php echo $errors['db']; ?></div>
                <?php endif; ?>
                <?php if (!empty($errors['credentials'])): ?>
                    <div class="alert alert-danger"><?php echo $errors['credentials']; ?></div>
                <?php endif; ?>

                <form action="login.php" method="POST" novalidate>
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
                        <?php if (isset($errors['password'])): ?>
                            <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="rememberMe">
                        <label class="form-check-label" for="rememberMe">Remember me</label>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">Login</button>
                    </div>
                </form>
            </div>
            <div class="card-footer text-center py-3">
                Don't have an account? <a href="register.php">Register here</a>
            </div>
        </div>
    </div>
</div>

<?php 
include 'includes/footer.php'; 
// $conn is typically closed in footer.php if it was opened in header.php
?>

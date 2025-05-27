<?php
// header.php includes session_start() and database.php
include 'includes/header.php'; // This already calls session_start() and includes database.php

// Access Control: Check if owner is logged in
if (!isset($_SESSION['owner_id'])) {
    // Not logged in, redirect to login page
    header("Location: login.php");
    exit; // Stop further script execution
}

// Fetch owner's name from session to personalize the welcome message
$owner_name = $_SESSION['owner_name'] ?? 'Owner'; // Default to 'Owner' if name not in session

?>

<div class="container mt-4"> 
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h2 class="mb-0">Owner Dashboard</h2>
                </div>
                <div class="card-body">
                    <h3 class="card-title">Welcome, <?php echo htmlspecialchars($owner_name); ?>!</h3>
                    <p class="card-text">This is your central hub for managing your business listings and profile on City Depository.</p>
                    <hr>
                    <p>From here, you will be able to:</p>
                    <ul>
                        <li>View and manage your registered businesses.</li>
                        <li>Add new business listings.</li>
                        <li>Update details for your existing businesses.</li>
                        <li>Manage your account settings and profile information.</li>
                    </ul>
                    
                    <a href="businesses.php" class="btn btn-primary mt-3">Manage My Businesses</a>
                    <a href="add-business.php" class="btn btn-info mt-3 ms-2">Add New Business</a> 
                    <a href="profile.php" class="btn btn-secondary mt-3 ms-2">Profile Settings</a> 
                    <a href="logout.php" class="btn btn-danger mt-3 ms-2">Logout</a>
                </div>
                <div class="card-footer text-muted">
                    Logged in as: <?php echo htmlspecialchars($_SESSION['owner_email'] ?? 'N/A'); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-12">
            <h4>Quick Links</h4>
            <div class="list-group">
                <a href="businesses.php" class="list-group-item list-group-item-action">
                    <i class="bi bi-briefcase-fill"></i> View My Businesses
                </a>
                <a href="add-business.php" class="list-group-item list-group-item-action"> 
                    <i class="bi bi-plus-circle-fill"></i> Add New Business
                </a>
                <a href="profile.php" class="list-group-item list-group-item-action"> 
                    <i class="bi bi-gear-fill"></i> Account Settings
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<?php
// header.php includes session_start() and database.php
include 'includes/header.php'; // This already calls session_start() and includes database.php

// Access Control: Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit; // Stop further script execution
}

// Fetch admin's email from session for display
$admin_display_email = $_SESSION['admin_email'] ?? 'Admin';

// Fetch Registered Business Owners
$owners = [];
$owners_error = '';
$sql_owners = "SELECT id, name, email, created_at FROM owners ORDER BY created_at DESC";
$result_owners = $conn->query($sql_owners);

if ($result_owners) {
    while ($row = $result_owners->fetch_assoc()) {
        $owners[] = $row;
    }
    $total_owners_count = count($owners); // Count of all owners
} else {
    $owners_error = "Error fetching business owners: " . htmlspecialchars($conn->error);
    $total_owners_count = 0;
}

// Fetch counts for dashboard widgets
$category_count_sql = "SELECT COUNT(*) as count FROM categories";
$category_count_result = $conn->query($category_count_sql);
$category_count = $category_count_result ? $category_count_result->fetch_assoc()['count'] : 0;

$subcategory_count_sql = "SELECT COUNT(*) as count FROM subcategories";
$subcategory_count_result = $conn->query($subcategory_count_sql);
$subcategory_count = $subcategory_count_result ? $subcategory_count_result->fetch_assoc()['count'] : 0;

$custom_field_count_sql = "SELECT COUNT(*) as count FROM custom_fields";
$custom_field_count_result = $conn->query($custom_field_count_sql);
$custom_field_count = $custom_field_count_result ? $custom_field_count_result->fetch_assoc()['count'] : 0;

$total_business_count_sql = "SELECT COUNT(*) as count FROM businesses"; 
$total_business_count_result = $conn->query($total_business_count_sql);
$total_business_count = $total_business_count_result ? $total_business_count_result->fetch_assoc()['count'] : 0;

$pending_business_count_sql = "SELECT COUNT(*) as count FROM businesses WHERE status = 'pending'";
$pending_business_count_result = $conn->query($pending_business_count_sql);
$pending_business_count = $pending_business_count_result ? $pending_business_count_result->fetch_assoc()['count'] : 0;

$approved_business_count_sql = "SELECT COUNT(*) as count FROM businesses WHERE status = 'approved'";
$approved_business_count_result = $conn->query($approved_business_count_sql);
$approved_business_count = $approved_business_count_result ? $approved_business_count_result->fetch_assoc()['count'] : 0;

?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow-sm border-light">
                <div class="card-header navbar-admin text-white"> {/* Using navbar-admin for consistent color */}
                    <h2 class="mb-0">Admin Dashboard</h2>
                </div>
                <div class="card-body">
                    <h3 class="card-title">Welcome, <?php echo htmlspecialchars($admin_display_email); ?>!</h3>
                    <p class="card-text">This is your central hub for managing City Depository.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats Widgets -->
    <div class="row mb-2">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card text-white bg-warning shadow h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="bi bi-hourglass-split"></i> Pending Businesses</h5>
                            <p class="card-text display-4"><?php echo $pending_business_count; ?></p>
                        </div>
                        <i class="bi bi-hourglass-split opacity-25" style="font-size: 3rem;"></i>
                    </div>
                    <a href="manage_businesses.php?filter_status=pending" class="text-white stretched-link">Review &raquo;</a>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card text-white bg-success shadow h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="bi bi-patch-check-fill"></i> Approved Businesses</h5>
                            <p class="card-text display-4"><?php echo $approved_business_count; ?></p>
                        </div>
                        <i class="bi bi-patch-check-fill opacity-25" style="font-size: 3rem;"></i>
                    </div>
                    <a href="manage_businesses.php?filter_status=approved" class="text-white stretched-link">Manage &raquo;</a> 
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card text-dark bg-light shadow h-100"> 
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="bi bi-people-fill"></i> Business Owners</h5>
                            <p class="card-text display-4"><?php echo $total_owners_count; ?></p>
                        </div>
                        <i class="bi bi-people-fill opacity-25" style="font-size: 3rem;"></i>
                    </div>
                     <a href="#" class="text-dark stretched-link disabled">Manage &raquo;</a>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card text-white bg-primary shadow h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="bi bi-briefcase-fill"></i> Total Businesses</h5>
                            <p class="card-text display-4"><?php echo $total_business_count; ?></p>
                        </div>
                        <i class="bi bi-briefcase-fill opacity-25" style="font-size: 3rem;"></i>
                    </div>
                    <a href="manage_businesses.php?filter_status=all" class="text-white stretched-link">View All &raquo;</a> 
                </div>
            </div>
        </div>
    </div>
     <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card text-white bg-secondary shadow h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="bi bi-tags-fill"></i> Categories</h5>
                            <p class="card-text display-4"><?php echo $category_count; ?></p>
                        </div>
                        <i class="bi bi-tags-fill opacity-25" style="font-size: 3rem;"></i>
                    </div>
                    <a href="categories.php" class="text-white stretched-link">Manage &raquo;</a>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card text-white bg-info shadow h-100">
                <div class="card-body">
                     <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="bi bi-diagram-3-fill"></i> Subcategories</h5>
                            <p class="card-text display-4"><?php echo $subcategory_count; ?></p>
                        </div>
                        <i class="bi bi-diagram-3-fill opacity-25" style="font-size: 3rem;"></i>
                    </div>
                    <a href="subcategories.php" class="text-white stretched-link">Manage &raquo;</a>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card text-white bg-purple shadow h-100"> {/* Custom purple already in style.css from previous task */}
                <div class="card-body">
                     <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0"><i class="bi bi-ui-checks-grid"></i> Custom Fields</h5>
                            <p class="card-text display-4"><?php echo $custom_field_count; ?></p>
                        </div>
                        <i class="bi bi-ui-checks-grid opacity-25" style="font-size: 3rem;"></i>
                    </div>
                    <a href="manage_custom_fields.php" class="text-white stretched-link">Manage &raquo;</a>
                </div>
            </div>
        </div>
        {/* Placeholder for another potential stat if needed */}
    </div>


    <!-- Recently Registered Business Owners Table -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm border-light">
                <div class="card-header bg-light">
                    <h4>Recently Registered Business Owners</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($owners_error)): ?>
                        <div class="alert alert-danger"><?php echo $owners_error; ?></div>
                    <?php endif; ?>

                    <?php if (empty($owners) && empty($owners_error)): ?>
                        <div class="alert alert-info">No business owners registered yet.</div>
                    <?php elseif (!empty($owners)): ?>
                        <div class="table-responsive"> {/* Added table-responsive wrapper */}
                            <table class="table table-striped table-hover">
                                <thead class="table-light"> 
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Registered On</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $recent_owners = array_slice($owners, 0, 5);
                                    foreach ($recent_owners as $owner): 
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($owner['id']); ?></td>
                                            <td><?php echo htmlspecialchars($owner['name']); ?></td>
                                            <td><?php echo htmlspecialchars($owner['email']); ?></td>
                                            <td><?php echo date("M j, Y, g:i a", strtotime($owner['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if(count($owners) > 5): ?>
                            <a href="#" class="btn btn-outline-primary btn-sm mt-2 disabled">View All Owners &raquo;</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                 <div class="card-footer text-muted">
                    Total Registered Owners: <?php echo $total_owners_count; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

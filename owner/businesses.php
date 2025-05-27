<?php
// header.php includes session_start() and database.php
include 'includes/header.php'; // This also includes $conn

// Access Control: Check if owner is logged in
if (!isset($_SESSION['owner_id'])) {
    header("Location: login.php");
    exit;
}

$owner_id = $_SESSION['owner_id'];
$businesses = [];
$page_error = '';

// Fetch businesses owned by the current user
$sql = "SELECT 
            b.id, 
            b.name AS business_name, 
            b.status, 
            b.created_at,
            c.name AS category_name,
            sc.name AS subcategory_name
        FROM businesses b
        JOIN categories c ON b.category_id = c.id
        JOIN subcategories sc ON b.subcategory_id = sc.id
        WHERE b.owner_id = ?
        ORDER BY b.created_at DESC";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    $page_error = "Error preparing query: " . htmlspecialchars($conn->error);
} else {
    $stmt->bind_param("i", $owner_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $businesses[] = $row;
        }
    } else {
        $page_error = "Error executing query: " . htmlspecialchars($stmt->error);
    }
    $stmt->close();
}

// Function to get badge class based on status
function get_status_badge($status) {
    switch (strtolower($status)) {
        case 'approved':
            return 'badge bg-success';
        case 'pending':
            return 'badge bg-warning text-dark';
        case 'rejected':
            return 'badge bg-danger';
        default:
            return 'badge bg-secondary';
    }
}
?>

<div class="container mt-4">
    <div class="row mb-3 align-items-center">
        <div class="col-md-6">
            <h1 class="h2 mb-0">My Businesses</h1>
        </div>
        <div class="col-md-6 text-md-end">
            <a href="add-business.php" class="btn btn-primary">
                <i class="bi bi-plus-circle-fill"></i> Add New Business
            </a>
        </div>
    </div>

    <?php if (!empty($page_error)): ?>
        <div class="alert alert-danger"><?php echo $page_error; ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['delete_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Business successfully deleted.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['delete_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            Error deleting business: <?php echo htmlspecialchars($_GET['delete_error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
     <?php if (isset($_GET['edit_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            Business successfully updated and is pending re-approval.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>


    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h3 class="mb-0">Your Business Listings</h3>
        </div>
        <div class="card-body">
            <?php if (empty($businesses) && empty($page_error)): ?>
                <div class="alert alert-info">
                    You have not added any businesses yet. <a href="add-business.php" class="alert-link">Click here to add your first business!</a>
                </div>
            <?php elseif (!empty($businesses)): ?>
                <div class="table-responsive"> {/* Added table-responsive wrapper */}
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Subcategory</th>
                                <th class="text-center">Status</th>
                                <th>Date Added</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($businesses as $business): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($business['business_name']); ?></td>
                                    <td><?php echo htmlspecialchars($business['category_name']); ?></td>
                                    <td><?php echo htmlspecialchars($business['subcategory_name']); ?></td>
                                    <td class="text-center">
                                        <span class="<?php echo get_status_badge($business['status']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($business['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date("M j, Y", strtotime($business['created_at'])); ?></td>
                                    <td class="text-center">
                                        <a href="../business-detail.php?id=<?php echo $business['id']; ?>" class="btn btn-sm btn-outline-info mb-1 mb-md-0" title="View Public Page" target="_blank">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        <a href="edit-business.php?id=<?php echo $business['id']; ?>" class="btn btn-sm btn-outline-primary ms-md-1 mb-1 mb-md-0" title="Edit Business">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </a>
                                        <a href="delete-business.php?id=<?php echo $business['id']; ?>" class="btn btn-sm btn-outline-danger ms-md-1" title="Delete Business" onclick="return confirm('Are you sure you want to delete this business: \'<?php echo htmlspecialchars(addslashes($business['business_name'])); ?>\'? This action cannot be undone.');">
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
        <div class="card-footer text-muted">
            Total Businesses: <?php echo count($businesses); ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<!-- Include Bootstrap Icons CSS if not already global -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

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

// --- Handle Approve/Reject Actions ---
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $business_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (!$business_id) {
        $errors[] = "Invalid business ID specified for action.";
    } else {
        $new_status = '';
        if ($action === 'approve') {
            $new_status = 'approved';
        } elseif ($action === 'reject') {
            $new_status = 'rejected';
        } else {
            $errors[] = "Invalid action specified.";
        }

        if (!empty($new_status) && empty($errors)) {
            $stmt_update_status = $conn->prepare("UPDATE businesses SET status = ? WHERE id = ?");
            if (!$stmt_update_status) {
                $errors[] = "Database error preparing status update: " . htmlspecialchars($conn->error);
            } else {
                $stmt_update_status->bind_param("si", $new_status, $business_id);
                if ($stmt_update_status->execute()) {
                    $success_message = "Business status successfully updated to '" . htmlspecialchars($new_status) . "'.";
                } else {
                    $errors[] = "Failed to update business status: " . htmlspecialchars($stmt_update_status->error);
                }
                $stmt_update_status->close();
            }
        }
    }
    $filter_status_redirect = isset($_GET['filter_status']) ? '&filter_status=' . urlencode($_GET['filter_status']) : '';
    header("Location: manage_businesses.php?action_done=1" . $filter_status_redirect . ($success_message ? "&success_msg=" . urlencode($success_message) : "") . (!empty($errors) ? "&error_msg=" . urlencode(implode(', ', $errors)) : ""));
    exit;
}

if(isset($_GET['action_done'])){
    if(isset($_GET['success_msg'])) $success_message = htmlspecialchars($_GET['success_msg']);
    if(isset($_GET['error_msg'])) $errors[] = htmlspecialchars($_GET['error_msg']);
}


// --- Filtering ---
$filter_status = $_GET['filter_status'] ?? 'pending'; 
$allowed_statuses = ['all', 'pending', 'approved', 'rejected'];
if (!in_array($filter_status, $allowed_statuses)) {
    $filter_status = 'all'; 
}

// --- Fetch Businesses ---
$businesses_list = [];
$sql_where_clause = "";
$params = [];
$types = "";

if ($filter_status !== 'all') {
    $sql_where_clause = "WHERE b.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

$sql_businesses = "SELECT b.id, b.name AS business_name, b.status, b.created_at,
                          o.name AS owner_name, o.email AS owner_email,
                          c.name AS category_name,
                          sc.name AS subcategory_name
                   FROM businesses b
                   LEFT JOIN owners o ON b.owner_id = o.id
                   LEFT JOIN categories c ON b.category_id = c.id
                   LEFT JOIN subcategories sc ON b.subcategory_id = sc.id
                   $sql_where_clause
                   ORDER BY b.created_at DESC";

$stmt_businesses = $conn->prepare($sql_businesses);

if (!$stmt_businesses) {
    $errors[] = "Database error preparing to fetch businesses: " . htmlspecialchars($conn->error);
} else {
    if (!empty($params)) {
        $stmt_businesses->bind_param($types, ...$params);
    }
    if ($stmt_businesses->execute()) {
        $result_businesses = $stmt_businesses->get_result();
        while ($row = $result_businesses->fetch_assoc()) {
            $businesses_list[] = $row;
        }
    } else {
        $errors[] = "Database error fetching businesses: " . htmlspecialchars($stmt_businesses->error);
    }
    $stmt_businesses->close();
}

function get_status_badge_admin($status) {
    switch (strtolower($status)) {
        case 'approved': return 'badge bg-success';
        case 'pending': return 'badge bg-warning text-dark';
        case 'rejected': return 'badge bg-danger';
        default: return 'badge bg-secondary';
    }
}
?>

<div class="container mt-4">
    <h1 class="mb-4">Manage Business Listings</h1>

    <!-- Display Messages -->
    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Errors:</strong><br>
            <?php foreach ($errors as $error): ?>
                - <?php echo $error; ?><br>
            <?php endforeach; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Filtering Tabs -->
    <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
            <a class="nav-link <?php echo ($filter_status === 'all') ? 'active' : ''; ?>" href="manage_businesses.php?filter_status=all">All</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($filter_status === 'pending') ? 'active' : ''; ?>" href="manage_businesses.php?filter_status=pending">Pending
                <?php 
                $pending_count_sql = "SELECT COUNT(*) as count FROM businesses WHERE status = 'pending'";
                $pending_result = $conn->query($pending_count_sql);
                $pending_count = $pending_result ? $pending_result->fetch_assoc()['count'] : 0;
                if ($pending_count > 0) echo "<span class='badge bg-warning text-dark ms-1'>{$pending_count}</span>";
                ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($filter_status === 'approved') ? 'active' : ''; ?>" href="manage_businesses.php?filter_status=approved">Approved</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($filter_status === 'rejected') ? 'active' : ''; ?>" href="manage_businesses.php?filter_status=rejected">Rejected</a>
        </li>
    </ul>

    <!-- List Businesses -->
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <h3 class="mb-0">Businesses (<?php echo htmlspecialchars(ucfirst($filter_status)); ?>)</h3>
        </div>
        <div class="card-body">
            <?php if (empty($businesses_list) && empty($errors)): ?>
                <div class="alert alert-info">No businesses found for the selected filter.</div>
            <?php elseif (!empty($businesses_list)): ?>
                <div class="table-responsive"> {/* Added table-responsive wrapper */}
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Owner</th>
                                <th>Category</th>
                                <th>Subcategory</th>
                                <th>Submitted</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($businesses_list as $business): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($business['business_name']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($business['owner_name'] ?? 'N/A'); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($business['owner_email'] ?? 'N/A'); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($business['category_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($business['subcategory_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo date("M j, Y", strtotime($business['created_at'])); ?></td>
                                    <td class="text-center">
                                        <span class="<?php echo get_status_badge_admin($business['status']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($business['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group" role="group" aria-label="Business Actions">
                                            <?php if ($business['status'] === 'pending'): ?>
                                                <a href="manage_businesses.php?action=approve&id=<?php echo $business['id']; ?>&filter_status=<?php echo $filter_status; ?>" class="btn btn-sm btn-success" title="Approve Business">
                                                    <i class="bi bi-check-circle"></i> Approve
                                                </a>
                                                <a href="manage_businesses.php?action=reject&id=<?php echo $business['id']; ?>&filter_status=<?php echo $filter_status; ?>" class="btn btn-sm btn-danger" title="Reject Business">
                                                    <i class="bi bi-x-circle"></i> Reject
                                                </a>
                                            <?php elseif ($business['status'] === 'approved'): ?>
                                                 <a href="manage_businesses.php?action=reject&id=<?php echo $business['id']; ?>&filter_status=<?php echo $filter_status; ?>" class="btn btn-sm btn-outline-danger" title="Reject Business">
                                                    <i class="bi bi-x-circle"></i> Reject
                                                </a>
                                            <?php elseif ($business['status'] === 'rejected'): ?>
                                                 <a href="manage_businesses.php?action=approve&id=<?php echo $business['id']; ?>&filter_status=<?php echo $filter_status; ?>" class="btn btn-sm btn-outline-success" title="Approve Business">
                                                    <i class="bi bi-check-circle"></i> Approve
                                                </a>
                                            <?php endif; ?>
                                            <a href="../business-detail.php?id=<?php echo $business['id']; ?>" class="btn btn-sm btn-outline-info" title="View Details" target="_blank">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
         <div class="card-footer text-muted">
            Total businesses shown: <?php echo count($businesses_list); ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<!-- Include Bootstrap Icons CSS if not already global -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

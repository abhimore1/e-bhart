<?php
require_once 'config/database.php'; // Establishes $conn
include 'includes/header.php';

// Initialize variables
$business = null;
$custom_data = [];
$error_message = '';

// Handle URL Parameter
if (!isset($_GET['id']) || empty($_GET['id']) || !ctype_digit((string)$_GET['id'])) {
    $error_message = "Invalid business ID specified. Please select a valid business.";
} else {
    $business_id = (int)$_GET['id'];

    // Fetch Business Details
    $sql = "SELECT b.*, 
                   c.name AS category_name, 
                   sc.name AS subcategory_name, 
                   o.name AS owner_name 
            FROM businesses b
            JOIN categories c ON b.category_id = c.id
            JOIN subcategories sc ON b.subcategory_id = sc.id
            LEFT JOIN owners o ON b.owner_id = o.id 
            WHERE b.id = ? AND b.status = 'approved'";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("i", $business_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $business = $result->fetch_assoc();

            // Fetch Custom Form Data
            $custom_sql = "SELECT field_name, field_value FROM custom_form_data WHERE business_id = ?";
            $custom_stmt = $conn->prepare($custom_sql);
            if ($custom_stmt) {
                $custom_stmt->bind_param("i", $business_id);
                $custom_stmt->execute();
                $custom_result = $custom_stmt->get_result();
                while ($row = $custom_result->fetch_assoc()) {
                    $custom_data[] = $row;
                }
                $custom_stmt->close();
            } else {
                // Log error or display a less critical message if custom data fails
                error_log("Error preparing statement for custom_form_data: " . $conn->error);
                // $error_message = "Could not retrieve additional details."; // Optional
            }
        } else {
            $error_message = "Business not found or not available. It might be pending approval or no longer listed.";
        }
        $stmt->close();
    } else {
        $error_message = "Database query error. Please try again later.";
        error_log("Error preparing statement for business details: " . $conn->error);
    }
}
?>

<div class="container mt-5 mb-5">
    <?php if ($error_message): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($error_message); ?>
            <p class="mt-2"><a href="businesses.php" class="alert-link">Return to Business Listings</a></p>
        </div>
    <?php elseif ($business): ?>
        <div class="row">
            <div class="col-md-12">
                <h1 class="display-4 mb-3"><?php echo htmlspecialchars($business['name']); ?></h1>
            </div>
        </div>

        <div class="row">
            <!-- Left Column: Image and Key Details -->
            <div class="col-md-5">
                <?php 
                $image_path = !empty($business['image']) ? htmlspecialchars($business['image']) : 'assets/images/placeholder.jpg'; 
                ?>
                <img src="<?php echo $image_path; ?>" class="img-fluid rounded mb-3" alt="<?php echo htmlspecialchars($business['name']); ?>" style="max-height: 400px; width: 100%; object-fit: cover;">
                
                <div class="card">
                    <div class="card-header">
                        Key Information
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <strong>Category:</strong> <?php echo htmlspecialchars($business['category_name']); ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Subcategory:</strong> <?php echo htmlspecialchars($business['subcategory_name']); ?>
                        </li>
                        <li class="list-group-item">
                            <strong>Location:</strong> <?php echo htmlspecialchars($business['location']); ?>
                        </li>
                        <?php if (!empty($business['contact'])): ?>
                        <li class="list-group-item">
                            <strong>Contact:</strong> <?php echo htmlspecialchars($business['contact']); ?>
                        </li>
                        <?php endif; ?>
                        <?php if (!empty($business['owner_name'])): // Optional Owner Display ?>
                        <li class="list-group-item">
                            <strong>Posted by:</strong> <?php echo htmlspecialchars($business['owner_name']); ?>
                        </li>
                        <?php endif; ?>
                        <li class="list-group-item">
                            <strong>Date Listed:</strong> <?php echo date("F j, Y", strtotime($business['created_at'])); ?>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Right Column: Description and Custom Fields -->
            <div class="col-md-7">
                <?php if (!empty($business['description'])): ?>
                <h3 class="mt-4 mt-md-0">Description</h3>
                <p class="lead"><?php echo nl2br(htmlspecialchars($business['description'])); ?></p>
                <hr>
                <?php endif; ?>

                <?php if (!empty($custom_data)): ?>
                <h3 class="mt-4">Additional Details</h3>
                <dl class="row">
                    <?php foreach ($custom_data as $data_item): ?>
                        <dt class="col-sm-4"><?php echo htmlspecialchars($data_item['field_name']); ?>:</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($data_item['field_value']); ?></dd>
                    <?php endforeach; ?>
                </dl>
                <?php else: ?>
                     <p class="mt-4 text-muted">No additional details available for this business.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-12">
                 <a href="businesses.php?category_id=<?php echo $business['category_id']; ?>&subcategory_id=<?php echo $business['subcategory_id']; ?>" class="btn btn-outline-secondary">
                    &laquo; Back to <?php echo htmlspecialchars($business['subcategory_name']); ?>
                </a>
                <a href="businesses.php" class="btn btn-outline-primary ms-2">
                    &laquo; Back to All Listings
                </a>
            </div>
        </div>

    <?php else: // Should not happen if error_message is properly set, but as a fallback ?>
        <div class="alert alert-warning" role="alert">
            Could not retrieve business details. Please try again.
            <p class="mt-2"><a href="businesses.php" class="alert-link">Return to Business Listings</a></p>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
<?php $conn->close(); // Close the database connection ?>

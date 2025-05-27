<?php
require_once 'config/database.php'; // Establishes $conn
include 'includes/header.php';

// Get URL parameters
$selected_category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$selected_subcategory_id = isset($_GET['subcategory_id']) ? (int)$_GET['subcategory_id'] : null;

// --- Pagination Settings ---
$businesses_per_page = 6;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $businesses_per_page;

?>

<div class="container mt-5">
    <h1 class="mb-4 text-center">Browse Our Business Directory</h1>

    <!-- 1. Display Categories -->
    <div class="row">
        <div class="col-md-12">
            <h2 class="mb-3">Categories</h2>
            <?php
            $cat_sql = "SELECT id, name FROM categories ORDER BY name ASC";
            $cat_result = $conn->query($cat_sql);

            if (!$cat_result) {
                echo '<div class="alert alert-danger" role="alert">Error fetching categories: ' . htmlspecialchars($conn->error) . '</div>';
            } elseif ($cat_result->num_rows > 0) {
                echo '<div class="list-group">';
                while($cat_row = $cat_result->fetch_assoc()) {
                    $active_class = ($selected_category_id === (int)$cat_row["id"]) ? 'active' : '';
                    echo '<a href="businesses.php?category_id=' . htmlspecialchars($cat_row["id"]) . '" class="list-group-item list-group-item-action ' . $active_class . '">';
                    echo htmlspecialchars($cat_row["name"]);
                    echo '</a>';
                }
                echo '</div>';
            } else {
                echo '<div class="alert alert-info" role="alert">No categories available at the moment.</div>';
            }
            ?>
        </div>
    </div>

    <hr class="my-5">

    <?php if ($selected_category_id): ?>
        <?php
        // Fetch selected category name
        $category_name_sql = "SELECT name FROM categories WHERE id = ?";
        $category_name_stmt = $conn->prepare($category_name_sql);
        if ($category_name_stmt) {
            $category_name_stmt->bind_param("i", $selected_category_id);
            $category_name_stmt->execute();
            $category_name_result = $category_name_stmt->get_result();
            $category_data = $category_name_result->fetch_assoc();
            $category_name = $category_data ? htmlspecialchars($category_data['name']) : 'Selected Category';
            $category_name_stmt->close();
        } else {
            echo '<div class="alert alert-danger" role="alert">Error preparing statement for category name: ' . htmlspecialchars($conn->error) . '</div>';
            $category_name = 'Error';
        }
        ?>
        <!-- 2. Display Subcategories -->
        <div class="row mt-4">
            <div class="col-md-12">
                <h2 class="mb-3">Subcategories in <span class="text-primary"><?php echo $category_name; ?></span></h2>
                <?php
                $subcat_sql = "SELECT id, name FROM subcategories WHERE category_id = ? ORDER BY name ASC";
                $subcat_stmt = $conn->prepare($subcat_sql);
                if ($subcat_stmt) {
                    $subcat_stmt->bind_param("i", $selected_category_id);
                    $subcat_stmt->execute();
                    $subcat_result = $subcat_stmt->get_result();

                    if ($subcat_result->num_rows > 0) {
                        echo '<div class="list-group">';
                        while($subcat_row = $subcat_result->fetch_assoc()) {
                            $active_class = ($selected_subcategory_id === (int)$subcat_row["id"]) ? 'active' : '';
                            echo '<a href="businesses.php?category_id=' . $selected_category_id . '&subcategory_id=' . htmlspecialchars($subcat_row["id"]) . '" class="list-group-item list-group-item-action ' . $active_class . '">';
                            echo htmlspecialchars($subcat_row["name"]);
                            echo '</a>';
                        }
                        echo '</div>';
                        if (!$selected_subcategory_id) {
                            echo '<div class="alert alert-info mt-3" role="alert">Please select a subcategory to view businesses.</div>';
                        }
                    } else {
                        echo '<div class="alert alert-info" role="alert">No subcategories found for ' . $category_name . '.</div>';
                    }
                    $subcat_stmt->close();
                } else {
                     echo '<div class="alert alert-danger" role="alert">Error preparing statement for subcategories: ' . htmlspecialchars($conn->error) . '</div>';
                }
                ?>
            </div>
        </div>
        <hr class="my-5">
    <?php endif; ?>


    <?php if ($selected_category_id && $selected_subcategory_id): ?>
        <?php
        // Fetch selected subcategory name
        $subcategory_name_sql = "SELECT name FROM subcategories WHERE id = ? AND category_id = ?";
        $subcategory_name_stmt = $conn->prepare($subcategory_name_sql);
        if ($subcategory_name_stmt) {
            $subcategory_name_stmt->bind_param("ii", $selected_subcategory_id, $selected_category_id);
            $subcategory_name_stmt->execute();
            $subcategory_name_result = $subcategory_name_stmt->get_result();
            $subcategory_data = $subcategory_name_result->fetch_assoc();
            $subcategory_name = $subcategory_data ? htmlspecialchars($subcategory_data['name']) : 'Selected Subcategory';
            $subcategory_name_stmt->close();

            if (!$subcategory_data) { // Invalid subcategory for this category
                echo '<div class="alert alert-warning" role="alert">Invalid subcategory selected. Please check the URL or select a valid subcategory.</div>';
                $selected_subcategory_id = null; // Prevent businesses from loading
            }
        } else {
            echo '<div class="alert alert-danger" role="alert">Error preparing statement for subcategory name: ' . htmlspecialchars($conn->error) . '</div>';
            $subcategory_name = 'Error';
            $selected_subcategory_id = null; // Prevent businesses from loading
        }
        ?>

        <?php if ($selected_subcategory_id): // Re-check if subcategory is valid ?>
        <!-- 3. Display Businesses -->
        <div class="row mt-4">
            <div class="col-md-12">
                <h2 class="mb-3">Businesses in <span class="text-success"><?php echo $subcategory_name; ?></span> (under <?php echo $category_name; ?>)</h2>
                <?php
                // Count total businesses for pagination
                $total_biz_sql = "SELECT COUNT(*) as total FROM businesses WHERE subcategory_id = ? AND status = 'approved'";
                $total_biz_stmt = $conn->prepare($total_biz_sql);
                if ($total_biz_stmt) {
                    $total_biz_stmt->bind_param("i", $selected_subcategory_id);
                    $total_biz_stmt->execute();
                    $total_biz_result = $total_biz_stmt->get_result();
                    $total_biz_row = $total_biz_result->fetch_assoc();
                    $total_businesses = $total_biz_row['total'];
                    $total_pages = ceil($total_businesses / $businesses_per_page);
                    $total_biz_stmt->close();
                } else {
                    echo '<div class="alert alert-danger" role="alert">Error preparing statement for business count: ' . htmlspecialchars($conn->error) . '</div>';
                    $total_businesses = 0;
                    $total_pages = 0;
                }


                // Fetch businesses for the current page
                $biz_sql = "SELECT b.id, b.name, b.image, b.location, c.name as category_name 
                            FROM businesses b
                            JOIN subcategories sc ON b.subcategory_id = sc.id
                            JOIN categories c ON sc.category_id = c.id
                            WHERE b.subcategory_id = ? AND b.status = 'approved'
                            ORDER BY b.name ASC
                            LIMIT ? OFFSET ?";
                $biz_stmt = $conn->prepare($biz_sql);

                if ($biz_stmt) {
                    $biz_stmt->bind_param("iii", $selected_subcategory_id, $businesses_per_page, $offset);
                    $biz_stmt->execute();
                    $biz_result = $biz_stmt->get_result();

                    if ($biz_result->num_rows > 0) {
                        echo '<div class="row">';
                        while($biz_row = $biz_result->fetch_assoc()) {
                            $image_path = !empty($biz_row["image"]) ? htmlspecialchars($biz_row["image"]) : 'assets/images/placeholder.jpg';
                            ?>
                            <div class="col-md-4 mb-4">
                                <div class="card h-100">
                                    <img src="<?php echo $image_path; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($biz_row["name"]); ?>" style="height: 200px; object-fit: cover;">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($biz_row["name"]); ?></h5>
                                        <p class="card-text mb-1"><small class="text-muted">Location:</small> <?php echo htmlspecialchars($biz_row["location"]); ?></p>
                                        <p class="card-text mb-1"><small class="text-muted">Category:</small> <?php echo htmlspecialchars($biz_row["category_name"]); ?></p>
                                        <p class="card-text"><small class="text-muted">Subcategory:</small> <?php echo $subcategory_name; ?></p>
                                        <a href="business-detail.php?id=<?php echo htmlspecialchars($biz_row["id"]); ?>" class="btn btn-primary mt-auto">View Details</a>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                        echo '</div>';

                        // Pagination Links
                        if ($total_pages > 1) {
                            echo '<nav aria-label="Businesses navigation"><ul class="pagination justify-content-center">';
                            // Previous
                            if ($current_page > 1) {
                                echo '<li class="page-item"><a class="page-link" href="businesses.php?category_id=' . $selected_category_id . '&subcategory_id=' . $selected_subcategory_id . '&page=' . ($current_page - 1) . '">Previous</a></li>';
                            } else {
                                echo '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
                            }
                            // Page numbers
                            for ($i = 1; $i <= $total_pages; $i++) {
                                $active_page = ($i == $current_page) ? 'active' : '';
                                echo '<li class="page-item ' . $active_page . '"><a class="page-link" href="businesses.php?category_id=' . $selected_category_id . '&subcategory_id=' . $selected_subcategory_id . '&page=' . $i . '">' . $i . '</a></li>';
                            }
                            // Next
                            if ($current_page < $total_pages) {
                                echo '<li class="page-item"><a class="page-link" href="businesses.php?category_id=' . $selected_category_id . '&subcategory_id=' . $selected_subcategory_id . '&page=' . ($current_page + 1) . '">Next</a></li>';
                            } else {
                                echo '<li class="page-item disabled"><span class="page-link">Next</span></li>';
                            }
                            echo '</ul></nav>';
                            echo '<p class="text-center">Page ' . $current_page . ' of ' . $total_pages . '</p>';
                        }

                    } else {
                        echo '<div class="alert alert-info" role="alert">No businesses found in ' . $subcategory_name . ' for ' . $category_name . '.</div>';
                    }
                    $biz_stmt->close();
                } else {
                    echo '<div class="alert alert-danger" role="alert">Error preparing statement for businesses: ' . htmlspecialchars($conn->error) . '</div>';
                }
                ?>
            </div>
        </div>
        <?php endif; // end check for valid subcategory_id before displaying businesses ?>
    <?php endif; ?>


    <?php if (!$selected_category_id): ?>
        <div class="alert alert-secondary mt-5" role="alert">
            Please select a category above to browse subcategories and businesses.
        </div>
    <?php endif; ?>

</div>

<?php include 'includes/footer.php'; ?>
<?php $conn->close(); // Close the database connection ?>

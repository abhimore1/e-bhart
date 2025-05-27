<?php
require_once 'config/database.php'; // Establishes $conn
include 'includes/header.php';

// Get search terms
$keywords = isset($_GET['keywords']) ? trim($_GET['keywords']) : '';
$location_search = isset($_GET['location']) ? trim($_GET['location']) : '';

// --- Pagination Settings ---
$results_per_page = 9; // 3 cards per row, 3 rows
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
$offset = ($current_page - 1) * $results_per_page;

$search_results = [];
$total_results = 0;
$error_message = '';
$query_conditions = [];
$params = [];
$types = "";

if (empty($keywords) && empty($location_search)) {
    $error_message = "Please enter a search term or location.";
} else {
    $sql_base = "FROM businesses b 
                 JOIN categories c ON b.category_id = c.id
                 JOIN subcategories sc ON b.subcategory_id = sc.id
                 WHERE b.status = 'approved'";
    
    $params[] = 'approved'; // For b.status
    // $types .= "s"; // This was incorrect, status is part of base SQL, not a param here.
    // Corrected: status is hardcoded 'approved', not a param.

    if (!empty($keywords)) {
        $keyword_like = "%" . $keywords . "%";
        $query_conditions[] = "(b.name LIKE ? OR b.description LIKE ? OR c.name LIKE ? OR sc.name LIKE ?)";
        for ($i = 0; $i < 4; $i++) {
            $params[] = $keyword_like;
            $types .= "s";
        }
    }

    if (!empty($location_search)) {
        $location_like = "%" . $location_search . "%";
        $query_conditions[] = "b.location LIKE ?";
        $params[] = $location_like;
        $types .= "s";
    }

    $sql_where_clause = "";
    if (!empty($query_conditions)) {
        // All conditions are ANDed with the base "b.status = 'approved'"
        // And also ANDed with each other if multiple search criteria are present
        $sql_where_clause = " AND " . implode(" AND ", $query_conditions);
    }

    // --- Count total results for pagination ---
    $sql_count = "SELECT COUNT(DISTINCT b.id) as total " . $sql_base . $sql_where_clause;
    $stmt_count = $conn->prepare($sql_count);

    if (!$stmt_count) {
        $error_message = "Error preparing count query: " . htmlspecialchars($conn->error);
    } else {
        if (!empty($params)) { // Parameters are only for LIKE clauses
            $stmt_count->bind_param($types, ...$params);
        }
        if ($stmt_count->execute()) {
            $total_results = $stmt_count->get_result()->fetch_assoc()['total'];
        } else {
            $error_message = "Error executing count query: " . htmlspecialchars($stmt_count->error);
        }
        $stmt_count->close();
    }
    
    $total_pages = ceil($total_results / $results_per_page);

    // --- Fetch results for the current page ---
    if ($total_results > 0 && empty($error_message)) {
        $sql_results = "SELECT DISTINCT b.id, b.name, b.image, b.location, 
                               c.name as category_name, sc.name as subcategory_name
                        " . $sql_base . $sql_where_clause . "
                        ORDER BY b.name ASC
                        LIMIT ? OFFSET ?";
        
        $stmt_results = $conn->prepare($sql_results);
        if (!$stmt_results) {
            $error_message = "Error preparing results query: " . htmlspecialchars($conn->error);
        } else {
            // Add LIMIT and OFFSET params
            $current_params_results = $params; // Params from search criteria
            $current_params_results[] = $results_per_page;
            $current_params_results[] = $offset;
            $current_types_results = $types . "ii"; // Types for search criteria + limit + offset

            $stmt_results->bind_param($current_types_results, ...$current_params_results);
            
            if ($stmt_results->execute()) {
                $result_set = $stmt_results->get_result();
                while ($row = $result_set->fetch_assoc()) {
                    $search_results[] = $row;
                }
            } else {
                $error_message = "Error executing results query: " . htmlspecialchars($stmt_results->error);
            }
            $stmt_results->close();
        }
    }
}
?>

<div class="container mt-5">
    <h1 class="mb-4">Search Results</h1>

    <div class="row mb-4">
        <div class="col-md-12">
             <form action="search.php" method="GET">
                <div class="row g-3">
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="keywords" placeholder="Keywords..." value="<?php echo htmlspecialchars($keywords); ?>">
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="location" placeholder="Location..." value="<?php echo htmlspecialchars($location_search); ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100">Search Again</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php else: ?>
        <p class="lead mb-4">
            <?php if ($total_results > 0): ?>
                Showing <?php echo count($search_results); ?> of <?php echo $total_results; ?> results
            <?php endif; ?>
            <?php if (!empty($keywords) || !empty($location_search)): ?>
                for
                <?php if (!empty($keywords)): ?>
                    <strong>"<?php echo htmlspecialchars($keywords); ?>"</strong>
                <?php endif; ?>
                <?php if (!empty($keywords) && !empty($location_search)): ?>
                    in
                <?php endif; ?>
                <?php if (!empty($location_search)): ?>
                    <strong>"<?php echo htmlspecialchars($location_search); ?>"</strong>
                <?php endif; ?>.
            <?php endif; ?>
        </p>

        <?php if (empty($search_results)): ?>
            <div class="alert alert-info">No businesses found matching your search criteria.</div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($search_results as $business): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100 shadow-sm">
                            <img src="<?php echo !empty($business['image']) ? htmlspecialchars($business['image']) : 'assets/images/placeholder.jpg'; ?>" 
                                 class="card-img-top" alt="<?php echo htmlspecialchars($business['name']); ?>" 
                                 style="height: 200px; object-fit: cover;">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?php echo htmlspecialchars($business['name']); ?></h5>
                                <p class="card-text mb-1"><small class="text-muted">Location:</small> <?php echo htmlspecialchars($business['location'] ?? 'N/A'); ?></p>
                                <p class="card-text mb-1"><small class="text-muted">Category:</small> <?php echo htmlspecialchars($business['category_name'] ?? 'N/A'); ?></p>
                                <p class="card-text"><small class="text-muted">Subcategory:</small> <?php echo htmlspecialchars($business['subcategory_name'] ?? 'N/A'); ?></p>
                                <a href="business-detail.php?id=<?php echo $business['id']; ?>" class="btn btn-primary mt-auto align-self-start">View Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination Links -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Search results navigation">
                    <ul class="pagination justify-content-center">
                        <?php 
                        // Previous Page
                        if ($current_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="search.php?keywords=<?php echo urlencode($keywords); ?>&location=<?php echo urlencode($location_search); ?>&page=<?php echo $current_page - 1; ?>">Previous</a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled"><span class="page-link">Previous</span></li>
                        <?php endif; ?>

                        <?php 
                        // Page Numbers
                        // Define how many page numbers to show around the current page
                        $links_to_show = 2; 
                        $start_page = max(1, $current_page - $links_to_show);
                        $end_page = min($total_pages, $current_page + $links_to_show);

                        // Show first page and ellipsis if needed
                        if ($start_page > 1) {
                            echo '<li class="page-item"><a class="page-link" href="search.php?keywords='.urlencode($keywords).'&location='.urlencode($location_search).'&page=1">1</a></li>';
                            if ($start_page > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }

                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo ($i == $current_page) ? 'active' : ''; ?>">
                                <a class="page-link" href="search.php?keywords=<?php echo urlencode($keywords); ?>&location=<?php echo urlencode($location_search); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php
                        // Show last page and ellipsis if needed
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="search.php?keywords='.urlencode($keywords).'&location='.urlencode($location_search).'&page='.$total_pages.'">'.$total_pages.'</a></li>';
                        }
                        ?>

                        <?php 
                        // Next Page
                        if ($current_page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="search.php?keywords=<?php echo urlencode($keywords); ?>&location=<?php echo urlencode($location_search); ?>&page=<?php echo $current_page + 1; ?>">Next</a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled"><span class="page-link">Next</span></li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <p class="text-center">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></p>
            <?php endif; ?>

        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
<?php if(isset($conn)) { $conn->close(); } ?>

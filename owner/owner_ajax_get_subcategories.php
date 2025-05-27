<?php
header('Content-Type: application/json'); // Ensure client knows to expect JSON

// Adjust path to config/database.php.
// If owner_ajax_get_subcategories.php is in owner/, and config/ is in the parent directory of owner/'s parent
// (e.g., project_root/config/ and project_root/app/owner/), the path needs to be correct.
// Assuming standard structure: project_root/config/ and project_root/owner/
require_once __DIR__ . '/../config/database.php'; // $conn should be available now

$subcategories = [];
$error_message = null;

if (!isset($_GET['category_id']) || !ctype_digit((string)$_GET['category_id'])) {
    $error_message = "Invalid or missing category ID.";
} else {
    $category_id = (int)$_GET['category_id'];

    $stmt = $conn->prepare("SELECT id, name FROM subcategories WHERE category_id = ? ORDER BY name ASC");
    
    if (!$stmt) {
        // Log detailed error to server logs for admin, send generic error to client
        error_log("Prepare failed for subcategories: (" . $conn->errno . ") " . $conn->error);
        $error_message = "Error preparing to fetch subcategories. Please try again later.";
    } else {
        $stmt->bind_param("i", $category_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                // Sanitize output, though these are from DB, good practice
                $subcategories[] = [
                    'id' => htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'),
                    'name' => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8')
                ];
            }
        } else {
            // Log detailed error
            error_log("Execute failed for subcategories: (" . $stmt->errno . ") " . $stmt->error);
            $error_message = "Error fetching subcategories. Please try again later.";
        }
        $stmt->close();
    }
    $conn->close();
}

if ($error_message) {
    echo json_encode(['error' => $error_message]);
} else {
    echo json_encode($subcategories);
}
?>

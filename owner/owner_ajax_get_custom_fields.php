<?php
header('Content-Type: application/json');
session_start(); // Optional: for access control if needed later

// Ensure owner is logged in (optional, but good practice)
/*
if (!isset($_SESSION['owner_id'])) {
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}
*/

require_once __DIR__ . '/../config/database.php'; // Adjust path as necessary

$custom_fields = [];
$error_message = null;

if (!isset($_GET['subcategory_id']) || !ctype_digit((string)$_GET['subcategory_id'])) {
    $error_message = "Invalid or missing subcategory ID.";
} else {
    $subcategory_id = (int)$_GET['subcategory_id'];

    $stmt = $conn->prepare("SELECT id, field_name, field_type, field_options FROM custom_fields WHERE subcategory_id = ? ORDER BY field_name ASC");
    
    if (!$stmt) {
        error_log("Prepare failed for custom_fields: (" . $conn->errno . ") " . $conn->error);
        $error_message = "Error preparing to fetch custom fields.";
    } else {
        $stmt->bind_param("i", $subcategory_id);
        if ($stmt->execute()) {
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                // Sanitize output
                $custom_fields[] = [
                    'id' => htmlspecialchars($row['id'], ENT_QUOTES, 'UTF-8'),
                    'field_name' => htmlspecialchars($row['field_name'], ENT_QUOTES, 'UTF-8'),
                    'field_type' => htmlspecialchars($row['field_type'], ENT_QUOTES, 'UTF-8'),
                    'field_options' => htmlspecialchars($row['field_options'] ?? '', ENT_QUOTES, 'UTF-8')
                ];
            }
        } else {
            error_log("Execute failed for custom_fields: (" . $stmt->errno . ") " . $stmt->error);
            $error_message = "Error fetching custom fields.";
        }
        $stmt->close();
    }
    $conn->close();
}

if ($error_message) {
    echo json_encode(['error' => $error_message]);
} else {
    echo json_encode($custom_fields);
}
?>

<?php
header('Content-Type: application/json');
session_start(); // Optional: for access control

// Ensure owner is logged in (optional, but good practice)
/*
if (!isset($_SESSION['owner_id'])) {
    echo json_encode(['error' => 'Authentication required.']);
    exit;
}
*/

require_once __DIR__ . '/../config/database.php'; // Adjust path as necessary

$business_custom_data = [];
$error_message = null;

if (!isset($_GET['business_id']) || !ctype_digit((string)$_GET['business_id'])) {
    $error_message = "Invalid or missing business ID.";
} else {
    $business_id = (int)$_GET['business_id'];

    // First, get the subcategory_id for the given business_id
    $subcategory_id = null;
    $stmt_get_subcat = $conn->prepare("SELECT subcategory_id FROM businesses WHERE id = ?");
    if (!$stmt_get_subcat) {
        error_log("Prepare failed for subcategory_id fetch: (" . $conn->errno . ") " . $conn->error);
        $error_message = "Error preparing to fetch business details.";
    } else {
        $stmt_get_subcat->bind_param("i", $business_id);
        if ($stmt_get_subcat->execute()) {
            $result_subcat = $stmt_get_subcat->get_result();
            if ($row_subcat = $result_subcat->fetch_assoc()) {
                $subcategory_id = $row_subcat['subcategory_id'];
            } else {
                $error_message = "Business not found.";
            }
        } else {
            error_log("Execute failed for subcategory_id fetch: (" . $stmt_get_subcat->errno . ") " . $stmt_get_subcat->error);
            $error_message = "Error fetching business details.";
        }
        $stmt_get_subcat->close();
    }

    if ($subcategory_id && !$error_message) {
        // Now fetch custom field data, joining custom_fields to get the ID
        $sql = "SELECT cf.id AS custom_field_id, cfd.field_value 
                FROM custom_form_data cfd
                JOIN custom_fields cf ON cfd.field_name = cf.field_name
                WHERE cfd.business_id = ? AND cf.subcategory_id = ?";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("Prepare failed for custom data join: (" . $conn->errno . ") " . $conn->error);
            $error_message = "Error preparing to fetch custom data.";
        } else {
            $stmt->bind_param("ii", $business_id, $subcategory_id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    // Map custom_field_id to its value
                    $business_custom_data[htmlspecialchars($row['custom_field_id'], ENT_QUOTES, 'UTF-8')] = htmlspecialchars($row['field_value'], ENT_QUOTES, 'UTF-8');
                }
            } else {
                error_log("Execute failed for custom data join: (" . $stmt->errno . ") " . $stmt->error);
                $error_message = "Error fetching custom data.";
            }
            $stmt->close();
        }
    } elseif (!$error_message) { 
        // This case means subcategory_id was not found, but no previous error message set.
        // It might happen if business_id is valid but has no subcategory_id (schema violation)
        // or if business_id was not found and $error_message wasn't set properly in the first block.
        if (!$error_message) $error_message = "Could not determine subcategory for the business.";
    }
    $conn->close();
}

if ($error_message) {
    echo json_encode(['error' => $error_message]);
} else {
    echo json_encode($business_custom_data);
}
?>

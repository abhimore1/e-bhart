<?php
// header.php includes session_start() and database.php
include 'includes/header.php'; // This also includes $conn

// Access Control: Check if owner is logged in
if (!isset($_SESSION['owner_id'])) {
    header("Location: login.php");
    exit;
}

$owner_id = $_SESSION['owner_id'];
$business_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$business_name_to_delete = '';
$error_message = '';
$confirm_delete = isset($_POST['confirm_delete_action']);

// Fetch business name for confirmation message and verify ownership
if ($business_id && !$confirm_delete) { // Only fetch if not confirming deletion yet
    $stmt_fetch_name = $conn->prepare("SELECT name FROM businesses WHERE id = ? AND owner_id = ?");
    if (!$stmt_fetch_name) {
        $error_message = "Error preparing to fetch business name: " . htmlspecialchars($conn->error);
    } else {
        $stmt_fetch_name->bind_param("ii", $business_id, $owner_id);
        $stmt_fetch_name->execute();
        $result_name = $stmt_fetch_name->get_result();
        if ($result_name->num_rows === 1) {
            $business_data = $result_name->fetch_assoc();
            $business_name_to_delete = $business_data['name'];
        } else {
            $error_message = "Business not found or you do not have permission to delete it.";
            $business_id = null; // Prevent deletion form if not found/owned
        }
        $stmt_fetch_name->close();
    }
}


// Handle Deletion Logic if confirmed
if ($business_id && $confirm_delete) {
    // Re-verify ownership before deletion, in case GET ID was manipulated after initial load
    $stmt_verify = $conn->prepare("SELECT id, image FROM businesses WHERE id = ? AND owner_id = ?");
     if (!$stmt_verify) {
        $error_message = "Error preparing verification: " . htmlspecialchars($conn->error);
    } else {
        $stmt_verify->bind_param("ii", $business_id, $owner_id);
        $stmt_verify->execute();
        $result_verify = $stmt_verify->get_result();

        if ($result_verify->num_rows === 1) {
            $business_to_delete = $result_verify->fetch_assoc();
            $image_to_delete_path = $business_to_delete['image'];

            $conn->begin_transaction(); // Start transaction

            try {
                // 1. Delete from custom_form_data
                $stmt_delete_custom = $conn->prepare("DELETE FROM custom_form_data WHERE business_id = ?");
                if (!$stmt_delete_custom) throw new Exception("Error preparing custom data delete: " . $conn->error);
                $stmt_delete_custom->bind_param("i", $business_id);
                if(!$stmt_delete_custom->execute()) throw new Exception("Error deleting custom data: " . $stmt_delete_custom->error);
                $stmt_delete_custom->close();

                // 2. Delete from businesses
                $stmt_delete_biz = $conn->prepare("DELETE FROM businesses WHERE id = ?");
                if (!$stmt_delete_biz) throw new Exception("Error preparing business delete: " . $conn->error);
                $stmt_delete_biz->bind_param("i", $business_id);
                if(!$stmt_delete_biz->execute()) throw new Exception("Error deleting business: " . $stmt_delete_biz->error);
                $stmt_delete_biz->close();

                // 3. Delete image file if it exists
                if ($image_to_delete_path) {
                    $absolute_image_path = __DIR__ . '/../' . $image_to_delete_path; // Relative to project root
                    if (file_exists($absolute_image_path)) {
                        if (!unlink($absolute_image_path)) {
                            // Log this error, but don't necessarily fail the whole transaction
                            error_log("Failed to delete image file: " . $absolute_image_path);
                        }
                    }
                }

                $conn->commit(); // Commit transaction
                header("Location: businesses.php?delete_success=1");
                exit;

            } catch (Exception $e) {
                $conn->rollback(); // Rollback on error
                $error_message = "Deletion failed: " . htmlspecialchars($e->getMessage());
                // Redirect with a generic error or specific one
                header("Location: businesses.php?delete_error=" . urlencode($error_message));
                exit;
            }

        } else {
            // This means the business_id was valid on page load, but not on POST, or owner_id doesn't match
            $error_message = "Business not found or you do not have permission to delete it. Deletion aborted.";
            header("Location: businesses.php?delete_error=" . urlencode($error_message));
            exit;
        }
        $stmt_verify->close();
    }
} elseif ($business_id && $_SERVER["REQUEST_METHOD"] == "POST" && !$confirm_delete) {
    // This case would happen if the form was submitted without the confirm_delete_action,
    // e.g., if JS was disabled and the "No" button was clicked (if it were a submit button).
    // For this setup, it's mainly if the POST doesn't have the expected confirmation.
    header("Location: businesses.php"); // Redirect back if confirmation not explicitly given
    exit;
}

?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">
            <div class="card shadow-lg">
                <div class="card-header bg-danger text-white">
                    <h2 class="mb-0 text-center">Confirm Business Deletion</h2>
                </div>
                <div class="card-body p-4">
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <?php echo $error_message; ?>
                            <p class="mt-2"><a href="businesses.php" class="alert-link">Return to My Businesses</a></p>
                        </div>
                    <?php elseif ($business_id && $business_name_to_delete): ?>
                        <p class="lead">Are you absolutely sure you want to delete the business named:</p>
                        <h3 class="text-center my-3">"<?php echo htmlspecialchars($business_name_to_delete); ?>"</h3>
                        <p class="text-danger">This action is irreversible and will permanently delete all associated data, including any custom form entries and uploaded images.</p>
                        
                        <form action="delete-business.php?id=<?php echo $business_id; ?>" method="POST" class="mt-4">
                            <input type="hidden" name="confirm_delete_action" value="1">
                            <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                                <button type="submit" class="btn btn-danger btn-lg">Yes, Delete This Business</button>
                                <a href="businesses.php" class="btn btn-secondary btn-lg">No, Cancel</a>
                            </div>
                        </form>
                    <?php else: // Should not happen if logic is correct and $business_id was initially valid ?>
                        <div class="alert alert-warning">
                            Could not load business details for deletion. 
                            <a href="businesses.php" class="alert-link">Return to My Businesses</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

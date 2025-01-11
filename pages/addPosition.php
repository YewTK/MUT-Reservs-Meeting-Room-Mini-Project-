<?php
session_start();
include_once "../config/connection.php";
require_once "../config/config.php";

$db = new connectDB();
$db->InitSession('../index.php');
$_SESSION['pageName'] = "ManagePosition";

include_once "menus/navbar.php";

// Fetch existing positions from the database
$positions = $db->Query("SELECT * FROM POSITION");

// Handle form submission for adding a new position
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_position'])) {
    // Sanitize and validate input
    $positionID = trim($_POST['position_id']);
    $positionName = trim($_POST['position_name']);

    // Convert page access checkboxes into a binary string (e.g., '11000')
    $pageAccess = '';
    foreach ($perMissionName as $permission) {
        $pageAccess .= isset($_POST['page_access'][$permission]) ? '1' : '0';
    }

    if (!empty($positionID) && !empty($positionName)) {
        try {
            // Check for existing position with the same POSITIONID
            $checkIdQuery = "SELECT * FROM POSITION WHERE POSITIONID = :POSITIONID";
            $existingId = $db->Query($checkIdQuery, [':POSITIONID' => $positionID]);

            // Check for existing position with the same POSITIONNAME
            $checkNameQuery = "SELECT * FROM POSITION WHERE POSITIONNAME = :POSITIONNAME";
            $existingName = $db->Query($checkNameQuery, [':POSITIONNAME' => $positionName]);

            if (empty($existingId) && empty($existingName)) {
                // Prepare and execute the SQL statement
                $insertQuery = "INSERT INTO POSITION (POSITIONID, POSITIONNAME, PAGEACCESS) VALUES (:POSITIONID, :POSITIONNAME, :PAGEACCESS)";
                $db->nonQuery($insertQuery, [
                    ':POSITIONID' => $positionID,
                    ':POSITIONNAME' => $positionName,
                    ':PAGEACCESS' => $pageAccess
                ]);

                // Set success message
                $_SESSION['success'] = "Position added successfully.";
            } else {
                if (!empty($existingId)) {
                    $_SESSION['error'] = "Position ID already exists. Please use a different ID.";
                }
                if (!empty($existingName)) {
                    $_SESSION['error'] = "Position Name already exists. Please use a different name.";
                }
            }
        } catch (Exception $e) {
            // Set error message if something goes wrong
            $_SESSION['error'] = "Error adding position: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $_SESSION['error'] = "Position ID and Name are required.";
    }
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $Title; ?> Add Position</title>
    <link rel="stylesheet" href="../assets/css/manageEmp.css">
    <?php include_once "menus/header.php"; ?>
</head>

<body>
    <div id="free" style="height: 55px;"></div>
    <div class="profile-Title mt-3">
        <h2>ADD POSITION</h2>
    </div>
    <div class="container">
        <form method="post" action="">
            <div class="form-group">
                <label for="position_id">Position ID:</label>
                <input type="text" name="position_id" id="position_id" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="position_name">Position Name:</label>
                <input type="text" name="position_name" id="position_name" class="form-control" required>
            </div>
            <!-- Table for managing page access -->
            <div class="mt-3">
                <label>Set Page Access:</label>
                <table class="table table-bordered mt-3">
                    <thead>
                        <tr>
                            <?php foreach ($perMissionName as $per): ?>
                            <th><?php echo htmlspecialchars($per); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <?php foreach ($perMissionName as $per): ?>
                            <td><input type="checkbox" name="page_access[<?php echo htmlspecialchars($per); ?>]"
                                    value="1"></td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>
            </div>
            <button type="submit" name="add_position" class="btn btn-primary">Add Position</button>
            <button type="button" class="btn btn-secondary mt-2"
                onclick="window.location.href='manageBooking.php'">Cancel</button>
        </form>

        <!-- Display Existing Positions -->
        <h2 class="mt-4">Existing Positions</h2>
        <?php if (!empty($positions)): ?>
        <table class="table table-bordered mt-3">
            <thead>
                <tr>
                    <th scope="col">Position ID</th>
                    <th scope="col">Position Name</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($positions as $position): ?>
                <tr>
                    <td><?php echo htmlspecialchars($position['POSITIONID']); ?></td>
                    <td><?php echo htmlspecialchars($position['POSITIONNAME']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>No positions found.</p>
        <?php endif; ?>
    </div>
    <?php include_once "menus/footer.php"; ?>
</body>

</html>
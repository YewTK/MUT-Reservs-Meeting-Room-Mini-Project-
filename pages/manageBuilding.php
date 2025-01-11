<?php
ob_start(); // Start output buffering
session_start();
include_once "../config/connection.php";
require_once "../config/config.php";

$db = new connectDB();
$db->InitSession('../index.php');
$_SESSION['pageName'] = "manageBuilding";
include_once "menus/navbar.php";

$floor = "SELECT Floor.FLOORID, Floor.FLOORNUMBER, Building.BUILDINGID, Building.BUILDINGNAME
    FROM Floor 
    JOIN Building ON Floor.BUILDINGID = Building.BUILDINGID
    ORDER BY Floor.FloorID, Floor.FLOORNUMBER";
$floors = $db->Query($floor);

// Re-fetch data after operations
$building = "SELECT * FROM Building ORDER BY BUILDINGID";
$buildings = $db->Query($building);

// Handle form submission for adding a new building
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_building'])) {
    $query = "SELECT MAX(BuildingID) as MaxID FROM BUILDING";
    $stmt = $db->Query($query, false, false, true);
    $buildingID = $stmt["MAXID"] + 1; // Increment max ID
    $buildingName = trim($_POST['building_name']);

    if (!empty($buildingID) && !empty($buildingName)) {
        try {
            $checkNameQuery = "SELECT * FROM BUILDING WHERE BUILDINGNAME = ?";
            $existingName = $db->Query($checkNameQuery, [$buildingName]);

            if (empty($existingName)) {
                $insertQuery = "INSERT INTO BUILDING (BUILDINGID, BUILDINGNAME) VALUES (:BUILDINGID, :BUILDINGNAME)";
                $stmt = $db->nonQuery($insertQuery, [
                    ':BUILDINGID' => $buildingID,
                    ':BUILDINGNAME' => $buildingName
                ]);

                if ($stmt) {
                    $_SESSION['success'] = "Building added successfully.";
                } else {
                    $_SESSION['error'] = "Can't add building.";
                }
            } else {
                $_SESSION['error'] = "Building Name already exists. Please use a different name.";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error adding building: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $_SESSION['error'] = "Building ID and Name are required.";
    }
    $buildings = $db->Query($building);
}

// Handle form submission for editing a building
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_building'])) {
    $buildingID = $_POST['buildingID'];
    $buildingName = trim($_POST['building_name']);

    if (!empty($buildingID) && !empty($buildingName)) {
        try {
            $updateQuery = "UPDATE BUILDING SET BUILDINGNAME = :BUILDINGNAME WHERE BUILDINGID = :BUILDINGID";
            $stmt = $db->nonQuery($updateQuery, [
                ':BUILDINGNAME' => $buildingName,
                ':BUILDINGID' => $buildingID
            ]);

            if ($stmt) {
                $_SESSION['success'] = "Building updated successfully.";
            } else {
                $_SESSION['error'] = "Can't update building.";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error updating building: " . htmlspecialchars($e->getMessage());
        }
        $buildings = $db->Query($building);
    } else {
        $_SESSION['error'] = "Building ID and Name are required.";
    }
}

// Handle form submission for deleting a building
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn-delete-building'])) {
    $buildingID = $_POST['buildingID'];
    $stmt = $db->nonQuery("DELETE FROM BUILDING WHERE BUILDINGID = ?", [$buildingID]);

    if ($stmt) {
        $_SESSION['success'] = 'Building deleted successfully!';
    } else {
        $_SESSION['error'] = 'Error deleting building!';
    }
    $buildings = $db->Query($building);
}


// Handle form submission for adding a new floor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn-add-floor'])) {
    $buildingID = $_POST['buildingID'];
    $floorNumber = $_POST['floorNumber'];

    // Check if floorNumber is a valid number
    if (!is_numeric($floorNumber) || (int) $floorNumber <= 0) {
        $_SESSION['error'] = 'Please enter a valid floor number.';
    } else {
        $floorNumber = (int) $floorNumber; // Convert to integer

        // Check if the floor already exists in the selected building
        $checkFloorQuery = "SELECT * FROM FLOOR WHERE FLOORNUMBER = ? AND BUILDINGID = ?";
        $existingFloor = $db->Query($checkFloorQuery, [$floorNumber, $buildingID]);

        if (empty($existingFloor)) {
            $max = $db->Query("SELECT MAX(FLOORID) as MaxID FROM FLOOR", false, false, true);
            $FloorID = $max["MAXID"] + 1;

            $stmt = $db->nonQuery("INSERT INTO FLOOR (FLOORID, FLOORNUMBER, BUILDINGID) VALUES (?, ?, ?)", [$FloorID, $floorNumber, $buildingID]);
            $_SESSION['success'] = $stmt ? 'Floor added successfully!' : 'Error adding floor!';
        } else {
            $_SESSION['error'] = 'This floor number already exists in the selected building!';
        }
    }

    $floors = $db->Query($floor);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['btn-delete-floor'])) {
    ob_clean(); // Clear output buffer
    header('Content-Type: application/json; charset=utf-8');

    $FID = $_POST['floorID'];
    $stmt = $db->nonQuery("DELETE FROM Floor WHERE FloorID = ?", [$FID]);

    if ($stmt) {
        // Deletion was successful
        echo json_encode(['success' => true, 'message' => "Floor deleted successfully"]);
    } else {
        // Deletion failed
        echo json_encode(['success' => false, 'message' => "Error deleting floor."]);
    }

    exit(); // Ensure no further output is sent
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search'])) {
    ob_clean(); // Clear buffer to avoid unexpected output
    header('Content-Type: application/json; charset=utf-8'); // Set the content type to JSON

    $buildingID = $_POST['buildingID']; // Use null coalescing operator to avoid notices
    $floorNumber = $_POST['floorNumber'];

    // Build query for searching
    $searchQuery = "SELECT Floor.FLOORID, Floor.FLOORNUMBER, Building.BUILDINGNAME 
                    FROM Floor 
                    JOIN Building ON Floor.BUILDINGID = Building.BUILDINGID 
                    ";

    $params = [];

    if (!empty($buildingID)) {
        $searchQuery .= " AND Building.BUILDINGID = :buildingID ORDER BY Floor.FLOORID";
        $params[':buildingID'] = $buildingID;
    }
    if (!empty($floorNumber)) {
        $searchQuery .= " AND Floor.FLOORNUMBER LIKE :floorNumber ORDER BY Floor.FLOORID";
        $params[':floorNumber'] = '%' . $floorNumber . '%';
    }

    try {
        $floors = $db->Query($searchQuery, $params, true);
        echo json_encode(['success' => true, 'data' => $floors ?: []]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => htmlspecialchars($e->getMessage())]);
    }
    exit(); // Stop further execution
}

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <title><?php echo htmlspecialchars($Title); ?> - Manage Building</title>
    <?php include_once "menus/header.php"; ?>
</head>

<body>
    <div class="f-1">
        <div class="box-title">
            <span class="">Manage Building</span>
        </div>
        <div class="container">
            <div class="report-container">
                <form method="post" action="">
                    <h2>Add Building</h2>
                    <div class="form-group">
                        <label for="building_name">Building Name:</label>
                        <input type="text" name="building_name" id="building_name" class="form-control" required>
                    </div>
                    <button type="submit" name="add_building" class="btn btn-primary mt-3">
                        <i class="fas fa-building"></i> Add Building
                    </button>
                </form>
            </div>

            <div class="report-container-table table-responsive mt-3">
                <h2>Existing Buildings</h2>
                <table class="table table-bordered mt-3">
                    <thead>
                        <tr>
                            <th>Building ID</th>
                            <th>Building Name</th>
                            <th>Edit</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($buildings as $building): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($building['BUILDINGID']); ?></td>
                                <td><?php echo htmlspecialchars($building['BUILDINGNAME']); ?></td>
                                <td>
                                    <button class="btn btn-warning btn-edit-building"
                                        data-buildingid="<?php echo htmlspecialchars($building['BUILDINGID']); ?>"
                                        data-buildingname="<?php echo htmlspecialchars($building['BUILDINGNAME']); ?>">Edit</button>
                                </td>
                                <td>
                                    <button class="btn btn-danger btn-delete-building"
                                        data-buildingid="<?php echo htmlspecialchars($building['BUILDINGID']); ?>">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Add Floor Section -->
            <div class="report-container mt-4">
                <h2>Add Floor</h2>
                <form method="post" action="" id="add-floor-form">
                    <div class="form-group">
                        <label for="buildingID">Select Building:</label>
                        <select class="form-control" id="buildingID" name="buildingID">
                            <option value="">Select Building</option>
                            <?php foreach ($buildings as $building): ?>
                                <option value="<?php echo htmlspecialchars($building['BUILDINGID']); ?>">
                                    <?php echo htmlspecialchars($building['BUILDINGNAME']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group mt-3">
                        <label for="floorNumber">Floor Number:</label>
                        <input type="text" name="floorNumber" class="form-control" id="floorNumber" required>
                    </div>
                    <div class="mt-3">
                        <button type="submit" name="btn-add-floor" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Floor
                        </button>
                        <button type="button" id="search-btn" class="btn btn-secondary">
                            <i class="fas fa-search"></i> Search Building
                        </button>
                    </div>
                </form>
            </div>

            <!-- Floors Table -->
            <div class="report-container-table table-responsive mt-3">
                <h2>Existing Floors</h2>
                <table class="table table-bordered mt-3">
                    <thead>
                        <tr>
                            <th>Floor ID</th>
                            <th>Floor Number</th>
                            <th>Building Name</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tbody id="floors-table-body">
                        <?php foreach ($floors as $floor): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($floor['FLOORID']); ?></td>
                                <td><?php echo htmlspecialchars($floor['FLOORNUMBER']); ?></td>
                                <td><?php echo htmlspecialchars($floor['BUILDINGNAME']); ?></td>
                                <td>
                                    <button class="btn btn-danger btn-delete-floor"
                                        data-floorid="<?php echo htmlspecialchars($floor['FLOORID']); ?>">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php include_once "menus/footer.php"; ?>
        </div>
    </div>

    <script>
        $(document).ready(function () {
            $('#search-btn').click(function (e) {
                e.preventDefault();

                let buildingID = $('#buildingID').val();
                let floorNumber = $('#floorNumber').val();

                $.ajax({
                    url: 'manageBuilding.php', // Ensure this is the correct URL
                    method: 'POST',
                    data: {
                        search: true,
                        buildingID: buildingID,
                        floorNumber: floorNumber
                    },
                    success: function (response) {
                        try {
                            // Check if response is an object; if not, parse it
                            const result = typeof response === 'object' ? response : JSON.parse(
                                response);

                            if (result.success) {
                                const floors = result.data;
                                $('#floors-table-body').empty();

                                if (floors.length > 0) {
                                    floors.forEach(floor => {
                                        $('#floors-table-body').append(`
                                    <tr>
                                        <td>${floor.FLOORID}</td>
                                        <td>${floor.FLOORNUMBER}</td>
                                        <td>${floor.BUILDINGNAME}</td>
                                        <td>
                                            <button class="btn btn-danger btn-delete-floor" data-floorid="${floor.FLOORID}">Delete</button>
                                        </td>
                                    </tr>
                                `);
                                    });
                                } else {
                                    $('#floors-table-body').append(`
                                <tr>
                                    <td colspan="4">No floors found.</td>
                                </tr>
                            `);
                                }
                            } else {
                                Swal.fire('Error!', result.error, 'error');
                            }
                        } catch (error) {
                            console.error('Error parsing response:', error);
                            Swal.fire('Error!',
                                'An error occurred while processing your request.', 'error');
                        }
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        console.error('AJAX error:', textStatus, errorThrown);
                        Swal.fire('Error!', 'Could not search floors. Please try again.',
                            'error');
                    }
                });
            });
        });


        document.querySelectorAll('.btn-edit-building').forEach(function (button) {
            button.addEventListener('click', function () {
                var buildingID = this.getAttribute('data-buildingid');
                var buildingName = this.getAttribute('data-buildingname');

                Swal.fire({
                    title: 'Edit Building Name',
                    input: 'text',
                    inputValue: buildingName,
                    showCancelButton: true,
                    confirmButtonText: 'Save',
                    cancelButtonText: 'Cancel',
                    preConfirm: (newBuildingName) => {
                        if (!newBuildingName) {
                            Swal.showValidationMessage('Please enter a building name');
                        }
                        return newBuildingName;
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '';
                        var inputID = document.createElement('input');
                        inputID.type = 'hidden';
                        inputID.name = 'buildingID';
                        inputID.value = buildingID;
                        var inputName = document.createElement('input');
                        inputName.type = 'hidden';
                        inputName.name = 'building_name';
                        inputName.value = result.value;
                        var editBtn = document.createElement('input');
                        editBtn.type = 'hidden';
                        editBtn.name = 'edit_building';
                        editBtn.value = '1';
                        form.appendChild(inputID);
                        form.appendChild(inputName);
                        form.appendChild(editBtn);
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
        });
        // Handle delete floor button click
        $(document).on('click', '.btn-delete-floor', function () {
            const floorID = $(this).data('floorid');
            confirmDelete(floorID);
        });

        function confirmDelete(floorID) {
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                timerProgressBar: true,
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '', // Use current PHP file
                        method: 'POST',
                        data: {
                            'btn-delete-floor': true,
                            'floorID': floorID
                        },
                        success: function (response) {
                            Swal.fire({
                                position: 'center',
                                icon: response.success ? 'success' : 'error',
                                title: response.message,
                                timerProgressBar: true,
                                showConfirmButton: true,
                                timer: 2000
                            }).then(() => {
                                if (response.success) {
                                    // Remove the deleted floor row from the table
                                    $(`button[data-floorid="${floorID}"]`).closest('tr')
                                        .remove();
                                }
                            });
                        },
                        error: function () {
                            Swal.fire('Error!', 'Could not delete the floor. Please try again.',
                                'error');
                        }
                    });
                }
            });
        }

        document.querySelectorAll('.btn-delete-building').forEach(function (button) {
            button.addEventListener('click', function () {
                var buildingID = this.getAttribute('data-buildingid');
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'You won\'t be able to revert this!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '';
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'buildingID';
                        input.value = buildingID;
                        var deleteBtn = document.createElement('input');
                        deleteBtn.type = 'hidden';
                        deleteBtn.name = 'btn-delete-building';
                        deleteBtn.value = '1';
                        form.appendChild(input);
                        form.appendChild(deleteBtn);
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
        });
    </script>
</body>

</html>
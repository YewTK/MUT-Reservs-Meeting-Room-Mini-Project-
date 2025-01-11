<?php
session_start();
include_once "../config/connection.php";
require_once "../config/config.php";

$db = new connectDB();
$db->InitSession('../index.php');
$_SESSION['pageName'] = "manageMtgRm";
include_once "menus/navbar.php";

// Permission check
if (!isset($permissions) || $permissions[4] != '1') {
    header('Location: home.php');
    exit();
}

// Handle search filters and initialize variables
$buildingFilter = $_POST['building'] ?? '';
$roomNameFilter = $_POST['roomName'] ?? '';
$floorFilter = $_POST['floor'] ?? '';
$typeroomFilter = $_POST['typeroom'] ?? '';
$capacityFilter = $_POST['capacity'] ?? '';

$whereClauses = [];
$params = [];

if (!empty($buildingFilter)) {
    $whereClauses[] = 'f.BUILDINGID = ?';
    $params[] = $buildingFilter;
}

if (!empty($roomNameFilter)) {
    $whereClauses[] = 'mr.ROOMID = ?';
    $params[] = $roomNameFilter;
}

if (!empty($floorFilter)) {
    $whereClauses[] = 'f.FLOORID = ?';
    $params[] = $floorFilter;
}

if (!empty($typeroomFilter)) {
    $whereClauses[] = 'mr.TYPEID = ?';
    $params[] = $typeroomFilter;
}

if (!empty($capacityFilter)) {
    $whereClauses[] = 'mr.CAPACITY >= ?';
    $params[] = $capacityFilter;
}

$whereSql = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Query to get meeting room data with optional filters
$meetingroom = $db->Query("
    SELECT mr.ROOMID, mr.ROOMNAME, mr.ROOMIMG, mr.CAPACITY, mr.STATEID,
               b.BUILDINGNAME, f.FLOORNUMBER, tr.TYPENAME , mrs.STATEDESCRIPTION
        FROM MeetingRoom mr
        JOIN Floor f ON mr.FLOORID = f.FLOORID
        JOIN Building b ON f.BUILDINGID = b.BUILDINGID
        JOIN TypeRoom tr ON mr.TYPEID = tr.TYPEID
        JOIN MeetingRoomState mrs ON mrs.STATEID = mr.STATEID
    $whereSql
", $params, count($whereClauses) > 0 ? true : false);

// Ensure meetingroom data is an array
if (!is_array($meetingroom)) {
    $meetingroom = []; // Set empty array to avoid errors
}

// Fetch building, floor, and room type data for the filters
$buildings = $db->Query("SELECT * FROM Building");
$typerooms = $db->Query("SELECT * FROM TypeRoom");

// Handle room deletion
if (isset($_POST['btn-delete-room'])) {
    $roomID = $_POST['roomID'];
    $stmt = $db->nonQuery("DELETE FROM MeetingRoom WHERE ROOMID = ?", [$roomID]);

    if ($stmt) {
        $_SESSION['success'] = 'Room deleted successfully!';
    } else {
        $_SESSION['error'] = 'Error deleting room!';
    }

    // Re-run the query after deletion to update the results
    $meetingroom = $db->Query("
        SELECT mr.ROOMID, mr.ROOMNAME, mr.ROOMIMG, mr.CAPACITY, mr.STATEID,
               b.BUILDINGNAME, f.FLOORNUMBER, tr.TYPENAME , mrs.STATEDESCRIPTION
        FROM MeetingRoom mr
        JOIN Floor f ON mr.FLOORID = f.FLOORID
        JOIN Building b ON f.BUILDINGID = b.BUILDINGID
        JOIN TypeRoom tr ON mr.TYPEID = tr.TYPEID
        JOIN MeetingRoomState mrs ON mrs.STATEID = mr.STATEID
        $whereSql
    ", $params);
}

// AJAX Request to fetch floors based on building selection
if (isset($_POST['buildingID'])) {
    $buildingID = $_POST['buildingID'];
    $floors = $db->Query("SELECT * FROM Floor WHERE BUILDINGID = ?", [$buildingID], true);

    if ($floors) {
        foreach ($floors as $floor) {
            echo '<option value="' . htmlspecialchars($floor['FLOORID']) . '">' . htmlspecialchars($floor['FLOORNUMBER']) . '</option>';
        }
    } else {
        echo '<option value="">No floors found</option>';
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title>Room Management</title>
    <?php include_once "menus/header.php" ?>
</head>

<body>
    <div class="f-1">
        <div class="box-title">
            <span class="">ROOM MANAGEMENT</span>
        </div>
        <div class="mb-2">
            <form action="addRoom.php" method="post">
                <button type="submit" class="btn text-primary border border-2" name="btn-addRoom">
                    <i class="fas fa-user-plus"></i> ADD ROOM
                </button>
            </form>
        </div>
        <div class="mb-2">
            <form action="addDetail.php" method="post">
                <button type="submit" class="btn text-primary border border-2" name="btn-addDetail">
                    <i class="fas fa-user-plus"></i> ADD ROOM Detail
                </button>
            </form>
        </div>

        <div class="container">
            <div class="report-container">
                <div class="container mt-4">
                    <!-- Meeting room filter form -->
                    <form method="post" action="">
                        <div class="row mb-3">
                            <div class="col">
                                <select class="form-select" id="building" name="building"
                                    onchange="fetchFloors(this.value)">
                                    <option value="">All Building</option>
                                    <?php foreach ($buildings as $building): ?>
                                    <option value="<?php echo htmlspecialchars($building['BUILDINGID']); ?>"
                                        <?php echo ($buildingFilter == $building['BUILDINGID']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($building['BUILDINGNAME']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col">
                                <select class="form-select" id="roomName" name="roomName">
                                    <option value="">All Room</option>
                                    <?php foreach ($meetingroom as $room): ?>
                                    <option value="<?php echo htmlspecialchars($room['ROOMID']); ?>"
                                        <?php echo ($roomNameFilter == $room['ROOMID']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($room['ROOMNAME']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col">
                                <select class="form-select" id="floor" name="floor" disabled>
                                    <option value="">Select Building First</option>
                                    <?php foreach ($floors as $floor): ?>
                                    <option value="<?php echo htmlspecialchars($floor['FLOORID']); ?>"
                                        <?php echo ($floorFilter == $floor['FLOORID']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($floor['FLOORNUMBER']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col">
                                <select class="form-select" id="typeroom" name="typeroom">
                                    <option value="">All Typeroom</option>
                                    <?php foreach ($typerooms as $typeroom): ?>
                                    <option value="<?php echo htmlspecialchars($typeroom['TYPEID']); ?>"
                                        <?php echo ($typeroomFilter == $typeroom['TYPEID']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($typeroom['TYPENAME']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col">
                            <input type="text" class="form-control" name="capacity" placeholder="Capacity" id="capacity"
                                value="<?php echo htmlspecialchars($capacityFilter); ?>" maxlength="5">
                        </div>

                        <div class="col-auto d-flex justify-content-end mt-3">
                            <button type="submit" class="btn btn-primary" id="search-btn" name="search-btn">
                                <i class="fas fa-magnifying-glass"></i> SEARCH
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Meeting room table -->
            <div class="report-container-table mt-3 table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th scope="col" class="text-center">NO</th>
                            <th scope="col" class="text-center">ID</th>
                            <th scope="col" class="text-center">ROOM IMG</th>
                            <th scope="col" class="text-center">ROOM NAME</th>
                            <th scope="col" class="text-center">BUILDING</th>
                            <th scope="col" class="text-center">FLOOR</th>
                            <th scope="col" class="text-center">CAPACITY</th>
                            <th scope="col" class="text-center">TYPE</th>
                            <th scope="col" class="text-center">STATE</th>
                            <th scope="col" class="text-center">EDIT</th>
                            <th scope="col" class="text-center">DELETE</th>
                        </tr>
                    </thead>
                    <tbody id="meetingroom-table-body">
                        <?php if (is_array($meetingroom) && count($meetingroom) > 0): ?>
                        <?php 
                                $i = 1;
                                foreach ($meetingroom as $room): ?>
                        <tr>
                            <td class="text-center"><?php echo htmlspecialchars($i++); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($room['ROOMID']); ?></td>
                            <td class="text-center"><img src="<?php echo htmlspecialchars($room['ROOMIMG']); ?>"
                                    alt="Room Image" width="120"></td>
                            <td class="text-center"><?php echo htmlspecialchars($room['ROOMNAME']); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($room['BUILDINGNAME']); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($room['FLOORNUMBER']); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($room['CAPACITY']); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($room['TYPENAME']); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($room['STATEDESCRIPTION']); ?></td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center">
                                    <form action="editRoom.php" method="post">
                                        <button type="submit" class="btn btn-secondary" name="btn-edit-room">
                                            <i class="far fa-pen-to-square"></i>
                                        </button>
                                        <input type="hidden" name="roomID"
                                            value="<?php echo htmlspecialchars($room['ROOMID']); ?>">
                                    </form>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center">
                                    <!-- Swal Delete Confirmation Trigger -->
                                    <button type="button" class="btn btn-danger btn-delete"
                                        data-roomid="<?php echo htmlspecialchars($room['ROOMID']); ?>">
                                        <i class="far fa-trash-can"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="11" class="text-center">No meeting rooms found</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
    function fetchFloors(buildingID) {
        if (buildingID) {
            $.ajax({
                url: '', // Current script
                type: 'POST',
                data: {
                    buildingID: buildingID
                },
                success: function(response) {
                    $('#floor').html(response); // Update the floor dropdown
                    $('#floor').prop('disabled', false); // Enable the floor dropdown
                }
            });
        } else {
            $('#floor').html('<option value="">Select Building First</option>'); // Reset floor dropdown
            $('#floor').prop('disabled', true); // Disable the floor dropdown
        }
    }

    document.querySelectorAll('.btn-delete').forEach(function(button) {
        button.addEventListener('click', function() {
            var roomID = this.getAttribute('data-roomid');
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
                    // Create a form and submit it to delete the room
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'roomID';
                    input.value = roomID;
                    var deleteBtn = document.createElement('input');
                    deleteBtn.type = 'hidden';
                    deleteBtn.name = 'btn-delete-room';
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
    <?php include_once "menus/footer.php"; ?>
</body>

</html>
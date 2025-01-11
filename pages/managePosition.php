<?php
ob_start(); // Start output buffering
session_start();
include_once "../config/connection.php";
require_once "../config/config.php";

$db = new connectDB();
$db->InitSession('../index.php');
$_SESSION['pageName'] = "managePosition";
include_once "menus/navbar.php";

// Get positions from the database, including PAGEACCESS
try {
    $positions = $db->Query("SELECT * FROM POSITION");
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    $positions = [];
}

function updatePageAccess($db, $positionID, $permissions)
{
    
        // Ensure $permissions is an array
        if (!is_array($permissions)) {
            throw new Exception("Invalid permissions format");
        }

        $permissionString = str_repeat('0', 13); // Initialize with '0's
        foreach ($permissions as $key => $value) {
            if ($value == '1' && isset($permissionString[$key])) {
                $permissionString[$key] = '1';
            }
        }

        $updateQuery = "UPDATE POSITION SET PAGEACCESS = :pageaccess WHERE POSITIONID = :positionid";
        return $db->nonQuery($updateQuery, [
            ':pageaccess' => $permissionString,
            ':positionid' => $positionID
        ]);
    }


// Handle permissions update
if (isset($_POST['update-permissions'])) {
    if (isset($_POST['PERMISSIONS']) && is_array($_POST['PERMISSIONS'])) {
        $positionPermissions = $_POST['PERMISSIONS'];
        $updateSuccess = true;

        foreach ($positionPermissions as $positionID => $permissions) {
            if (!updatePageAccess($db, $positionID, $permissions)) {
                $updateSuccess = false;
                break;
            }
        }

        if ($updateSuccess) {
            $_SESSION['success'] = "อัปเดตสิทธิ์เรียบร้อยแล้ว";
        }
    } else {
        $_SESSION['error'] = "No permissions were selected or invalid format.";
    }
}

// Handle position deletion
if (isset($_POST['delete-position'])){

        $positionID = $_POST['position_id'];
        
        // Check for existing employees with this position
        $checkEmployee = "SELECT POSITIONID FROM EMPLOYEE WHERE POSITIONID = ?";
        $result = $db->Query($checkEmployee, [$positionID]);
        
        if ($result == null) {
            // No employees found, safe to delete
            $deleteQuery = "DELETE FROM POSITION WHERE POSITIONID = ?";
            $success = $db->nonQuery($deleteQuery, [$positionID]);
            
            if ($success) {
                $_SESSION['success'] = "ลบตำแหน่งเรียบร้อยแล้ว";
            } else {
                $_SESSION['error'] = "ไม่สามารถลบตำแหน่งนี้ได้!";
            }
        } else {
            $_SESSION['error'] = "ยังมีพนักงานอยู่ในตำแหน่งนี้ทำให้ไม่สามารถลบได้!";
        }
    
}

// Fetch existing positions from the database
$positions = $db->Query("SELECT * FROM POSITION");

// Handle form submission for adding a new position
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_position'])) {
    // Sanitize and validate input
    $query = "SELECT MAX(PositionID) as MaxID FROM Position";
    $stmt = $db->Query($query, false, false, true);
    $positionID = $stmt["MAXID"];
    $positionID++;
    $positionName = trim($_POST['position_name']);

    // Convert page access checkboxes into a binary string (e.g., '11000')
    $pageAccess = '';
    foreach ($perMissionName as $permission) {
        $pageAccess .= isset($_POST['page_access'][$permission]) ? '1' : '0';
    }

    if (!empty($positionID) && !empty($positionName)) {
        try {
            // Check for existing position with the same POSITIONID
            $checkIdQuery = "SELECT * FROM POSITION WHERE POSITIONID = ?";
            $existingId = $db->Query($checkIdQuery, [$positionID]);

            // Check for existing position with the same POSITIONNAME
            $checkNameQuery = "SELECT * FROM POSITION WHERE POSITIONNAME = ?";
            $existingName = $db->Query($checkNameQuery, [$positionName]);

            if (empty($existingId) && empty($existingName)) {
                // Prepare and execute the SQL statement
                $insertQuery = "INSERT INTO POSITION (POSITIONID, POSITIONNAME, PAGEACCESS) VALUES (?, ?, ?)";
                $stmt = $db->nonQuery($insertQuery, [
                    $positionID,
                    $positionName,
                    $pageAccess // Use the constructed binary string
                ]);

                // Set success message
                if ($stmt) {
                    $_SESSION['success'] = "Position added successfully.";
                    // รีเฟรชตำแหน่งที่อัปเดตใหม่เพื่อแสดงในตาราง
                    $positions = $db->Query("SELECT * FROM POSITION");
                } else {
                    $_SESSION['error'] = "Can't Added Position";
                }

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

// Handle form submission for editing a position
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_position'])) {
    $positionID = $_POST['positionID'];
    $positionName = trim($_POST['position_name']);

    if (!empty($positionID) && !empty($positionName)) {
        try {
            $updateQuery = "UPDATE POSITION SET POSITIONNAME = :POSITIONNAME WHERE POSITIONID = :POSITIONID";
            $stmt = $db->nonQuery($updateQuery, [
                ':POSITIONNAME' => $positionName,
                ':POSITIONID' => $positionID
            ]);

            if ($stmt) {
                $_SESSION['success'] = "Position updated successfully.";
            } else {
                $_SESSION['error'] = "Can't update position.";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error updating position: " . htmlspecialchars($e->getMessage());
        }
        $positions = $db->Query("SELECT * FROM POSITION");
    } else {
        $_SESSION['error'] = "Position ID and Name are required.";
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <title><?php echo htmlspecialchars($Title); ?> - Manage Position</title>
    <?php include_once "menus/header.php"; ?>
</head>

<body>
    <div class="f-1">
        <div class="box-title">
            <span class="">Manage Position</span>
        </div>
        <div class="container">
            <div class="report-container">
                <form method="post" action="">
                    <div class="form-group">
                        <label for="position_name">Position Name:</label>
                        <input type="text" name="position_name" id="position_name" class="form-control" required>
                    </div>
                    <!-- Table for managing page access -->
                    <div class="table-responsive mt-3 mb-3">
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
                                    <?php $i = 0;
                                    foreach ($perMissionName as $per): ?>
                                    <td><input type="checkbox" name="page_access[<?php echo htmlspecialchars($per); ?>]"
                                            value="1" <?php echo (isset($per) && $i < 3) ? 'checked' : '';
                                                $i++; ?>></td>
                                    <?php endforeach; ?>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" name="add_position" class="btn btn-primary"> <i class="fas fa-user-plus"></i>
                        Add
                        Position</button>
                </form>
            </div>

            <div class="container">
                <div class="report-container">
                    <div class="container mt-4">
                        <form method="post" action="">
                            <div class="table-responsive mb-3">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th scope="col">NO</th>
                                            <th scope="col">PositionID</th>
                                            <th scope="col">Position</th>
                                            <?php foreach ($perMissionName as $name): ?>
                                            <th scope="col"><?php echo $name; ?></th>
                                            <?php endforeach; ?>
                                            <th scope="col">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $index = 1;
                                        // print_r($positions);
                                        foreach ($positions as $position):
                                            $permissions = str_split($position['PAGEACCESS']); // แบ่ง PAGEACCESS เป็น array
                                            ?>
                                        <tr>
                                            <td><?php echo $index++; ?></td>
                                            <td><?php echo htmlspecialchars($position['POSITIONID']); ?></td>
                                            <td>
                                                <a href="javascript:void(0)" class="btn btn-warning btn-edit-position"
                                                    data-positionid="<?php echo htmlspecialchars($position['POSITIONID']); ?>"
                                                    data-positionname="<?php echo htmlspecialchars($position['POSITIONNAME']); ?>"><?php echo htmlspecialchars($position['POSITIONNAME']); ?></a>
                                            </td>
                                            <?php for ($i = 0; $i < count($perMissionName); $i++): // ใช้ count($permissionname) ?>
                                            <td>
                                                <input type="hidden"
                                                    name="PERMISSIONS[<?php echo htmlspecialchars($position['POSITIONID']); ?>][<?php echo $i; ?>]"
                                                    value="0">
                                                <input type="checkbox"
                                                    name="PERMISSIONS[<?php echo htmlspecialchars($position['POSITIONID']); ?>][<?php echo $i; ?>]"
                                                    value="1"
                                                    <?php echo (isset($permissions[$i]) && $permissions[$i] == '1') ? 'checked' : ''; ?>>
                                            </td>
                                            <?php endfor; ?>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-danger delete-btn"
                                                    data-positionid="<?php echo htmlspecialchars($position['POSITIONID']); ?>">
                                                    <i class="fas fa-trash-can"></i> Delete
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <button type="submit" class="btn btn-primary" name="update-permissions">
                                <i class="far fa-pen-to-square"></i> Update Permissions</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php include_once "menus/footer.php"; ?>

        <script>
        $(document).on('click', '.btn-edit-position', function() {
            var positionID = this.getAttribute('data-positionid');
            var positionName = this.getAttribute('data-positionname');
            Swal.fire({
                title: 'Edit Position Name',
                input: 'text',
                inputValue: positionName,
                showCancelButton: true,
                confirmButtonText: 'Save',
                cancelButtonText: 'Cancel',
                preConfirm: (newPositionName) => {
                    if (!newPositionName) {
                        Swal.showValidationMessage('Please enter a position name');
                    }
                    return newPositionName;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';
                    var inputID = document.createElement('input');
                    inputID.type = 'hidden';
                    inputID.name = 'positionID';
                    inputID.value = positionID;
                    var inputName = document.createElement('input');
                    inputName.type = 'hidden';
                    inputName.name = 'position_name';
                    inputName.value = result.value;
                    var editBtn = document.createElement('input');
                    editBtn.type = 'hidden';
                    editBtn.name = 'edit_position';
                    editBtn.value = '1';
                    form.appendChild(inputID);
                    form.appendChild(inputName);
                    form.appendChild(editBtn);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        })

        // document.querySelectorAll('.btn-edit-position').forEach(function (button) {
        //     button.addEventListener('click', function () {
        //         var positionID = this.getAttribute('data-positionid');
        //         var positionName = this.getAttribute('data-positionname');

        //         Swal.fire({
        //             title: 'Edit Position Name',
        //             input: 'text',
        //             inputValue: positionName,
        //             showCancelButton: true,
        //             confirmButtonText: 'Save',
        //             cancelButtonText: 'Cancel',
        //             preConfirm: (newPositionName) => {
        //                 if (!newPositionName) {
        //                     Swal.showValidationMessage('Please enter a position name');
        //                 }
        //                 return newPositionName;
        //             }
        //         }).then((result) => {
        //             if (result.isConfirmed) {
        //                 var form = document.createElement('form');
        //                 form.method = 'POST';
        //                 form.action = '';
        //                 var inputID = document.createElement('input');
        //                 inputID.type = 'hidden';
        //                 inputID.name = 'positionID';
        //                 inputID.value = positionID;
        //                 var inputName = document.createElement('input');
        //                 inputName.type = 'hidden';
        //                 inputName.name = 'position_name';
        //                 inputName.value = result.value;
        //                 var editBtn = document.createElement('input');
        //                 editBtn.type = 'hidden';
        //                 editBtn.name = 'edit_position';
        //                 editBtn.value = '1';
        //                 form.appendChild(inputID);
        //                 form.appendChild(inputName);
        //                 form.appendChild(editBtn);
        //                 document.body.appendChild(form);
        //                 form.submit();
        //             }
        //         });
        //     });
        // });
        // Swal Confirmation for Delete
        document.querySelectorAll('.delete-btn').forEach(button => {
            button.addEventListener('click', function() {
                const positionID = this.dataset.positionid;
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Submit the form with the selected positionID
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = ''; // current page
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'position_id';
                        input.value = positionID;
                        form.appendChild(input);

                        const deleteInput = document.createElement('input');
                        deleteInput.type = 'hidden';
                        deleteInput.name = 'delete-position';
                        deleteInput.value = true;
                        form.appendChild(deleteInput);

                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
        });
        </script>
</body>

</html>
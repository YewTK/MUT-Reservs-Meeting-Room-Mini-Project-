<?php
session_start();
include_once "../config/connection.php";
require_once "../config/config.php";

$db = new connectDB();
$db->InitSession('../index.php');
$_SESSION['pageName'] = "manageEmp";
include_once "menus/navbar.php";

// Fetch existing Detail from the database
$Detail = $db->Query("SELECT * FROM Detail");

// Handle form submission for adding a new Detail
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_Detail'])) {
    $query = "SELECT MAX(DetailID) as MaxID FROM Detail";
    $stmt = $db->Query($query, false, false, true);
    $DetailID = $stmt["MAXID"] + 1; // Increment max ID
    $NameStuff = trim($_POST['Detail_name']);

    if (!empty($DetailID) && !empty($NameStuff)) {
        try {
            $checkNameQuery = "SELECT * FROM Detail WHERE NAMESTUFF = ?";
            $existingName = $db->Query($checkNameQuery, [$NameStuff]);

            if (empty($existingName)) {
                $insertQuery = "INSERT INTO Detail (DETAILID, NAMESTUFF) VALUES (:DETAILID, :NAMESTUFF)";
                $stmt = $db->nonQuery($insertQuery, [
                    ':DETAILID' => $DetailID,
                    ':NAMESTUFF' => $NameStuff
                ]);

                if ($stmt) {
                    $_SESSION['success'] = "Detail added successfully.";
                } else {
                    $_SESSION['error'] = "Can't add Detail.";
                }
            } else {
                $_SESSION['error'] = "NameStuff already exists. Please use a different name.";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error adding Detail: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $_SESSION['error'] = "Detail ID and Name are required.";
    }
    $Detail = $db->Query("SELECT * FROM Detail");
}

// Handle form submission for editing a Detail
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_Detail'])) {
    $detailID = $_POST['DetailID'];
    $nameStuff = trim($_POST['Detail_name']);

    if (!empty($detailID) && !empty($nameStuff)) {
        try {
            $updateQuery = "UPDATE Detail SET NAMESTUFF = :NAMESTUFF WHERE DETAILID = :DETAILID";
            $stmt = $db->nonQuery($updateQuery, [
                ':NAMESTUFF' => $nameStuff,
                ':DETAILID' => $detailID
            ]);

            if ($stmt) {
                $_SESSION['success'] = "Detail updated successfully.";
            } else {
                $_SESSION['error'] = "Can't update Detail.";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error updating Detail: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $_SESSION['error'] = "Detail ID and Name are required.";
    }
    $Detail = $db->Query("SELECT * FROM Detail");
}

// Handle form submission for deleting a Detail
if (isset($_POST['btn-delete-Detail'])) {
    $detailID = $_POST['DetailID'];
    $stmt = $db->nonQuery("DELETE FROM Detail WHERE DETAILID = ?", [$detailID]);

    if ($stmt) {
        $_SESSION['success'] = 'Detail deleted successfully!';
    } else {
        $_SESSION['error'] = 'Error deleting Detail!';
    }
    $Detail = $db->Query("SELECT * FROM Detail");
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title><?php echo htmlspecialchars($Title); ?> - Manage Detail</title>
    <?php require_once "menus/header.php"; ?>
</head>

<body>
    <div class="f-1">
        <div class="box-title">
            <span class="">ADD Detail</span>
        </div>
        <div class="container">
            <div class="form-container">
                <form method="post" action="">
                    <div class="form-group">
                        <label for="Detail_name">Name Stuff:</label>
                        <input type="text" name="Detail_name" id="Detail_name" class="form-control" required>
                    </div>
                    <div class="mt-4">
                        <button type="submit" name="add_Detail" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Detail
                        </button>
                    </div>
                </form>
            </div>

            <!-- Details Table -->
            <div class="form-container-table table-responsive mt-3">
                <h2>Existing Details</h2>
                <table class="table table-bordered mt-3">
                    <thead>
                        <tr>
                            <th>Detail ID</th>
                            <th>Name Stuff</th>
                            <th>Edit</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($Detail as $detail): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($detail['DETAILID']); ?></td>
                                <td><?php echo htmlspecialchars($detail['NAMESTUFF']); ?></td>
                                <td>
                                    <button class="btn btn-warning btn-edit-detail"
                                        data-detailid="<?php echo htmlspecialchars($detail['DETAILID']); ?>"
                                        data-detailname="<?php echo htmlspecialchars($detail['NAMESTUFF']); ?>">Edit</button>
                                </td>
                                <td>
                                    <button class="btn btn-danger btn-delete-detail"
                                        data-detailid="<?php echo htmlspecialchars($detail['DETAILID']); ?>">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php include_once "menus/footer.php"; ?>
    <script>
        document.querySelectorAll('.btn-edit-detail').forEach(function (button) {
            button.addEventListener('click', function () {
                var detailID = this.getAttribute('data-detailid');
                var detailName = this.getAttribute('data-detailname');

                Swal.fire({
                    title: 'Edit Name Stuff',
                    input: 'text',
                    inputValue: detailName,
                    showCancelButton: true,
                    confirmButtonText: 'Save',
                    cancelButtonText: 'Cancel',
                    preConfirm: (newDetailName) => {
                        if (!newDetailName) {
                            Swal.showValidationMessage('Please enter a name');
                        }
                        return newDetailName;
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        var form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '';
                        var inputID = document.createElement('input');
                        inputID.type = 'hidden';
                        inputID.name = 'DetailID';
                        inputID.value = detailID;
                        var inputName = document.createElement('input');
                        inputName.type = 'hidden';
                        inputName.name = 'Detail_name';
                        inputName.value = result.value;
                        var editBtn = document.createElement('input');
                        editBtn.type = 'hidden';
                        editBtn.name = 'edit_Detail';
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

        document.querySelectorAll('.btn-delete-detail').forEach(function (button) {
            button.addEventListener('click', function () {
                var detailID = this.getAttribute('data-detailid');
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
                        input.name = 'DetailID';
                        input.value = detailID;
                        var deleteBtn = document.createElement('input');
                        deleteBtn.type = 'hidden';
                        deleteBtn.name = 'btn-delete-Detail';
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
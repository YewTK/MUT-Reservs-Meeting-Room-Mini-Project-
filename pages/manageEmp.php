<?php
ob_start(); // Start output buffering
session_start();
include_once "../config/connection.php";
require_once "../config/config.php";
$db = new connectDB();
$db->InitSession('../index.php');
$_SESSION['pageName'] = "manageEmp";
include_once "menus/navbar.php";

if (!isset($permissions) || $permissions[4] != '1') {
    header('Location: home.php');
    exit();
}

// Default values for pagination
$limit = 50;
$page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
$offset = ($page - 1) * $limit;

// Get the total number of employees to calculate total pages
$totalQuery = "SELECT COUNT(*) AS TOTAL FROM Employee";
$totalResult = $db->Query($totalQuery);

if ($totalResult && isset($totalResult['TOTAL'])) {
    $totalEmployees = $totalResult['TOTAL'];
    $totalPages = ceil($totalEmployees / $limit);
} else {
    // แสดงข้อผิดพลาดหรือค่าเริ่มต้นในกรณีที่ query ไม่สำเร็จ
    $totalEmployees = 0;
    $totalPages = 1;
}

// Load the employees for the current page
$query = "SELECT * FROM (
            SELECT e.*, d.DepartmentName, p.PositionName, a.AccountStatusName, ROWNUM AS rnum
            FROM Employee e
            LEFT JOIN Department d ON e.DepartmentID = d.DepartmentID
            LEFT JOIN Position p ON e.PositionID = p.PositionID
            LEFT JOIN AccountStatus a ON e.AccountStatusID = a.AccountStatusID
            WHERE ROWNUM <= :end_row
          ) WHERE rnum > :start_row";
$employees = $db->Query($query, [':end_row' => $offset + $limit, ':start_row' => $offset], true);

$deptQuery = "SELECT DepartmentID, DepartmentName FROM Department";
$departments = $db->Query($deptQuery);

$positionQuery = "SELECT PositionID, PositionName FROM Position";
$positions = $db->Query($positionQuery);

$errorMessage = '';
$successMessage = '';

// Handle search request
if (isset($_POST['search'])) {
    ob_clean(); // Clear buffer to avoid unexpected output
    header('Content-Type: application/json; charset=utf-8'); // Set the content type to JSON

    $employeeID = $_POST['employeeID'];
    $employeeName = $_POST['employeeName'];
    $department = $_POST['department'];
    $jobPosition = $_POST['jobPosition'];

    // Build query for searching
    $query = "SELECT e.*, d.DepartmentName, p.PositionName, a.AccountStatusName 
              FROM Employee e 
              JOIN Department d ON e.DepartmentID = d.DepartmentID
              JOIN Position p ON e.PositionID = p.PositionID
              JOIN AccountStatus a ON e.AccountStatusID = a.AccountStatusID
              ";

    $params = [];
    if (!empty($employeeID)) {
        $query .= " AND e.EmployeeID LIKE :employeeID";
        $params[':employeeID'] = '%' . $employeeID . '%';
    }
    if (!empty($employeeName)) {
        $query .= " AND e.FName LIKE :employeeName";
        $params[':employeeName'] = '%' . $employeeName . '%';
    }
    if (!empty($department)) {
        $query .= " AND e.DepartmentID = :department";
        $params[':department'] = $department;
    }
    if (!empty($jobPosition)) {
        $query .= " AND e.PositionID = :jobPosition";
        $params[':jobPosition'] = $jobPosition;
    }

    // Fetch data
    $result = $db->Query($query, $params, true);

    // If there's a database error, log it
    if ($result === false) {
        echo json_encode(['error' => 'Database query failed.']);
    } else {
        // Ensure response is always a JSON array
        echo json_encode($result ?: []);
    }
    exit(); // Stop further execution
}

// Handle delete request
if (isset($_POST['btn-delete'])) {
    $empID = $_POST['empID'];

    ob_clean(); // Clear output buffer
    header('Content-Type: application/json; charset=utf-8');

    if ($empID != $_SESSION['EmployeeID']) {
        $deleteQuery = "DELETE FROM Employee WHERE EmployeeID = :empID";
        $isDeleted = $db->nonQuery($deleteQuery, [':empID' => $empID]);
        echo json_encode([
            'success' => $isDeleted,
            'message' => $isDeleted ? "ลบพนักงานเรียบร้อยแล้ว" : "ไม่สามารถลบพนักงานได้"
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => "คุณไม่สามารถลบตัวเองได้"]);
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <title><?php echo htmlspecialchars($Title); ?> - report</title>
    <?php include_once "menus/header.php"; ?>
</head>

<body>
    <div class="f-1">
        <div class="box-title">
            <span class="">EMPLOYEE MANAGEMENT</span>
        </div>

        <form action="addEmp.php" method="post">
            <button type="submit" class="btn text-primary border border-2" name="AddEmployee">
                <i class="fas fa-user-plus"></i> ADD EMPLOYEE
            </button>
        </form>

        <div class="container">
            <div class="employee-container">
                <div class="container mt-4">
                    <div class="d-flex justify-content-center">
                        <?php if ($errorMessage): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
                        <?php elseif ($successMessage): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
                        <?php endif; ?>
                    </div>
                    <form method="post" action="">
                        <div class="row mb-3">
                            <div class="col">
                                <select class="form-select" id="Department" name="department">
                                    <option value="">All Departments</option>
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?php echo htmlspecialchars($department['DEPARTMENTID']); ?>">
                                            <?php echo htmlspecialchars($department['DEPARTMENTNAME']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col">
                                <select class="form-select" id="JobPosition" name="jobPosition">
                                    <option value="">All Positions</option>
                                    <?php foreach ($positions as $position): ?>
                                        <option value="<?php echo htmlspecialchars($position['POSITIONID']); ?>">
                                            <?php echo htmlspecialchars($position['POSITIONNAME']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row mb-2">
                            <div class="col">
                                <input type="text" class="form-control" name="employeeID" placeholder="Employee ID"
                                    id="search-id" maxlength="10">
                            </div>

                            <div class="col">
                                <input type="text" class="form-control" name="employeeName" placeholder="Employee Name"
                                    id="search-name" maxlength="40">
                            </div>
                        </div>

                        <div class="list-group">
                            <div id="show-id"></div>
                            <div id="show-name"></div>
                        </div>

                        <div class="col-auto d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary" id="search-btn" name="search-btn">
                                <i class="fas fa-magnifying-glass"></i> SEARCH
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="employee-container-table table-responsive">
                <table class="table table-bordered ">
                    <thead>
                        <tr>
                            <th scope="col">NO</th>
                            <th scope="col">EMPLOYEE ID</th>
                            <th scope="col">NAME</th>
                            <th scope="col">LAST NAME</th>
                            <th scope="col">TELL NUMBER</th>
                            <th scope="col">STATUS</th>
                            <th scope="col">COUNT LOCKED</th>
                            <th scope="col">DEPARTMENT</th>
                            <th scope="col">POSITION</th>
                            <th scope="col" class="text-center">EDIT</th>
                            <th scope="col" class="text-center">DELETE</th>
                        </tr>
                    </thead>
                    <tbody id="employee-table-body">
                        <?php if (is_array($employees) && count($employees) > 0): ?>
                            <?php foreach ($employees as $employee): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($employee['RNUM']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['EMPLOYEEID']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['FNAME']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['LNAME']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['TELLNUMBER']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['ACCOUNTSTATUSNAME']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['COUNTLOCK']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['DEPARTMENTNAME']); ?></td>
                                    <td><?php echo htmlspecialchars($employee['POSITIONNAME']); ?></td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center align-items-center">
                                            <form action="editEmp.php" method="post">
                                                <button type="submit" class="btn btn-secondary" name="Update_EmployeeID">
                                                    <i class="far fa-pen-to-square"></i>
                                                </button>
                                                <input type="hidden" name="empID"
                                                    value="<?php echo htmlspecialchars($employee['EMPLOYEEID']); ?>">
                                            </form>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex justify-content-center align-items-center">
                                            <button type="button" class="btn btn-danger btn-delete"
                                                data-empid="<?php echo htmlspecialchars($employee['EMPLOYEEID']); ?>">
                                                <i class="far fa-trash-can"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11">ไม่พบข้อมูลพนักงาน</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="d-flex justify-content-end align-items-center mt-3">
                    <form method="post" action="" class="me-2">
                        <input type="hidden" name="page" value="<?php echo $page - 1; ?>">
                        <button class="btn btn-primary" type="submit" <?php echo $page <= 1 ? 'disabled' : ''; ?>>&lt;</button>
                    </form>
                    <span class="me-2">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                    <form method="post" action="">
                        <input type="hidden" name="page" value="<?php echo $page + 1; ?>">
                        <button class="btn btn-primary" type="submit" <?php echo $page >= $totalPages ? 'disabled' : ''; ?>>&gt;</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php include_once "menus/footer.php"; ?>

    <script>
        $(document).ready(function () {
            // Handle search button click
            $('#search-btn').click(function (e) {
                e.preventDefault();

                let employeeID = $('#search-id').val();
                let employeeName = $('#search-name').val();
                let department = $('#Department').val();
                let jobPosition = $('#JobPosition').val();

                $.ajax({
                    url: '', // Use the current PHP file
                    method: 'POST',
                    data: {
                        search: true,
                        employeeID: employeeID,
                        employeeName: employeeName,
                        department: department,
                        jobPosition: jobPosition
                    },
                    success: function (response) {
                        try {
                            // Check if response is already an object (not a string) or parse JSON
                            const employees = typeof response === 'object' ? response : JSON
                                .parse(response);

                            // Clear previous table data
                            $('#employee-table-body').empty();

                            // Check if there are employees in the response
                            if (employees.length > 0) {
                                employees.forEach((employee, index) => {
                                    $('#employee-table-body').append(`
                        <tr>
                            <td>${index + 1}</td>
                            <td>${employee.EMPLOYEEID}</td>
                            <td>${employee.FNAME}</td>
                            <td>${employee.LNAME}</td>
                            <td>${employee.TELLNUMBER}</td>
                            <td>${employee.ACCOUNTSTATUSNAME}</td>
                            <td>${employee.COUNTLOCK}</td>
                            <td>${employee.DEPARTMENTNAME}</td>
                            <td>${employee.POSITIONNAME}</td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center">
                                    <form action="editEmp.php" method="post">
                                        <button type="submit" class="btn btn-secondary" name="Update_EmployeeID">
                                            <i class="far fa-pen-to-square"></i>
                                        </button>
                                        <input type="hidden" name="empID" value="${employee.EMPLOYEEID}">
                                    </form>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="d-flex justify-content-center align-items-center">
                                    <button type="button" class="btn btn-danger btn-delete" data-empid="${employee.EMPLOYEEID}">
                                        <i class="far fa-trash-can"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    `);
                                });
                            } else {
                                $('#employee-table-body').append(`
                    <tr>
                        <td colspan="11">ไม่พบข้อมูลพนักงาน</td>
                    </tr>
                `);
                            }
                        } catch (error) {
                            console.error('Error parsing JSON:', error);
                            Swal.fire('ข้อผิดพลาด!',
                                'เกิดข้อผิดพลาดในการแปลงข้อมูลจากเซิร์ฟเวอร์.', 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('ข้อผิดพลาด!', 'ไม่สามารถค้นหาพนักงานได้ กรุณาลองอีกครั้ง.',
                            'error');
                    }
                });
            });

            // Handle delete button click
            $(document).on('click', '.btn-delete', function () {
                const empID = $(this).data('empid');
                confirmDelete(empID);
            });

            function confirmDelete(empID) {
                Swal.fire({
                    title: 'คุณแน่ใจหรือไม่?',
                    text: "คุณจะไม่สามารถย้อนกลับได้!",
                    icon: 'warning',
                    timerProgressBar: true,
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'ใช่, ลบเลย!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: '', // Use current PHP file
                            method: 'POST',
                            data: {
                                'btn-delete': true,
                                'empID': empID
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
                                        location.reload();
                                    }
                                });
                            },
                            error: function () {
                                Swal.fire('ข้อผิดพลาด!',
                                    'ไม่สามารถลบพนักงานได้ กรุณาลองอีกครั้ง.', 'error');
                            }
                        });
                    }
                });
            }
        });

        // Search ID
        $(document).ready(function () {
            $("#search-id").keyup(function () {
                let search_id = $(this).val();
                if (search_id != "") {
                    $.ajax({
                        url: "search.php",
                        method: "post",
                        data: {
                            query_i: search_id
                        },
                        success: function (response) {
                            $("#show-id").html(response);
                        }
                    });
                } else {
                    $("#show-id").html(""); // Clear the results if input is empty
                }
            });

            $(document).on('click', '#show-id a', function (e) {
                e.preventDefault();
                let selectedID = $(this).text();
                $('#search-id').val(selectedID);
                $("#show-id").html(""); // Clear the list after selection
            });
        });

        // Search NAME
        $(document).ready(function () {
            $("#search-name").keyup(function () {
                let search_name = $(this).val();
                if (search_name != "") {
                    $.ajax({
                        url: "search.php",
                        method: "post",
                        data: {
                            query_n: search_name
                        },
                        success: function (response) {
                            $("#show-name").html(response);
                        }
                    });
                } else {
                    $("#show-name").html(""); // Clear the results if input is empty
                }
            });

            $(document).on('click', '#show-name a', function (e) {
                e.preventDefault();
                let selectedNAME = $(this).text();
                $('#search-name').val(selectedNAME);
                $("#show-name").html(""); // Clear the list after selection
            });
        });
    </script>
</body>

</html>
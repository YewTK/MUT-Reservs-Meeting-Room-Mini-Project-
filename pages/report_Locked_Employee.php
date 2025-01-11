<?php
ob_start(); // Start output buffering
session_start();
include_once "../config/connection.php";
require_once "../config/config.php";
$db = new connectDB();
$db->InitSession('../index.php');
$_SESSION['pageName'] = "reportLockEmp";
include_once "menus/navbar.php";

if (!isset($permissions) || $permissions[3] != '1') {
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
    SELECT e.*, 
           d.DepartmentName, 
           p.PositionName, 
           a.AccountStatusName, 
           (SELECT COUNT(l.EMPLOYEEID) FROM lockemployee l WHERE l.EMPLOYEEID = e.EMPLOYEEID) AS LockCount,
           ROWNUM AS RNUM
    FROM Employee e
    LEFT JOIN Department d ON e.DepartmentID = d.DepartmentID
    LEFT JOIN Position p ON e.PositionID = p.PositionID
    LEFT JOIN AccountStatus a ON e.AccountStatusID = a.AccountStatusID
    WHERE ROWNUM <= :end_row
    ORDER BY e.EMPLOYEEID
) WHERE RNUM > :start_row";

$employees = $db->Query($query, [':start_row' => $offset, ':end_row' => $offset + $limit], true);

$deptQuery = "SELECT DepartmentID, DepartmentName FROM Department";
$departments = $db->Query($deptQuery);

$errorMessage = '';
$successMessage = '';
// Handle search request
if (isset($_POST['search'])) {
    ob_clean(); // Clear buffer to avoid unexpected output
    header('Content-Type: application/json; charset=utf-8'); // Set the content type to JSON
    $department = $_POST['department'];
    $employeeID = $_POST['employeeID'];
    // Build the base query for searching
    $query = "SELECT * FROM (
            SELECT e.*, 
                   d.DepartmentName, 
                   p.PositionName, 
                   a.AccountStatusName, 
                   COALESCE(l.LockCount, 0) AS LockCount,
                   ROWNUM AS RNUM
            FROM Employee e
            LEFT JOIN Department d ON e.DepartmentID = d.DepartmentID
            LEFT JOIN Position p ON e.PositionID = p.PositionID
            LEFT JOIN AccountStatus a ON e.AccountStatusID = a.AccountStatusID
            LEFT JOIN (
                SELECT EMPLOYEEID, COUNT(*) AS LockCount 
                FROM lockemployee 
                GROUP BY EMPLOYEEID
            ) l ON e.EMPLOYEEID = l.EMPLOYEEID
            WHERE ROWNUM <= :end_row
    ";
    // Initialize parameters
    $params = [];

    // Filter by department if provided
    if (!empty($department)) {
        $query .= " AND e.DepartmentID = :department";
        $params[':department'] = $department;
    }
    if (!empty($employeeID)) {
        $query .= " AND e.EmployeeID LIKE :employeeID";
        $params[':employeeID'] = '%' . $employeeID . '%';
    }

    // Close the subquery for pagination
    $query .= ") WHERE RNUM > :start_row";

    // Calculate offset for pagination
    $limit = 50; // Adjust as needed
    $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
    $offset = ($page - 1) * $limit;

    // Add pagination parameters
    $params[':start_row'] = $offset;
    $params[':end_row'] = $offset + $limit;

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

?>

<!DOCTYPE html>
<html lang="th">

<head>
    <title><?php echo htmlspecialchars($Title); ?> - REPORT LOCKEMPLOYEE</title>
    <?php include_once "menus/header.php"; ?>
</head>

<body>
    <div class="f-1">
        <div class="box-title">
            <span class="">REPORT LOCKEMPLOYEE</span>
        </div>
        <div class="container mt-4">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">รายงานการล็อกพนักงาน</h4>
                </div>
                <div class="card-body">
                    <div class="container">
                        <div class="report-lock-emp">
                            <div class="container">
                                <div class="d-flex justify-content-center">
                                    <?php if ($errorMessage): ?>
                                    <div class="alert alert-danger"><?php echo htmlspecialchars($errorMessage); ?></div>
                                    <?php elseif ($successMessage): ?>
                                    <div class="alert alert-success"><?php echo htmlspecialchars($successMessage); ?></div>
                                    <?php endif; ?>
                                </div>
                                <form method="post" action="">
                                    <div class="row mb-3 select-report">
                                        <div class="col">
                                            <select class="form-select" id="Department" name="department">
                                                <option value="">All Departments</option>
                                                <?php foreach ($departments as $department): ?>
                                                <option
                                                    value="<?php echo htmlspecialchars($department['DEPARTMENTID']); ?>">
                                                    <?php echo htmlspecialchars($department['DEPARTMENTNAME']); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>

                                        </div>
                                        <div class="col">
                                            <input type="text" class="form-control" name="employeeID"
                                                placeholder="Employee ID" id="search-id" maxlength="10">
                                        </div>
                                    </div>

                                    <div class="list-group mb-2">
                                        <div id="show-id"></div>
                                    </div>
                                    
                                    <div class="graph-search">
                                        <a href="report_Locked_graph.php" class="btn btn-primary">
                                            <i class="fas fa-chart-bar"></i> กราฟเปรียบเทียบแต่ละแผนก
                                        </a>
                                        <button type="submit" class="btn btn-primary" id="search-btn" name="search-btn">
                                            <i class="fas fa-magnifying-glass"></i> SEARCH
                                        </button>
                                    </div>                               
                                </form>

                            </div>
                        </div>

                        <div class="report-lock-emp table-responsive">
                            <table class="table table-bordered ">
                                <thead>
                                    <tr>
                                        <th scope="col">NO</th>
                                        <th scope="col">EMPLOYEE ID</th>
                                        <th scope="col">NAME</th>
                                        <th scope="col">LAST NAME</th>
                                        <th scope="col">DEPARTMENT</th>
                                        <th scope="col">POSITION</th>
                                        <th scope="col">LOCK COUNT</th>
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
                                        <td><?php echo htmlspecialchars($employee['DEPARTMENTNAME']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['POSITIONNAME']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['LOCKCOUNT']); ?></td>
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
                                    <button class="btn btn-primary" type="submit"
                                        <?php echo $page <= 1 ? 'disabled' : ''; ?>>&lt;</button>
                                </form>
                                <span class="me-2">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                                <form method="post" action="">
                                    <input type="hidden" name="page" value="<?php echo $page + 1; ?>">
                                    <button class="btn btn-primary" type="submit"
                                        <?php echo $page >= $totalPages ? 'disabled' : ''; ?>>&gt;</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
   

    <script>
    $(document).ready(function() {
        // Handle search button click
        $('#search-btn').click(function(e) {
            e.preventDefault();

            let employeeID = $('#search-id').val();
            let department = $('#Department').val();

            $.ajax({
                url: '', // Use the current PHP file
                method: 'POST',
                data: {
                    search: true,
                    employeeID: employeeID,
                    department: department,
                },
                success: function(response) {
                    try {
                        // Check if response is already an object (not a string) or parse JSON
                        const employees = typeof response === 'object' ? response :
                            JSON.parse(response);

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
                            <td>${employee.DEPARTMENTNAME}</td>
                            <td>${employee.POSITIONNAME}</td>
                            <td>${employee.LOCKCOUNT}</td>
                        </tr>
                    `);
                            });
                        } else {
                            $('#employee-table-body').append(`
                    <tr>
                        <td colspan="7">ไม่พบข้อมูลพนักงาน</td>
                    </tr>
                `);
                        }
                    } catch (error) {
                        console.error('Error parsing JSON:', error);
                        Swal.fire('ข้อผิดพลาด!',
                            'เกิดข้อผิดพลาดในการแปลงข้อมูลจากเซิร์ฟเวอร์.',
                            'error');
                    }
                },
                error: function() {
                    Swal.fire('ข้อผิดพลาด!',
                        'ไม่สามารถค้นหาพนักงานได้ กรุณาลองอีกครั้ง.', 'error');
                }
            });
        });

        // Search ID
        $("#search-id").keyup(function() {
            let search_id = $(this).val();
            if (search_id != "") {
                $.ajax({
                    url: "search.php",
                    method: "post",
                    data: {
                        query_i: search_id
                    },
                    success: function(response) {
                        $("#show-id").html(response);
                    }
                });
            } else {
                $("#show-id").html(""); // Clear the results if input is empty
            }
        });

        $(document).on('click', '#show-id a', function(e) {
            e.preventDefault();
            let selectedID = $(this).text();
            $('#search-id').val(selectedID);
            $("#show-id").html(""); // Clear the list after selection
        });
    });
    </script>
    <?php include_once "menus/footer.php"; ?>
</body>

</html>
<?php
session_start();
include_once "../config/connection.php";
require_once "../config/config.php";

// Initialize database connection
$db = new connectDB();
$db->InitSession('../index.php');
$_SESSION['pageName'] = "reportLockEmp";
include_once "menus/navbar.php";

// Initialize variables
$DepartmentName = '%'; // Default to all departments

// Handle form submission
if (isset($_POST['btn-search'])) {
    $DepartmentName = $_POST['DEPARTMENTNAME'] ?? '%';
}


$query = "SELECT 
            d.DEPARTMENTNAME,
            COUNT(l.EMPLOYEEID) AS TOTAL_LOCKED_EMPLOYEES
        FROM 
            DEPARTMENT d
        LEFT JOIN 
            EMPLOYEE e ON d.DEPARTMENTID = e.DEPARTMENTID
        LEFT JOIN 
            LOCKEMPLOYEE l ON e.EMPLOYEEID = l.EMPLOYEEID
        WHERE 
            d.DEPARTMENTNAME LIKE :dept
        GROUP BY 
            d.DEPARTMENTNAME
        ORDER BY 
            d.DEPARTMENTNAME";

// Prepare parameters
$params = [
   $DepartmentName
];

$lockData = $db->Query($query, $params, true);

// Process data for Chart.js
$departments = [];
$lockCounts = [];

if (!empty($lockData)) {
    foreach ($lockData as $data) {
        $departments[] = $data['DEPARTMENTNAME'];
        $lockCounts[] = intval($data['TOTAL_LOCKED_EMPLOYEES']);
    }
}

// Prepare chart configuration
$chartConfig = json_encode([
    'labels' => $departments,
    'datasets' => [[
        'label' => 'จำนวนพนักงานที่ถูกล็อก',
        'data' => $lockCounts,
        'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
        'borderColor' => 'rgba(54, 162, 235, 1)',
        'borderWidth' => 1
    ]]
]);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php include_once "menus/header.php"; ?>
    <title><?php echo htmlspecialchars($Title); ?> - รายงานพนักงานที่ถูกล็อก</title>
</head>

<body>
    <div class="f-1">
        <div class="box-title">
            <span class="">REPORT LOCKEMPLOYEE</span>
        </div>
        <div class="container mt-4">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">รายงานพนักงานที่ถูกล็อก</h4>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label for="DEPARTMENTNAME" class="form-label">เลือกแผนก</label>
                            <select class="form-select" name="DEPARTMENTNAME" id="DEPARTMENTNAME">
                                <option value="%">ทุกแผนก</option>
                                <?php 
                                $departments = $db->Query("SELECT DEPARTMENTNAME FROM DEPARTMENT ORDER BY DEPARTMENTNAME");
                                foreach ($departments as $dept): 
                                ?>
                                <option value="<?php echo htmlspecialchars($dept['DEPARTMENTNAME']); ?>"
                                    <?php echo ($DepartmentName === $dept['DEPARTMENTNAME']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept['DEPARTMENTNAME']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100" name="btn-search">
                                <i class="fas fa-search"></i> ค้นหา
                            </button>
                        </div>
                    </form>

                    <!-- Table view of the data -->
                    <div class="table-responsive mb-4">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>แผนก</th>
                                    <th>จำนวนพนักงานที่ถูกล็อก</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lockData as $data): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($data['DEPARTMENTNAME']); ?></td>
                                    <td class="text-center"><?php echo htmlspecialchars($data['TOTAL_LOCKED_EMPLOYEES']); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Chart view of the data -->
                    <div class="chart-container" style="position: relative; height:60vh; width:100%">
                        <canvas id="lockChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include_once "menus/footer.php"; ?>

    <script>
    const ctx = document.getElementById('lockChart').getContext('2d');
    const chartConfig = <?php echo $chartConfig; ?>;

    new Chart(ctx, {
        type: 'bar',
        data: chartConfig,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'จำนวนพนักงานที่ถูกล็อกในแต่ละแผนก'
                },
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'จำนวนพนักงาน'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'แผนก'
                    }
                }
            }
        }
    });
    </script>
</body>

</html>
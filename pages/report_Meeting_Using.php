<?php
session_start();
include_once "../config/connection.php";
require_once "../config/config.php";

// Initialize database connection
$db = new connectDB();
$db->InitSession('../index.php');
$_SESSION['pageName'] = "reportMeetingUsing";
include_once "menus/navbar.php";

// Initialize variables
$currentMonth = date('m');
$currentYear = date('Y');
$startDate = date('Y-m-01'); // First day of current month
$endDate = date('Y-m-t');    // Last day of current month
$roomName = '%';             // Default to all rooms

// Handle form submission
if (isset($_POST['btn-search'])) {
    $roomName = $_POST['rooms'] ?? '%';
    $month = $_POST['months'] ?? '';
    $year = $_POST['years'] ?? '';

    if (!empty($month) && !empty($year)) {
        $startDate = "{$year}-{$month}-01";
        $endDate = date('Y-m-t', strtotime($startDate));
    } elseif (!empty($year)) { // Only year selected
        $startDate = "{$year}-01-01";
        $endDate = "{$year}-12-31";
    } elseif (!empty($month)) { // Only month selected
        $startDate = date("Y-{$month}-01");
        $endDate = date("Y-{$month}-t");
    }
}

// SQL query for booking data with proper date formatting for Oracle
$query = "SELECT 
            TO_CHAR(TRUNC(u.USINGDATE), 'YYYY-MM-DD') AS USINGDATE,
            COUNT(mrt.ROOMID) AS TOTALBOOKINGS,
            mrt.ROOMNAME AS ROOMNAME
        FROM 
            USINGROOM u
        JOIN 
            MEETINGROOM mrt ON u.ROOMID = mrt.ROOMID
        WHERE 
            TRUNC(u.USINGDATE) BETWEEN TO_DATE(?, 'YYYY-MM-DD') 
            AND TO_DATE(?, 'YYYY-MM-DD')
            AND mrt.ROOMNAME LIKE ?
        GROUP BY 
            TRUNC(u.USINGDATE), mrt.ROOMNAME
        ORDER BY 
            TRUNC(u.USINGDATE), mrt.ROOMNAME";

// Prepare parameters
$params = [
    $startDate,
    $endDate,
    $roomName
];

// Execute query with error handling
try {
    $bookingData = $db->Query($query, $params, true);
    if ($bookingData === false) {
        throw new Exception("ไม่สามารถดึงข้อมูลการจองห้องได้");
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    $bookingData = [];
}
// Process data for Chart.js
$chartLabels = [];
$roomStats = [];

// Check if bookingData is not empty before processing
if (!empty($bookingData)) {
    foreach ($bookingData as $booking) {
        $date = $booking['USINGDATE'];
        $room = $booking['ROOMNAME'];
        $total = intval($booking['TOTALBOOKINGS']);
        
        if (!isset($roomStats[$room])) {
            $roomStats[$room] = [];
        }
        $roomStats[$room][$date] = $total;
        
        if (!in_array($date, $chartLabels)) {
            $chartLabels[] = $date;
        }
    }
}

// Sort dates
sort($chartLabels);

// Prepare datasets for each room
$datasets = [];
$colors = [
    'rgba(75, 192, 192, 0.2)',
    'rgba(255, 99, 132, 0.2)',
    'rgba(54, 162, 235, 0.2)',
    'rgba(255, 206, 86, 0.2)',
    'rgba(153, 102, 255, 0.2)'
];

$i = 0;
foreach ($roomStats as $room => $data) {
    $roomData = [];
    foreach ($chartLabels as $date) {
        $roomData[] = $data[$date] ?? 0;
    }

    $datasets[] = [
        'label' => $room,
        'data' => $roomData,
        'backgroundColor' => $colors[$i % count($colors)],
        'borderColor' => str_replace('0.2', '1', $colors[$i % count($colors)]),
        'borderWidth' => 1
    ];
    $i++;
}

$chartConfig = json_encode([
    'labels' => $chartLabels,
    'datasets' => $datasets
]);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <title><?php echo htmlspecialchars($Title); ?> - รายงานการใช้ห้องประชุม</title>
    <?php include_once "menus/header.php"; ?>
</head>

<body>
    <div class="f-1">
        <div class="box-title">
            <span class="">REPORT MEETINGUSING</span>
        </div>
        <div class="container mt-4">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">รายงานการใช้ห้องประชุม</h4>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3 mb-4">
                        <div class="col-md-4">
                            <select class="form-select" name="rooms">
                                <option value="%">ทุกห้อง</option>
                                <?php 
                                $rooms = $db->Query("SELECT * FROM MEETINGROOM");
                                foreach ($rooms as $room): 
                                ?>
                                <option value="<?php echo htmlspecialchars($room['ROOMNAME']); ?>"
                                    <?php echo ($roomName === $room['ROOMNAME']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($room['ROOMNAME']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="months">
                                <option value="">เลือกเดือน</option>
                                <?php for ($i = 1; $i <= 12; $i++): 
                                    $monthValue = str_pad($i, 2, '0', STR_PAD_LEFT);
                                ?>
                                <option value="<?php echo $monthValue; ?>"
                                    <?php echo ($monthValue === date('m', strtotime($startDate))) ? 'selected' : ''; ?>>
                                    <?php echo date('F', mktime(0, 0, 0, $i, 1)); ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="years">
                                <option value="">เลือกปี</option>
                                <?php 
                                $currentYear = date('Y');
                                for ($i = $currentYear - 5; $i <= $currentYear; $i++): 
                                ?>
                                <option value="<?php echo $i; ?>"
                                    <?php echo ($i === intval(date('Y', strtotime($startDate)))) ? 'selected' : ''; ?>>
                                    <?php echo $i; ?>
                                </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100" name="btn-search">
                                <i class="fas fa-search"></i> ค้นหา
                            </button>
                        </div>
                    </form>
                    <div class="chart-container" style="position: relative; height:60vh; width:100%">
                        <canvas id="bookingChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    

    <?php include_once "menus/footer.php"; ?>

    <script>
    const ctx = document.getElementById('bookingChart').getContext('2d');
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
                    text: 'จำนวนการจองห้องประชุมตามวันที่'
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
                        text: 'จำนวนการจอง'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'วันที่'
                    }
                }
            }
        }
    });
    </script>
</body>

</html>
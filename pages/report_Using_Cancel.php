<?php 
ob_start(); // Start output buffering 
session_start(); 
include_once "../config/connection.php";
require_once "../config/config.php";
$db = new connectDB();
$db->InitSession('../index.php');
$_SESSION['pageName'] = "reportUsingCancel";
include_once "menus/navbar.php";

// Initialize variables
$currentMonth = date('m');
$currentYear = date('Y');
$startDate = date('Y-m-01'); // First day of current month
$endDate = date('Y-m-t'); // Last day of current month
$roomName = '%'; // Default to all rooms

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

// SQL query for usage data
$queryUsing = "SELECT
    TO_CHAR(TRUNC(u.USINGDATE), 'YYYY-MM-DD') AS USINGDATE,
    COUNT(u.ROOMID) AS TOTALUSING,
    mrt.ROOMNAME
    FROM USINGROOM u
    JOIN MEETINGROOM mrt ON u.ROOMID = mrt.ROOMID
    WHERE TRUNC(u.USINGDATE) BETWEEN TO_DATE(?, 'YYYY-MM-DD')
    AND TO_DATE(?, 'YYYY-MM-DD')
    AND mrt.ROOMNAME LIKE ?
    GROUP BY TRUNC(u.USINGDATE), mrt.ROOMNAME
    ORDER BY TRUNC(u.USINGDATE), mrt.ROOMNAME";

// SQL query for booking data
$queryBooking = "SELECT
    TO_CHAR(TRUNC(rbk.BOOKDATE), 'YYYY-MM-DD') AS BOOKINGDATE,
    COUNT(rbk.ROOMID) AS TOTALBOOKINGS,
    mrt.ROOMNAME
    FROM ROOMBOOKING rbk
    JOIN MEETINGROOM mrt ON rbk.ROOMID = mrt.ROOMID
    WHERE TRUNC(rbk.BOOKDATE) BETWEEN TO_DATE(?, 'YYYY-MM-DD')
    AND TO_DATE(?, 'YYYY-MM-DD')
    AND mrt.ROOMNAME LIKE ?
    GROUP BY TRUNC(rbk.BOOKDATE), mrt.ROOMNAME
    ORDER BY TRUNC(rbk.BOOKDATE), mrt.ROOMNAME";

// SQL query for cancellation data
$queryCancel = "SELECT
    TO_CHAR(TRUNC(c.CANCELDATE), 'YYYY-MM-DD') AS CANCELDATE,
    COUNT(c.ROOMID) AS TOTALCANCELS,
    mrt.ROOMNAME
    FROM CANCELBOOKING c
    JOIN MEETINGROOM mrt ON c.ROOMID = mrt.ROOMID
    WHERE TRUNC(c.CANCELDATE) BETWEEN TO_DATE(?, 'YYYY-MM-DD')
    AND TO_DATE(?, 'YYYY-MM-DD')
    AND mrt.ROOMNAME LIKE ?
    GROUP BY TRUNC(c.CANCELDATE), mrt.ROOMNAME
    ORDER BY TRUNC(c.CANCELDATE), mrt.ROOMNAME";
// Execute queries
$params = [$startDate, $endDate, $roomName];
$usings = $db->Query($queryUsing, $params, true) ?? []; // Default to empty array if no results
$bookings = $db->Query($queryBooking, $params, true) ?? []; // Default to empty array if no results
$cancels = $db->Query($queryCancel, $params, true) ?? []; // Default to empty array if no results

// Initialize data structures
$allDates = [];
$roomStats = [];

// Process usage data
if (is_array($usings)) {
    foreach ($usings as $using) {
        $date = $using['USINGDATE'];
        $room = $using['ROOMNAME'];
        $total = intval($using['TOTALUSING']);
        
        if (!isset($roomStats[$room])) {
            $roomStats[$room] = ['using' => [], 'bookings' => [], 'cancels' => []];
        }
        $roomStats[$room]['usage'][$date] = $total;
        $allDates[$date] = true;
    }
}

// Process booking data
if (is_array($bookings)) {
    foreach ($bookings as $booking) {
        $date = $booking['BOOKINGDATE'];
        $room = $booking['ROOMNAME'];
        $total = intval($booking['TOTALBOOKINGS']);
        
        if (!isset($roomStats[$room])) {
            $roomStats[$room] = ['usage' => [], 'bookings' => [], 'cancels' => []];
        }
        $roomStats[$room]['bookings'][$date] = $total;
        $allDates[$date] = true;
    }
}

// Process cancellation data
if (is_array($cancels)) {
    foreach ($cancels as $cancel) {
        $date = $cancel['CANCELDATE'];
        $room = $cancel['ROOMNAME'];
        $total = intval($cancel['TOTALCANCELS']);
        
        if (!isset($roomStats[$room])) {
            $roomStats[$room] = ['usage' => [], 'bookings' => [], 'cancels' => []];
        }
        $roomStats[$room]['cancels'][$date] = $total;
        $allDates[$date] = true;
    }
}

// Prepare chart data
$chartLabels = array_keys($allDates);
sort($chartLabels);

$datasets = [];
$colors = [
    'usage' => ['rgba(75, 192, 192, 0.2)', 'rgba(75, 192, 192, 1)'],
    'bookings' => ['rgba(54, 162, 235, 0.2)', 'rgba(54, 162, 235, 1)'],
    'cancels' => ['rgba(255, 99, 132, 0.2)', 'rgba(255, 99, 132, 1)']
];

foreach ($roomStats as $room => $stats) {
    foreach (['usage', 'bookings', 'cancels'] as $type) {
        $data = [];
        foreach ($chartLabels as $date) {
            $data[] = $stats[$type][$date] ?? 0;
        }
        
        $datasets[] = [
            'label' => "{$room} - " . ucfirst($type),
            'data' => $data,
            'backgroundColor' => $colors[$type][0],
            'borderColor' => $colors[$type][1],
            'borderWidth' => 1
        ];
    }
}

$chartConfig = json_encode([
    'labels' => $chartLabels,
    'datasets' => $datasets
]);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php include_once "menus/header.php"; ?>
    <title><?php echo htmlspecialchars($Title); ?> - รายงานการใช้ห้องประชุม</title>
</head>

<body>
    <div class="f-1">
        <div class="box-title">
            <span class="">REPORT USINGCANCEL</span>
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
                    text: 'สถิติการใช้ห้องประชุม'
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
                        text: 'จำนวนครั้ง'
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
<?php
session_start();
include_once "../config/connection.php";
require_once "../config/config.php";

// Initialize database connection and session
$db = new connectDB();
$db->InitSession('../index.php');
$_SESSION['pageName'] = 'home';
include_once "menus/navbar.php";

// Redirect if user doesn't have permission
if ($permissions[0] != '1') {
    header('Location: home.php');
    exit();
}

// Base query for retrieving room information with proper joins
$baseQuery = "SELECT DISTINCT mtr.*, bd.BUILDINGNAME 
              FROM MEETINGROOM mtr
              JOIN FLOOR f ON f.FLOORID = mtr.FLOORID
              JOIN BUILDING bd ON bd.BUILDINGID = f.BUILDINGID";

// Initialize search variables
$params = [];
$whereConditions = [];
$searchResults = null;
$searchPerformed = false;

// Handle search form submission
if (isset($_POST['btn-search-home'])) {
    $searchPerformed = true;
    $building = $_POST['building'] ?? '';
    $roomName = $_POST['roomName'] ?? '%';
    $capacity = $_POST['capacity'] ?? '';
    $beginDate = $_POST['beginDate']; // รูปแบบ: MM/dd/yyyy
    $beginTime = $_POST['beginTime']; // รูปแบบ: HH:MM AM/PM
    $endDate = $_POST['endDate'];     // รูปแบบ: MM/dd/yyyy
    $endTime = $_POST['endTime'];     // รูปแบบ: HH:MM AM/PM

    // Build base search conditions
    if (!empty($building)) {
        $whereConditions[] = "bd.BUILDINGID = :building";
        $params[':building'] = $building;
    }

    if (!empty($roomName)) {
        $whereConditions[] = "mtr.ROOMID = :roomName";
        $params[':roomName'] = $roomName;
    }

    if (!empty($capacity)) {
        $whereConditions[] = "mtr.CAPACITY >= :capacity";
        $params[':capacity'] = $capacity;
    }

    // If date/time search is needed, modify the query to include booking status
    if (!empty($_POST['beginDate']) && !empty($_POST['beginTime']) &&
    !empty($_POST['endDate']) && !empty($_POST['endTime'])) {
    
    $beginDateTime = $beginDate . ' ' . $beginTime;
    $endDateTime = $endDate . ' ' . $endTime;

    // Modified query to check room availability
    $baseQuery .= " WHERE mtr.ROOMID NOT IN (
        SELECT b.ROOMID
        FROM BOOKING b
        JOIN ROOMBOOKINGSTATUS rbs ON b.BOOKINGID = rbs.BOOKINGID
        WHERE 
          (rbs.DATEBEGIN <= TO_TIMESTAMP(:beginDateTime, 'YYYY-MM-DD HH24:MI:SS') AND rbs.DATEEND >= TO_TIMESTAMP(:endDateTime, 'YYYY-MM-DD HH24:MI:SS')) 
          OR (rbs.DATEBEGIN BETWEEN TO_TIMESTAMP(:beginDateTime, 'YYYY-MM-DD HH24:MI:SS') AND TO_TIMESTAMP(:endDateTime, 'YYYY-MM-DD HH24:MI:SS'))
          OR (rbs.DATEEND BETWEEN TO_TIMESTAMP(:beginDateTime, 'YYYY-MM-DD HH24:MI:SS') AND TO_TIMESTAMP(:endDateTime, 'YYYY-MM-DD HH24:MI:SS'))
    )";

$params[':beginDateTime'] = $beginDateTime;
$params[':endDateTime'] = $endDateTime;
}

// Construct final search query
$searchQuery = $baseQuery;
if (!empty($whereConditions)) {
    $searchQuery .= (strpos($searchQuery, 'WHERE') !== false ? " AND " : " WHERE ") . 
                   implode(" AND ", $whereConditions);
}
$searchQuery .= " ORDER BY mtr.ROOMNAME"; // No semicolon here

$searchResults = $db->Query($searchQuery, $params, true);
        // Set session message based on results
        if ($searchResults && count($searchResults) > 0) {
            $_SESSION['success'] = "ค้นหาห้องสำเร็จ";
        } else {
            $_SESSION['error'] = "ไม่พบห้องที่ตรงตามเงื่อนไขการค้นหา กรุณาลองใหม่อีกครั้งหรือปรับเปลี่ยนเงื่อนไขการค้นหา";
        }
    }


// Get all rooms if no search performed
if (!$searchPerformed) {
    $searchResults = $db->Query($baseQuery . " ORDER BY mtr.ROOMNAME", [], true);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <title><?php echo htmlspecialchars($Title); ?> - Home</title>
    <?php include_once "menus/header.php"; ?>
</head>

<body>
    <!-- Search Form Section -->
    <section id="homepage">
        <div class="search-form">
            <div class="film d-flex justify-content-center align-items-center">
                <div class="search-body container">
                    <form method="POST">
                        <h1 class="Text-Homepage">SEARCH ROOMS</h1>

                        <div class="form-group">
                            <!-- Building Selection -->
                            <select class="form-select h-hompage" name="building" id="Building">
                                <option value="" selected>ALL Building</option>
                                <?php
                                $query = "SELECT BUILDINGID, BUILDINGNAME FROM BUILDING GROUP BY BUILDINGID, BUILDINGNAME";
                                $stmtBuilding = $db->Query($query);
                                if ($stmtBuilding):
                                    foreach ($stmtBuilding as $buildings): ?>
                                <option value="<?php echo htmlspecialchars($buildings['BUILDINGID']); ?>">
                                    <?php echo htmlspecialchars($buildings['BUILDINGNAME']); ?>
                                </option>
                                <?php endforeach;
                                endif; ?>
                            </select>

                            <!-- Room Selection -->
                            <select class="form-select h-hompage" name="roomName" id="RoomName">
                                <option value="" selected>ALL Room Name</option>
                                <?php
                                $query = "SELECT DISTINCT ROOMID, ROOMNAME FROM MEETINGROOM ORDER BY ROOMID";
                                $stmtMRT = $db->Query($query);
                                if ($stmtMRT):
                                    foreach ($stmtMRT as $meetingrooms): ?>
                                <option value="<?php echo htmlspecialchars($meetingrooms['ROOMID']); ?>">
                                    <?php echo htmlspecialchars($meetingrooms['ROOMNAME']); ?>
                                </option>
                                <?php endforeach;
                                endif; ?>
                            </select>
                        </div>

                        <!-- Capacity Input -->
                        <div class="input-group rounded mt-2" style="width: 100%;">
                            <input type="number" class="form-control rounded" placeholder="Capacity" name="capacity"
                                aria-label="Capacity" min="0" />
                        </div>

                        <!-- Booking Date/Time Form -->
                        <div class="booking-datetime-form text-white">
                            <div class="booking-start my-4 ">
                                <h5 class="text-white">เวลาเริ่มต้น (Optional)</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label text-white" for="beginDate">วันที่เริ่ม</label>
                                            <input type="date" id="beginDate" name="beginDate" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label text-white" for="beginTime">เวลาเริ่ม</label>
                                            <input type="time" id="beginTime" name="beginTime" class="form-control">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="booking-end">
                                <h5 class="text-white">เวลาสิ้นสุด (Optional)</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label text-white" for="endDate">วันที่สิ้นสุด</label>
                                            <input type="date" id="endDate" name="endDate" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label text-white" for="endTime">เวลาสิ้นสุด</label>
                                            <input type="time" id="endTime" name="endTime" class="form-control">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>


                        <!-- Search Button -->
                        <button type="submit" class="btn btn-primary" name="btn-search-home"
                            style="width: 100%; margin-top: 0.5rem; background-color: #4285F4; border: none;">
                            <i class="fas fa-magnifying-glass"></i> SEARCH
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Meeting Rooms Display Section -->
    <section id="meetingroom">
        <div class="container text-center mt-5 mb-4">
            <h1 class="mb-5">Meeting Rooms</h1>
            <div class="d-flex justify-content-center align-items-center">
                <div class="row h-100 w-100">
                    <?php if ($searchResults && count($searchResults) > 0): ?>
                    <?php foreach ($searchResults as $room): ?>
                    <div class="col-xl-3 col-lg-4 col-md-6 col-12 mt-4 d-flex justify-content-center align-items-center">
                        <div class="card-meetingroom card">
                            <div class="c-head">
                                <img src="<?php echo htmlspecialchars($room['ROOMIMG']); ?>" class="card-img-top"
                                    alt="Room Image" />
                            </div>

                            <div class="c-body p-3">
                                <h5 class="card-title">Room name: <?php echo htmlspecialchars($room['ROOMNAME']); ?>
                                </h5>
                                <p class="card-text"><strong>Building:
                                    </strong><?php echo htmlspecialchars($room['BUILDINGNAME']); ?></p>
                                <?php
                                $queryTypename = "SELECT TypeName FROM TypeRoom WHERE TypeID = ?";
                                $typeName = $db->Query($queryTypename, [$room['TYPEID']]);
                                ?>
                                <p class="card-text"><strong>Type:
                                    </strong><?php echo htmlspecialchars($typeName['TYPENAME']); ?></p>

                                <?php
                                $queryStatus = "SELECT StateDescription FROM MeetingRoomState WHERE StateID = ?";
                                $stateName = $db->Query($queryStatus, [$room['STATEID']]);
                                ?>
                                <p class="card-text"><strong>Status: </strong>
                                    <?php 
                                    $isReserved = false;
                                    if (isset($currentReservations)) {
                                        foreach ($currentReservations as $reservation) {
                                            if ($reservation['ROOMID'] == $room['ROOMID']) {
                                                $isReserved = true;
                                                break;
                                            }
                                        }
                                    }
                                    
                                    if ($isReserved): ?>
                                    <span style="color: orange;">Occupied</span>
                                    <?php else:
                                        switch ($room['STATEID']) {
                                            case '2': ?>
                                    <span
                                        style="color: orange;"><?php echo htmlspecialchars($stateName['STATEDESCRIPTION']); ?></span>
                                    <?php break;
                                            case '3': ?>
                                    <span
                                        style="color: red;"><?php echo htmlspecialchars($stateName['STATEDESCRIPTION']); ?></span>
                                    <?php break;
                                            default: ?>
                                    <span
                                        style="color: green;"><?php echo htmlspecialchars($stateName['STATEDESCRIPTION']); ?></span>
                                    <?php break;
                                        }
                                    endif; ?>
                                </p>

                                <p class="card-text"><strong>Capacity:
                                    </strong><?php echo htmlspecialchars($room['CAPACITY']); ?> People</p>

                                <!-- Booking Form -->
                                <form action="bookingRoom.php" method="POST"
                                    onsubmit="setRoomCookie('<?php echo htmlspecialchars($room['ROOMID']); ?>'); setBookingTimeCookie();">
                                    <button type="submit" class="btn btn-primary" name="btn-booking"
                                        <?php if ($isReserved || $room['STATEID'] != '1'): ?> disabled <?php endif; ?>>
                                        <i class="fas fa-door-closed"></i> Book Now
                                    </button>
                                    <input type="hidden" value="<?php echo htmlspecialchars($room['ROOMID']); ?>"
                                        name="room-id">
                                    <input type="hidden" value="<?php echo htmlspecialchars($room['STATEID']); ?>"
                                        name="state-id">
                                    <input type="hidden"
                                        value="<?php echo htmlspecialchars($stateName['STATEDESCRIPTION']); ?>"
                                        name="state-desc">
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="text-lg-center">
                        <p>ไม่พบห้องประชุม</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    <section id="contact">
        <?php include_once "menus/footer.php"; ?>
    </section>
    <script src="../assets/js/time.js"></script>

    <script>
    window.addEventListener('scroll', () => {
        const sections = document.querySelectorAll("section");
        const navLinks = document.querySelectorAll(".navbar a");

        sections.forEach(section => {
            const rect = section.getBoundingClientRect();
            if (rect.top <= 700 && rect.bottom >= 700) {
                navLinks.forEach(link => link.classList.remove("active"));
                document.querySelector(`.navbar a[href="home.php#${section.id}"]`).classList.add(
                    "active");
            }
        });
    });
    </script>

    <script>
    function setRoomCookie(roomId) {
        document.cookie = "roomId=" + roomId + "; path=/"; // Cookie expires when the browser session ends
    }
    </script>
</body>

</html>
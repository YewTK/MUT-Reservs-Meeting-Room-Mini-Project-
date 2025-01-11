<?php
include_once "../config/connection.php";
$db = new connectDB();

if (isset($_POST['query_i'])) {
    $input_txt = $_POST['query_i'];

    $query = "SELECT EmployeeID FROM Employee WHERE EmployeeID LIKE ?";
    $result = $db->getSearch($query, $input_txt);
    if ($result) {
        foreach ($result as $row) {
            echo "<a class='list-group-item list-group-item-action border-1'>" . htmlspecialchars($row['EMPLOYEEID']) . "</a>";
        }
    } else {
        echo "<div class='list-group-item border-1'>No record.</div>";
    }
}

if (isset($_POST['query_n'])) {
    $input_txt = $_POST['query_n'];

    $query = "SELECT FName FROM Employee WHERE FName LIKE ?";
    $result = $db->getSearch($query, $input_txt);
    if ($result) {
        foreach ($result as $row) {
            echo "<a class='list-group-item list-group-item-action border-1'>" . htmlspecialchars($row['FNAME']) . "</a>";
        }
    } else {
        echo "<div class='list-group-item border-1'>No record.</div>";
    }
}
?>
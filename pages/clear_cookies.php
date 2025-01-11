<?php
// clear_cookies.php
if (isset($_COOKIE['beginTime'])) {
    unset($_COOKIE['beginTime']);
    setcookie('beginTime', '', time() - 3600, '/');
}
if (isset($_COOKIE['endTime'])) {
    unset($_COOKIE['endTime']);
    setcookie('endTime', '', time() - 3600, '/');
}
?>
<?php
session_start();
require_once "config/connection.php";
require_once "config/config.php";
$db = new connectDB();
$_SESSION['pageName'] = 'index';

if (isset($_SESSION['user_login'])) {
    header("Location: pages/home.php");
}

// กำหนดเวลาหมดอายุของ cookie username, password และปุ่มเช็ค
$gDateCookie = 60 * 60 * 24 * 365; // 1 ปี

function setCookieWithExpiry($name, $value, $expiry)
{
    setcookie($name, $value, time() + $expiry, "/");
}

function deleteCookie($name)
{
    global $gDateCookie;
    setcookie($name, '', time() - ($gDateCookie), "/");
}

if (isset($_POST['signin'])) {
    $username = $_POST['username'];
    $pwd = $_POST['password'];

    if (empty($username)) {
        $_SESSION['error'] = 'กรุณากรอกชื่อผู้ใช้';
        header('Location: index.php');
        exit();
    } else if (empty($pwd)) {
        $_SESSION['error'] = 'กรุณากรอกรหัสผ่าน';
        header('Location: index.php');
        exit();
    } else {
        try {
            $stmt = $db->getEmployee($username);
            if ($username == $stmt['EMPLOYEEID']) {

                $isPasswordCorrect = false;

                if ($useHashPassword) {
                    if (password_verify($pwd, $stmt['PASSWORD'])) {
                        $isPasswordCorrect = true;
                    }
                } else {
                    if ($pwd == $stmt['PASSWORD']) {
                        $isPasswordCorrect = true;
                    }
                }

                if ($isPasswordCorrect) {
                    if ($stmt['ACCOUNTSTATUSID'] == '2') {
                        $_SESSION['error'] = "ไม่สามารถเข้าสู่ระบบได้ เนื่องจากบัญชีถูกระงับการใช้งาน!";
                        header("Location: index.php");
                        exit();
                    } else if ($stmt['ACCOUNTSTATUSID'] == '0') {
                        $_SESSION['error'] = "ท่านไม่ได้เป็นสมาชิกในองค์กร!";
                        header("Location: index.php");
                        exit();
                    } else {
                        ob_start();
                        $_SESSION['EmployeeID'] = $stmt['EMPLOYEEID'];
                        $_SESSION['user_login'] = true;
                        $_SESSION['wellcome'] = "ยินดีต้อนรับ " . $stmt['FNAME'];
                        if (!empty($_POST['dropdownCheck'])) {
                            setCookieWithExpiry("user_name", $_POST['username'], $gDateCookie);
                            setCookieWithExpiry("user_password", $_POST['password'], $gDateCookie);
                            setCookieWithExpiry("checkBox", $_POST['dropdownCheck'], $gDateCookie);
                        } else {
                            deleteCookie("user_name");
                            deleteCookie("user_password");
                            deleteCookie("checkBox");
                        }
                        header("Location: pages/home.php");
                        ob_end_flush();
                        exit();
                    }
                } else {
                    ob_start();
                    $_SESSION['error'] = "รหัสผ่านไม่ถูกต้อง!";
                    deleteCookie("user_password");
                    setCookieWithExpiry("user_name", $_POST['username'], $gDateCookie);
                    header("Location: index.php");
                    ob_end_flush();
                    exit();
                }
            } else {
                ob_start();
                $_SESSION['error'] = "ชื่อผู้ใช้ไม่ถูกต้อง!";
                deleteCookie("user_name");
                deleteCookie("user_password");
                header("Location: index.php");
                ob_end_flush();
                exit();
            }
        } catch (PDOException $e) {
            echo $e->getMessage();
        }
    }
}
?>

<!doctype html>
<html lang="en">

<head>
    <title><?php echo htmlspecialchars($Title) . " - Login"; ?></title>
    <?php include_once "pages/menus/header.php"; ?>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-light"
        style="background-color: #A31D25; padding: 0 0 0 10px; height: 55px;">
        <a class="navbar-brand" href="#">
            <img src="assets/img/LOGO-Mut.png" class="d-inline-block align-text-top mut" alt="MUT Logo">
        </a>
    </nav>

    <div class="container container-login">
        <div class="row box-login">
            <div class="left col-4 col-md-4">
                <div class="head text-center">
                    <img src="assets/img/mut.png" alt="MUT Logo">
                    <h3 class="mt-4">Meeting Room Booking</h3>
                </div>

                <div class="body mt-5">
                    <form action="" method="POST">
                        <div class="form-outline mb-3">
                            <input type="text" id="username" class="form-control" name="username"
                                value="<?php echo isset($_COOKIE['user_name']) ? htmlspecialchars($_COOKIE['user_name']) : ''; ?>"
                                maxlength="10" required />
                            <label class="form-label" for="username">Username</label>
                        </div>
                        <div class="form-outline mb-3" data-mdb-input-init>
                            <input type="password" id="password" class="form-control" name="password"
                                value="<?php echo isset($_COOKIE['user_password']) ? htmlspecialchars($_COOKIE['user_password']) : ''; ?>"
                                maxlength="44" required />
                            <label class="form-label" for="password">Password</label>
                            <i class="bi bi-eye-slash" id="togglePassword"></i>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="dropdownCheck" name="dropdownCheck" <?php if (isset($_COOKIE['checkBox'])):
                                        echo 'checked';
                                    endif; ?>>
                                <label class="form-check-label" for="dropdownCheck">Remember Me</label>
                            </div>
                        </div>
                        <button type="submit" name="signin" class="btn btn-primary"><i
                                class="fas fa-right-to-bracket"></i> LOGIN</button>
                        <p class="mb-5"></p>
                    </form>
                </div>
            </div>
            <div class="right col-12 col-md-8">
                <img src="assets/img/bg.jpg" class="img-fluid" alt="Background">
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
    <script>
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: '<?php echo htmlspecialchars($_SESSION['error']); ?>',
        timer: 2800,
        timerProgressBar: true,
        showConfirmButton: true
    });
    </script>
    <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <script src="assets/js/jquery.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/3.10.0/mdb.min.js"></script>
    <script>
    $(document).ready(function() {
        const togglePassword = $("#togglePassword");
        const password = $("#password");
        togglePassword.on("click", function() {
            const type = password.attr("type") === "password" ? "text" : "password";
            password.attr("type", type);
            togglePassword.toggleClass("bi-eye");
            togglePassword.toggleClass("bi-eye-slash");
        });
    });
    </script>
</body>

</html>
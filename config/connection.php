<?php
class connectDB
{
    private $db;

    public function __construct()
    {
        $this->db = $this->connectDB();
    }

    private function connectDB()
    {
        $nameserver = "203.188.54.7";
        $port = "1521";
        $workDB = "database"; // SID
        $username = "db671108";
        $password = "43691";
        try {
            $dsn = "oci:dbname=(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST={$nameserver})(PORT={$port}))(CONNECT_DATA=(SERVICE_NAME={$workDB})));charset=UTF8";
            $conn = new PDO($dsn, $username, $password);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $conn;
        } catch (PDOException $e) {
            echo "Connection failed: " . $e->getMessage();
        }
    }


    public function Query($query, $param = false, $opt = false, $singleRow = false): ?array //use for select
    {
        try {
            $stmt = $this->db->prepare($query);

            if ($param) {
                foreach ($param as $key => $value) {
                    if (is_numeric($key)) {
                        $stmt->bindValue($key + 1, $value);
                    } else {
                        $stmt->bindValue($key, $value);
                    }
                }
            }

            $stmt->execute();

            if ($singleRow) {
                return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            if ($param && !$opt) {
                return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            } else {
                return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: null;
            }

        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
            return null;
        }
    }
    public function nonQuery($query, $param)
    {
        try {
            $stmt = $this->db->prepare($query);
            foreach ($param as $key => $value) {
                if (is_numeric($key)) {
                    $stmt->bindValue($key + 1, $value);
                } else {
                    $stmt->bindValue($key, $value);
                }
            }
            return $stmt->execute();
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
            return false;
        }
    }

    public function InitSession($path)
    {
        if (!isset($_SESSION['user_login'])) {
            $_SESSION['error'] = 'กรุณาเข้าสู่ระบบก่อน';
            header("Location: $path");
            exit();
        }
    }

    public function getEmployee($EmployeeID = false)
    {
        $query = "SELECT * FROM Employee WHERE EmployeeID = :empid";
        $param = $EmployeeID ? [$EmployeeID] : [$_SESSION['EmployeeID']];
        $stmt = $this->Query($query, $param);
        return $stmt;
    }

    public function getStateSwal()
    {
        $loginStatus = isset($_SESSION['action_status']) ? $_SESSION['action_status'] : '';
        unset($_SESSION['action_status']);
        return $loginStatus;
    }

    public function autoID($departmentID)
    {
        $year = date("Y");
        $thaiYear = ($year + 543) % 100;
        $numOfYear = str_pad($thaiYear, 2, '0', STR_PAD_LEFT);
        $query = "SELECT MAX(EmployeeID) AS MAXID FROM Employee WHERE EmployeeID LIKE ?";
        $param = ["$numOfYear$departmentID%"];
        $stmt = $this->Query($query, $param);

        if (!empty($stmt['MAXID'])) { // ถ้ามีพนักงานในระบบ
            $input = substr($stmt['MAXID'], 6, 4); // ดึงเลขลำดับ 4 หลักสุดท้าย YY + DPT + ID
            if ($input == '9999') {
                return false; //เต็ม
            }
        } else { // ถ้าไม่มีพนักงานในระบบ
            $input = '0000'; // เริ่มที่ 0000
        }
        $prefix = substr($input, 0, 2);
        $number = (int) substr($input, 2);
        $newNumber = $number + 1;
        if ($newNumber > 99) {
            $prefix = str_pad($prefix + 1, 2, '0', STR_PAD_LEFT);
            $newNumber = 0;
        }
        $result = $prefix . str_pad($newNumber, 2, '0', STR_PAD_LEFT);
        $employeeID = $numOfYear . $departmentID . $result;

        return $employeeID;
    }

    public function getSearch($query, $value)
    {
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(1, '%' . $value . '%');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getConnection()
    {
        return $this->db;
    }
}
?>
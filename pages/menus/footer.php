<?php
$base_url = 'http://203.188.54.9/~u6511130055/cbt/';
if ($_SESSION['pageName'] == 'home'):
    ?>
        <footer class="footer py-4" style="bottom: 0;">        
            <div class="container">
                <div class="row justify-content-end">
                    <!-- About Section -->
                    <div class="col-md-2 mb-4">
                        <h6 class="text-uppercase fw-bold mb-4">เกี่ยวกับมหาวิทยาลัย</h6>
                        <ul class="list-unstyled">
                            <li><a href="#" class="text-reset">เกี่ยวกับมหานคร</a></li>
                            <li><a href="#" class="text-reset">Art for Life</a></li>
                            <li><a href="#" class="text-reset">Privacy Policy</a></li>
                            <li><a href="#" class="text-reset">Cookie Policy</a></li>
                        </ul>
                    </div>

                    <!-- Contact Section -->
                    <div class="col-md-2 mb-4">
                        <h6 class="text-uppercase fw-bold mb-4">ข้อมูลติดต่อ</h6>
                        <ul class="list-unstyled">
                            <li><a href="#" class="text-reset">บุคลากร</a></li>
                            <li><a href="#" class="text-reset">ติดต่อเรา</a></li>
                            <li><a href="#" class="text-reset">หน่วยงานภายใน</a></li>
                            <li><a href="#" class="text-reset">ร่วมงานกับมหาวิทยาลัย</a></li>
                        </ul>
                    </div>
                    <!-- Student Info Section -->
                    <div class="col-md-2 mb-4">
                        <h6 class="text-uppercase fw-bold mb-4">เกี่ยวกับนักศึกษา</h6>
                        <ul class="list-unstyled">
                            <li><a href="#" class="text-reset">ปฏิทินการศึกษา</a></li>
                            <li><a href="#" class="text-reset">ข้อบังคับและระเบียบ</a></li>
                            <li><a href="#" class="text-reset">ค่าเทอมและทุน</a></li>
                            <li><a href="#" class="text-reset">สำนักงานทะเบียน</a></li>
                        </ul>
                    </div>
                    <!-- MUT Logo and Contact Info -->
                    <div class="col-md-4 mb-4 text-md-end text-center">
                        <img src="https://mut.ac.th/wp-content/uploads/2022/03/LOGO-Mut-New-03-corp-300x125.png"
                            alt="MUT Logo" style="width: 200px;">
                        <p class="mt-2">
                            มหาวิทยาลัยเทคโนโลยีมหานคร
                            <br> 140 ถนนเชื่อมสัมพันธ์ แขวงกระทุ่มราย เขตหนองจอก กรุงเทพมหานคร 10530
                        </p>
                        <p>โทร: 02-988-4021-4 (สมัครเรียน)</p>
                        <div>
                            <a href="#" class="me-3"><i class="fab fa-facebook fa-lg"></i></a>
                            <a href="#" class="me-3"><i class="fab fa-line fa-lg"></i></a>
                            <a href="#" class="me-3"><i class="fab fa-instagram fa-lg"></i></a>
                            <a href="#" class="me-3"><i class="fab fa-youtube fa-lg"></i></a>
                        </div>
                    </div>
                    <hr>
                </div>
                <div class="text-center mt-4">
                    <p>Copyright © Mahanakorn University of Technology 2024. All rights reserved.</p>
                </div>
            </div>        
        </footer>
<?php else: ?>
    <div class="text-center mt-4">
        <hr>
        <p>Copyright © Mahanakorn University of Technology 2024. All rights reserved.</p>
    </div>
<?php endif; ?>

<!-- Swal Success and Error Messages -->
<?php if (isset($_SESSION['error'])): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?php echo htmlspecialchars($_SESSION['error']); ?>',
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: true
        });
    </script>
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['success'])): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Success',
            text: '<?php echo htmlspecialchars($_SESSION['success']); ?>',
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: true
        });
    </script>
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['warning'])): ?>
    <script>
        Swal.fire({
            icon: 'warning',
            title: 'Warning',
            text: '<?php echo htmlspecialchars($_SESSION['warning']); ?>',
            timer: 3000,
            timerProgressBar: true,
            showConfirmButton: true
        });
    </script>
    <?php unset($_SESSION['warning']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['wellcome'])): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: '<?php echo htmlspecialchars($_SESSION['wellcome']); ?>',
            timer: 1800,
            timerProgressBar: true,
            showConfirmButton: true
        });
    </script>
    <?php unset($_SESSION['wellcome']); ?>
<?php endif; ?>

<style>
    .footer {
        background-color: #661E1E;
        /* MUT primary color */
        color: white;
        padding: 40px 0;
    }

    .footer a {
        color: white;
        text-decoration: none;
    }

    .footer a:hover {
        text-decoration: underline;
        color: #ddd;
    }

    .footer i {
        color: white;
        font-size: 24px;
    }

    .footer ul {
        list-style: none;
        padding: 0;
    }

    .footer img {
        display: block;
        margin: 0 0 0 55%;
    }

    .footer h6 {
        color: white;
    }

    .footer p {
        margin-bottom: 5px;
    }

    @media (min-width: 768px) {
        .footer .col-md-4 {
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }
    }
</style>

<!-- jQuery Start -->
<script src="<?= $base_url ?>assets/js/jquery.js"></script>
<script src="<?= $base_url ?>assets/js/function.js"></script>
<!-- jQuery End-->

<!-- MDBootstrap Start -->
<script 
  type="text/javascript"
  src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/8.0.0/mdb.umd.min.js">
</script> <!-- Mobile -->

<script type="text/javascript"
src="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.0.0/mdb.min.js">
</script>  <!-- Desktop -->

<link href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.0.0/mdb.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/mdb-ui-kit/6.0.0/mdb.min.css" />

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php ob_end_flush(); ?>
<?php
require __DIR__ . '/includes/bootstrap.php';
if (!empty($_SESSION['admin_id'])) {
    redirect('/admin/dashboard.php');
}
redirect('/admin/login.php');

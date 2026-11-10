<?php
// Protect admin pages from visitors who have not signed in.
if (empty($_SESSION['admin_id'])) {
    redirect('/admin/login.php');
}

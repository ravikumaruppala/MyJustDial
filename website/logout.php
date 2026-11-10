<?php
/** Ends the website member session and returns to the home page. */
require __DIR__ . '/includes/bootstrap.php';
unset($_SESSION['member_id'], $_SESSION['member_name']);
session_regenerate_id(true);
header('Location: ' . site_url('index.php'));
exit;

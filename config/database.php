<?php

if (!isset($db_host)) { $db_host = 'localhost'; }
if (!isset($db_user)) { $db_user = 'root'; }
if (!isset($db_pass)) { $db_pass = ''; }

if (!isset($db_name)) { $db_name = 'Simple_Market_db'; }

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die('Connection failed: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8mb4');

<?php
global $conn;
global $title;
global $separator;
global $description;
global $logo;
$user_logged_in = $_SESSION['logged_in'] ?? false;
if ($user_logged_in === true) {
    $id = $_SESSION['user']['id'];
    $username = $_SESSION['user']['uname'];
    $name = $_SESSION['user']['name'];
    $email = $_SESSION['user']['email'];
} else {
    $username = "MMORTS";
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <!-- <meta http-equiv="X-UA-Compatible" content="IE=edge"> -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Teko:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="frontend/design/css/bootstrap.min.css">
    <link rel="stylesheet" href="frontend/design/css/custom.css">
</head>

<body>
    <section class="layout">
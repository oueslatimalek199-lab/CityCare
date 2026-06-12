<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($title) ? $title : 'CityCare'; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/public/css/style1.css">
</head>
<body>
<header>
    <nav class="navbar">
        <div class="container">
            <div class="logo">
                <img src="<?php echo BASE_URL; ?>/public/images/LOGO.png" alt="CityCare Logo">
                <span>CityCare</span>
            </div>
            <ul class="nav-menu">
                <li><a href="<?php echo BASE_URL; ?>/">Home</a></li>
                <li><a href="<?php echo BASE_URL; ?>/services">Services</a></li>
                <li><a href="<?php echo BASE_URL; ?>/complaints">Complaints</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="<?php echo BASE_URL; ?>/profile">Profile</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/logout">Logout</a></li>
                <?php else: ?>
                    <li><a href="<?php echo BASE_URL; ?>/login">Login</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/register">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>
</header>
<main class="container">
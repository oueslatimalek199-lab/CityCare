<?php
/**
 * includes/header.php
 * Full HTML shell header — used by pages that don't use dashboard_header.php.
 * Provides <html><head><body> and the page wrapper div.
 *
 * Usage:
 * $headerConfig = [
 *     'title'      => 'Page Title',
 *     'subtitle'   => 'Page Subtitle',
 *     'icon'       => '🎯',
 *     'bgGradient' => 'linear-gradient(...)',
 *     'role'       => 'Citoyen|Agent|Admin'
 * ];
 * require 'includes/header.php';
 */

$defaults = [
    'title'      => 'CityCare',
    'subtitle'   => 'Plateforme Municipale',
    'icon'       => '🏛️',
    'bgGradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
];
$config = array_merge($defaults, $headerConfig ?? []);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($config['title']) ?> — CityCare</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
        }
        .page-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .main-content {
            /* content area inside footer's closing div */
        }
        /* Global utility classes shared across all pages */
        .btn { display:inline-block;padding:8px 16px;border:none;border-radius:4px;cursor:pointer;text-decoration:none;font-size:13px;font-weight:600;transition:all .3s; }
        .btn-primary  { background:#667eea;color:white; }
        .btn-primary:hover { background:#5568d3; }
        .btn-danger   { background:#dc3545;color:white; }
        .btn-danger:hover { background:#c82333; }
        .btn-sm { padding:5px 10px;font-size:12px; }
        .badge { display:inline-block;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600; }
        .badge-attente    { background:#fff3cd;color:#856404; }
        .badge-traitement { background:#d1ecf1;color:#0c5460; }
        .badge-resolu     { background:#d4edda;color:#155724; }
        .badge-annule     { background:#f8d7da;color:#721c24; }
        .alert-error   { background:#fdecea;color:#b00020;padding:15px;border-radius:5px;margin-bottom:20px;border-left:4px solid #b00020; }
        .alert-success { background:#d4edda;color:#155724;padding:15px;border-radius:5px;margin-bottom:20px;border-left:4px solid #28a745; }
        input[type="text"],input[type="email"],input[type="password"],input[type="tel"],
        textarea, select {
            width:100%; padding:10px 12px; border:1px solid #ddd; border-radius:6px;
            font-size:14px; font-family:inherit; outline:none; transition:border .2s;
            box-sizing:border-box;
        }
        input:focus, textarea:focus, select:focus {
            border-color:#667eea; box-shadow:0 0 0 3px rgba(102,126,234,.1);
        }
        label { display:block;font-weight:600;margin-bottom:5px;color:#444;font-size:14px; }
    </style>
</head>
<body>
<div class="page-wrapper">

    <!-- Coloured header banner -->
    <div class="dashboard-header" style="background:<?= $config['bgGradient'] ?>;color:white;padding:25px;border-radius:8px;margin-bottom:25px;box-shadow:0 4px 15px rgba(0,0,0,.2);">
        <h1 style="margin:0 0 6px;font-size:28px;"><?= $config['icon'] ?> <?= htmlspecialchars($config['title']) ?></h1>
        <p style="margin:0;font-size:14px;opacity:.9;"><?= htmlspecialchars($config['subtitle']) ?></p>
    </div>

    <div class="main-content">
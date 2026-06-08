<?php
// logout.php
require_once 'config/session.php';
require_once 'Auth/auth.php';

Auth::deconnecter();
header('Location: login.php');
exit;
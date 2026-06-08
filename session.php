<?php
// config/session.php

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,   // passer à true en production (HTTPS)
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}
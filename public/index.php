<?php
// Main entry point for the application
// Set base constants
define('BASE_URL', 'http://localhost/CityCare'); // Change to your domain
define('APP_PATH', dirname(__DIR__) . '/app');
define('ROOT_PATH', dirname(__DIR__));

// Include configuration and autoloader
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/app/autoload.php';

// Simple routing
$request = isset($_GET['request']) ? $_GET['request'] : 'home';
$request = rtrim($request, '/');

// Route the request
route($request);

function route($request) {
    $parts = explode('/', $request);
    $controller = isset($parts[0]) ? ucfirst($parts[0]) . 'Controller' : 'HomeController';
    $action = isset($parts[1]) ? $parts[1] : 'index';
    
    $controllerPath = APP_PATH . '/Controllers/' . $controller . '.php';
    
    if (file_exists($controllerPath)) {
        require_once $controllerPath;
        $controllerClass = new $controller();
        
        if (method_exists($controllerClass, $action)) {
            $controllerClass->$action();
        } else {
            echo "Action not found: $action";
        }
    } else {
        echo "Controller not found: $controller";
    }
}
?>
<?php
/**
 * JobHub - Logout Handler
 * File: logout.php
 * Description: Destroys the user session completely and clears
 *              all cookies. Redirects to home page.
 */

require_once 'config/db.php';

// Destroy session data
$_SESSION = [];

// Destroy session cookie
if (ini_get('session.use_cookies')) 
{
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

// Destroy the session itself
session_destroy();

// Clear remember-me cookie
setcookie('remember_token', '', time() - 3600, '/', '', false, true);

// Redirect to home
header('Location: ' . BASE_URL . '/index.php');
exit;

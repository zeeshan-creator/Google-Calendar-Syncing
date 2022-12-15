<?php
// require_once('../core/db.php');
require_once('../core/login_checked.php');

require_once __DIR__ . '/../vendor/autoload.php';


// session_start();
global $conn;

$client = new Google\Client();
$client->setAuthConfig(__DIR__ . '/../client_secret_43161991761-h60kkqsk7e5ipjavo8577asa1mr2okqs.apps.googleusercontent.com.json');
$client->setRedirectUri('https://phpstack-539799-2649384.cloudwaysapps.com/api_views/oauth2callback.php');
$client->setAccessType('offline');
$client->setApprovalPrompt('force');
$client->addScope(
    array(
        "https://www.googleapis.com/auth/calendar.readonly",
        "https://www.googleapis.com/auth/calendar"
    )
);
if (!isset($_GET['code'])) {
    $auth_url = $client->createAuthUrl();
    header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
} else {
    $client->fetchAccessTokenWithAuthCode($_GET['code']);
    $access_token = $client->getAccessToken();
    $refresh_token = $client->getRefreshToken();
    $json_access_token = json_encode($access_token);

    $query = "SELECT * FROM `access_tokens` WHERE `user_id` = '$auth_id'";
    $result = mysqli_query($conn, $query);
    $numrows = mysqli_num_rows($result);

    if ($numrows > 0) {
        $query = "UPDATE `access_tokens` SET `access_token` = '$json_access_token', `refresh_token` = '$refresh_token', `created_at` = '" . date('Y-m-d H:i:s', time()) . "' WHERE `access_tokens`.`user_id` = '$auth_id'";
        mysqli_query($conn, $query);
    } else {
        $query = "INSERT INTO `access_tokens` (`access_token`,`refresh_token`, `expires_in`, `user_id`, `created_at`) VALUES ('" . json_encode($access_token) . "', '$refresh_token', '" . $access_token['expires_in'] . "', '$auth_id', '" . date('Y-m-d H:i:s', time()) . "')";
        mysqli_query($conn, $query);
    }

    setcookie("access_token", json_encode($access_token), time() + 3600, "/");
    $redirect_uri = 'https://phpstack-539799-2649384.cloudwaysapps.com';
    header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));
}

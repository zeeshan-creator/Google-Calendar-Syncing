<?php

require_once __DIR__ . '/../core/login_checked.php';

require_once __DIR__ . '/../vendor/autoload.php';

global $conn;

$client = new Google\Client();
$client->setAccessType('offline');
$client->setAuthConfig(__DIR__ . '/../client_secret_43161991761-h60kkqsk7e5ipjavo8577asa1mr2okqs.apps.googleusercontent.com.json');
$client->addScope(
    array(
        "https://www.googleapis.com/auth/calendar"
    )
);
$client->setRedirectUri('https://phpstack-539799-2649384.cloudwaysapps.com/api_views/oauth2callback.php');
$client->setAccessType('offline');
$client->setApprovalPrompt('force');

$query = "SELECT * FROM `access_tokens`";
$result = mysqli_query($conn, $query);

while ($row = mysqli_fetch_array($result)) {
    $access_token = json_decode($row['access_token'], true);
    $refresh_token = $row['refresh_token'];

    $created_at =   strtotime($row['created_at']);
    $expires_in =   $row['expires_in'];
    $expiry_time = $created_at + intval($expires_in);
    $remaining = $expiry_time - time();



    if ($expiry_time > time()) {
        echo "<br> User_id   : " . $row['user_id'] . ", remaining " . $remaining;
        if ($remaining < 300) {
            echo "<br>remaining < 5min  : " . $remaining;
            $client->fetchAccessTokenWithRefreshToken($refresh_token);
            $accessTokenUpdated = $client->getAccessToken();
            $refreshTokenSaved = $client->getRefreshToken();

            refreshAccessToken($row['user_id'], $accessTokenUpdated, $refreshTokenSaved);
        }
    } else {
        $client->fetchAccessTokenWithRefreshToken($refresh_token);
        $accessTokenUpdated = $client->getAccessToken();
        $refreshTokenSaved = $client->getRefreshToken();
        refreshAccessToken($row['user_id'], $accessTokenUpdated, $refreshTokenSaved);
    }

    // Set the access token on the client.
    $client->setAccessToken($access_token);

    if ($client->isAccessTokenExpired()) {
        echo "Seems like access token is expired User_id : " . $row['user_id'];

        $client->fetchAccessTokenWithRefreshToken($refresh_token);
        $accessTokenUpdated = $client->getAccessToken();
        $refreshTokenSaved = $client->getRefreshToken();

        refreshAccessToken($row['user_id'], $accessTokenUpdated, $refreshTokenSaved);
    }
}


function refreshAccessToken($auth_id, $access_token, $refresh_token)
{
    global $conn;

    $query = "UPDATE `access_tokens` SET `access_token` = '" . json_encode($access_token) . "', `refresh_token` = '" . $refresh_token . "', `created_at` = '" . date('Y-m-d H:i:s', time()) . "' WHERE `access_tokens`.`user_id` = '$auth_id'";
    mysqli_query($conn, $query);
}

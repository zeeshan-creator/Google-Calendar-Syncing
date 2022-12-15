<?php
require_once __DIR__ . '/../../core/login_checked.php';
require_once __DIR__ . '/../../vendor/autoload.php';

global $conn;

use RRule\RRule;

$client = new Google\Client();
$client->setAuthConfig(__DIR__ . '/../../client_secret_43161991761-h60kkqsk7e5ipjavo8577asa1mr2okqs.apps.googleusercontent.com.json');
$client->addScope(
    array(
        "https://www.googleapis.com/auth/calendar"
    )
);
$client->setRedirectUri('https://phpstack-539799-2649384.cloudwaysapps.com/api_views/oauth2callback.php');
$client->setAccessType('offline');        // offline access
$client->setIncludeGrantedScopes(true);   // incremental auth


$query = "SELECT * FROM `access_tokens` WHERE `user_id` = '$auth_id' ORDER BY id DESC";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_array($result);
$access_token = json_decode($row['access_token'], true);

if (!isset($access_token) && !$access_token) {
    $redirect_uri = 'https://phpstack-539799-2649384.cloudwaysapps.com/api_views/oauth2callback.php';
    header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));
    exit;
}
$created_at =   strtotime($row['created_at']);
$expires_in =   $row['expires_in'];

$expiry_time = $created_at + intval($expires_in);
echo "Current : " . date("Y-m-d H:i:s A", time());
echo "<br>";
echo "expiry_time : " .  date("Y-m-d H:i:s A", $expiry_time);

echo "<br>";
if ($expiry_time > time()) {
    $remaining = $expiry_time - time();
    echo "remaining : " . $remaining / 60;
    // echo "<br> zk 0142";
} else {
    echo "Access Token expired";
    $redirect_uri = 'https://phpstack-539799-2649384.cloudwaysapps.com/api_views/oauth2callback.php';
    header('Location: ' . filter_var($redirect_uri, FILTER_SANITIZE_URL));
    exit;
}

$client->setAccessToken($access_token, true);
$service = new Google\Service\Calendar($client);




function row_to_array($row)
{
    // Event Title
    $event = array('summary' => $row['event_title']);
    // Event Location
    if ($row['location']) {
        $event['location'] = $row['location'];
    }
    // Event Description
    if ($row['event_description']) {
        $event['description'] = $row['event_description'];
    }
    // Event Visibility
    if ($row['event_visibility']) {
        if ($row['event_visibility'] == 'busy') {
            $event['transparency'] = 'opaque';
        }
        if ($row['event_visibility'] == 'free') {
            $event['transparency'] = 'transparent';
        }
    }
    // Event privacy
    if ($row['event_privacy']) {
        $event['visibility'] = $row['event_privacy'];
    }

    // Event Date / DateTime
    // if event is an all day event or not 
    if ($row['all_day_recurrence'] == 1) {
        $event['start'] = array('date' => $row['start_date'], 'timeZone' => $row['time_zone']);
        $event['end'] = array('date' => $row['end_date'], 'timeZone' => $row['time_zone']);
    } else {
        $event['start'] = array('dateTime' =>  $row['start_date'] . "T" . $row['start_date_time'], 'timeZone' => $row['time_zone']);
        $event['end'] = array('dateTime' =>  $row['end_date'] . "T" . $row['end_date_time'], 'timeZone' => $row['time_zone']);
    }

    // Event RRULE array
    $rrule = array();

    if (!$row['custom_rrule']) {
        // -- if daily
        if ($row['all_day_recurrence_type'] == 'daily') {
            $rrule['FREQ'] = 'Daily';
        }

        // -- if weekly_on
        if ($row['all_day_recurrence_type'] == 'weekly_on') {
            $rrule['FREQ'] = 'Weekly';
        }

        // -- if every_weekday
        if ($row['all_day_recurrence_type'] == 'every_weekday') {
            $rrule['FREQ'] = 'Weekly';
            $rrule['BYDAY'] = 'MO, TU, WE, TH, FR';
        }

        // -- if monthly_on_(n) n: any number
        if (str_contains($row['all_day_recurrence_type'], 'monthly_on_')) {
            $timestamp = strtotime($row['start_date']);
            $day = date('D', $timestamp);

            $rrule['FREQ'] = 'Monthly';
            $nthday = '';

            if (str_ends_with($row['all_day_recurrence_type'], 'first')) {
                $nthday = 1;
            }
            if (str_ends_with($row['all_day_recurrence_type'], 'second')) {
                $nthday = 2;
            }
            if (str_ends_with($row['all_day_recurrence_type'], 'third')) {
                $nthday = 3;
            }
            if (str_ends_with($row['all_day_recurrence_type'], 'fourth')) {
                $nthday = 4;
            }

            $rrule['BYDAY'] = "$nthday" .  substr($day, 0, 2);
        }

        // -- if annually_on
        if ($row['all_day_recurrence_type'] == 'annually_on') {
            $rrule['FREQ'] = 'Yearly';
        }
    } else {
        $event['recurrence'] = array($row['custom_rrule']);
    }

    // Create RRule string
    if (count($rrule) > 0) {
        $rrule = new RRule($rrule);
        $event['recurrence'] = array('RRULE:' . $rrule);
    }
    return $event;
}

function get_calendar_id($cl_db_did)
{
    global $conn;
    $query = "SELECT calendar_id FROM lead_calendars WHERE cl_db_did = '$cl_db_did'";
    $reuslt = mysqli_query($conn, $query);
    $row = mysqli_fetch_array($reuslt);
    $calendar_id = $row['calendar_id'];
    return $calendar_id;
}

// Create event on google
function INSERT_event($event_id)
{
    global $conn;

    $query0 = "SELECT * FROM lead_calnedar_events WHERE event_db_id = '$event_id'";
    $reuslt0 = mysqli_query($conn, $query0);
    $row0 = mysqli_fetch_array($reuslt0);
    $calendar_id = $row0['event_calendar_id'];

    $query9 = "SELECT cl_db_did  FROM lead_calendars WHERE calendar_id = '$calendar_id'";
    $reuslt9 = mysqli_query($conn, $query9);
    $row9 = mysqli_fetch_array($reuslt9);
    $id = $row9['cl_db_did']; // $cl_db_did 

    $query = "SELECT * FROM `api_calendar_id` WHERE calendar_id = '$id'";
    $result = mysqli_query($conn, $query);
    // $row = mysqli_fetch_array($result);

    // return mysqli_num_rows($result);
    $myArr = [];
    // while ($row = mysqli_fetch_array($result)) {
    //     array_push($myArr, $row['api_calendar_id']);
    // }
    // return $myArr;

    while ($row = mysqli_fetch_array($result)) {
        $api_calendar_id = $row['api_calendar_id'];
        $user_id = $row['user_id'];
        $query2 = "SELECT * FROM `access_tokens` WHERE `user_id` = '$user_id'";
        $result2 = mysqli_query($conn, $query2);
        $row2 = mysqli_fetch_array($result2);
        $access_token =  $row2['access_token'];

        $client = new Google\Client();
        $client->setAuthConfig(__DIR__ . '/../../client_secret_43161991761-h60kkqsk7e5ipjavo8577asa1mr2okqs.apps.googleusercontent.com.json');
        $client->addScope(
            array(
                "https://www.googleapis.com/auth/calendar"
            )
        );
        $client->setRedirectUri('https://phpstack-539799-2649384.cloudwaysapps.com/api_views/oauth2callback.php');
        $client->setAccessType('offline');        // offline access
        $client->setIncludeGrantedScopes(true);   // incremental auth


        date_default_timezone_set('America/New_York');

        $created_at =   strtotime($row2['created_at']);
        $expires_in =   $row2['expires_in'];

        $expiry_time = $created_at + intval($expires_in);
        echo "<br> current : " . date("Y-m-d H:i:s A", time());
        echo "<br> expiry_time : " . date("Y-m-d H:i:s A", $expiry_time);

        if ($expiry_time > time()) {
            $remaining = $expiry_time - time();
            echo "<br> remaining : " . $remaining / 60;
        } else {
            continue;
        }
        $client->setAccessToken($access_token, true);
        $service = new Google\Service\Calendar($client);

        // Select all events 
        $query3 = "SELECT * FROM api_event_id WHERE calendar_id = '$id' AND event_id = '$event_id' AND user_id = '$user_id'";
        $result23 = mysqli_query($conn, $query3);
        $numROWs = mysqli_num_rows($result23);

        if ($numROWs == 0) {
            $query4 = "INSERT INTO `api_event_id` (`user_id`, `event_id`, `calendar_id`) VALUES ('$user_id', '$event_id', '$id')";
            mysqli_query($conn, $query4);
        }

        $query5 = "SELECT lead_calnedar_events.*, api_event_id.api_event_id, api_event_id.user_id FROM api_event_id INNER JOIN lead_calnedar_events ON api_event_id.event_id=lead_calnedar_events.event_db_id WHERE api_event_id.event_id = '$event_id' AND api_event_id.user_id = '$user_id'";
        $result2 = mysqli_query($conn, $query5);
        $numROWs = mysqli_num_rows($result2);

        // Create or update if the event is new or changed on Site.
        $row3 = mysqli_fetch_array($result2);
        if (isset($row3['api_event_id'])) {
            $api_event_id = $row3['api_event_id'];
        }

        // Event Title
        $event = array('summary' => $row3['event_title']);
        // Event Location
        if ($row3['location']) {
            $event['location'] = $row3['location'];
        }
        // Event Description
        if ($row3['event_description']) {
            $event['description'] = $row3['event_description'];
        }
        // Event Visibility
        if ($row3['event_visibility']) {
            if ($row3['event_visibility'] == 'busy') {
                $event['transparency'] = 'opaque';
            }
            if ($row3['event_visibility'] == 'free') {
                $event['transparency'] = 'transparent';
            }
        }
        // Event privacy
        if ($row3['event_privacy']) {
            $event['visibility'] = $row3['event_privacy'];
        }

        // Event Date / DateTime
        // if event is an all day event or not 
        if ($row3['all_day_recurrence'] == 1) {
            $event['start'] = array('date' => $row3['start_date'], 'timeZone' => $row3['time_zone']);
            $event['end'] = array('date' => $row3['end_date'], 'timeZone' => $row3['time_zone']);
        } else {
            $event['start'] = array('dateTime' =>  $row3['start_date'] . "T" . $row3['start_date_time'], 'timeZone' => $row3['time_zone']);
            $event['end'] = array('dateTime' =>  $row3['end_date'] . "T" . $row3['end_date_time'], 'timeZone' => $row3['time_zone']);
        }

        // Event RRULE array
        $rrule = array();

        if (!$row3['custom_rrule']) {
            // -- if daily
            if ($row3['all_day_recurrence_type'] == 'daily') {
                $rrule['FREQ'] = 'Daily';
            }

            // -- if weekly_on
            if ($row3['all_day_recurrence_type'] == 'weekly_on') {
                $rrule['FREQ'] = 'Weekly';
            }

            // -- if every_weekday
            if ($row3['all_day_recurrence_type'] == 'every_weekday') {
                $rrule['FREQ'] = 'Weekly';
                $rrule['BYDAY'] = 'MO, TU, WE, TH, FR';
            }

            // -- if monthly_on_(n) n: any number
            if (str_contains($row3['all_day_recurrence_type'], 'monthly_on_')) {
                $timestamp = strtotime($row3['start_date']);
                $day = date('D', $timestamp);

                $rrule['FREQ'] = 'Monthly';
                $nthday = '';

                if (str_ends_with($row3['all_day_recurrence_type'], 'first')) {
                    $nthday = 1;
                }
                if (str_ends_with($row3['all_day_recurrence_type'], 'second')) {
                    $nthday = 2;
                }
                if (str_ends_with($row3['all_day_recurrence_type'], 'third')) {
                    $nthday = 3;
                }
                if (str_ends_with($row3['all_day_recurrence_type'], 'fourth')) {
                    $nthday = 4;
                }

                $rrule['BYDAY'] = "$nthday" .  substr($day, 0, 2);
            }

            // -- if annually_on
            if ($row3['all_day_recurrence_type'] == 'annually_on') {
                $rrule['FREQ'] = 'Yearly';
            }
        } else {
            $event['recurrence'] = array($row3['custom_rrule']);
        }

        // Create RRule string
        if (count($rrule) > 0) {
            $rrule = new RRule($rrule);
            $event['recurrence'] = array('RRULE:' . $rrule);
        }

        // Passing Event Object
        $event = new Google\Service\Calendar\Event($event);

        $event = $service->events->insert($api_calendar_id, $event);

        $api_event_id = $event->id;


        // updating event api_event_api
        $query6 = "UPDATE `api_event_id` SET `calendar_id` = '$id', `api_event_id` = '$api_event_id' WHERE `api_event_id`.`event_id` = $event_id AND `user_id` = '$user_id'";
        $result6 = mysqli_query($conn, $query6);
        array_push($myArr,  $result6);
    }
    return $myArr;
}

// Update event on google
function UPDATE_event($event_id)
{
    global $conn;

    $query0 = "SELECT * FROM lead_calnedar_events WHERE event_db_id = '$event_id'";
    $reuslt0 = mysqli_query($conn, $query0);
    $row0 = mysqli_fetch_array($reuslt0);
    $calendar_id = $row0['event_calendar_id'];

    $query9 = "SELECT cl_db_did  FROM lead_calendars WHERE calendar_id = '$calendar_id'";
    $reuslt9 = mysqli_query($conn, $query9);
    $row9 = mysqli_fetch_array($reuslt9);
    $id = $row9['cl_db_did']; // $cl_db_did 

    $query = "SELECT * FROM `api_calendar_id` WHERE calendar_id = '$id'";
    $result = mysqli_query($conn, $query);
    // $row = mysqli_fetch_array($result);

    // return mysqli_num_rows($result);
    $myArr = [];
    // while ($row = mysqli_fetch_array($result)) {
    //     array_push($myArr, $row['api_calendar_id']);
    // }
    // return $myArr;

    while ($row = mysqli_fetch_array($result)) {
        $api_calendar_id = $row['api_calendar_id'];
        $user_id = $row['user_id'];
        $query2 = "SELECT `access_token` FROM `access_tokens` WHERE `user_id` = '$user_id'";
        $result2 = mysqli_query($conn, $query2);
        $row2 = mysqli_fetch_array($result2);
        $access_token =  $row2['access_token'];

        $client = new Google\Client();
        $client->setAuthConfig(__DIR__ . '/../../client_secret_43161991761-h60kkqsk7e5ipjavo8577asa1mr2okqs.apps.googleusercontent.com.json');
        $client->addScope(
            array(
                "https://www.googleapis.com/auth/calendar"
            )
        );
        $client->setRedirectUri('https://phpstack-539799-2649384.cloudwaysapps.com/api_views/oauth2callback.php');
        $client->setAccessType('offline');        // offline access
        $client->setIncludeGrantedScopes(true);   // incremental auth
        $created_at =   strtotime($row['created_at']);
        $expires_in =   $row['expires_in'];

        $expiry_time = $created_at + intval($expires_in);
        echo date("Y-m-d H:i:s A", time());
        echo "<br>";
        echo date("Y-m-d H:i:s A", $expiry_time);

        echo "<br>";
        if ($expiry_time > time()) {
            $remaining = $expiry_time - time();
            echo $remaining / 60;
        } else {
            continue;
        }
        $client->setAccessToken($access_token, true);
        $service = new Google\Service\Calendar($client);

        // Select Event 

        $query5 = "SELECT lead_calnedar_events.*, api_event_id.api_event_id, api_event_id.user_id FROM api_event_id INNER JOIN lead_calnedar_events ON api_event_id.event_id=lead_calnedar_events.event_db_id WHERE api_event_id.event_id = '$event_id' AND api_event_id.user_id = '$user_id'";
        $result2 = mysqli_query($conn, $query5);

        // Create or update if the event is new or changed on Site.
        $row3 = mysqli_fetch_array($result2);
        if (isset($row3['api_event_id'])) {
            $api_event_id = $row3['api_event_id'];
        }

        // Event Title
        $event = array('summary' => $row3['event_title']);
        // Event Location
        if ($row3['location']) {
            $event['location'] = $row3['location'];
        }
        // Event Description
        if ($row3['event_description']) {
            $event['description'] = $row3['event_description'];
        }
        // Event Visibility
        if ($row3['event_visibility']) {
            if ($row3['event_visibility'] == 'busy') {
                $event['transparency'] = 'opaque';
            }
            if ($row3['event_visibility'] == 'free') {
                $event['transparency'] = 'transparent';
            }
        }
        // Event privacy
        if ($row3['event_privacy']) {
            $event['visibility'] = $row3['event_privacy'];
        }

        // Event Date / DateTime
        // if event is an all day event or not 
        if ($row3['all_day_recurrence'] == 1) {
            $event['start'] = array('date' => $row3['start_date'], 'timeZone' => $row3['time_zone']);
            $event['end'] = array('date' => $row3['end_date'], 'timeZone' => $row3['time_zone']);
        } else {
            $event['start'] = array('dateTime' =>  $row3['start_date'] . "T" . $row3['start_date_time'], 'timeZone' => $row3['time_zone']);
            $event['end'] = array('dateTime' =>  $row3['end_date'] . "T" . $row3['end_date_time'], 'timeZone' => $row3['time_zone']);
        }

        // Event RRULE array
        $rrule = array();

        if (!$row3['custom_rrule']) {
            // -- if daily
            if ($row3['all_day_recurrence_type'] == 'daily') {
                $rrule['FREQ'] = 'Daily';
            }

            // -- if weekly_on
            if ($row3['all_day_recurrence_type'] == 'weekly_on') {
                $rrule['FREQ'] = 'Weekly';
            }

            // -- if every_weekday
            if ($row3['all_day_recurrence_type'] == 'every_weekday') {
                $rrule['FREQ'] = 'Weekly';
                $rrule['BYDAY'] = 'MO, TU, WE, TH, FR';
            }

            // -- if monthly_on_(n) n: any number
            if (str_contains($row3['all_day_recurrence_type'], 'monthly_on_')) {
                $timestamp = strtotime($row3['start_date']);
                $day = date('D', $timestamp);

                $rrule['FREQ'] = 'Monthly';
                $nthday = '';

                if (str_ends_with($row3['all_day_recurrence_type'], 'first')) {
                    $nthday = 1;
                }
                if (str_ends_with($row3['all_day_recurrence_type'], 'second')) {
                    $nthday = 2;
                }
                if (str_ends_with($row3['all_day_recurrence_type'], 'third')) {
                    $nthday = 3;
                }
                if (str_ends_with($row3['all_day_recurrence_type'], 'fourth')) {
                    $nthday = 4;
                }

                $rrule['BYDAY'] = "$nthday" .  substr($day, 0, 2);
            }

            // -- if annually_on
            if ($row3['all_day_recurrence_type'] == 'annually_on') {
                $rrule['FREQ'] = 'Yearly';
            }
        } else {
            $event['recurrence'] = array($row3['custom_rrule']);
        }

        // Create RRule string
        if (count($rrule) > 0) {
            $rrule = new RRule($rrule);
            $event['recurrence'] = array('RRULE:' . $rrule);
        }

        // Passing Event Object
        $event = new Google\Service\Calendar\Event($event);

        $event = $service->events->patch($api_calendar_id, $row3['api_event_id'], $event);
        $api_event_id = $event->id;

        array_push($myArr, $api_event_id);

        // updating event api_event_api
        // $query6 = "UPDATE `api_event_id` SET `calendar_id` = '$id', `api_event_id` = '$api_event_id' WHERE `api_event_id`.`event_id` = $event_id AND `user_id` = '$user_id'";
        // $result6 = mysqli_query($conn, $query6);
    }
    return $myArr;
}

// Update event on google
function DELETE_event($event_id)
{
    global $conn;

    $query0 = "SELECT * FROM lead_calnedar_events WHERE event_db_id = '$event_id'";
    $reuslt0 = mysqli_query($conn, $query0);
    $row0 = mysqli_fetch_array($reuslt0);
    $calendar_id = $row0['event_calendar_id'];


    $query9 = "SELECT cl_db_did  FROM lead_calendars WHERE calendar_id = '$calendar_id'";
    $reuslt9 = mysqli_query($conn, $query9);
    $row9 = mysqli_fetch_array($reuslt9);
    $id = $row9['cl_db_did']; // $cl_db_did 

    $query = "SELECT * FROM `api_calendar_id` WHERE calendar_id = '$id'";
    $result = mysqli_query($conn, $query);
    // $row = mysqli_fetch_array($result);

    // return mysqli_num_rows($result);
    $myArr = [];
    // while ($row = mysqli_fetch_array($result)) {
    //     array_push($myArr, $row['api_calendar_id']);
    // }
    // return $myArr;

    while ($row = mysqli_fetch_array($result)) {
        $api_calendar_id = $row['api_calendar_id'];
        $user_id = $row['user_id'];
        $query2 = "SELECT `access_token` FROM `access_tokens` WHERE `user_id` = '$user_id'";
        $result2 = mysqli_query($conn, $query2);
        $row2 = mysqli_fetch_array($result2);
        $access_token =  $row2['access_token'];

        $client = new Google\Client();
        $client->setAuthConfig(__DIR__ . '/../../client_secret_43161991761-h60kkqsk7e5ipjavo8577asa1mr2okqs.apps.googleusercontent.com.json');
        $client->addScope(
            array(
                "https://www.googleapis.com/auth/calendar"
            )
        );
        $client->setRedirectUri('https://phpstack-539799-2649384.cloudwaysapps.com/api_views/oauth2callback.php');
        $client->setAccessType('offline');        // offline access
        $client->setIncludeGrantedScopes(true);   // incremental auth
        $created_at =   strtotime($row['created_at']);
        $expires_in =   $row['expires_in'];

        $expiry_time = $created_at + intval($expires_in);
        echo date("Y-m-d H:i:s A", time());
        echo "<br>";
        echo date("Y-m-d H:i:s A", $expiry_time);

        echo "<br>";
        if ($expiry_time > time()) {
            $remaining = $expiry_time - time();
            echo $remaining / 60;
        } else {
            continue;
        }
        $client->setAccessToken($access_token, true);
        $service = new Google\Service\Calendar($client);

        // Select Event 
        $query5 = "SELECT lead_calnedar_events.*, api_event_id.api_event_id, api_event_id.user_id FROM api_event_id INNER JOIN lead_calnedar_events ON api_event_id.event_id=lead_calnedar_events.event_db_id WHERE api_event_id.event_id = '$event_id' AND api_event_id.user_id = '$user_id'";
        $result2 = mysqli_query($conn, $query5);

        $row3 = mysqli_fetch_array($result2);

        $event = $service->events->delete($api_calendar_id, $row3['api_event_id']);
        array_push($myArr, $event);

        // updating event api_event_api
        // $query6 = "UPDATE `api_event_id` SET `calendar_id` = '$id', `api_event_id` = '$api_event_id' WHERE `api_event_id`.`event_id` = $event_id AND `user_id` = '$user_id'";
        // $result6 = mysqli_query($conn, $query6);
    }
    return $myArr;
}

// Sync events on google
function sync_event($cl_db_did, $auth_id)
{
    global $conn;
    global $service;
    $myArr = [];

    $event_calendar_id = get_calendar_id($cl_db_did);

    $query0 = "SELECT * FROM `lead_calnedar_events` WHERE event_calendar_id = '$event_calendar_id'";
    $result0 = mysqli_query($conn, $query0);

    while ($row0 = mysqli_fetch_array($result0)) {
        $event_id = $row0['event_db_id'];

        // Select all events 
        $query1 = "SELECT * FROM api_event_id WHERE calendar_id = '$cl_db_did' AND event_id = '$event_id' AND user_id = '$auth_id'";
        $result1 = mysqli_query($conn, $query1);
        $numROWs1 = mysqli_num_rows($result1);

        if ($numROWs1 == 0) {
            $query2 = "INSERT INTO `api_event_id` (`user_id`, `event_id`, `calendar_id`) VALUES ('$auth_id', '$event_id', '$cl_db_did')";
            $result2 = mysqli_query($conn, $query2);

            $query3 = "SELECT * FROM `api_calendar_id` WHERE calendar_id = '$cl_db_did' AND user_id = '$auth_id'";
            $result3 = mysqli_query($conn, $query3);
            $row3 = mysqli_fetch_array($result3);
            $api_calendar_id = $row3['api_calendar_id'];

            $event = row_to_array($row0);

            // Passing Event Object
            $event = new Google\Service\Calendar\Event($event);

            $event = $service->events->insert($api_calendar_id, $event);
            $api_event_id = $event->id;

            $query4 = "UPDATE `api_event_id` SET `api_event_id` = '$api_event_id' WHERE user_id = '$auth_id' AND event_id = '$event_id' AND calendar_id = '$cl_db_did'";
            $result4 = mysqli_query($conn, $query4);

            array_push($myArr, $api_event_id);
        }
    }

    return $myArr;
}

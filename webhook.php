<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../code/calendar/calendar_functions.php';
// require_once __DIR__ . '/../code/calendar/event_functions.php';

$conn = mysqli_connect("localhost", "ccdqbsgktu", "yhB6BZtmtS@@", "ccdqbsgktu");


use RRule\RRule;

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
function INSERT_event($event_id, $auth_id)
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

    $query = "SELECT * FROM `api_calendar_id` WHERE calendar_id = '$id' AND user_id != '$auth_id'";
    $result = mysqli_query($conn, $query);

    // echo "query => $query";

    $myArr = [];

    while ($row = mysqli_fetch_array($result)) {
        $api_calendar_id = $row['api_calendar_id'];
        array_push($myArr,  $api_calendar_id);

        $user_id = $row['user_id'];
        $query2 = "SELECT * FROM `access_tokens` WHERE `user_id` = '$user_id'";
        $result2 = mysqli_query($conn, $query2);
        $row2 = mysqli_fetch_array($result2);
        $access_token =  $row2['access_token'];

        $client = new Google\Client();
        $client->setAuthConfig(__DIR__ . '/../client_secret_43161991761-h60kkqsk7e5ipjavo8577asa1mr2okqs.apps.googleusercontent.com.json');
        $client->addScope(
            array(
                "https://www.googleapis.com/auth/calendar"
            )
        );
        $client->setRedirectUri('https://phpstack-539799-2649384.cloudwaysapps.com/api_views/oauth2callback.php');
        $client->setAccessType('offline');        // offline access
        $client->setIncludeGrantedScopes(true);   // incremental auth




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

        $event = row_to_array($row3);

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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {



    $headers = array();
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0) {
            $headers[str_replace(' ', '', ucwords(str_replace('_', '-', strtolower(substr($key, 5)))))] = $value;
        }
    }

    $channel_id = $headers['X-goog-channel-id'];


    $query0 =  "SELECT * FROM `notification_channel` WHERE channel_id = '$channel_id'";
    $result0 = mysqli_query($conn, $query0);
    $row0  = mysqli_fetch_array($result0);
    $calendar_id  = $row0['calendar_id'];
    $notification_channel_id  = $row0['id'];
    $user_id  = $row0['user_id'];

    $query1 =  "SELECT * FROM `api_calendar_id` WHERE calendar_id = '$calendar_id' AND user_id = '$user_id'";
    $result1 = mysqli_query($conn, $query1);
    $row1  = mysqli_fetch_array($result1);
    $api_calendar_id  = $row1['api_calendar_id'];


    $query2 =  "SELECT * FROM `sync_tokens` WHERE notification_channel_id = '$notification_channel_id' ORDER by id DESC LIMIT 1";
    $result2 = mysqli_query($conn, $query2);
    $row22  = mysqli_fetch_array($result2);
    $sync_token  = $row22['sync_token'];


    $client = new Google\Client();
    $client->setAuthConfig(__DIR__ . '/../client_secret_43161991761-h60kkqsk7e5ipjavo8577asa1mr2okqs.apps.googleusercontent.com.json');
    $client->addScope(
        array(
            "https://www.googleapis.com/auth/calendar"
        )
    );
    $client->setRedirectUri('https://phpstack-539799-2649384.cloudwaysapps.com/api_views/oauth2callback.php');
    $client->setAccessType('offline');        // offline access
    $client->setIncludeGrantedScopes(true);   // incremental auth

    $query3 = "SELECT * FROM `access_tokens` WHERE `user_id` = '$user_id' ORDER BY id DESC";
    $result3 = mysqli_query($conn, $query3);

    $row3 = mysqli_fetch_array($result3);
    $access_token = json_decode($row3['access_token'], true);

    if (!isset($access_token) && !$access_token) {
        exit;
    }



    $created_at =   strtotime($row3['created_at']);
    $expires_in =   $row3['expires_in'];
    $expiry_time = $created_at + intval($expires_in);

    if ($expiry_time > time()) {
        $remaining = $expiry_time - time();
        echo "<br> IF  -- remaining : " . $remaining / 60 . "<br>";
    } else {
        exit;
    }

    $client->setAccessToken($access_token, true);
    $service = new Google\Service\Calendar($client);



    if ($sync_token) {
        $events = $service->events->listEvents($api_calendar_id, array("syncToken" => "$sync_token"));
    } else {
        $events = $service->events->listEvents($api_calendar_id);
    }

    if ($events) {
        $myarr = [];
        while (true) {

            foreach ($events->getItems() as $event) {
                $api_event_id = $event->getId();
                array_push($myarr, $api_event_id);

                // Select all events 
                $query5 = "SELECT lead_calnedar_events.*, api_event_id.api_event_id, api_event_id.user_id FROM api_event_id INNER JOIN lead_calnedar_events ON api_event_id.event_id=lead_calnedar_events.event_db_id WHERE api_event_id.api_event_id = '$api_event_id'";

                $result5 = mysqli_query($conn, $query5);

                $coloumn_names = array();
                $coloumn_values = array();

                // Create a new event in local
                $updated_event = create_array_of_event($event);
                $updated_event['event_calendar_id'] = $calendar_id;
                $updated_event['event_type'] =  'event';
                $updated_event['event_user_id'] =  0;
                $updated_event['created_by_actual'] = 0;

                $numcount = mysqli_num_rows($result5);

                if (mysqli_num_rows($result5) == 0) {

                    foreach ($updated_event as $key => $value) {
                        array_push($coloumn_names, $key);
                        array_push($coloumn_values, $value);
                    }

                    $coloumn_names = implode("`,`", $coloumn_names);
                    $coloumn_values = implode("','", $coloumn_values);

                    $query6 = "INSERT INTO `lead_calnedar_events` (`$coloumn_names`) VALUES ('$coloumn_values')";
                    $result6 = mysqli_query($conn, $query6);
                    $last_inserted_id = $conn->insert_id;



                    // $query7 = "INSERT INTO `api_event_id` (`api_event_id`, `user_id`, `event_id`, `calendar_id`) VALUES ('$api_event_id', '$user_id', '$last_inserted_id', '$calendar_id')";
                    // $result7 = mysqli_query($conn, $query7);

                    // ======== insert on other google calendar
                    $inserted = INSERT_event($last_inserted_id, $user_id);
                    // $inserted = json_encode($inserted);
                    // print_r($inserted);
                    // $query4 =  "INSERT INTO `sync_tokens`(`sync_token`, `notification_channel_id`) VALUES (inserted','$inserted')";
                    // $result4 = mysqli_query($conn, $query4);

                    // ======== \\\\\ insert on other google calendar

                } else {
                    $row5 = mysqli_fetch_array($result5);
                    $event_db_id = $row5['event_db_id'];

                    $query8 = 'UPDATE `lead_calnedar_events` SET ';
                    foreach ($updated_event as $key => $value) {
                        $query8 .= " `$key` = \"$value\" ,";
                    }
                    $query8 = rtrim($query8, ',');
                    $query8 .= " WHERE `event_db_id` = $event_db_id";

                    $result8 = mysqli_query($conn, $query8);
                }
            }

            $syncToken = $events->getNextSyncToken();
            if ($syncToken) {
                $query4 =  "INSERT INTO `sync_tokens`(`sync_token`, `notification_channel_id`) VALUES ('$syncToken','$notification_channel_id')";
                $result4 = mysqli_query($conn, $query4);
            }

            $pageToken = $events->getNextPageToken();
            if ($pageToken) {
                $optParams = array('pageToken' => $pageToken);
                $events = $service->events->listEvents($api_calendar_id, $optParams);
            } else {
                break;
            }
        }

        // $myarr = json_encode($myarr);
        // $query4 =  "INSERT INTO `sync_tokens`(`sync_token`, `notification_channel_id`) VALUES ('myarr','$myarr')";
        // $result4 = mysqli_query($conn, $query4);
    }
}

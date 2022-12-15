<?php
require_once __DIR__ . '/../../core/login_checked.php';
require_once __DIR__ . '/../../vendor/autoload.php';

global $conn;

use RRule\RRule;


function generateRandomString($length = 10)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

// Create a key => value of coloumn => value for event;
function create_array_of_event($Google_event)
{
    $updated_event = array();

    // Google event Title
    if ($Google_event->getSummary()) {
        $updated_event['event_title'] = $Google_event->getSummary();
    }
    // Google event location
    if ($Google_event->getLocation()) {
        $updated_event['location'] = $Google_event->getLocation();
    }
    // Google event start -> date
    if ($Google_event->getStart()['date']) {
        $updated_event['start_date'] = date('Y-m-d', strtotime($Google_event->getStart()['date']));
        $updated_event['all_day_recurrence'] = 1;
    }
    // Google event start -> dateTime
    if ($Google_event->getStart()['dateTime']) {
        $updated_event['start_date'] = date('Y-m-d', strtotime($Google_event->getStart()['dateTime']));
        $updated_event['start_date_time'] = date('H:i:s', strtotime($Google_event->getStart()['dateTime']));
        $updated_event['all_day_recurrence'] = 0;
        $updated_event['all_day_recurrence_type'] = 'do_not_repeat';
    }
    // Google event End -> date
    if ($Google_event->getEnd()['date']) {
        $updated_event['End_date'] = date('Y-m-d', strtotime($Google_event->getEnd()['date']));
    }
    // Google event End -> dateTime
    if ($Google_event->getEnd()['dateTime']) {
        $updated_event['End_date'] = date('Y-m-d', strtotime($Google_event->getEnd()['dateTime']));
        $updated_event['End_date_time'] = date('H:i:s', strtotime($Google_event->getEnd()['dateTime']));
    }
    // Google event timeZone
    if ($Google_event->getStart()['timeZone']) {
        $updated_event['time_zone'] = $Google_event->getStart()['timeZone'];
    }
    // Google event privacy
    if ($Google_event->getVisibility()) {
        $updated_event['event_privacy'] = $Google_event->getVisibility();
    }
    // Google event visibility
    if ($Google_event->getTransparency()) {
        $transparency = ($Google_event->getTransparency() == 'opaque') ? 'busy' : 'free';
        $updated_event['event_visibility'] = $transparency;
    }
    // Google event recurrence
    if ($Google_event->getRecurrence()) {

        // Set recurrence rrule
        $updated_event['all_day_recurrence_type'] =  'cust';
        $updated_event['custom_rrule'] =  $Google_event->getRecurrence()[0];

        // Exploding the RRULE by ';'
        $rrule = str_replace("RRULE:", " ", $Google_event->getRecurrence());
        $rrule = explode(';', $rrule[0]);

        // then make an key value pairs of each of the RRULE feilds
        $reccurence = array();
        foreach ($rrule as $value) {
            $key_value = explode('=', $value);
            $reccurence[trim($key_value[0])] = trim($key_value[1]);
        }

        // creating RRULE in text format
        $rrule = new RRule($reccurence);
        $updated_event['event_recurrence_text'] =  $rrule->humanReadable();

        $weekdays = ["MO" => 1, "TU" => 2, "WE" => 3, "TH" => 4, "FR" => 5, "SA" => 6, "SU" => 7];

        // check if the RRULE fields exists
        if (isset($reccurence['FREQ'])) {
            // If FREQ = DAILY
            if ($reccurence['FREQ'] == 'DAILY') {
                $updated_event['custom_recurrence_repeat_period'] = 'day';
            }
            // If FREQ = WEEKLY
            if ($reccurence['FREQ'] == 'WEEKLY') {
                $updated_event['custom_recurrence_repeat_period'] = 'week';
                if (isset($reccurence['BYDAY'])) {
                    $custom_recurrence_repeat_days = '';
                    // Exploding the BYDAY by ','
                    $BYDAY = explode(',', $reccurence['BYDAY']);

                    foreach ($BYDAY as $value) {
                        $custom_recurrence_repeat_days .=  $weekdays[$value] . ',';
                    }

                    $updated_event['custom_recurrence_repeat_days'] = trim($custom_recurrence_repeat_days, ',');
                }
            }
            // If FREQ = MONTHLY
            if ($reccurence['FREQ'] == 'MONTHLY') {
                $updated_event['custom_recurrence_repeat_period'] = 'month';
                if (isset($reccurence['BYSETPOS'])) {
                    $custom_recurrence_repeat_months = $reccurence['BYSETPOS'] . ',';

                    $date = ($Google_event->getStart()['date']) ? $Google_event->getStart()['date'] : $Google_event->getStart()['dateTime'];

                    $dayofweek = date('w', strtotime($date));
                    $custom_recurrence_repeat_months .= $dayofweek;

                    $updated_event['custom_recurrence_repeat_months'] = trim($custom_recurrence_repeat_months, ',');
                }
                if (isset($reccurence['BYDAY'])) {
                    if (is_numeric($reccurence['BYDAY'][0])) {
                        $updated_event['custom_recurrence_repeat_months'] = $reccurence['BYDAY'][0];
                        $updated_event['custom_recurrence_repeat_months'] .= "," . $weekdays[substr($reccurence['BYDAY'], 1)];
                    } else {
                        // Date
                        $date = $updated_event['start_date'];

                        // extract date parts
                        list($y, $m, $d) = explode('-', date('Y-m-d', strtotime($date)));

                        // current week, min 1
                        $w = 1;

                        // for each day since the start of the month
                        for ($i = 1; $i < $d; ++$i) {
                            // if that day was a sunday and is not the first day of month
                            if ($i > 1 && date('w', strtotime("$y-$m-$i")) == 0) {
                                // increment current week
                                ++$w;
                            }
                        }
                        $updated_event['custom_recurrence_repeat_months'] = "$w," . $weekdays[substr($reccurence['BYDAY'], 2)];
                    }
                }
            }
            // If FREQ = YEARLY
            if ($reccurence['FREQ'] == 'YEARLY') {
                $updated_event['custom_recurrence_repeat_period'] = 'year';
            }
        }

        // IF COUNT exists
        if (isset($reccurence['COUNT'])) {
            $updated_event['custom_recurrence_ends_type'] = 'after';
            $updated_event['custom_recurrence_ends_type_value'] = $reccurence['COUNT'];
        }
        // IF INTERVAL exists
        if (isset($reccurence['INTERVAL'])) {
            $updated_event['custom_recurrence_repeat_interval'] =  $reccurence['INTERVAL'];
        } else {
            $updated_event['custom_recurrence_repeat_interval'] = 1;
        }
        // IF UNTIL exists
        if (isset($reccurence['UNTIL'])) {
            $updated_event['custom_recurrence_ends_type'] = 'on';
            $updated_event['custom_recurrence_ends_type_value'] = date('Y-m-d', strtotime($reccurence['UNTIL']));
        }

        if (!isset($reccurence['UNTIL']) && !isset($reccurence['COUNT'])) {
            $updated_event['custom_recurrence_ends_type'] = 'never';
        }
    }
    return $updated_event;
}



// INSERT INTO `api_calendar_id` if this table doesn't have a record with current $auth_id & $id;
function INSERT_INTO_api_calendar_id($user_email, $auth_id, $id)
{
    global $conn;

    $query = "SELECT * FROM `api_calendar_id` WHERE `user_id` ='$auth_id' AND  `calendar_id`= '$id'";
    $result = mysqli_query($conn, $query);
    $numofrow = mysqli_num_rows($result);

    if ($numofrow == 0) {
        $query = "INSERT INTO `api_calendar_id` (`user_email`,`user_id`, `calendar_id`) VALUES ('$user_email','$auth_id', '$id')";
        return mysqli_query($conn, $query);
    } else {
        return "Record already exist";
    }
}


function INSERT_calendar($client, $calendar, $auth_id, $id)
{
    global $conn;
    global $service;

    // Passing object to insert
    $createdCalendar = $service->calendars->insert($calendar);
    $api_calendar_id = $createdCalendar->id;

    // creating a record in `api_calendar_id` 
    $query0 = "UPDATE `api_calendar_id` SET `api_calendar_id` = '$api_calendar_id' WHERE `user_id` = '$auth_id' AND `calendar_id` = '$id'";

    $channel =  new Google\Service\Calendar\Channel($client);

    $channel->setId(generateRandomString(64));
    $channel->setType('web_hook');
    $channel->setAddress('https://phpstack-539799-2649384.cloudwaysapps.com/api_views/webhook.php'); // server url

    $watchEvent = $service->events->watch($api_calendar_id, $channel);

    $channel_id = $watchEvent['id'];
    $epoch = substr($watchEvent['expiration'], 0, 10);
    $dt = new DateTime("@$epoch");
    $expiration = $dt->format('Y-m-d H:i:s');

    $query1 = "INSERT INTO `notification_channel` (`channel_id`, `user_id`, `calendar_id`, `expiration`) VALUES ('$channel_id','$auth_id','$id','$expiration')";
    $result1 = mysqli_query($conn, $query1);

    return mysqli_query($conn, $query0);
}

function UPDATE_calendar($api_calendar_id, $calendar, $id)
{
    global $conn;
    global $service;

    $createdCalendar = $service->calendars->patch($api_calendar_id, $calendar);
    // reset ifChanged to 0;
    $query = "UPDATE `lead_calendars` SET `ifChanged` = '0' WHERE `lead_calendars`.`cl_db_did` = '$id'";
    mysqli_query($conn, $query);

    return  $createdCalendar;
}

function UPDATE_calendar_local($api_calendar_id, $id)
{
    global $conn;
    global $service;

    // First retrieve the calendar from the API.
    $calendar = $service->calendars->get($api_calendar_id);

    $updated_calendar = array();

    // Google Calendar Title
    if ($calendar->getSummary()) {
        $updated_calendar['calendar_name'] = $calendar->getSummary();
    }
    // Google Calendar location
    if ($calendar->getLocation()) {
        $updated_calendar['calendar_location'] = $calendar->getLocation();
    }
    // Google Calendar description
    if ($calendar->getDescription()) {
        $updated_calendar['calendar_description'] = $calendar->getLocation();
    }

    $query = 'UPDATE `lead_calendars` SET ';

    foreach ($updated_calendar as $key => $value) {
        $query .= " `$key` = '$value' ,";
    }
    $query = rtrim($query, ',');
    $query .= " WHERE `cl_db_did` = '$id'";

    return mysqli_query($conn, $query);
}

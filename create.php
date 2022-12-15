<?php
require_once 'calendar_functions.php';

require_once 'event_functions.php';


$calendar = $service->calendars->get('primary');
$user_email =  $calendar->getSummary();



if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $summary = $_POST['summary'];
    $description = $_POST['description'];

    $calendar = new Google\Service\Calendar\Calendar();
    $calendar->setSummary($summary);

    $createdCalendar = $service->calendars->insert($calendar);

    echo json_encode($createdCalendar);
}


if (isset($_GET['calendarId'])) {
    $id = $_GET['calendarId'];

    // INSERT INTO `api_calendar_id` if this table doesn't have a record with current $auth_id & $id;
    // returns result if query excuted.
    $INSERT_result = INSERT_INTO_api_calendar_id($user_email, $auth_id, $id);

    // SELECT FROM lead_calendars & api_calendar_id joined.
    $query = "SELECT lead_calendars.*,api_calendar_id.api_calendar_id FROM api_calendar_id INNER JOIN lead_calendars ON api_calendar_id.calendar_id=lead_calendars.cl_db_did WHERE api_calendar_id.user_email = '$user_email' AND  api_calendar_id.calendar_id= '$id' AND api_calendar_id.user_id='$auth_id'";



    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_array($result);

    // Variables
    if (isset($row['api_calendar_id'])) {
        $api_calendar_id = $row['api_calendar_id'];
    }

    // initializing Calendar Object
    $calendar = new Google\Service\Calendar\Calendar();

    // Setting new values
    if (isset($row['calendar_name'])) {
        $calendar->setSummary($row['calendar_name']);
    }
    if (isset($row['calendar_description'])) {
        $calendar->setDescription($row['calendar_description']);
    }
    if (isset($row['calendar_location'])) {
        $calendar->setLocation($row['calendar_location']);
    }

    // Creating or Updating Calender details
    if (!isset($api_calendar_id)) {
        // if ($api_calendar_id) not set INSERT calendar to google & update the api_calendar_id;
        $result = INSERT_calendar($client, $calendar, $auth_id, $id);
        echo "<br > If you are seeing this page please <b>refresh</b>.";
    } else {
        $query = "SELECT lead_calendars.* FROM api_calendar_id INNER JOIN lead_calendars ON api_calendar_id.calendar_id=lead_calendars.
                    cl_db_did WHERE api_calendar_id.calendar_id= '$id' AND api_calendar_id.user_id='$auth_id'";

        // $query = "SELECT * FROM `api_calendar_id` WHERE `api_calendar_id` = '$api_calendar_id'";

        $result = mysqli_query($conn, $query);

        $row = mysqli_fetch_array($result);
        if ($row['ifChanged'] == 1) {
            // ifChanged == 1 then, update calendar on google & reset ifChanged to 0;
            $createdCalendar = UPDATE_calendar($api_calendar_id, $calendar, $id);
        } else {
            // ifChanged == 0 then, update calendar in local database;
            $result6 = UPDATE_calendar_local($api_calendar_id, $id);

            if (!$result6) {
                echo "Something went wrong while updating event through API.";
            }
        }
    }

    echo "<br>";

    // Sync all the events present in this calendar but not on Google Calendar
    $sync_events = sync_event($id, $auth_id);
    print_r($sync_events);



    // Create a Session & head to the calender.php
    $_SESSION['success_message'] = "Calender synced With Google Calendar.";
    header("Location: ../../calendar?calendar=" . $id);
}

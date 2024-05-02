<?php

require 'vendor/autoload.php';

// Load enviorment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Connect to MySQL
$conn = new mysqli($_ENV['MYSQL_SERVER'], $_ENV['MYSQL_USERNAME'], $_ENV['MYSQL_PASSWORD'], $_ENV['MYSQL_DBNAME']);

// Check connection
if ($conn->connect_error) { http_response_code(400); die("MySQL Connection Failed!"); }

// If POST form data is sent
if(isset($_POST['submit'])) {
  // Validate form data
  if(!isset($_POST['id'])) { die('Missing data. Please try again.'); }
  if(!isset($_POST['training_flight'])) { die('Missing data. Please try again.'); }
  if(isset($_POST['training_flight']) && $_POST['training_flight'] == 0 && empty($_POST['flight_code'])) { die('Missing data. Please try again.'); }
  if(isset($_POST['training_flight']) && $_POST['training_flight'] == 0 && empty($_POST['mission_details'])) { die('Missing data. Please try again.'); }

  // Prepare variables
  $flight_code = $_POST['flight_code'];
  $mission_details = $_POST['mission_details'];
  $case_number = $_POST['case_number'];

  // Prepare POST data
  if($_POST['training_flight'] == 1) {
    $flight_code = 'TRAINING';
  }

  // Prepare and Bind
  $stmt = $conn->prepare("UPDATE flights SET flight_code = ?, case_number = ?, notes = ?, date_updated = NOW() WHERE id = ?");
  $stmt->bind_param("sssi", $flight_code, $case_number, $mission_details, $_POST['id']);
  $stmt->execute();
  $stmt->close();

  // Configure the Google Client
  $client = new \Google_Client();
  $client->setApplicationName('Google Sheets API');
  $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
  $client->setAccessType('offline');
  $client->setAuthConfig('credentials.json');

  // Configure the Sheets Service
  $service = new \Google_Service_Sheets($client);

  // Get Spreadsheet
  $spreadsheetId = $_ENV['SPREADSHEET_ID'];
  $spreadsheet = $service->spreadsheets->get($spreadsheetId);

  // Check if row exists
  $range = 'Sheet1';
  $response = $service->spreadsheets_values->get($spreadsheetId, $range);
  $values = $response->getValues();

  // Set up search variables
  $row = 1;
  $foundRow = 0;

  // Loop through values in spreadsheet
  foreach($values as $value) {

    if($value[1] == $_POST['flight_id']) {
      $foundRow = $row;
    }

    $row++;
  }

  // Get data from MySQL database
  $stmt = $conn->prepare('SELECT * FROM flights WHERE id = ?');
  $stmt->bind_param("i", $_POST['id']);
  $stmt->execute();

  if ($result = $stmt->get_result()) {
    while ($db = mysqli_fetch_object($result)) {
      // Create new row
      $newRow = [
        $db->battery_serial,
        $db->flight_id,
        $db->has_telemetry,
        $db->landing,
        $db->takeoff,
        $db->takeoff_latitude,
        $db->takeoff_longitude,
        $db->user_email,
        $db->vehicle_serial,
        $db->time,
        $db->flight_code,
        $db->case_number,
        $db->notes,
      ];
    }
  }

  $stmt->execute();
  $stmt->close();
  $conn->close();

  // Row exists, edit it
  if($foundRow > 0) {
    $rows = [$newRow];
    $valueRange = new \Google_Service_Sheets_ValueRange();
    $valueRange->setValues($rows);
    $range = 'Sheet1!A' . $foundRow;
    $options = ['valueInputOption' => 'USER_ENTERED'];
    $service->spreadsheets_values->update($spreadsheetId, $range, $valueRange, $options);
  } else {
    // Row does not exist, create a new row
    $rows = [$newRow];
    $valueRange = new \Google_Service_Sheets_ValueRange();
    $valueRange->setValues($rows);
    $range = 'Sheet1';
    $options = ['valueInputOption' => 'USER_ENTERED'];
    $service->spreadsheets_values->append($spreadsheetId, $range, $valueRange, $options);
  }

  // Stop script
  die('Data saved!');
}

// If an ID is set, allow user to check content
if(isset($_GET['id'])) {
  // Get data from MySQL database
  $stmt = $conn->prepare('SELECT * FROM flights WHERE id = ?');
  $stmt->bind_param("i", $_GET['id']);
  $stmt->execute();

  if ($result = $stmt->get_result()) {
    while ($db = mysqli_fetch_object($result)) {
      echo '
      <html>
        <head>
          <meta name="viewport" content="width=device-width, initial-scale=1.0">
          <title>Flight Record Edit</title>

          <style>
            body, td, input, textarea {
              font-size: 24px;
            }
          </style>
        </head>

        <body>
          <script type="text/javascript">
          window.addEventListener("DOMContentLoaded", (event) => {

            document.querySelector("form").addEventListener("click", function(event) {
              const radio = document.querySelector("input[name=\'training_flight\']:checked");

              // Show additional forms based on options
              if(radio) {
                if(radio.value == 1) {
                  document.querySelector("#mission_details").style.display = "none";
                } else {
                  document.querySelector("#mission_details").style.display = "inline";
                }

                // Show save button
                document.querySelector("#submit").style.display = "inline";
              }
            });
          });
          </script>

          <table border="1" style="margin-left: auto; margin-right: auto;">
            <tr>
              <td>Battery Serial</td>
              <td>'. $db->battery_serial .'</td>
            </tr>

            <tr>
              <td>Flight ID</td>
              <td>'. $db->flight_id .'</td>
            </tr>

            <tr>
              <td>Has Telemetry</td>
              <td>'. $db->has_telemetry .'</td>
            </tr>

            <tr>
              <td>Landing</td>
              <td>'. $db->landing .'</td>
            </tr>

            <tr>
              <td>Take Off</td>
              <td>'. $db->takeoff .'</td>
            </tr>

            <tr>
              <td>Take Off Latitude</td>
              <td>'. $db->takeoff_latitude .'</td>
            </tr>

            <tr>
              <td>Take Off Longitude</td>
              <td>'. $db->takeoff_longitude .'</td>
            </tr>

            <tr>
              <td>User E-mail</td>
              <td>'. $db->user_email .'</td>
            </tr>

            <tr>
              <td>Vehicle Serial</td>
              <td>'. $db->vehicle_serial .'</td>
            </tr>

            <tr>
              <td>Time</td>
              <td>'. $db->time .'</td>
            </tr>
          </table>

          <br />
          <hr />
          <br />

          <div style="text-align: center;">
            <form action="" method="POST">
              <input type="hidden" name="id" value="'. htmlspecialchars($_GET['id']) .'" />
              <input type="hidden" name="flight_id" value="'. htmlspecialchars($db->flight_id) .'" />

              <p>
                Is this a training flight?
              </p>

              <input type="radio" id="training_flight_yes" name="training_flight" value="1">
              <label for="training_flight_yes">Yes</label>

              <input type="radio" id="training_flight_no" name="training_flight" value="0">
              <label for="training_flight_no">No</label>

              <span id="mission_details" style="display: none;">
                <br /><br />
                <label for="flight_code">Flight Signal Code:</label>
                <br />
                <input type="text" id="flight_code" name="flight_code" value="">
                <br /><br />
                <label for="case_number">Case Number:</label>
                <br />
                <input type="text" id="case_number" name="case_number" value="">
                <br /><br />
                <label for="mission_details">Mission Notes:</label>
                <br />
                <textarea id="mission_details" name="mission_details" style="width: 50%; height: 200px;"></textarea>
              </span>
              <br /><br />
              <input type="submit" name="submit" id="submit" value="Save!" style="display: none;" />
            </form>
          </div>
        </body>
      </html>';
    }
  }

  // Stop script
  die('');
}

// Get JSON data from Skydio webhook
$json = file_get_contents("php://input");
$obj = json_decode($json);

// Check if data is valid
if(!isset($obj->id) || !isset($obj->data->resource->flight_id)) { http_response_code(400); die('Data not valid!'); }

// Send API request to Skydio API for Flight data
$client = new \GuzzleHttp\Client();

try {
  $response = $client->request('GET', 'https://api.skydio.com/api/v0/flight/' . $obj->data->resource->flight_id, [
    'headers' => [
      'accept' => 'application/json',
      'Authorization' => $_ENV['SKYDIO_API'],
    ],
  ]);
} catch(Exception $e) {
  http_response_code(400);
  die('Guzzle Error!');
}

// Get JSON flight data from Skydio API
$objFlight = json_decode($response->getBody());

// Must convert to int before inserting
$has_telemetry = intval($objFlight->data->flight->has_telemetry);

// Prepare and Bind
$stmt = $conn->prepare("INSERT INTO flights (battery_serial, flight_id, has_telemetry, landing, takeoff, takeoff_latitude, takeoff_longitude, user_email, vehicle_serial, time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssisssssss", $objFlight->data->flight->battery_serial, $objFlight->data->flight->flight_id, $has_telemetry, $objFlight->data->flight->landing, $objFlight->data->flight->takeoff, $objFlight->data->flight->takeoff_latitude, $objFlight->data->flight->takeoff_longitude, $objFlight->data->flight->user_email, $objFlight->data->flight->vehicle_serial, $objFlight->meta->time);
$stmt->execute();
$last_id = $conn->insert_id;
$stmt->close();
$conn->close();

// Send e-mail to user to notify them of action required
$email = new \SendGrid\Mail\Mail(); 
$email->setFrom("noreply@uasmanage.com", "No Reply");
$email->setSubject("Flight Recorded. Action required!");
$email->addTo($objFlight->data->flight->user_email, "Pilot");
$email->addContent("text/plain", "Please tag your flight here: https://uasmanage.com/service/lcso/app.php?id=" . $last_id);
$sendgrid = new \SendGrid($_ENV['SENDGRID_API_KEY']);
try {
  $response = $sendgrid->send($email);
  print $response->statusCode() . "\n";
  print_r($response->headers());
  print $response->body() . "\n";
  // Success
  http_response_code(200);
  die('');
} catch (Exception $e) {
  echo 'Caught exception: '. $e->getMessage() ."\n";
  http_response_code(400);
  die('');
}

?>
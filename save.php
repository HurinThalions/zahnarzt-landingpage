<?php

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS'){
	exit(0);
}

require_once __DIR__ . '/vendor/autoload.php';

if(!($env = json_decode(file_get_contents('env.json'),true))){
	http_response_code(500);
	echo json_encode(["error"=>"Couldn't open .env"]);
	exit;
}

try{
	$client = new \Google_Client();
	$client->setApplicationName('Zahnarzt Landingpage PHP');
	$client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);

	$client->setAuthConfig([
		'type' => 'service_account',
		'client_email' => $env['GOOGLE_SHEETS_CLIENT_EMAIL'],
		'private_key' => str_replace('\n', "\n", $env['GOOGLE_SHEETS_PRIVATE_KEY'])
	]);
	$service = new \Google_Service_Sheets($client);
} catch (Exception $e) {
	http_response_code(500);
	echo json_encode(["error"=> "Auth configuration failed: " . $e->getMessage()]);
	exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?? $_POST;

if (empty($input)){
	http_response_code(400);
	echo json_encode(["error" => "No data provided"]);
	exit;
}

if (empty( $input['consentGiven'])||empty($input['phone'])){
	http_response_code(422);
	echo json_encode(["error"=>"missing Email or consent"]);
	exit;
}

$timestampStr = gmdate('YmdHis');
$randomStr = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"),0,6);
$leadId = "lead_{$timestampStr}_{$randomStr}";

$createdAt = gmdate('c');
$source = $input['source'] ?? 'zahnarzt-landingpage';
$consentGiven = !empty($input['consentGiven']) ? 'yes' : 'no';

function escapeChar($in){
	if (empty($in) || !is_string($in)){
		return $in;
	}
	$dangerousPrefixes = ['=', '+', '-', '@', "\t", "\r"];
	$firstChar = substr($in,0,1);
	if(in_array($firstChar, $dangerousPrefixes, true)){
		return "'" . $in;
	}
	return $in;
}

$row = [
	$createdAt,
	$leadId,
	$source,
	escapeChar($input['insuranceType'] ?? ''),
	escapeChar($input['insurance'] ?? ''),
	escapeChar($input['residenceGermany'] ?? ''),
	escapeChar($input['reason'] ?? ''),
	escapeChar($input['family'] ?? ''),
	escapeChar($input['employmentStatus'] ?? ''),
	escapeChar($input['ageRange'] ?? ''),
	escapeChar($input['employmentStatus'] ?? ''),
	escapeChar($input['insuranceHolder'] ?? ''),
	escapeChar($input['hasDentalSupplement'] ?? ''),
	escapeChar($input['dentalSupplementCompany'] ?? ''),
	escapeChar($input['firstName'] ?? ''),
	escapeChar($input['lastName'] ?? ''),
	escapeChar($input['phone'] ?? ''),
	escapeChar($input['email'] ?? ''),
	$consentGiven,
	json_encode($input),
	'new',
	''
];

$spreadsheetId = $env['GOOGLE_SHEETS_SPREADSHEET_ID'] ?? '1SkkDREMkH31N-uMeS1132nem2osqeMJqSh6Sc1vxjp8';
$sheetName = $env['GOOGLE_SHEETS_SHEET_NAME'] ?? 'Leads';

$range = "{$sheetName}!A:R";
$body = new \Google_Service_Sheets_ValueRange([
	'values' => [$row]
]);
$params = [
	'valueInputOption' => 'USER_ENTERED',
	'insertDataOption' => 'INSERT_ROWS'
];

try {
	$result = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);
	error_log("SUCCESS: ".json_encode([
		"success" => true,
		"leadId" => $leadId,
		"updatedRows" => $result->getUpdates()->getUpdatedRows()
	]));
	header("Location: /danke.html");
	exit;
} catch (Exception $e){
	http_response_code(500);
	echo json_encode(["error" => "Failed to write to Google Sheets: " . $e->getMessage(),
			"account name" => $env['GOOGLE_SHEETS_CLIENT_EMAIL'
			]]);
}

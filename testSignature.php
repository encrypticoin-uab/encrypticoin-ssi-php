<?php
// error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();
$data = json_decode(file_get_contents('php://input'), true);
if ($data === null || !$data['message'] || !$data['signature'] || !isset($_SESSION['etalon-messageId']) || strlen($_SESSION['etalon-messageId']) !== 48) {
    http_response_code(400);
    exit();
}

$message = $data['message'];
$signature = $data['signature'];

include './Etalon.php';
include './_const.php';

$factory = new ProofMessageFactory($message_description);
$messageId = $factory->extractId($message);
if (!$messageId || !hash_equals($_SESSION['etalon-messageId'], $messageId)) {
    
    http_response_code(400);
    exit();
}

// At this point the messageId **MUST** be removed from the session to ensure it is only accepted a single time.
unset($_SESSION['etalon-messageId']);

$tia = new ServerIntegrationClient();
// TODO: handle exceptions
$address = $tia->walletBySigned($message, $signature);
$balance = $tia->tokenBalance($address);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(array(
    'address' => $address,  // only returning this for debugging, production shall not need it
    'attribution' => $balance->hasAttribution()
        ));

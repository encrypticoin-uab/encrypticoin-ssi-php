<?php
// error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();

include './Etalon.php';
include './_const.php';

$factory = new ProofMessageFactory($message_description);

$messageId = bin2hex(random_bytes(24));
$_SESSION['etalon-messageId'] = $messageId;

header('Content-Type: application/json; charset=utf-8');
echo json_encode(array('message' => $factory->create($messageId)));

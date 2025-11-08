<?php

require '../vendor/autoload.php';

use Neili\Client;
use Neili\Settings;

$token = 'TOKEN'; // telegram api bot access token
$chatId = 123456789; // example chat id
$message = 'Hello from Neili async!';



$settings = (new Settings)
    ->setAccessToken($token);

$client = new Client($settings);

$future = $client->sendMessage($chatId, $message);


echo "Message sent, waiting for response..." . PHP_EOL;

$result = $future->await();

print_r($result);

<?php
require '../vendor/autoload.php';

use Neili\Client;
use Neili\Settings;
use Neili\Media;

$token = 'TOKEN'; // telegram api bot access token
$chatId = 123456789; // example chat id
$message = 'Hello from Neili async!';



$settings = (new Settings)
    ->setAccessToken($token);

$client = new Client($settings);


$file = new Media('afaz.jpg');

$future = $client->sendPhoto($chatId, $file);

echo "Media sent, waiting for response..." . PHP_EOL;

$result = $future->await();
print_r($result);

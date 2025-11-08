<?php

require '../vendor/autoload.php';

use Neili\Client;
use Neili\Settings;

$token = 'TOKEN'; // telegram api bot access token

$settings = (new Settings)
    ->setAccessToken($token)
    ->setMultiProcess(true); // enabled multi process

$client = new Client($settings);



$update = $client->handleUpdate();

if (isset($update['message']['chat']['id']) && isset($update['message']['text'])) {
    $chatId = $update['message']['chat']['id'];
    $text = $update['message']['text'];

    $reply = 'Echo: ' . $text;

    $future = $client->sendMessage($chatId, $reply);
    $future->await();
}
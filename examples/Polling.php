<?php

require '../vendor/autoload.php';

use Neili\Client;
use Neili\Settings;
use Neili\Poller;


$token = 'TOKEN'; // telegram api bot access token

$settings = (new Settings)
    ->setAccessToken($token)
    ->setPollerTimeout(1)
    ->setPollerBackoffBase(1)
    ->setPollerMaxBackoff(16)
    ->setPollerMaxConcurrency(100);

$client = new Client($settings);

$poller = new Poller($client);

$poller->onUpdate(function ($update) use ($client) {


    if (isset($update['message']['chat']['id']) && isset($update['message']['text'])) {
        $chatId = $update['message']['chat']['id'];
        $text = "Echo: " . $update['message']['text'];
        $client->sendMessage($chatId, $text);
    }

});

$poller->start();

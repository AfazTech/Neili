<?php

require '../vendor/autoload.php';

use Neili\Client;
use Neili\Settings;
use Neili\Poller;

$token = 'TOKEN';

$settings = (new Settings)
    ->setAccessToken($token)
    ->setPollerTimeout(1)
    ->setPollerBackoffBase(1)
    ->setPollerMaxBackoff(16)
    ->setPollerMaxConcurrency(100);

$client = new Client($settings);
$poller = new Poller($client);

// Global update handler (optional)
$poller->onUpdate(function ($update) {
    // Can be used for logging or handling all updates globally
});

// Separate specific handlers
$poller->onMessage(function ($update) use ($client) {
    $chatId = $update['message']['chat']['id'] ?? null;
    $text = $update['message']['text'] ?? null;
    if ($chatId && $text) {
        $client->sendMessage($chatId, "Echo: " . $text);
    }
});

$poller->onEditedMessage(function ($update) use ($client) {
    $chatId = $update['edited_message']['chat']['id'] ?? null;
    $text = $update['edited_message']['text'] ?? null;
    if ($chatId && $text) {
        $client->sendMessage($chatId, "Edited: " . $text);
    }
});

// Add more onX handlers as needed
//$poller->onCallbackQuery(...);
//$poller->onInlineQuery(...);

$poller->start();

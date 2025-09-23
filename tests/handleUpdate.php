<?php

// include autoload or Neili.php
include('../src/Neili.php');

// use Neili class
use TelegramBot\Neili;

// create Neili object
$token = 'TOKEN_BOT'; // setup access token
$bot = new Neili($token);


// handle received update
$update = $bot->handleUpdate('my-custom-secret'); // or $update = $bot->handleUpdate();


// check valid update
if ($update) {

    // check chat and send message
    $message = $update['message'];
    $chatType = $message['chat']['type'];
    $chatId = $message['chat']['id'];
    if ($chatType == 'private') {
        $message = 'This message was sent by Neili Library!';
        $bot->sendMessage($chatId, $message);
    }
}

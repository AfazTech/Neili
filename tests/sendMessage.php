<?php

include('../src/Neili.php');

// use Neili class
use TelegramBot\Neili;

// create Neili object
$token = 'TOKEN_BOT'; // setup access token
$bot = new Neili($token);

// send message
$chatId = '1826312667';
$message = 'This message was sent by Neili Library!';
$content = $bot->sendMessage($chatId, $message);


/// print response
var_dump($content);

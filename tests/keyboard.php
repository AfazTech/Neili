<?php

include('../src/Neili.php');

// use Neili class
use TelegramBot\Neili;

// create Neili objects
$token = 'TOKEN_BOT'; // setup access token
$bot = new Neili($token);

// create keybooard
$keyboard = $bot->keyboard(['button1', 'button2', 'button3']);
$chatId = '1826312667';
$message = 'This message was sent by Neili Library!';
$content = $bot->sendMessage($chatId, $message, $keyboard);


/// print response
var_dump($content);

<?php

include('../src/Neili.php');

// use Neili class
use TelegramBot\Neili;

// create Neili objects
$token = 'TOKEN_BOT'; // setup access token
$bot = new Neili($token);

// create keybooard
$keyboard = Neili::inlineKeyboard(['button1' => 'customdata', 'button2' => 'customdata', 'button3' => 'customdata']);
$chatId = '1826312667';
$message = 'This message was sent by Neili Library!';
$content = $bot->sendMessage($chatId, $message, $keyboard);


/// print response
var_dump($content);

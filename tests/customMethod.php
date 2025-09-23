<?php

// include autoload or Neili.php
include('../src/Neili.php');

// use Neili class
use TelegramBot\Neili;

// create Neili object
$token = 'TOKEN_BOT'; // setup access token
$bot = new Neili($token);

// send photo (cuustom method)
$chatId = '18263126678';
$content = $bot->sendPhoto([
    'chat_id' => $chatId,
    'photo' => new CURLFile('test.jpg')
]);

/// print response
var_dump($content);

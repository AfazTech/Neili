<?php

// include autoload or Neili.php
include('../src/Neili.php');

// use Neili class
use TelegramBot\Neili;

// create Neili object
$token = '6442038046:AAGfKm3q-YaNF38ps1EwMUN0QOeKbg7DX4M'; // setup access token
$bot = new Neili($token);

// send photo (cuustom method)
$chatId = '18263126678';
$content = $bot->sendPhoto([
    'chat_id' => $chatId,
    'photo' => new CURLFile('test.jpg')
]);

/// print response
var_dump($content);

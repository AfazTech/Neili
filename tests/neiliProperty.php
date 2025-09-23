<?php

// include autoload or Neili.php
include('../src/Neili.php');


// use Neili class
use TelegramBot\Neili;


// create Neili object
$bot = new Neili('TOKEN_BOT');


// set custom proxy server or load balancer
// default: https://api.telegram.org/bot
$bot->apiUrl = 'https://api.telegram.org/bot';


// set custom log file name
// default: neili.log
$bot->logFile = 'neili.log';



// enable print log
$bot->printLog = true;


// send message
$chatId = '1826312667';
$message = 'This message was sent by Neili Library!';
$content = $bot->sendMessage($message, $message);

/// print response
var_dump($content);

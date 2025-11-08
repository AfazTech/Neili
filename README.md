# Neili — Asynchronous Telegram Bot Library for PHP

Neili is an async-first PHP library built on Amp that streamlines creating robust Telegram bots.
It provides a non-blocking HTTP client, wrappers for all Telegram Bot API methods, a long-polling `Poller` with concurrency control, and a flexible webhook handler.
Neili is optimized for both constrained hosting environments and long-running worker processes.

**If this project is helpful to you, you may wish to give it a**:star2: **to support future updates and feature additions!**

---

## Donate

<a href="http://www.coffeete.ir/afaz">
  <img src="http://www.coffeete.ir/images/buttons/lemonchiffon.png" width="260" />
</a>

---

## Table of contents

* [Introduction](#introduction)
* [Features](#features)
* [Requirements](#requirements)
* [Installation](#installation)
* [Configuration](#configuration)
* [Usage](#usage)
* [Multi-process](#multi-process)
* [Polling vs Webhook](#polling-vs-webhook)
* [Settings](#settings)
* [Keyboard Builder](#keyboard-builder)
* [Client Methods Reference](#client-methods-reference)
* [License](#license)

---

## Introduction

Neili wraps the Telegram Bot API with Amp-based asynchronous primitives.
It offers:

* Safe, non-blocking HTTP requests
* Async message sending, media uploads, and chat management
* Receiving updates via long polling or webhooks
* Lightweight, framework-agnostic architecture suitable for both short-lived webhook endpoints and long-running pollers

---

## Features

* Async-first Telegram Bot API wrapper built on Amp HTTP client
* `Poller` implementation with configurable timeout, exponential backoff, and concurrency
* Webhook helper compatible with standard PHP and multi-process setups
* Minimal external dependencies for easy integration
* Multiprocess support for concurrent update handling
* Examples included for polling, webhook, sending messages/media, and keyboards

---

## Requirements

Neili requires the following environment and extensions:

* **PHP >= 8.1**
* **amphp/file ^3.2** — Async file handling
* **amphp/http-client ^5.3** — Non-blocking HTTP requests
* **PHP extensions:** `fileinfo`, `posix`
* **PHP function `exec()`** — Required only if using multi-process mode in webhook

---
## Installation

Install via Composer:

```bash
composer require afaztech/neili
```

Or clone repository:

```bash
git clone https://github.com/afaztech/neili.git
cd neili
composer install
```

Autoloading is PSR-4 (`Neili\` → `src/`).

---

## Configuration

Neili uses `Neili\Settings` for configuration.

Example:

```php
use Neili\Settings;

$settings = (new Settings())
    ->setAccessToken('TELEGRAM_BOT_TOKEN')
    ->setApiUrl('https://api.telegram.org/bot')
    ->setPollerTimeout(5)
    ->setPollerMaxConcurrency(null);
```

---

## Usage

### Long Polling Example

```php
use Neili\Client;
use Neili\Poller;
use Neili\Settings;

$settings = (new Settings())->setAccessToken('TELEGRAM_BOT_TOKEN');
$client = new Client($settings);

$poller = new Poller($client);

$poller->onUpdate(function(array $update) use ($client) {
    $chatId = $update['message']['chat']['id'] ?? null;
    $text = $update['message']['text'] ?? null;
    if ($chatId && $text) {
        $client->sendMessage((int)$chatId, 'Echo: '.$text);
    }
});

$poller->start();
```

### Webhook Example

```php
use Neili\Client;
use Neili\Settings;

$settings = (new Settings())->setAccessToken('TELEGRAM_BOT_TOKEN');
$client = new Client($settings);

$update = $client->handleUpdate('WEBHOOK_SECRET_TOKEN');
if ($update) {
    // Async dispatch example
}
```

---

## Multi-process

Neili supports multi-process update handling in webhook mode using PHP `exec()`.

```php
$settings->setUseMultiProcess(true)
         ->setPhpBinary('/usr/bin/php');
```

Incoming webhook updates are automatically forked into separate PHP processes for non-blocking execution.

---

## Polling vs Webhook

| Method                     | Pros                                               | Cons                          |
| -------------------------- | -------------------------------------------------- | ----------------------------- |
| Standard Webhook           | Easy to integrate with HTTP servers                | Single-threaded by default    |
| Webhook with Multi-process | Non-blocking, concurrent handling of updates       | Requires PHP CLI and `exec()` |
| Long Polling               | Simple, reliable, no external server config needed | Continuous running process    |

---

## Settings

| Attribute            | Description                                 | Type   | Required | Default                        |
| -------------------- | ------------------------------------------- | ------ | -------- | ------------------------------ |
| apiUrl               | Base Telegram API URL                       | string | no       | `https://api.telegram.org/bot` |
| apiVerifySSL         | Enable TLS verification                     | bool   | no       | `true`                         |
| timeout              | HTTP request timeout (seconds)              | int    | no       | `30`                           |
| connectionTimeout    | HTTP connection timeout (seconds)           | int    | no       | `5`                            |
| useMultiProcess      | Enable multi-process mode                   | bool   | no       | `false`                        |
| phpBinary            | Path to PHP CLI binary for worker processes | string | no       | `/usr/bin/php`                 |
| pollerTimeout        | Long polling request timeout (seconds)      | int    | no       | `5`                            |
| pollerBackoffBase    | Base seconds for exponential backoff        | int    | no       | `1`                            |
| pollerMaxBackoff     | Maximum backoff seconds                     | int    | no       | `32`                           |
| pollerMaxConcurrency | Maximum concurrent async handlers           | int    | no       | `null`                         |
| accessToken          | Telegram bot token                          | string | yes      | `null`                         |
| logger               | PSR-3 compatible logger instance            | object | no       | `null`                         |

---

### Logger

Neili’s `Settings` constructor **directly accepts a PSR-3 logger**:

* You can pass any PSR-3 compatible logger (e.g., Monolog).
* If you do **not** provide a logger, Neili will use its **default lightweight async logger**.

Example:

```php
use Neili\Settings;
use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;

$logger = new MonologLogger('bot', [new StreamHandler('/path/to/logfile.log')]);

$settings = new Settings($logger)
    ->setAccessToken('TELEGRAM_BOT_TOKEN');
```

This ensures full async logging support in both long-polling and multi-process webhook modes.


---
## Keyboard Builder

`Neili\KeyboardBuilder` provides a fluent interface for building **either** a reply keyboard **or** an inline keyboard.
**Important:** You cannot mix inline and regular rows in the same keyboard.

### Methods Table

| Method    | Description                                      | Parameters                                | Returns |
| --------- | ------------------------------------------------ | ----------------------------------------- | ------- |
| row       | Add a row of buttons for a reply keyboard        | `string ...$buttons`                      | `self`  |
| inlineRow | Add a row of inline buttons with callback data   | `array $buttons` (`text => callbackData`) | `self`  |
| inline    | Convert the keyboard to inline mode              | none                                      | `self`  |
| resize    | Set `resize_keyboard` option for reply keyboards | `bool $resize = true`                     | `self`  |
| oneTime   | Set `one_time_keyboard` option                   | `bool $oneTime = true`                    | `self`  |
| clear     | Clear all rows and reset options                 | none                                      | `self`  |
| build     | Compile final array for Telegram API             | none                                      | `array` |

### Example: Reply Keyboard

```php
use Neili\KeyboardBuilder;

$keyboard = (new KeyboardBuilder())
    ->row('Yes', 'No')
    ->row('Maybe')
    ->resize(true)
    ->oneTime(true)
    ->build();
```

### Example: Inline Keyboard

```php
use Neili\KeyboardBuilder;

$keyboard = (new KeyboardBuilder())
    ->inlineRow(['Button1' => 'callback_1', 'Button2' => 'callback_2'])
    ->inline()
    ->build();
```

---

## Client Methods Reference

All `Client` methods in Neili are **asynchronous** and return `Amp\Future` objects.
A `Future` represents a pending result; you can use `onResolve()` to handle the result or error when it completes.

| Method              | Description                                | Parameters                                                               | Returns  | Usage / Notes                                                                       |
| ------------------- | ------------------------------------------ | ------------------------------------------------------------------------ | -------- | ----------------------------------------------------------------------------------- |
| sendMessage         | Send a text message                        | `$chatId: int, $text: string, $options: array = []`                      | `Future` | `.onResolve(fn($err, $res) => ...)` gets the result asynchronously                  |
| sendPhoto           | Send a photo to chat                       | `$chatId: int, $media: Media, $options: array = []`                      | `Future` | `Media` object wraps local file path; resolves to API response                      |
| sendDocument        | Send a document/file                       | `$chatId: int, $media: Media, $options: array = []`                      | `Future` | Supports local file upload asynchronously                                           |
| editMessageText     | Edit the text of a previously sent message | `$chatId: int, $messageId: int, $text: string, $options: array = []`     | `Future` | Can edit inline or regular messages; async response from Telegram API               |
| deleteMessage       | Delete a message                           | `$chatId: int, $messageId: int`                                          | `Future` | Resolves to boolean success/failure                                                 |
| forwardMessage      | Forward a message from one chat to another | `$fromChatId: int, $toChatId: int, $messageId: int`                      | `Future` | Returns message object of forwarded message                                         |
| getUpdates          | Fetch updates (long polling)               | `$params: array = []`                                                    | `Future` | Returns array of updates; use in Poller or manual async handling                    |
| answerCallbackQuery | Respond to inline button callback          | `$callbackQueryId: string, $text: string = '', $showAlert: bool = false` | `Future` | Needed to acknowledge inline button presses                                         |
| sendChatAction      | Send typing / upload action to chat        | `$chatId: int, $action: string`                                          | `Future` | e.g., `'typing'`, `'upload_photo'`; resolves when action is sent                    |
| handleUpdate        | Process incoming webhook update            | `$secretToken: string`                                                   | `Future` | Resolves to update array if a valid request; integrates with multi-process workflow |


### How `Future` Works

`Amp\Future` lets you work asynchronously:

```php
$future = $client->sendMessage($chatId, 'Hello async');

$future->onResolve(function($error, $result) {
    if ($error) {
        echo "Error: ".$error->getMessage();
    } else {
        print_r($result); // Telegram API response
    }
});
```

Or `await()` to block until the result is ready (inside an async context):

```php
$result = $client->sendMessage($chatId, 'Hello')->await();
print_r($result);
```

**Note:** Every `Client` method returns a `Future`, which means all network requests are **non-blocking** by default, letting you run multiple requests concurrently without waiting.

---

## License

MIT License — See [LICENSE](LICENSE) file.
<?php

declare(strict_types=1);


/**
 * lightweight library for managing and creating automation for telegram api bots.
 *
 * @version 1.0.0
 * @author Mr Afaz
 * @package neili
 * @copyright Copyright 2023 Neili library
 * @license https://opensource.org/licenses/MIT
 * @link https://github.com/imafaz/neili
 */

namespace TelegramBot;

use EasyLog\Logger;


/**
 * @method void __construct(string $accessToken)
 * @method void __set($property, $value)
 * @method void __call($method, $arguments)
 * @method array|false handleUpdate(string $secretToken = null)
 * @method string keyboard(string $secretToken = null)
 * @method array|false handleUpdate(string $secretToken = null)
 * @method array sendMessage(int $chatId, string $text, string $keyboard = null, array $params = null)
 * @method array forwardMessage(int $chatId, int $fromChatId, int $messageId, array $params = null)
 * @method array copyMessage(int $chatId, int $fromChatId, int $messageId, array $params = null)
 * @method array sendPhoto(int $chatId, string $photo, string $caption = null, array $params = null)
 * @method array sendAudio(int $chatId, string $audio, string $caption = null, array $params = null)
 * @method array sendDocument(int $chatId, string $document, string $caption = null, array $params = null)
 * @method array sendVideo(int $chatId, string $video, string $caption = null, array $params = null)
 * @method array sendAnimation(int $chatId, string $animation, string $caption = null, array $params = null)
 * @method array sendVoice(int $chatId, string $voice, string $caption = null, array $params = null)
 * @method array sendVideoNote(int $chatId, string $videoNote, array $params = null)
 * @method array sendMediaGroup(int $chatId, array $media, array $params = null)
 * @method array sendLocation(int $chatId, float $latitude, float $longitude, array $params = null)
 * @method array editMessageText(int $chatId, int $messageId, string $text, array $params = null)
 * @method array editMessageCaption(int $chatId, int $messageId, string $caption, array $params = null)
 * @method array editMessageMedia(int $chatId, int $messageId, array $media, array $params = null)
 * @method array editMessageReplyMarkup(int $chatId, int $messageId, array $replyMarkup, array $params = null)
 * @method array deleteMessage(int $chatId, int $messageId)
 * @method array answerCallbackQuery(string $callbackQueryId, string $text = null, array $params = null)
 * @method array setWebhook(string $url, array $params = null)
 * @method array deleteWebhook()
 * @method array getWebhookInfo()
 * @method array getUpdates(array $params = null)
 * @method array getMe()
 * @method static string keyboard(array $buttons, int $row = 2, bool $resize = true, bool $oneTime = false) Create a reply keyboard (normal keyboard) JSON
 * @method static string inlineKeyboard(array $keyboard, int $row = 2) Create an inline keyboard JSON
 * coming soon 
 */

class Neili
{

    /**
     * bot api access token
     *
     * @var string
     */
    private $accessToken;


    /**
     * loggger object
     *
     * @var object
     */
    private $logger;


    /**
     * telegram api url
     *
     * @var string
     */
    public $apiUrl = 'https://api.telegram.org/bot';



    /**
     * log file name
     *
     * @var string
     */
    private $logFile = 'neili.log';


    /**
     * print logs
     *
     * @var string
     */
    private $printlog = false;

    /**
     * setup neili object
     *
     * @param string $accessToken
     * @return Neili
     */

    public function __construct(string $accessToken)
    {
        $this->logger = new Logger($this->logFile, $this->printlog);
        $this->accessToken = $accessToken;
    }



    /**
     * setup Neili property
     *
     * @param string $property
     * @param string $value
     * @return void
     */
    public function __set($property, $value)
    {
        if ($property == 'printLog') {
            $this->logger->printLog = $value;
        } elseif ($property == 'logFile') {
            $this->logger->logFile = $value;
            if (function_exists('ini_set')) {
                ini_set('log_errors', '1');
                ini_set('error_log', $this->logFile);
            }
        }
    }


    /**
     * using custom method
     *
     * @param method $property
     * @param string $value
     * @return void
     */
    public function __call($method, $arguments)
    {
        return $this->request($method, $arguments[0]);
    }





    /**
     * receive telegram hook updates
     *
     * @param string $hash
     * @return array|bool
     */
    public function handleUpdate(string $secretToken = null)
    {
        $headers = getallheaders();
        if (!is_null($secretToken)) {
            if (!isset($headers['X-Telegram-Bot-Api-Secret-Token'])) {
                $this->logger->error('The secret token was not found in the header');
                return false;
            }
            if ($secretToken != $headers['X-Telegram-Bot-Api-Secret-Token']) {
                $this->logger->error('secret token invalid');
                return false;
            }
        }
        $hook = json_decode(file_get_contents('php://input'), true);
        return $hook;
    }



    /**
     * send http request to telegram api
     *
     * @param string $method
     * @param array $params
     * @return array
     */
    private function request(string $method, array $params): array
    {
        $handler = curl_init();

        curl_setopt_array($handler, [
            CURLOPT_URL => $this->apiUrl . $this->accessToken . '/' . $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $result =  curl_exec($handler);

        if ($result === false) {
            $log = 'curl telegram api failed, error no: ' . curl_errno($handler);
            $this->logger->error($log);
            return ['ok' => false, 'description' => $log];
        } else {
            $result = json_decode($result, true);
            if (!$result['ok']) {
                $this->logger->debug('telegram method error ' . $result['description']);
            }
            return $result;
        }
    }



    /**
     * Send sticker
     *
     * @param int $chatId
     * @param string $sticker
     * @param array|null $params
     * @return array
     */
    public function sendSticker(int $chatId, string $sticker, array $params = null): array
    {
        $data = [
            'chat_id' => $chatId,
            'sticker' => $sticker,
        ];
        return $this->request('sendSticker', is_null($params) ? $data : array_merge($data, $params));
    }


    /**
     * create keyboard
     *
     * @param array $buttons
     * @param int $raw
     * @param bool $resize
     * @return json
     */
    public static function keyboard(array $buttons, int $raw = 2, bool $resize = true)
    {
        $buttonChunks = array_chunk($buttons, $raw);
        $keyboard = array_map(fn($buttonRow) => array_map(fn($button) => ['text' => $button], $buttonRow), $buttonChunks);
        return json_encode(['resize_keyboard' => $resize, 'keyboard' => $keyboard]);
    }

    /**
     * create inlineKeyboard
     *
     * @param array $buttons
     * @param int $raw
     * @return json
     */
    public static function inlineKeyboard(array $keyboard, int $row = 2)
    {
        $buttonChunks = array_chunk($keyboard, $row, true);
        $inlineKeyboard = [];
        foreach ($buttonChunks as $buttonRow) {
            $buttons = [];
            foreach ($buttonRow as $text => $callbackData) {
                $buttons[] = ['text' => $text, 'callback_data' => $callbackData];
            }
            $inlineKeyboard[] = $buttons;
        }
        return json_encode(['inline_keyboard' => $inlineKeyboard]);
    }
    //Dobs
    /**
     * Create reply keyboard for Telegram
     *
     * @param array $buttons   ['Button1', 'Button2', 'Button3', ...]
     * @param int $row         Number of buttons per row
     * @param bool $resize     Auto resize keyboard
     * @param bool $oneTime    Hide keyboard after one use
     * @return string          JSON encoded reply keyboard
     */
    public static function buttonkeyboard(array $buttons, int $row = 2, bool $resize = true, bool $oneTime = false): string
    {
        $buttonChunks = array_chunk($buttons, $row);
        $keyboard = [];

        foreach ($buttonChunks as $buttonRow) {
            $rowButtons = [];
            foreach ($buttonRow as $button) {
                $rowButtons[] = ['text' => $button];
            }
            $keyboard[] = $rowButtons;
        }

        return json_encode([
            'resize_keyboard' => $resize,
            'one_time_keyboard' => $oneTime,
            'keyboard' => $keyboard
        ]);
    }

    /**
     * Get basic info about the bot
     *
     * @return array
     */
    public function getMe(): array
    {
        return $this->request('getMe', []);
    }

    /**
     * Get updates via long polling
     *
     * @param array|null $params offset, limit, timeout, etc.
     * @return array
     */
    public function getUpdates(array $params = null): array
    {
        return $this->request('getUpdates', $params ?? []);
    }

    /**
     * Set a webhook for receiving updates
     *
     * @param string $url Webhook URL
     * @param array|null $params Optional parameters
     * @return array
     */
    public function setWebhook(string $url, array $params = null): array
    {
        $data = ['url' => $url];
        return $this->request('setWebhook', $params ? array_merge($data, $params) : $data);
    }

    /**
     * Send a text message
     *
     * @param int $chatId
     * @param string $text
     * @param string|null $keyboard Inline or reply keyboard JSON
     * @param array|null $params Optional params (parse_mode, disable_web_page_preview, etc.)
     * @return array
     */
    public function sendMessage(int $chatId, string $text, string $keyboard = null, array $params = null): array
    {
        $data = ['chat_id' => $chatId, 'text' => $text];
        if ($keyboard) $data['reply_markup'] = $keyboard;
        return $this->request('sendMessage', $params ? array_merge($data, $params) : $data);
    }


    /**
     * Forward an existing message
     *
     * @param int $chatId
     * @param int $fromChatId
     * @param int $messageId
     * @param array|null $params
     * @return array
     */
    public function forwardMessage(int $chatId, int $fromChatId, int $messageId, array $params = null): array
    {
        $data = ['chat_id' => $chatId, 'from_chat_id' => $fromChatId, 'message_id' => $messageId];
        return $this->request('forwardMessage', $params ? array_merge($data, $params) : $data);
    }

    /**
     * Copy a message (without forwarding)
     *
     * @param int $chatId
     * @param int $fromChatId
     * @param int $messageId
     * @param array|null $params
     * @return array
     */
    public function copyMessage(int $chatId, int $fromChatId, int $messageId, array $params = null): array
    {
        $data = ['chat_id' => $chatId, 'from_chat_id' => $fromChatId, 'message_id' => $messageId];
        return $this->request('copyMessage', $params ? array_merge($data, $params) : $data);
    }

    /**
     * Edit text of a sent message
     *
     * @param int $chatId
     * @param int $messageId
     * @param string $text
     * @param array|null $params
     * @return array
     */
    public function editMessageText(int $chatId, int $messageId, string $text, array $params = null): array
    {
        $data = ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text];
        return $this->request('editMessageText', $params ? array_merge($data, $params) : $data);
    }

    /**
     * Delete a message
     *
     * @param int $chatId
     * @param int $messageId
     * @return array
     */
    public function deleteMessage(int $chatId, int $messageId): array
    {
        return $this->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
    }

    /**
     * Send a photo
     *
     * @param int $chatId
     * @param string $photo File_id, URL or attach://file
     * @param string|null $caption
     * @param array|null $params
     * @return array
     */
    public function sendPhoto(int $chatId, string $photo, string $caption = null, array $params = null): array
    {
        $data = ['chat_id' => $chatId, 'photo' => $photo];
        if ($caption) $data['caption'] = $caption;
        return $this->request('sendPhoto', $params ? array_merge($data, $params) : $data);
    }

    /**
     * Send an audio file
     *
     * @param int $chatId
     * @param string $audio
     * @param string|null $caption
     * @param array|null $params
     * @return array
     */
    public function sendAudio(int $chatId, string $audio, string $caption = null, array $params = null): array
    {
        $data = ['chat_id' => $chatId, 'audio' => $audio];
        if ($caption) $data['caption'] = $caption;
        return $this->request('sendAudio', $params ? array_merge($data, $params) : $data);
    }

    /**
     * Send a document
     *
     * @param int $chatId
     * @param string $document
     * @param string|null $caption
     * @param array|null $params
     * @return array
     */
    public function sendDocument(int $chatId, string $document, string $caption = null, array $params = null): array
    {
        $data = ['chat_id' => $chatId, 'document' => $document];
        if ($caption) $data['caption'] = $caption;
        return $this->request('sendDocument', $params ? array_merge($data, $params) : $data);
    }

    /**
     * Send a video
     *
     * @param int $chatId
     * @param string $video
     * @param string|null $caption
     * @param array|null $params
     * @return array
     */
    public function sendVideo(int $chatId, string $video, string $caption = null, array $params = null): array
    {
        $data = ['chat_id' => $chatId, 'video' => $video];
        if ($caption) $data['caption'] = $caption;
        return $this->request('sendVideo', $params ? array_merge($data, $params) : $data);
    }

    /**
     * Send a voice message
     *
     * @param int $chatId
     * @param string $voice
     * @param string|null $caption
     * @param array|null $params
     * @return array
     */
    public function sendVoice(int $chatId, string $voice, string $caption = null, array $params = null): array
    {
        $data = ['chat_id' => $chatId, 'voice' => $voice];
        if ($caption) $data['caption'] = $caption;
        return $this->request('sendVoice', $params ? array_merge($data, $params) : $data);
    }

    /**
     * Get chat info
     *
     * @param int $chatId
     * @return array
     */
    public function getChat(int $chatId): array
    {
        return $this->request('getChat', ['chat_id' => $chatId]);
    }

    /**
     * Get list of chat admins
     *
     * @param int $chatId
     * @return array
     */
    public function getChatAdministrators(int $chatId): array
    {
        return $this->request('getChatAdministrators', ['chat_id' => $chatId]);
    }

    /**
     * Get info about a chat member
     *
     * @param int $chatId
     * @param int $userId
     * @return array
     */
    public function getChatMember(int $chatId, int $userId): array
    {
        return $this->request('getChatMember', ['chat_id' => $chatId, 'user_id' => $userId]);
    }

    /**
     * Ban a user from chat
     *
     * @param int $chatId
     * @param int $userId
     * @param array|null $params until_date, revoke_messages
     * @return array
     */
    public function banChatMember(int $chatId, int $userId, array $params = null): array
    {
        $data = ['chat_id' => $chatId, 'user_id' => $userId];
        return $this->request('banChatMember', $params ? array_merge($data, $params) : $data);
    }

    /**
     * Unban a previously banned user
     *
     * @param int $chatId
     * @param int $userId
     * @return array
     */
    public function unbanChatMember(int $chatId, int $userId): array
    {
        return $this->request('unbanChatMember', ['chat_id' => $chatId, 'user_id' => $userId]);
    }

    /**
     * Leave a chat
     *
     * @param int $chatId
     * @return array
     */
    public function leaveChat(int $chatId): array
    {
        return $this->request('leaveChat', ['chat_id' => $chatId]);
    }

    /**
     * Answer a callback query (button pressed)
     *
     * @param string $callbackId
     * @param string|null $text
     * @param array|null $params show_alert, url, cache_time
     * @return array
     */
    public function answerCallbackQuery(string $callbackId, string $text = null, array $params = null): array
    {
        $data = ['callback_query_id' => $callbackId];
        if ($text) $data['text'] = $text;
        return $this->request('answerCallbackQuery', $params ? array_merge($data, $params) : $data);
    }

    /**
     * Answer an inline query
     *
     * @param string $inlineId
     * @param array $results Inline query results
     * @param array|null $params cache_time, is_personal, next_offset
     * @return array
     */
    public function answerInlineQuery(string $inlineId, array $results, array $params = null): array
    {
        $data = ['inline_query_id' => $inlineId, 'results' => json_encode($results)];
        return $this->request('answerInlineQuery', $params ? array_merge($data, $params) : $data);
    }
}

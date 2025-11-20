<?php

/**
 * @version 2.0.0
 * @author Abolfazl Majidi (Afaz)
 * @package neili
 * @license https://opensource.org/licenses/MIT
 * @link https://github.com/AfazTech/neili
 */

declare(strict_types=1);

namespace Neili;

use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Request;
use Amp\Future;
use Amp\Http\Client\Form;
use function Amp\async;

class Client
{
    /**
     * Telegram bot settings (token, API URL, etc)
     */
    private Settings $settings;

    /**
     * HTTP client for async requests
     */
    private HttpClient $httpClient;

    /**
     * Constructor
     * Initializes the HTTP client and stores settings
     */
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $this->httpClient = HttpClientBuilder::buildDefault();
    }

    /**
     * Magic method for dynamically calling Telegram API methods.
     * Converts calls like $client->sendMessage(...) to request('sendMessage', [...])
     */
    public function __call($method, $arguments): Future
    {
        return $this->request($method, $arguments[0] ?? []);
    }

    /**
     * Get current settings
     */
    public function getSettings(): Settings
    {
        return $this->settings;
    }

    /**
     * Checks if a string is a valid URL
     */
    private static function isUrl(string $string): bool
    {
        return filter_var($string, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Handle incoming update
     * Supports both CLI (for multi-process) and webhook mode
     */
    public function handleUpdate(?string $secretToken = null): array
    {
        $isCli = (php_sapi_name() === 'cli');
        global $argv;

        if (!$isCli) {
            // Webhook mode
            $headers = getallheaders();
            if ($secretToken !== null) {
                $headerToken = $headers['X-Telegram-Bot-Api-Secret-Token'] ?? null;
                if ($headerToken !== $secretToken) {
                    throw new \RuntimeException('Invalid secret token');
                }
            }

            $rawInput = file_get_contents('php://input');
            $update = json_decode($rawInput, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON: ' . json_last_error_msg());
            }

            // Multi-process support: fork a new PHP process for the update
            if ($this->settings->isMultiProcess()) {
                $payload = base64_encode(json_encode($update));
                $executedFile = $_SERVER['SCRIPT_FILENAME'];
                $phpBinary = $this->settings->getPhpBinary();
                exec("{$phpBinary} {$executedFile} '$payload' > /dev/null 2>&1 &");
                http_response_code(200);
                exit;
            }

            return $update;

        } else {
            // CLI mode
            if (!isset($argv[1])) {
                throw new \RuntimeException('No payload provided in CLI');
            }
            $update = json_decode(base64_decode($argv[1]), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON in CLI payload: ' . json_last_error_msg());
            }
            return $update;
        }
    }

    /**
     * Perform async HTTP request to Telegram API
     */
    private function request(string $method, array $params = []): Future
    {
        $url = $this->settings->getApiUrl() . $this->settings->getAccessToken() . '/' . $method;

        return async(function () use ($url, $params) {
            $request = new Request($url, 'POST');
            $request->setHeader('Content-Type', 'application/json');
            $request->setBody(json_encode($params));

            $response = $this->httpClient->request($request);
            $body = $response->getBody()->buffer();
            return json_decode($body, true);
        });
    }

    /**
     * Send request with file upload support
     * Useful for photos, documents, audio, stickers, etc.
     */
    private function requestWithFile(string $method, array $fields, array $files = []): Future
    {
        $url = $this->settings->getApiUrl() . $this->settings->getAccessToken() . '/' . $method;

        return async(function () use ($url, $fields, $files) {
            $form = new Form();

            foreach ($fields as $key => $value) {
                $form->addField($key, (string) $value);
            }

            foreach ($files as $key => $filePath) {
                $realPath = realpath($filePath);
                if (!$realPath)
                    throw new \RuntimeException("File not found: $filePath");
                $form->addFile($key, $realPath);
            }

            $request = new Request($url, 'POST');
            $request->setBody($form);

            $response = $this->httpClient->request($request);
            $body = $response->getBody()->buffer();
            return json_decode($body, true);
        });
    }


    /**
     * Get bot info (getMe)
     */
    public function getMe(): Future
    {
        return $this->request('getMe', []);
    }



    /**
     * Send text message
     */
    public function sendMessage(int $chatId, string $text, ?array $keyboard = null, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'text' => $text];
        if ($keyboard !== null)
            $payload['reply_markup'] = json_encode($keyboard);

        return $this->request('sendMessage', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    public function reply(int $chatId, int $replyToMessageId, string $text, ?array $keyboard = null, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'text' => $text, 'reply_to_message_id' => $replyToMessageId];
        if ($keyboard !== null)
            $payload['reply_markup'] = json_encode($keyboard);
        return $this->request('sendMessage', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }



    /**
     * Send photo
     * Supports both Media object or URL/file_id string
     */
    public function sendPhoto(int $chatId, string|Media $photo, ?string $caption = null, ?array $keyboard = null, ?array $extraParams = null): Future
    {
        $fields = ['chat_id' => $chatId];
        if ($caption !== null)
            $fields['caption'] = $caption;
        if ($keyboard !== null)
            $fields['reply_markup'] = json_encode($keyboard);
        if ($extraParams !== null)
            $fields = array_merge($fields, $extraParams);

        if ($photo instanceof Media)
            return $this->requestWithFile('sendPhoto', $fields, ['photo' => $photo->filePath]);

        $fields['photo'] = $photo;
        return $this->request('sendPhoto', $fields);
    }

    /**
     * Send video
     * Supports Media object for file upload or string for URL/file_id
     */
    public function sendVideo(int $chatId, string|Media $video, ?string $caption = null, ?array $keyboard = null, ?array $extraParams = null): Future
    {
        $fields = ['chat_id' => $chatId];
        if ($caption !== null)
            $fields['caption'] = $caption;
        if ($keyboard !== null)
            $fields['reply_markup'] = json_encode($keyboard);
        if ($extraParams !== null)
            $fields = array_merge($fields, $extraParams);

        if ($video instanceof Media)
            return $this->requestWithFile('sendVideo', $fields, ['video' => $video->filePath]);

        $fields['video'] = $video;
        return $this->request('sendVideo', $fields);
    }

    /**
     * Send audio (music or voice)
     */
    public function sendAudio(int $chatId, string|Media $audio, ?string $caption = null, ?array $keyboard = null, ?array $extraParams = null): Future
    {
        $fields = ['chat_id' => $chatId];
        if ($caption !== null)
            $fields['caption'] = $caption;
        if ($keyboard !== null)
            $fields['reply_markup'] = json_encode($keyboard);
        if ($extraParams !== null)
            $fields = array_merge($fields, $extraParams);

        if ($audio instanceof Media)
            return $this->requestWithFile('sendAudio', $fields, ['audio' => $audio->filePath]);

        $fields['audio'] = $audio;
        return $this->request('sendAudio', $fields);
    }

    /**
     * Send document (pdf, zip, etc)
     */
    public function sendDocument(int $chatId, string|Media $document, ?string $caption = null, ?array $keyboard = null, ?array $extraParams = null): Future
    {
        $fields = ['chat_id' => $chatId];
        if ($caption !== null)
            $fields['caption'] = $caption;
        if ($keyboard !== null)
            $fields['reply_markup'] = json_encode($keyboard);
        if ($extraParams !== null)
            $fields = array_merge($fields, $extraParams);

        if ($document instanceof Media)
            return $this->requestWithFile('sendDocument', $fields, ['document' => $document->filePath]);

        $fields['document'] = $document;
        return $this->request('sendDocument', $fields);
    }

    /**
     * Send animation (GIF)
     */
    public function sendAnimation(int $chatId, string|Media $animation, ?string $caption = null, ?array $keyboard = null, ?array $extraParams = null): Future
    {
        $fields = ['chat_id' => $chatId];
        if ($caption !== null)
            $fields['caption'] = $caption;
        if ($keyboard !== null)
            $fields['reply_markup'] = json_encode($keyboard);
        if ($extraParams !== null)
            $fields = array_merge($fields, $extraParams);

        if ($animation instanceof Media)
            return $this->requestWithFile('sendAnimation', $fields, ['animation' => $animation->filePath]);

        $fields['animation'] = $animation;
        return $this->request('sendAnimation', $fields);
    }

    /**
     * Send sticker by ID
     */
    public function sendSticker(int $chatId, string $stickerId, ?array $extraParams = null): Future
    {
        return $this->request('sendSticker', array_merge(['chat_id' => $chatId, 'sticker' => $stickerId], $extraParams ?? []));
    }

    /**
     * Edit existing message text
     */
    public function editMessageText(int $chatId, int $messageId, string $text, ?array $keyboard = null, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'message_id' => $messageId, 'text' => $text];
        if ($keyboard !== null)
            $payload['reply_markup'] = json_encode($keyboard);
        return $this->request('editMessageText', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Delete message
     */
    public function deleteMessage(int $chatId, int $messageId): Future
    {
        return $this->request('deleteMessage', ['chat_id' => $chatId, 'message_id' => $messageId]);
    }

    /**
     * Forward message from one chat to another
     */
    public function forwardMessage(int $chatId, int $fromChatId, int $messageId, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'from_chat_id' => $fromChatId, 'message_id' => $messageId];
        return $this->request('forwardMessage', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Answer callback query (from inline keyboards)
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, ?bool $showAlert = false, ?array $extraParams = null): Future
    {
        $payload = ['callback_query_id' => $callbackQueryId];
        if ($text !== null)
            $payload['text'] = $text;
        if ($showAlert !== null)
            $payload['show_alert'] = $showAlert;
        return $this->request('answerCallbackQuery', $extraParams ? array_merge($payload, $extraParams) : $payload);
    }

    /**
     * Get chat info
     */
    public function getChat(int $chatId): Future
    {
        return $this->request('getChat', ['chat_id' => $chatId]);
    }

    /**
     * Get specific chat member info
     */
    public function getChatMember(int $chatId, int $userId): Future
    {
        return $this->request('getChatMember', ['chat_id' => $chatId, 'user_id' => $userId]);
    }

    /**
     * Get chat administrators
     */
    public function getChatAdministrators(int $chatId): Future
    {
        return $this->request('getChatAdministrators', ['chat_id' => $chatId]);
    }

    /**
     * Get chat members count
     */
    public function getChatMembersCount(int $chatId): Future
    {
        return $this->request('getChatMembersCount', ['chat_id' => $chatId]);
    }

    /**
     * Pin message in chat
     */
    public function pinChatMessage(int $chatId, int $messageId, ?bool $disableNotification = false): Future
    {
        return $this->request('pinChatMessage', ['chat_id' => $chatId, 'message_id' => $messageId, 'disable_notification' => $disableNotification]);
    }

    /**
     * Unpin pinned message
     */
    public function unpinChatMessage(int $chatId): Future
    {
        return $this->request('unpinChatMessage', ['chat_id' => $chatId]);
    }

    /**
     * Set chat title
     */
    public function setChatTitle(int $chatId, string $title): Future
    {
        return $this->request('setChatTitle', ['chat_id' => $chatId, 'title' => $title]);
    }

    /**
     * Set chat description
     */
    public function setChatDescription(int $chatId, string $description): Future
    {
        return $this->request('setChatDescription', ['chat_id' => $chatId, 'description' => $description]);
    }

    /**
     * Set chat photo
     */
    public function setChatPhoto(int $chatId, string $photoUrl): Future
    {
        return $this->request('setChatPhoto', ['chat_id' => $chatId, 'photo' => $photoUrl]);
    }

    /**
     * Get file info from Telegram server
     */
    public function getFile(string $fileId): Future
    {
        return $this->request('getFile', ['file_id' => $fileId]);
    }

    /**
     * Send dice animation
     */
    public function sendDice(int $chatId, ?string $emoji = null, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId];
        if ($emoji !== null)
            $payload['emoji'] = $emoji;
        return $this->request('sendDice', $payload + ($extraParams ?? []));
    }

    /**
     * Send poll (quiz or survey)
     */
    public function sendPoll(int $chatId, string $question, array $options, ?bool $isAnonymous = true, ?string $type = 'regular', ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'question' => $question, 'options' => json_encode($options), 'is_anonymous' => $isAnonymous, 'type' => $type];
        return $this->request('sendPoll', $payload + ($extraParams ?? []));
    }

    /**
     * Stop a running poll
     */
    public function stopPoll(int $chatId, int $messageId, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'message_id' => $messageId];
        return $this->request('stopPoll', $payload + ($extraParams ?? []));
    }

    /**
     * Send venue location
     */
    public function sendVenue(int $chatId, float $latitude, float $longitude, string $title, string $address, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'latitude' => $latitude, 'longitude' => $longitude, 'title' => $title, 'address' => $address];
        return $this->request('sendVenue', $payload + ($extraParams ?? []));
    }

    /**
     * Send live location
     */
    public function sendLocation(int $chatId, float $latitude, float $longitude, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'latitude' => $latitude, 'longitude' => $longitude];
        return $this->request('sendLocation', $payload + ($extraParams ?? []));
    }

    /**
     * Send contact info
     */
    public function sendContact(int $chatId, string $phoneNumber, string $firstName, ?string $lastName = null, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'phone_number' => $phoneNumber, 'first_name' => $firstName];
        if ($lastName !== null)
            $payload['last_name'] = $lastName;
        return $this->request('sendContact', $payload + ($extraParams ?? []));
    }

    /**
     * Get sticker set info
     */
    public function getStickerSet(string $name): Future
    {
        return $this->request('getStickerSet', ['name' => $name]);
    }

    /**
     * Upload PNG sticker file
     */
    public function uploadStickerFile(int $userId, Media $pngSticker): Future
    {
        return $this->requestWithFile('uploadStickerFile', ['user_id' => $userId], ['png_sticker' => $pngSticker->filePath]);
    }

    /**
     * Create new sticker set
     */
    public function createNewStickerSet(int $userId, string $name, string $title, string $emojis, Media $pngSticker, ?array $extraParams = null): Future
    {
        $fields = ['user_id' => $userId, 'name' => $name, 'title' => $title, 'emojis' => $emojis];
        if ($extraParams !== null)
            $fields = array_merge($fields, $extraParams);
        return $this->requestWithFile('createNewStickerSet', $fields, ['png_sticker' => $pngSticker->filePath]);
    }

    /**
     * Add sticker to existing set
     */
    public function addStickerToSet(int $userId, string $name, string $emojis, Media $pngSticker, ?array $extraParams = null): Future
    {
        $fields = ['user_id' => $userId, 'name' => $name, 'emojis' => $emojis];
        if ($extraParams !== null)
            $fields = array_merge($fields, $extraParams);
        return $this->requestWithFile('addStickerToSet', $fields, ['png_sticker' => $pngSticker->filePath]);
    }

    /**
     * Delete sticker from set
     */
    public function deleteStickerFromSet(string $stickerId): Future
    {
        return $this->request('deleteStickerFromSet', ['sticker' => $stickerId]);
    }

    /**
     * Set sticker position inside set
     */
    public function setStickerPositionInSet(string $stickerId, int $position): Future
    {
        return $this->request('setStickerPositionInSet', ['sticker' => $stickerId, 'position' => $position]);
    }

    /**
     * Set thumbnail of a sticker set
     */
    public function setStickerSetThumb(string $name, Media $thumb): Future
    {
        return $this->requestWithFile('setStickerSetThumb', ['name' => $name], ['thumb' => $thumb->filePath]);
    }

    /**
     * Send "typing", "upload_photo", etc. action indicator
     */
    public function sendChatAction(int $chatId, string $action): Future
    {
        return $this->request('sendChatAction', ['chat_id' => $chatId, 'action' => $action]);
    }

    /**
     * Get user profile photos
     */
    public function getUserProfilePhotos(int $userId, ?int $offset = null, ?int $limit = null): Future
    {
        $payload = ['user_id' => $userId];
        if ($offset !== null)
            $payload['offset'] = $offset;
        if ($limit !== null)
            $payload['limit'] = $limit;
        return $this->request('getUserProfilePhotos', $payload);
    }

    /**
     * Kick user from chat
     */
    public function kickChatMember(int $chatId, int $userId, ?int $untilDate = null, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'user_id' => $userId];
        if ($untilDate !== null)
            $payload['until_date'] = $untilDate;
        return $this->request('kickChatMember', $payload + ($extraParams ?? []));
    }

    /**
     * Unban user
     */
    public function unbanChatMember(int $chatId, int $userId, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'user_id' => $userId];
        return $this->request('unbanChatMember', $payload + ($extraParams ?? []));
    }

    /**
     * Restrict user permissions in chat
     */
    public function restrictChatMember(int $chatId, int $userId, array $permissions, ?int $untilDate = null, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'user_id' => $userId, 'permissions' => json_encode($permissions)];
        if ($untilDate !== null)
            $payload['until_date'] = $untilDate;
        return $this->request('restrictChatMember', $payload + ($extraParams ?? []));
    }

    /**
     * Promote user with admin privileges
     */
    public function promoteChatMember(int $chatId, int $userId, array $privileges, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'user_id' => $userId] + $privileges;
        return $this->request('promoteChatMember', $payload + ($extraParams ?? []));
    }

    /**
     * Set chat-wide permissions
     */
    public function setChatPermissions(int $chatId, array $permissions, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'permissions' => json_encode($permissions)];
        return $this->request('setChatPermissions', $payload + ($extraParams ?? []));
    }

    /**
     * Export chat invite link
     */
    public function exportChatInviteLink(int $chatId): Future
    {
        return $this->request('exportChatInviteLink', ['chat_id' => $chatId]);
    }

    /**
     * Create a new invite link
     */
    public function createChatInviteLink(int $chatId, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId];
        return $this->request('createChatInviteLink', $payload + ($extraParams ?? []));
    }

    /**
     * Edit an existing invite link
     */
    public function editChatInviteLink(int $chatId, string $inviteLink, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'invite_link' => $inviteLink];
        return $this->request('editChatInviteLink', $payload + ($extraParams ?? []));
    }

    /**
     * Revoke an invite link
     */
    public function revokeChatInviteLink(int $chatId, string $inviteLink): Future
    {
        return $this->request('revokeChatInviteLink', ['chat_id' => $chatId, 'invite_link' => $inviteLink]);
    }

    /**
     * Answer inline query (used in inline bots)
     */
    public function answerInlineQuery(string $inlineQueryId, array $results, ?bool $cacheTime = null, ?bool $isPersonal = null, ?array $extraParams = null): Future
    {
        $payload = ['inline_query_id' => $inlineQueryId, 'results' => json_encode($results)];
        if ($cacheTime !== null)
            $payload['cache_time'] = $cacheTime;
        if ($isPersonal !== null)
            $payload['is_personal'] = $isPersonal;
        return $this->request('answerInlineQuery', $payload + ($extraParams ?? []));
    }

    /**
     * Send invoice for payments
     */
    public function sendInvoice(int $chatId, string $title, string $description, string $payloadStr, string $providerToken, string $currency, array $prices, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'title' => $title, 'description' => $description, 'payload' => $payloadStr, 'provider_token' => $providerToken, 'currency' => $currency, 'prices' => json_encode($prices)];
        return $this->request('sendInvoice', $payload + ($extraParams ?? []));
    }

    /**
     * Answer shipping query
     */
    public function answerShippingQuery(string $shippingQueryId, bool $ok, ?array $shippingOptions = null, ?string $errorMessage = null): Future
    {
        $payload = ['shipping_query_id' => $shippingQueryId, 'ok' => $ok];
        if ($shippingOptions !== null)
            $payload['shipping_options'] = json_encode($shippingOptions);
        if ($errorMessage !== null)
            $payload['error_message'] = $errorMessage;
        return $this->request('answerShippingQuery', $payload);
    }

    /**
     * Answer pre-checkout query
     */
    public function answerPreCheckoutQuery(string $preCheckoutQueryId, bool $ok, ?string $errorMessage = null): Future
    {
        $payload = ['pre_checkout_query_id' => $preCheckoutQueryId, 'ok' => $ok];
        if ($errorMessage !== null)
            $payload['error_message'] = $errorMessage;
        return $this->request('answerPreCheckoutQuery', $payload);
    }

    /**
     * Send game message
     */
    public function sendGame(int $chatId, string $gameShortName, ?array $extraParams = null): Future
    {
        $payload = ['chat_id' => $chatId, 'game_short_name' => $gameShortName];
        return $this->request('sendGame', $payload + ($extraParams ?? []));
    }

    /**
     * Set game score
     */
    public function setGameScore(int $userId, int $score, int $chatId, int $messageId, ?bool $force = false, ?bool $disableEditMessage = false): Future
    {
        $payload = ['user_id' => $userId, 'score' => $score, 'chat_id' => $chatId, 'message_id' => $messageId];
        if ($force !== null)
            $payload['force'] = $force;
        if ($disableEditMessage !== null)
            $payload['disable_edit_message'] = $disableEditMessage;
        return $this->request('setGameScore', $payload);
    }

    /**
     * Get game high scores
     */
    public function getGameHighScores(int $userId, int $chatId, int $messageId): Future
    {
        return $this->request('getGameHighScores', ['user_id' => $userId, 'chat_id' => $chatId, 'message_id' => $messageId]);
    }

    /**
     * Get file URL for download
     */
    public function getFileUrl(string $fileId): string
    {
        return "https://api.telegram.org/file/bot" . $this->settings->getAccessToken() . "/" . $fileId;
    }

    /**
     * Download file from Telegram servers
     */
    public function downloadFile(string $fileId, string $destinationPath): Future
    {
        return async(function () use ($fileId, $destinationPath) {
            $fileInfo = yield $this->getFile($fileId);
            if (!isset($fileInfo['result']['file_path']))
                throw new \RuntimeException("Invalid file_id or file not found");
            $filePath = $fileInfo['result']['file_path'];
            $url = "https://api.telegram.org/file/bot" . $this->settings->getAccessToken() . "/" . $filePath;

            $request = new Request($url);
            $response = yield $this->httpClient->request($request);
            $body = yield $response->getBody()->buffer();

            file_put_contents($destinationPath, $body);
            return $destinationPath;
        });
    }

    /**
     * Set webhook for receiving updates
     */
    public function setWebhook(string $url, ?string $certificate = null, ?int $maxConnections = null, ?array $allowedUpdates = null, ?bool $dropPendingUpdates = null): Future
    {
        $payload = ['url' => $url];
        if ($certificate !== null)
            $payload['certificate'] = $certificate;
        if ($maxConnections !== null)
            $payload['max_connections'] = $maxConnections;
        if ($allowedUpdates !== null)
            $payload['allowed_updates'] = $allowedUpdates;
        if ($dropPendingUpdates !== null)
            $payload['drop_pending_updates'] = $dropPendingUpdates;
        return $this->request('setWebhook', $payload);
    }

    /**
     * Delete webhook
     */
    public function deleteWebhook(?bool $dropPendingUpdates = null): Future
    {
        $payload = [];
        if ($dropPendingUpdates !== null)
            $payload['drop_pending_updates'] = $dropPendingUpdates;
        return $this->request('deleteWebhook', $payload);
    }

    /**
     * Get webhook info
     */
    public function getWebhookInfo(): Future
    {
        return $this->request('getWebhookInfo');
    }
}

<?php

/**
 * @version 2.2.0
 * @author Abolfazl Majidi (Afaz)
 * @package neili
 * @license https://opensource.org/licenses/MIT
 * @link https://github.com/AfazTech/neili
 */

declare(strict_types=1);

namespace Neili;

use Amp\Future;
use function Amp\async;
use function Amp\delay;
use Amp\Sync\LocalSemaphore;

class Poller
{
    private Client $client;
    private array $handlers = [];
    private $updateHandler = null; // backward compatible single update callback
    private int $offset = 0; // last processed update ID
    private bool $running = false; // poller active state
    private ?Future $mainFuture = null; // main async loop future
    private string $lockFile = 'neili.lock'; // optional lock file for single instance
    private Logger $logger;
    private ?LocalSemaphore $semaphore = null; // concurrency control semaphore

    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->logger = $this->client->getSettings()->getLogger();

        $maxConcurrency = $this->client->getSettings()->getPollerMaxConcurrency();
        $this->semaphore = $maxConcurrency ? new LocalSemaphore($maxConcurrency) : null;
    }

    // Set a global update callback
    public function onUpdate(callable $callback): void
    {
        $this->updateHandler = $callback;
    }

    // Register callback handlers for specific Telegram update types
    public function onMessage(callable $callback): void { $this->handlers['message'][] = $callback; }
    public function onEditedMessage(callable $callback): void { $this->handlers['edited_message'][] = $callback; }
    public function onMessageReaction(callable $callback): void { $this->handlers['message_reaction'][] = $callback; }
    public function onMessageReactionCount(callable $callback): void { $this->handlers['message_reaction_count'][] = $callback; }
    public function onChatBoost(callable $callback): void { $this->handlers['chat_boost'][] = $callback; }
    public function onRemovedChatBoost(callable $callback): void { $this->handlers['removed_chat_boost'][] = $callback; }
    public function onChannelPost(callable $callback): void { $this->handlers['channel_post'][] = $callback; }
    public function onEditedChannelPost(callable $callback): void { $this->handlers['edited_channel_post'][] = $callback; }
    public function onInlineQuery(callable $callback): void { $this->handlers['inline_query'][] = $callback; }
    public function onChosenInlineResult(callable $callback): void { $this->handlers['chosen_inline_result'][] = $callback; }
    public function onCallbackQuery(callable $callback): void { $this->handlers['callback_query'][] = $callback; }
    public function onShippingQuery(callable $callback): void { $this->handlers['shipping_query'][] = $callback; }
    public function onPreCheckoutQuery(callable $callback): void { $this->handlers['pre_checkout_query'][] = $callback; }
    public function onPoll(callable $callback): void { $this->handlers['poll'][] = $callback; }
    public function onPollAnswer(callable $callback): void { $this->handlers['poll_answer'][] = $callback; }
    public function onMyChatMember(callable $callback): void { $this->handlers['my_chat_member'][] = $callback; }
    public function onChatMember(callable $callback): void { $this->handlers['chat_member'][] = $callback; }
    public function onChatJoinRequest(callable $callback): void { $this->handlers['chat_join_request'][] = $callback; }
    public function onBusinessMessage(callable $callback): void { $this->handlers['business_message'][] = $callback; }
    public function onEditedBusinessMessage(callable $callback): void { $this->handlers['edited_business_message'][] = $callback; }
    public function onDeletedBusinessMessage(callable $callback): void { $this->handlers['deleted_business_message'][] = $callback; }
    public function onBusinessConnection(callable $callback): void { $this->handlers['business_connection'][] = $callback; }

    // Determine the type of incoming update based on registered handlers
    private function detectType(array $update): string
    {
        foreach (array_keys($this->handlers) as $type) {
            if (isset($update[$type])) return $type;
        }
        return 'unknown';
    }

    // Start polling loop with optional discarding of old updates
    public function start(bool $discardOldUpdates = true): void
    {
        if ($this->running) throw new \RuntimeException('Poller already running');

        // Ensure background execution and unlimited script runtime
        if (function_exists('ignore_user_abort')) ignore_user_abort(true);
        if (function_exists('set_time_limit')) set_time_limit(0);
        if (function_exists('ini_set')) @ini_set('max_execution_time', '0');

        // Send headers and flush if not running in CLI
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            header('Connection: close');
            header('Content-Type: text/html');
            echo "Poller started in background";
            flush();
            if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
            if (function_exists('litespeed_finish_request')) litespeed_finish_request();
        }

        $this->running = true;

        $settings = $this->client->getSettings();
        $timeout = $settings->getPollerTimeout();
        $backoffBase = $settings->getPollerBackoffBase();
        $maxBackoff = $settings->getPollerMaxBackoff();

        // Optionally discard old updates to start fresh
        if ($discardOldUpdates) {
            try {
                $latest = $this->client->getUpdates(['timeout'=>1])->await();
                $result = $latest['result'] ?? [];
                if ($result) $this->offset = (int) end($result)['update_id'] + 1;
            } catch (\Throwable $e) {
                $this->logger->warning("Discard old updates failed: ".$e->getMessage());
            }
        }

        // Main asynchronous polling loop
        $this->mainFuture = async(function () use ($timeout, $backoffBase, $maxBackoff) {
            $failCount = 0;
            while ($this->running) {
                try {
                    $response = $this->client->getUpdates(['offset'=>$this->offset,'timeout'=>$timeout])->await();
                    $updates = $response['result'] ?? [];

                    foreach ($updates as $update) {
                        if (!is_array($update)) continue;
                        $this->offset = (int) ($update['update_id'] ?? $this->offset) + 1;

                        async(function () use ($update) {
                            $lock = $this->semaphore?->acquire();
                            try {
                                $type = $this->detectType($update);
                                foreach ($this->handlers[$type] ?? [] as $handler) {
                                    try { $handler($update); } 
                                    catch (\Throwable $e) { $this->logger->error("Handler error for {$type}: ".$e->getMessage()); }
                                }
                                if ($this->updateHandler !== null) {
                                    try { ($this->updateHandler)($update); } 
                                    catch (\Throwable $e) { $this->logger->error("onUpdate handler error: ".$e->getMessage()); }
                                }
                            } finally { $lock?->release(); }
                        });
                    }

                    $failCount = 0;
                } catch (\Throwable $e) {
                    $failCount++;
                    $backoff = min($backoffBase << min($failCount,6), $maxBackoff);
                    $this->logger->error("Poller error: ".$e->getMessage());
                    delay($backoff * 1000);
                }
            }
            $this->logger->info("Poller stopped");
        });

        $this->mainFuture->await();
    }

    // Stop the poller loop
    public function stop(): void { $this->running = false; }

    // Check if poller is currently running
    public function isRunning(): bool { return $this->running; }
}

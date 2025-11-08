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

use Amp\Future;
use function Amp\async;
use function Amp\delay;
use Amp\Sync\LocalSemaphore;

class Poller
{
    /**
     * Telegram API client instance
     */
    private Client $client;

    /**
     * Callback function to handle each update
     */
    private $updateHandler = null;

    /**
     * Offset for updates to avoid processing duplicates
     */
    private int $offset = 0;

    /**
     * Flag indicating whether poller is currently running
     */
    private bool $running = false;

    /**
     * Main async future for the polling loop
     */
    private ?Future $mainFuture = null;

    /**
     * Lock file path to prevent multiple poller instances
     */
    private string $lockFile = 'neili.lock';

    /**
     * Logger instance for error and info reporting
     */
    private Logger $logger;

    /**
     * Constructor
     * @param Client $client Telegram API client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;
        $this->logger = $this->client->getSettings()->getLogger();
    }

    /**
     * Register update callback
     * @param callable $callback Function executed for each update
     */
    public function onUpdate(callable $callback): void
    {
        $this->updateHandler = $callback;
    }

    /**
     * Start the poller
     * @param bool $discardOldUpdates Whether to ignore old updates on start
     */
    public function start(bool $discardOldUpdates = true): void
    {
        if ($this->running) {
            throw new \RuntimeException('Poller already running');
        }

        // Stop poller via web request if needed
        if (php_sapi_name() !== 'cli' && isset($_GET['stopPoller'])) {
            if (file_exists($this->lockFile)) {
                $pid = (int) trim(file_get_contents($this->lockFile));
                if ($pid > 0 && function_exists('posix_kill') && posix_kill($pid, 0)) {
                    posix_kill($pid, SIGTERM);
                }
                @unlink($this->lockFile);
            }
            http_response_code(200);
            echo "Poller stopped";
            exit;
        }

        // Open lock file to ensure single instance
        $fp = fopen($this->lockFile, 'c+');
        if (!$fp) {
            throw new \RuntimeException("Cannot open lock file: {$this->lockFile}");
        }

        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fseek($fp, 0);
            $existingPid = (int) trim(fread($fp, 100));
            throw new \RuntimeException("Poller already running with PID {$existingPid}");
        }

        ftruncate($fp, 0);
        fwrite($fp, (string) getmypid());
        fflush($fp);

        // Release lock on shutdown
        register_shutdown_function(function () use ($fp) {
            flock($fp, LOCK_UN);
            fclose($fp);
            @unlink('neili.lock');
        });

        // Background execution for web requests
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            ignore_user_abort(true);
            header('Connection: close');
            header('Content-Type: text/html');
            echo "Poller started in background";
            flush();
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
        }

        $this->running = true;

        $settings = $this->client->getSettings();
        $timeout = $settings->getPollerTimeout();
        $backoffBase = $settings->getPollerBackoffBase();
        $maxBackoff = $settings->getPollerMaxBackoff();
        $logger = $settings->getLogger();
        $maxConcurrency = $settings->getPollerMaxConcurrency();
        $semaphore = $maxConcurrency !== null ? new LocalSemaphore($maxConcurrency) : null;

        // Discard old updates to start fresh
        if ($discardOldUpdates) {
            try {
                $latest = $this->client->getUpdates(['timeout' => 1])->await();
                $result = $latest['result'] ?? [];
                if (!empty($result)) {
                    $last = end($result);
                    $this->offset = (int) ($last['update_id'] ?? 0) + 1;
                }
            } catch (\Throwable $e) {
                $logger->warning("Discard old updates failed: " . $e->getMessage());
            }
        }

        // Main polling loop
        $this->mainFuture = async(function () use ($timeout, $backoffBase, $maxBackoff, $logger, $semaphore) {
            $failCount = 0;

            while ($this->running) {
                try {
                    // Request updates from Telegram API
                    $response = $this->client->getUpdates([
                        'offset' => $this->offset,
                        'timeout' => $timeout,
                    ])->await();

                    $updates = $response['result'] ?? [];

                    if (!empty($updates) && is_array($updates)) {
                        foreach ($updates as $update) {
                            if (!is_array($update)) continue;

                            // Update offset to avoid re-processing
                            $this->offset = (int) ($update['update_id'] ?? $this->offset) + 1;

                            // Handle update asynchronously
                            if ($this->updateHandler !== null) {
                                async(function () use ($update, $semaphore, $logger) {
                                    $lock = $semaphore?->acquire();
                                    try {
                                        ($this->updateHandler)($update);
                                    } catch (\Throwable $e) {
                                        $logger->error("Handler error: " . $e->getMessage());
                                    } finally {
                                        $lock?->release();
                                    }
                                });
                            }
                        }
                    }

                    $failCount = 0;
                } catch (\Throwable $e) {
                    // Log and apply exponential backoff on failure
                    $logger->error("Poller error: " . $e->getMessage());
                    $failCount++;
                    $backoff = $backoffBase << min($failCount, 6);
                    if ($backoff > $maxBackoff) $backoff = $maxBackoff;
                    delay($backoff * 1000);
                }
            }

            $logger->info("Poller stopped");
        });

        $this->mainFuture->await();
    }

    /**
     * Stop the poller gracefully
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * Check if the poller is running
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->running;
    }
}

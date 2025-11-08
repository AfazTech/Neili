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

use Psr\Log\LoggerInterface;

class Settings
{
    /**
     * Telegram bot access token
     */
    private string $accessToken;

    /**
     * Base API URL
     */
    private string $apiUrl = 'https://api.telegram.org/bot';

    /**
     * Enable SSL verification for API requests
     */
    private bool $apiVerifySSL = true;

    /**
     * Request timeout in seconds
     */
    private int $timeout = 10;

    /**
     * Connection timeout in seconds
     */
    private int $connectionTimeout = 5;

    /**
     * Flag to enable multi-process usage
     */
    private bool $useMultiProcess = false;

    /**
     * Path to PHP binary for multi-process execution
     */
    private string $phpBinary = '/usr/bin/php';

    /**
     * Timeout for poller getUpdates requests
     */
    private int $pollerTimeout = 5;

    /**
     * Base seconds for exponential backoff in poller
     */
    private int $pollerBackoffBase = 1;

    /**
     * Maximum backoff seconds in poller
     */
    private int $pollerMaxBackoff = 32;

    /**
     * Maximum concurrent async handlers in poller
     */
    private ?int $pollerMaxConcurrency = null;

    /**
     * Logger instance
     */
    private LoggerInterface $logger;

    /**
     * Constructor
     * @param Logger|null $logger Optional custom logger
     */
    public function __construct(?Logger $logger = null)
    {
        $this->logger = $logger ?? new Logger('/neili.log');
    }

    /**
     * Set bot access token
     */
    public function setAccessToken(string $token): self { $this->accessToken = $token; return $this; }

    /**
     * Get bot access token
     */
    public function getAccessToken(): string { return $this->accessToken; }

    /**
     * Set API URL
     */
    public function setApiUrl(string $url): self { $this->apiUrl = $url; return $this; }

    /**
     * Get API URL
     */
    public function getApiUrl(): string { return $this->apiUrl; }

    /**
     * Enable or disable SSL verification
     */
    public function setApiVerifySSL(bool $state): self { $this->apiVerifySSL = $state; return $this; }

    /**
     * Check if SSL verification is enabled
     */
    public function isApiVerifySSL(): bool { return $this->apiVerifySSL; }

    /**
     * Set request timeout and optionally connection timeout
     */
    public function setTimeout(int $timeout, ?int $connectionTimeout = null): self {
        $this->timeout = $timeout;
        if ($connectionTimeout !== null) $this->connectionTimeout = $connectionTimeout;
        return $this;
    }

    /**
     * Get request timeout
     */
    public function getTimeout(): int { return $this->timeout; }

    /**
     * Get connection timeout
     */
    public function getConnectionTimeout(): int { return $this->connectionTimeout; }

    /**
     * Enable or disable multi-process
     * @throws \RuntimeException if exec is disabled
     */
    public function setMultiProcess(bool $state): self {
        if ($state && !function_exists('exec')) throw new \RuntimeException("Cannot enable multi-process: exec disabled");
        $this->useMultiProcess = $state;
        return $this;
    }

    /**
     * Check if multi-process is enabled
     */
    public function isMultiProcess(): bool { return $this->useMultiProcess; }

    /**
     * Set PHP binary path for multi-process execution
     */
    public function setPhpBinary(string $path): self { $this->phpBinary = $path; return $this; }

    /**
     * Get PHP binary path
     */
    public function getPhpBinary(): string { return $this->phpBinary; }

    /**
     * Set base seconds for poller backoff
     */
    public function setPollerBackoffBase(int $seconds): self { $this->pollerBackoffBase = $seconds; return $this; }

    /**
     * Get poller backoff base seconds
     */
    public function getPollerBackoffBase(): int { return $this->pollerBackoffBase; }

    /**
     * Set maximum backoff seconds for poller
     */
    public function setPollerMaxBackoff(int $seconds): self { $this->pollerMaxBackoff = $seconds; return $this; }

    /**
     * Get poller maximum backoff seconds
     */
    public function getPollerMaxBackoff(): int { return $this->pollerMaxBackoff; }

    /**
     * Set maximum concurrent handlers for poller
     */
    public function setPollerMaxConcurrency(?int $n): self { $this->pollerMaxConcurrency = $n; return $this; }

    /**
     * Get maximum concurrent handlers
     */
    public function getPollerMaxConcurrency(): ?int { return $this->pollerMaxConcurrency; }

    /**
     * Get logger instance
     */
    public function getLogger(): LoggerInterface { return $this->logger; }

    /**
     * Set poller request timeout
     */
    public function setPollerTimeout(int $seconds): self { $this->pollerTimeout = $seconds; return $this; }

    /**
     * Get poller request timeout
     */
    public function getPollerTimeout(): int { return $this->pollerTimeout ?? 30; }
}

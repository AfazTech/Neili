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

use Amp\File;
use Amp\Future;
use function Amp\async;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;

class Logger extends AbstractLogger implements LoggerInterface
{
    /**
     * File path to store logs
     */
    private string $filePath;

    /**
     * Flag to print log messages to console
     */
    private bool $printToConsole;

    /**
     * Async handle for file operations
     */
    private ?Future $fileHandle = null;

    /**
     * Constructor
     * @param string $filePath Path to log file
     * @param bool $printToConsole Whether to print logs to console
     */
    public function __construct(string $filePath, bool $printToConsole = true)
    {
        $this->filePath = $filePath;
        $this->printToConsole = $printToConsole;
    }

    /**
     * Open file asynchronously if not already opened
     * @return Future Async handle for the file
     */
    private function openFile(): Future
    {
        if ($this->fileHandle === null) {
            $this->fileHandle = async(function () {
                return yield File\openFile($this->filePath, "a");
            });
        }
        return $this->fileHandle;
    }

    /**
     * Log a message with specified level and context
     * @param string $level Log level (PSR-3)
     * @param string $message Log message with placeholders
     * @param array $context Context array for message interpolation
     */
    public function log($level, $message, array $context = []): void
    {
        $interpolated = $this->interpolate($message, $context);
        $timestamp = date('Y-m-d H:i:s');
        $formatted = "[{$timestamp}] {$level}: {$interpolated}\n";

        if ($this->printToConsole) {
            echo $formatted;
        }

        // Write log asynchronously to file
        async(function () use ($formatted) {
            $handle = yield $this->openFile();
            yield $handle->write($formatted);
        });
    }

    /**
     * Interpolate context values into message placeholders
     * @param string $message Log message
     * @param array $context Context key-value pairs
     * @return string Interpolated message
     */
    private function interpolate(string $message, array $context): string
    {
        foreach ($context as $key => $value) {
            $message = str_replace("{" . $key . "}", (string) $value, $message);
        }
        return $message;
    }

    /**
     * Close the log file asynchronously
     * @return Future
     */
    public function close(): Future
    {
        if ($this->fileHandle === null) {
            return async(fn() => null);
        }

        return async(function () {
            $handle = yield $this->fileHandle;
            yield $handle->close();
        });
    }
}

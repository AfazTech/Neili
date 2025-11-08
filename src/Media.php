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

class Media
{
    /**
     * Absolute path to the media file
     * @var string
     */
    public string $filePath;

    /**
     * Media constructor
     * Validates the file exists and resolves absolute path
     *
     * @param string $filePath Relative or absolute path to the file
     * @throws \RuntimeException if the file does not exist
     */
    public function __construct(string $filePath)
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: $filePath");
        }
        $this->filePath = realpath($filePath);
    }
}

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

class KeyboardBuilder
{
    /**
     * Rows for regular keyboard
     */
    private array $rows = [];

    /**
     * Rows for inline keyboard
     */
    private array $inlineRows = [];

    /**
     * Flag indicating inline keyboard
     */
    private bool $isInline = false;

    /**
     * Flag to resize keyboard (Telegram feature)
     */
    private bool $resize = true;

    /**
     * Flag for one-time keyboard
     */
    private bool $oneTime = false;

    /**
     * Add a row to a reply keyboard
     * @param string ...$buttons Text labels for buttons
     */
    public function row(string ...$buttons): self
    {
        if ($this->isInline)
            throw new \RuntimeException("Use inlineRow() for inline keyboard");
        $this->rows[] = array_map(fn($text) => ['text' => $text], $buttons);
        return $this;
    }

    /**
     * Add a row to an inline keyboard
     * @param array $buttons Array of 'ButtonText' => 'CallbackData'
     */
    public function inlineRow(array $buttons): self
    {
        $row = [];
        foreach ($buttons as $text => $callbackData) {
            $row[] = ['text' => $text, 'callback_data' => $callbackData];
        }
        $this->inlineRows[] = $row;
        $this->isInline = true;
        return $this;
    }


    /**
     * Add a row to an inline keyboard with URL buttons
     * @param array $buttons Array of 'ButtonText' => 'URL'
     */
    public function inlineUrlRow(array $buttons): self
    {
        $row = [];
        foreach ($buttons as $text => $url) {
            $row[] = ['text' => $text, 'url' => $url];
        }
        $this->inlineRows[] = $row;
        $this->isInline = true;
        return $this;
    }


    /**
     * Convert keyboard to inline mode
     */
    public function inline(): self
    {
        $this->isInline = true;
        return $this;
    }

    /**
     * Set resize option for reply keyboard
     */
    public function resize(bool $resize = true): self
    {
        $this->resize = $resize;
        return $this;
    }

    /**
     * Set one-time keyboard option
     */
    public function oneTime(bool $oneTime = true): self
    {
        $this->oneTime = $oneTime;
        return $this;
    }

    /**
     * Clear all keyboard rows and reset options
     */
    public function clear(): self
    {
        $this->rows = [];
        $this->inlineRows = [];
        $this->isInline = false;
        $this->resize = true;
        $this->oneTime = false;
        return $this;
    }

    /**
     * Build final keyboard array for Telegram API
     * @return array
     */
    public function build(): array
    {
        if ($this->isInline) {
            return ['inline_keyboard' => $this->inlineRows];
        }

        return [
            'keyboard' => $this->rows,
            'resize_keyboard' => $this->resize,
            'one_time_keyboard' => $this->oneTime
        ];
    }
}

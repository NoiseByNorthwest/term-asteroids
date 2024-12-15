<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class InputEvent
{
    public const KEY_UP = "\033[A";

    public const KEY_DOWN = "\033[B";

    public const KEY_RIGHT = "\033[C";

    public const KEY_LEFT = "\033[D";

    public const KEY_ENTER = "\n";

    public const KEY_SPACE = " ";

    public const KEY_TAB = "\t";

    public const KEY_ESC =  "\e";

    public const TYPE_PRESS = 'press';

    public const TYPE_RELEASE = 'release';

    private string $key;

    private string $type;

    /**
     * @param string $key
     * @param string $type
     */
    public function __construct(string $key, string $type)
    {
        $this->key = $key;
        $this->type = $type;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isPress(): bool
    {
        return $this->type === self::TYPE_PRESS;
    }

    public function isRelease(): bool
    {
        return $this->type === self::TYPE_RELEASE;
    }
}

<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

class Input
{
    // FIXME support all functional keys
    private static array $functionalKeyMap = [
        57399 => '0',
        57400 => '1',
        57401 => '2',
        57402 => '3',
        57403 => '4',
        57404 => '5',
        57405 => '6',
        57406 => '7',
        57407 => '8',
        57408 => '9',
        57409 => '.',
        57410 => '/',
        57411 => '*',
        57412 => '-',
        57413 => '+',
        57415 => '=',
        57416 => ','
    ];

    private static bool $kittyKeyboardProtocolSupported;

    /**
     * @var resource|null
     */
    private static $stdin = null;

    private static array $lastHitTime = [];

    private static array $pressedKeys = [];

    public static function init(bool $kittyKeyboardProtocolSupported): void
    {
        self::$kittyKeyboardProtocolSupported = $kittyKeyboardProtocolSupported;
        self::$stdin = fopen('php://stdin', 'r');
        assert(self::$stdin !== false);

        stream_set_blocking(self::$stdin, 0);

        if (self::$kittyKeyboardProtocolSupported) {
            // More details here: https://sw.kovidgoyal.net/kitty/keyboard-protocol/#progressive-enhancement
            echo "\033", '[>2u';
        }

        system('stty cbreak -echo');
    }

    /**
     * @return array<InputEvent>
     */
    public static function getEvents(): array
    {
        assert(self::$stdin !== null);

        $currentTime = microtime(true);

        $inputText = fgets(self::$stdin);
        if ($inputText === false) {
            return self::resolveEmulatedReleaseEvents($currentTime);
        }

        $events = [];
        $len = strlen($inputText);

        for ($i = 0; $i < $len; $i++) {
            if ($inputText[$i] === "\033") {
                $s = $inputText[$i];

                $i++;
                assert($inputText[$i] === '[');

                $s .= $inputText[$i];
                $i++;
                while ($i < $len) {
                    $end = false;
                    if (
                        $inputText[$i] === '~' || (
                            $inputText[$i] === 'u' && ctype_digit($inputText[$i - 1])
                        )
                    ) {
                        $end = true;
                    } elseif ($inputText[$i] === "\033" || ctype_lower($inputText[$i])) {
                        $i--;

                        break;
                    }

                    $s .= $inputText[$i];
                    $i++;

                    if ($end) {
                        $i--;

                        break;
                    }
                }

                $pressedKey = $s;
            } else {
                $pressedKey = $inputText[$i];
            }

            if (preg_match('~\\[(\d+);129:3~', $pressedKey, $m) === 1) {
                $releasedKeyCode = (int) $m[1];
                $releasedKey = self::$functionalKeyMap[$releasedKeyCode] ?? chr($releasedKeyCode);

                if (! isset(self::$pressedKeys[$releasedKey])) {
                    continue;
                }

                unset(self::$pressedKeys[$releasedKey]);
                $events[] = new InputEvent($releasedKey, InputEvent::TYPE_RELEASE);

                continue;
            }

            $pressedKey = preg_replace('~\\[1;129:2~', '[', $pressedKey);
            $pressedKey = preg_replace('~\\[1;129~', '[', $pressedKey);

            if (isset(self::$pressedKeys[$pressedKey])) {
                if (self::isReleaseEventSupported($pressedKey)) {
                    continue;
                }

                if ($currentTime - self::$pressedKeys[$pressedKey]['startTime'] > 0.06) {
                    $events[] = new InputEvent($pressedKey, InputEvent::TYPE_RELEASE);
                    $events[] = new InputEvent($pressedKey, InputEvent::TYPE_PRESS);
                }

                self::$pressedKeys[$pressedKey] = [
                    'startTime' => $currentTime,
                    'emulatedEndTime' => $currentTime + 0.1,
                ];

                self::$lastHitTime[$pressedKey] = $currentTime;

                continue;
            }

            self::$pressedKeys[$pressedKey] = [
                'startTime' => $currentTime,
                'emulatedEndTime' => $currentTime + Math::lerpPath([
                    '0.00' => 0.1,
                    '0.06' => 0.1,
                    '0.2' => 0.3,
                    '0.7' => 0.1,
                ], Math::bound($currentTime - (self::$lastHitTime[$pressedKey] ?? 0), 0, 0.7)),
            ];

            self::$lastHitTime[$pressedKey] = $currentTime;

            $events[] = new InputEvent($pressedKey, InputEvent::TYPE_PRESS);
        }

        array_push($events, ...self::resolveEmulatedReleaseEvents($currentTime));

        return $events;
    }

    /**
     * @param float $currentTime
     * @return array<InputEvent>
     */
    private static function resolveEmulatedReleaseEvents(float $currentTime): array
    {
        $events = [];

        foreach (self::$pressedKeys as $key => $info) {
            if (
                self::isReleaseEventSupported($key)
            ) {
                continue;
            }

            if ($currentTime >= $info['emulatedEndTime']) {
                unset(self::$pressedKeys[$key]);
                $events[] = new InputEvent($key, InputEvent::TYPE_RELEASE);
            }
        }

        return $events;
    }

    private static function isReleaseEventSupported(string $key): bool
    {
        return self::$kittyKeyboardProtocolSupported
            && ! in_array($key, [
                InputEvent::KEY_UP,
                InputEvent::KEY_DOWN,
                InputEvent::KEY_LEFT,
                InputEvent::KEY_RIGHT,
            ], true)
        ;
    }
}

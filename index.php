<?php

require 'vendor/autoload.php';

// just to avoid having the xterm window not maximized yet at screen creation time
usleep(200 * 1000);

(new \NoiseByNorthwest\TermAsteroids\Game\TermAsteroids(
    devMode: in_array('--dev-mode', $argv, true),
    benchmarkMode: in_array('--benchmark-mode', $argv, true),
    useNativeRenderer: in_array('--use-native-renderer', $argv, true),
    kittyKeyboardProtocolSupported: ($_ENV['TERM_ASTEROIDS_KITTY_KBP'] ?? '0') === '1',
))->run();

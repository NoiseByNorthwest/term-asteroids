<?php

namespace NoiseByNorthwest\TermAsteroids\Engine;

enum Key: string {
    case UP = "\033[A";

    case DOWN = "\033[B";

    case RIGHT = "\033[C";

    case LEFT = "\033[D";

    case ENTER = "\n";

    case SPACE = " ";

    case TAB = "\t";

    case ESC =  "\e";
}
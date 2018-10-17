<?php

namespace naoki1510\areapvp\events;

use pocketmine\event\Event;
use pocketmine\level\Level;

abstract class GameEvent extends Event
{
    /** @var string|null */
    protected $eventName = 'GameEvent';

    /** @var Level */
    protected $gameLevel;

    public function __construct(Level $gamelevel)
    {

    }


}

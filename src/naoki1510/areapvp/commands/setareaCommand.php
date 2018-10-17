<?php

namespace naoki1510\areapvp\commands;

use naoki1510\areapvp\AreaPvP;
use naoki1510\areapvp\team\TeamManager;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\block\Block;
use pocketmine\command\Command;
use pocketmine\command\CommandEnumValues;
use pocketmine\command\CommandSender;
use pocketmine\level\Level;
use pocketmine\network\mcpe\protocol\types\CommandEnum;
use pocketmine\network\mcpe\protocol\types\CommandParameter;

class setareaCommand extends Command
{
    /** @var AreaPvP */
    private $AreaPvP;

    /** @var TeamManager */
    private $TeamManager;

    public function __construct(AreaPvP $AreaPvP, TeamManager $teamManager)
    {
        parent::__construct(
            'setarea',
            AreaPvP::translate('commands.setarea.description'),
            AreaPvP::translate('commands.setarea.usage')
        );
        $this->setPermission("areapvp.command.setarea");
        $this->AreaPvP = $AreaPvP;
        $this->TeamManager = $teamManager;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if (!$this->testPermission($sender)) {
            return true;
        }
        if ($sender instanceof Player) {
            switch($args[0] ?? ''){
                case 'pos1':
                    $pos = $sender->getLevel()->getBlock($sender->subtract(0, 0.5));
                    $this->TeamManager->setArea($pos->asPosition(), 1);
                    break;

                case 'pos2':
                    $pos = $sender->getLevel()->getBlock($sender->subtract(0, 0.5));
                    $this->TeamManager->setArea($pos->asPosition(), 2);
                    break;
            }

        } else {
            $sender->sendMessage('You can use this in game');
        }
        return true;
    }
}

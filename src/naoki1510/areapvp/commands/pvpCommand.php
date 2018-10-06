<?php

namespace naoki1510\areapvp\commands;

use naoki1510\areapvp\AreaPvP;
use naoki1510\areapvp\team\TeamManager;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\command\Command;
use pocketmine\command\CommandEnumValues;
use pocketmine\command\CommandSender;
use pocketmine\network\mcpe\protocol\types\CommandEnum;
use pocketmine\network\mcpe\protocol\types\CommandParameter;

class pvpCommand extends Command
{

    public function __construct(TeamManager $teamManager)
    {
        parent::__construct(
            'pvp',
            AreaPvP::translate('commands.pvp.description'),
            AreaPvP::translate('commands.pvp.usage'),
            [],
            [[
                new CommandParameter("sub-command", CommandParameter::ARG_TYPE_STRING, false, new CommandEnum("sub-command", ['join','leave','setsp'])),
                new CommandParameter("player", CommandParameter::ARG_TYPE_TARGET)
            ]]
        );
        $this->setPermission("areapvp.command.pvp");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if (!$this->testPermission($sender)) {
            return true;
        }
        if ($sender instanceof Player) {

            switch ($args[0] ?? '') {
                /*
                case 'setsp':
                    if (TeamManager::getInstance()->existsTeam($args[1])) {
                        TeamManager::getInstance()->setSpawn($sender->asPosition(), TeamManager::getInstance()->getTeam($args[1]));
                    }
                    
                    if (HotBlock::getInstance()->getGameLevel() === $sender->getLevel()) {
                        if (TeamManager::getInstance()->existsTeam($args[1])) {
                            TeamManager::getInstance()->getTeam($args[1])->setSpawn($sender->asPosition());
                        }
                    }

                    $pos = \implode(',', [$sender->x, $sender->y, $sender->z, $sender->level->getName()]);
                    TeamManager::getInstance()->getTeamConfig()->setNested('respawns.' . $sender->getLevel()->getName() . '.' . $args[1], $pos);
                    TeamManager::getInstance()->getTeamConfig()->save();
                    break: */

                default:
                    if (empty(TeamManager::getInstance()->getTeamOf($sender))) {
                        TeamManager::getInstance()->joinTeam($sender);
                    } else {
                        TeamManager::getInstance()->leaveTeam($sender);
                    }
                    break;
            }

        } else {
            $sender->sendMessage('You can use this in game');
        }
        return true;
    }
}

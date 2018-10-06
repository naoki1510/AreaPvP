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

class setspCommand extends Command
{
    /** @var AreaPvP */
    private $AreaPvP;

    /** @var TeamManager */
    private $TeamManager;

    public function __construct(AreaPvP $AreaPvP, TeamManager $teamManager)
    {
        $teams = [];
        foreach ($teamManager->getAllTeams() as $teamname => $team) {
            array_push($teams, $team->getName());
        }
        parent::__construct(
            'setsp',
            AreaPvP::translate('commands.setsp.description'),
            AreaPvP::translate('commands.setsp.usage'),
            [],
            [[
                new CommandParameter("Teams", CommandParameter::ARG_TYPE_STRING, true, new CommandEnum("Teams", $teams)),
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
            if (TeamManager::getInstance()->existsTeam($args[0])) {
                TeamManager::getInstance()->setSpawn($sender->asPosition(), TeamManager::getInstance()->getTeam($args[0]));
                $sender->sendMessage(TeamManager::getInstance()->getTeam($args[0])->getName() . "'s respawn point was set to " . implode(', ', [$sender->x, $sender->y, $sender->z]));
            }

        } else {
            $sender->sendMessage('You can use this in game');
        }
        return true;
    }
}

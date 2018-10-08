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
    /** @var AreaPvP */
    public $AreaPvP;
    
    /** @var TeamManager */
    public $TeamManager;

    public function __construct(AreaPvP $areapvp, TeamManager $teamManager)
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

        $this->TeamManager = $teamManager;
        $this->AreaPvP = $areapvp;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if (!$this->testPermission($sender)) {
            return true;
        }

        switch ($args[0] ?? '') {
            
            case 'info':
                $sender->sendMessage("GameCount : " . $this->AreaPvP->getGameTask()->getCount());
                foreach ($this->TeamManager->getAllTeams() as $team) {
                    $playernames = [];
                    foreach ($team->getAllPlayers() as $playername => $player) {
                        $playernames += [$playername];
                    }
                    $sender->sendMessage('§' . $team->getColor('text') . $team->getName() . ' (' . $team->getPlayerCount() . ")\n" . implode("\n", $playernames));
                }
                break;
            
            default:
                if ($sender instanceof Player) {
                    if (empty($this->TeamManager->getTeamOf($sender))) {
                        $this->TeamManager->joinTeam($sender);
                    } else {
                        $this->TeamManager->leaveTeam($sender);
                    }
                    break;
                } else {
                    $sender->sendMessage('You can use this in game');
                }
        }

        
        return true;
    }
}

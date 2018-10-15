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
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if (!$this->testPermission($sender)) {
            return true;
        }
        if ($sender instanceof Player) {
            $blockUnderPlayer = $sender->getLevel()->getBlock($sender->subtract(0, 0.5))->getId() ? $sender->getLevel()->getBlock($sender->subtract(0, 0.5)) : $sender->getLevel()->getBlock($sender->subtract(0, 1.5));
            $this->changeBlock($sender->getLevel(), $blockUnderPlayer, $args[0] ?? 0, true);

        } else {
            $sender->sendMessage('You can use this in game');
        }
        return true;
    }

    private function changeBlock(Level $level, Block $block, int $meta, bool $back = true, bool $vertical = false)
    {
        //var_dump($block->asVector3(), $back);
        $level->setBlock($block, Block::get($block->getId(), $meta));
        if ($back && ($nblock = $level->getBlock($block->subtract(-1)))->getId() === $block->getId()) {
            $this->changeBlock($level, $nblock, $meta, true);
            return;
        } else if ($back && ($nblock = $level->getBlock($block->subtract(0, 0, -1)))->getId() === $block->getId()) {
            $this->changeBlock($level, $nblock, $meta, true);
            return;
        } else {
            if (($nblock = $level->getBlock($block->subtract(1)))->getId() === $block->getId()) {
                $this->changeBlock($level, $nblock, $meta, false);
            } else if (!$vertical) {
                return;
            }
            if (($nblock = $level->getBlock($block->subtract(0, 0, 1)))->getId() === $block->getId()) {
                $this->changeBlock($level, $nblock, $meta, false, true);
            } else {
                return;
            }
        }
    }
}

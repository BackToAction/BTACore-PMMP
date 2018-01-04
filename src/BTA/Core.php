<?php

namespace BTA;

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\event\Event;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;

use pocketmine\utils\TextFormat as C;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\scheduler\CallbackTask;
use pocketmine\math\Vector3;
use pocketmine\level\sound\EndermanTeleportSound;
use pocketmine\level\sound\Sound;
use pocketmine\level\particle\Particle;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\entity\Entity;
use pocketmine\Inventory;

use pocketmine\item\Item;
use pocketmine\item\ItemBlock;
use pocketmine\block\Block;

use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerIntersectEvent;
use pocketmine\event\entity\EntityDamageEvent;


class Core extends PluginBase implements Listener {

    CONST BEGINNER = "Beginner";
    CONST INTERMEDIATE = "Intermediate";
    CONST ADVANCE = "Advance";
    CONST MASTER = "Master";
    CONST BEYOND = "Beyond";
    CONST HERO = "Hero";
    CONST LEGENDARY = "Legendary";

    public $conf;

    public function onLoad(){
        $this->getLogger()->info("Loading...");

        $this->conf = new Config($this->getDataFolder() . "Config.yml", CONFIG::YAML, array(
            "HubWorld" => "Cynoser",
            "PvPWorld" => "PvpWorld",
        ));
        $this->internal = new Config($this->getDataFolder() . "Internal.yml", CONFIG::YAML, array(
            "SetFoodOnJoinInHub" => 20,
            "SetHealthOnJoinInHub" => 1,
            "SetMaxHealthOnJoinInHub" => 1,
        ));
	    @mkdir($this->getDataFolder()); 
        @mkdir($this->getDataFolder() . "\\players"); 

    }
    public function onEnable(){
        $this->getLogger()->info(C::GREEN . "Enabled.");
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this,"onRun"]), 20);
    }
    public function onRun($tick){
        foreach($this->getServer()->getOnlinePlayers() as $p){
            //$p = $this->getServer()->getPlayer();
            $ign = $p->getName();
            $i = $this->getPlayerDatabase($ign);
            $ph = $this->getPlayerHealth($ign);
            $pc = $this->getPlayerCoins($ign);
            $pr = $this->getPlayerRank($ign);
            $pl = $this->getPlayerLevel($ign);
            $pe = $this->getPlayerEXP($ign);
            $mE = $this->maxEXP($ign);
            $maxPH = $i->get("maxhealth");
            $br = "\n";
            $p->sendTip(C::AQUA." | ".C::GREEN."Name".C::WHITE.": ".C::GOLD.$ign.C::AQUA." | ".C::GREEN."Rank".C::WHITE.": ".C::GOLD.$pr.C::AQUA." | ".C::GREEN."Health".C::WHITE.": ".C::GOLD.$ph.C::RED." / ".C::GOLD.$maxPH.C::AQUA." | ".C::GREEN."Level".C::WHITE.": ".C::GOLD.$pl.C::AQUA." | ".C::GREEN."Exp".C::WHITE.": ".C::GOLD.$pe.C::RED." / ".C::GOLD.$mE.$br.$br.$br.$br.$br);
        }
    }
    public function playerSetup($player){
        if($player->getLevel()->getName() == $this->conf->get("HubWorld")){
            $player->setFood($this->internal->get("SetFoodOnJoinInHub"));
            $player->setHealth($this->internal->get("SetHealthOnJoinInHub"));
            $player->setMaxHealth($this->internal->get("SetMaxHealthOnJoinInHub"));
            $player->setAllowFlight(false);
        }
    }
    public function onJoin(PlayerJoinEvent $e){
        $p = $e->getPlayer();
        if($p->getLevel()->getName() == $this->conf->get("HubWorld")){
            $e->setJoinMessage("");
            $hublevel = $this->conf->get("HubWorld");
            $hublvl = Server::getInstance()->getLevelByName($hublevel);
            $hubx = $this->getServer()->getLevelByName($hublevel)->getSafeSpawn()->getX();
            $huby = $this->getServer()->getLevelByName($hublevel)->getSafeSpawn()->getY() + 1.3;
            $hubz = $this->getServer()->getLevelByName($hublevel)->getSafeSpawn()->getZ();
            $p->teleport(new Vector3($hubx, $huby, $hubz, $hublvl));
            $p->getLevel()->addSound(new EndermanTeleportSound($p));
            $this->playerSetup($p);
            $this->updateTag($p);
            $this->getPlayerDatabase($p->getName());
            $this->addEXP($p->getName(), 0);// WHUD??
        }
    }
    public function rankArray(){
        return array(self::BEGINNER, self::INTERMEDIATE, self::ADVANCE, self::MASTER, self::BEYOND, self::HERO, self::LEGENDARY);
    }
    public function convertRank($rank){
            if($rank == strtolower(self::BEGINNER)){
                return self::BEGINNER;
            }elseif($rank == strtolower(self::INTERMEDIATE)){
                return self::INTERMEDIATE;
            }elseif($rank == strtolower(self::ADVANCE)){
                return self::ADVANCE;
            }elseif($rank == strtolower(self::MASTER)){
                return self::MASTER;
            }elseif($rank == strtolower(self::BEYOND)){
                return self::BEYOND;
            }elseif($rank == strtolower(self::HERO)){
                return self::HERO;
            }elseif($rank == strtolower(self::LEGENDARY)){
                return self::LEGENDARY;
            }else{
            return 0;
        }
    }
    public function updateTag($player){
        $ign = $player->getName();
        $i = $this->getPlayerDatabase($ign);
        $ranks = $i->get("rank");
        $rank = $this->convertRank($ranks);
        if($rank == self::BEGINNER){
            $player->setNameTag(C::GOLD . self::BEGINNER . C::WHITE . ": " . C::GRAY . $ign);
        }elseif($rank == self::INTERMEDIATE){
            $player->setNameTag(C::GOLD . self::INTERMEDIATE . C::WHITE . ": " . C::GRAY . $ign);
        }elseif($rank == self::ADVANCE){
            $player->setNameTag(C::GOLD . self::ADVANCE . C::WHITE . ": " . C::GRAY . $ign);
        }elseif($rank == self::MASTER){
            $player->setNameTag(C::GOLD . self::MASTER . C::WHITE . ": " . C::GRAY . $ign);
        }elseif($rank == self::BEYOND){
            $player->setNameTag(C::GOLD . self::BEYOND . C::WHITE . ": " . C::GRAY . $ign);
        }elseif($rank == self::HERO){
            $player->setNameTag(C::GOLD . self::HERO . C::WHITE . ": " . C::GRAY . $ign);
        }elseif($rank == self::LEGENDARY){
            $player->setNameTag(C::GOLD . self::LEGENDARY . C::WHITE . ": " . C::GRAY . $ign);
        }
    }
    public function updateTagOnChat(PlayerChatEvent $e){
        $p = $e->getPlayer();
        $msg = $e->getMessage();
        $i = $this->getPlayerDatabase($p->getName());
        $ranks = $i->get("rank");
        $getrank = $this->convertRank($ranks);
        if($getrank == self::BEGINNER){
            $e->setFormat(C::GOLD . self::BEGINNER . C::WHITE . ": " . C::GRAY . $p->getName() . C::RESET . C::GREEN . " >> " . C::YELLOW . $msg);
        }elseif($getrank == self::INTERMEDIATE){
            $e->setFormat(C::GOLD . self::INTERMEDIATE . C::WHITE . ": " . C::GRAY . $p->getName() . C::RESET . C::GREEN . " >> " . C::YELLOW . $msg);
        }elseif($getrank == self::ADVANCE){
            $e->setFormat(C::GOLD . self::ADVANCE . C::WHITE . ": " . C::GRAY . $p->getName() . C::RESET . C::GREEN . " >> " . C::YELLOW . $msg);
        }elseif($getrank == self::MASTER){
            $e->setFormat(C::GOLD . self::MASTER . C::WHITE . ": " . C::GRAY . $p->getName() . C::RESET . C::GREEN . " >> " . C::YELLOW . $msg);
        }elseif($getrank == self::BEYOND){
            $e->setFormat(C::GOLD . self::BEYOND . C::WHITE . ": " . C::GRAY . $p->getName() . C::RESET . C::GREEN . " >> " . C::YELLOW . $msg);
         }elseif($getrank == self::HERO){
         $e->setFormat(C::GOLD . self::HERO . C::WHITE . ": " . C::GRAY . $p->getName() . C::RESET . C::GREEN . " >> " . C::YELLOW . $msg);
        }elseif($getrank == self::LEGENDARY){
            $e->setFormat(C::GOLD . self::LEGENDARY . C::WHITE . ": " . C::GRAY . $p->getName() . C::RESET . C::GREEN . " >> " . C::YELLOW . $msg);
        }
    }

    // Player Database
    // Fast Code [ Might Have Bug (WHUD?!?!!!?!?!?!?!?) ]

    public function getPlayerDatabase($player){ // PlayerDatabase
        $result = new Config($this->getDataFolder() . "\\players\\" . strtolower($player) . ".yml", CONFIG::YAML, array(
            "level" => 1,
            "exp" => 0,
            "maxhealth" => 100,
            "health" => 100,
            "coins" => 0,
            "rank" => "beginner",
        ));
        return $result;
    }

    // Health API
    public function getPlayerHealth($player){
        $i = $this->getPlayerDatabase($player);
        $result = $i->get("health");
        return $result;
    }
    public function setNewHealth($level){
        $mtt = 3773;
        if($level > 0 && $level <= 200){
            return $level + $mtt;
        }else{
            return 0;
        }
    }
    public function updateHealth($player){
        $i = $this->getPlayerDatabase($player);
        $ll = $this->getPlayerLevel($player);
        $h = $this->getPlayerHealth($player);
        $mxh = $i->get("maxhealth");
        if($h >= $mxh){
            $i->set("health", $mxh);
            $i->save();
        }
    }
    public function addHealth($player, $addhealth){
        $i = $this->getPlayerDatabase($player);
        $mxh = $i->get("maxhealth");
        $h = $i->get("health");
        $cal = $h + $addhealth;
        if($addhealth > $mxh || $cal > $mxh){
            $this->updateHealth($player);
        }else{
            $i->set("health", $h + $mxh);
            $i->save();
        }
    }
    public function minusHealth($player, $dmg){
        $i = $this->getPlayerDatabase($player);
        $maxh = $i->get("maxhealth");
        $h = $i->get("health");
        if($h >= 1){
            if($dmg > $maxh || $dmg > $h){
                $i->set("health", 0);
                $i->save();
            }elseif($dmg < $h){
                $i->set("health", $h - $dmg);
                $i->save();
            }
        }
    }

    // RANK [ API ]
    public function getPlayerRank($player){
        $i = $this->getPlayerDatabase($player);
        $rnk = $i->get("rank");
        $rank = $this->convertRank($rnk);
        return $rank;
    }
    public function setPlayerRank($player, $rank){
        $i = $this->getPlayerDatabase($player);
        $i->set("rank", strtolower($rank));
        $i->save();
        }
    public function updatePlayerRank($player){
        $i = $this->getPlayerDatabase($player);
        $getLevel = $i->get("level");
        if($getLevel == 25){
            $i->set("rank", strtolower(self::INTERMEDIATE));
            $i->save();
        }elseif($getLevel == 50){
            $i->set("rank", strtolower(self::ADVANCE));
            $i->save();
        }elseif($getLevel == 75){
            $i->set("rank", strtolower(self::MASTER));
            $i->save();
        }elseif($getLevel == 100){
            $i->set("rank", strtolower(self::BEYOND));
            $i->save();
        }elseif($getLevel == 150){
            $i->set("rank", strtolower(self::HERO));
            $i->save();
        }elseif($getLevel == 200){
            $i->set("rank", strtolower(self::LEGENDARY));
            $i->save();
        }else{
            return 0;
        }
    }
    // Level [ API ]
    public function setNextExpForEachLevel($level){
        $mlty = 4325;//861
        if($level > 0 && $level <= 200){
            return $level * $mlty;
        }else{
            return 0;
        }
    }
    public function getPlayerLevel($player){
        $i = $this->getPlayerDatabase($player);
        $result = $i->get("level");
        return $result;
    }
    public function getPlayerEXP($player){
        $i = $this->getPlayerDatabase($player);
        $gtPL = $this->getPlayerLevel($player);
        $gCE = $i->get("exp");
        $gNE = $this->setNextExpForEachLevel($gtPL);
        return $gCE;
    }
    public function maxEXP($player){
        $i = $this->getPlayerDatabase($player);
        $gtPL = $this->getPlayerLevel($player);
        $gCE = $i->get("exp");
        $gNE = $this->setNextExpForEachLevel($gtPL);
        return $gNE;
    }
	public function levelup($player) {
		$i = $this->getPlayerDatabase($player);
		$lvel = $i->get("level");
		$lvl = $lvel + 1;
		$i->set("level", $lvl);
		$i->set("coins", $i->get("coins") + $this->sendCoinsOnLvLUP($player));
        $i->save();
        $nlvl = $i->get("level");
        $i->set("maxhealth", $this->setNewHealth($nlvl));
        $this->updateHealth($player);
        $this->updatePlayerRank($player);
		$i->save();
		$player = $this->getServer()->getPlayerExact($player);
	}
    public function addEXP($player, $exp){
        $i = $this->getPlayerDatabase($player);
        $exps = $i->get("exp");
        $lvl = $i->get("level");
        if($lvl <= 0 || $lvl >= 200){
            return;
        }
        if($exps + $exp >= $this->setNextExpForEachLevel($lvl)){
            $exps = $exps + $exp - $this->setNextExpForEachLevel($lvl);
            $i->set("exp", $exps);
            $i->save();
            $this->levelup($player);
        }else{
            $i->set("exp", $exps + $exp);
            $i->save();
        }
    }

    // Coins [ API ]
    public function getPlayerCoins($player){
        $i = $this->getPlayerDatabase($player);
        $result = $i->get("health");
        return $result;
    }
    public function sendCoinsOnLvLUP($player){
        $i = $this->getPlayerDatabase($player);
        $level = $i->get("level");
        return $level * 7;
    }
    public function minusCoins($player, $coinreducer){
        $i = $this->getPlayerDatabase($player);
        $getcoins = $i->get("coins");
        if($getcoins >= 1){
            if($coinreducer > $coins){
                return "Not Enough Coins";
            }else{
                $i->set("coins", $getcoins - $coinreducer);
                $i->save();
            }
        }
    }
    public function addCoins($player, $coinadd){
        $i = $this->getPlayerDatabase($player);
        $getcoins = $i->get("coins");
        if($coinadd >= 1){
            $i->set("coins", $getcoins + $coinadd);
            $i->save();
        }
    }

    // Event [ aPI ]
    public function playerDeadEvent($player){
        $i = $this->getPlayerDatabase($player->getName());
        $getmaxh = $i->get("maxhealth");
        $geth = $i->get("health");
        if($geth <= 0){
            $player = $this->getServer()->getPlayer();
            if($player->getLevel()->getName() == $this->conf->get("PvPWorld")){
                $ii = $this->conf->get("PvPWorld");
                $levl = Server::getInstance()->getLevelByName($ii);
                $xget = $this->getServer()->getLevelByName($ii)->getSafeSpawn()->getX();
                $yget = $this->getServer()->getLevelByName($ii)->getSafeSpawn()->getY() + 1.3;
                $zget = $this->getServer()->getLevelByName($ii)->getSafeSpawn()->getZ();
                $player->teleport(new Vector3($xget, $yget, $zget, $levl));
                $player->getLevel()->addSound(new EndermanTeleportSound($player));
                $player->getInventory()->clearAll();
                $player->sendMessage(C::DARK_RED ."You Dead....");
                $i->set("health", $getmaxh);
            }elseif($player->getLevel()->getName() == $this->conf->get("HubWorld")){
                $u = $this->conf->get("HubWorld");
                $uu = Server::getInstance()->getLevelByName($u);
                $ux = $this->getServer()->getLevelByName($u)->getSafeSpawn()->getX();
                $uy = $this->getServer()->getLevelByName($u)->getSafeSpawn()->getY() + 1.3;
                $uz = $this->getServer()->getLevelByName($u)->getSafeSpawn()->getZ();
                $player->teleport(new Vector3($ux, $uy, $uz, $u));
                $player->getLevel()->addSound(new EndermanTeleportSound($player));
                $player->getInventory()->clearAll();
                $player->sendMessage(C::DARK_RED ."You Dead....");
                $this->updateHealth($player->getName());
            }
        }
    }
	public function PlayerKillEvent(PlayerDeathEvent $event){
			$player = $event->getEntity();
            if ($player instanceof Player){
				$cause = $player->getLastDamageCause();
                if($cause instanceof EntityDamageByEntityEvent){
					$damager = $cause->getDamager();
					if($damager instanceof Player){
						$PlayerKiller = mt_rand(5, 150);
						$this->addEXP($damager->getName(), $PlayerKiller);
					}
				}
			}
		}
    public function minusHealthEvent(EntityDamageEvent $e){
        $p = $e->getEntity();
        $e->getCancelled(true);
        $lvle = $p->getLevel(); 
        foreach($this->getServer()->getOnlinePlayers() as $p){
            $cause = $e->getCause();
            if($cause instanceof EntityDamageByEntityEvent){
                $dmager = $cause->getDamager();
                $dmg = $dmager->getDamage();
                $this->minusHealth($p->getName(), $dmg);
            }
        }
    }
    /*
	public function PVP(EntityDamageEvent $event) {
		$cause = $event->getCause();
		if($cause instanceof EntityDamageByEntityEvent){
		$damager = $event->getDamager();// Player Or Entity
		$bdamage = $event->getEntity();
		$item = $damager->getInventory()->getItemInHand();
		$id = $item->getId();
		$damage = $item->getDamage();

		$i = $this->getPlayerDatabase($damager->getName());
		//$teamm = $config->get("teamm");
		//$lover = $config->get("lover");
		$hdamage = $event->getDamage();
		if($damager instanceof Player && $bdamager instanceof Entity) {
			if ($bdamager->getHealth() - $hdamage <= 0) {// Entity
                $rnd = mt_rand(10, 50);
				$damager->AddExp($damager->getName(), $rnd);
			}
		}elseif($damager instanceof Player && $bdamager instanceof Player) {
            $ii = $this->getPlayerDatabase($bdamager->getName());
            $bdamagerH = $ii->get("health");
            $mxbH = $ii->get("health");
            $damagerH = $i->get("health");
			if ($bdamagerH - $hdamage <= 0 || $hdamage >= $mxbH) {
                $rndd = mt_rand(20, 70);
				$this->addExp($damager->getName(), $rndd);
                $this->playerDeadEvent($bdamager->getName());
			}elseif($hdamage <= $bdamagerH){
                $this->minusHealth($bdamager->getName());
			}
		}
	}
    }
*/





}

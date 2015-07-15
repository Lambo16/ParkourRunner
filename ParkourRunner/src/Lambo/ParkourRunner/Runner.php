<?php

namespace Lambo\ParkourRunner;

use Lambo\ParkourRunner\Map;

use pocketmine\plugin\PluginBase;
use pocketmine\Server;

use pocketmine\utils\Config;

use pocketmine\math\Vector3;
use pocketmine\level\Level;
use pocketmine\level\Position;

use pocketmine\command\CommandExecutor;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\event\Event;
use pocketmine\event\EventPriority;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginManager;
use pocketmine\plugin\PluginLogger;

use pocketmine\event\player\PlayerMoveEvent;

class Runner extends PluginBase implements Listener,CommandExecutor{

    public static $mysql;
    private $arenas=array();
    private $arenaconf;
    private $activeplayers=array();
    private $race=array();

    public function onEnable(){
        $this->getServer()->getLogger()->info("ParkourRunner enabled");
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveDefaultConfig();
        $this->saveResource("arenas.yml");
        $this->saveResource("mysql.yml");
        $this->arenaconf = new Config($this->getDataFolder()."arenas.yml");
        $arenas = (new Config($this->getDataFolder()."arenas.yml"))->getAll();
        $mysql = (new Config($this->getDataFolder()."mysql.yml"));
        self::$mysql = new \mysqli($mysql->get("host"), $mysql->get("user"), $mysql->get("password"), $mysql->get("database"), $mysql->get("port"));
        
        if(self::$mysql->connect_error){
            $this->getLogger()->critical("Cannot connect to MySQL database: ".self::$mysql->connect_error);
        }else{
            $this->getLogger()->info("Connected to MySQL database.");
            self::$mysql->query("CREATE TABLE IF NOT EXISTS ParkourRunner (
            username VARCHAR(32),
            map VARCHAR(64),
            highscore FLOAT
            )");
        }
        foreach($arenas as $name=>$info){
            $level = $this->getServer()->getLevelByName($info['level']);
            $checkpoints = array();
            if(isset($info['checkpoints'])){
                foreach($info['checkpoints'] as $checkpoint=>$cinfo){
                    $checkpoints[(int)$checkpoint] = array("yaw"=>$cinfo['yaw'], "position"=>new Position($cinfo['x'],$cinfo['y'],$cinfo['z'],$level));
                }
            }
            $this->arenas[strtolower($name)] = new Map($name, $info["map-maker"], $info["date-of-creation"], $level, $info['floor-y'], new Position($info['timer-block']['x'],$info['timer-block']['y'],$info['timer-block']['z'],$level),$info['start-position']['yaw'],new Position($info['start-position']['x'],$info['start-position']['y'],$info['start-position']['z'],$level),new Position($info['end-block']['x'],$info['end-block']['y'],$info['end-block']['z'],$level),$checkpoints);
            $this->getLogger()->info("§cMap §b'".$name."'§c has loaded.");
        }
    }

    public function onDisable(){
        $this->getServer()->getLogger()->info("ParkourRunner disabled");
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args){
        $this->getLogger()->info("test");
            if($command->getName() == "pk"){
                if($args[0]=="join"){
                    if(isset($args[1])){
                        if(isset($this->arenas[strtolower($args[1])]) and substr(strtolower($args[1]), -5) != "_race"){
                            if(!isset($this->activeplayers[$sender->getName()])){
                                $this->activeplayers[$sender->getName()] = array("current-map"=>strtolower($args[1]),"last-set"=>0,"micro-seconds"=>0,"active"=>false,"current-checkpoint"=>0);
                                $sender->teleport($this->arenas[strtolower($args[1])]->getStartPosition());
                                $sender->setRotation($this->arenas[strtolower($args[1])]->getStartYaw(), $sender->getPitch());
                                $sender->sendMessage("§cYou have started the map '§b".$this->arenas[strtolower($args[1])]->getMapName()."§c'.");
                            }else{
                                $sender->sendMessage("§cYou are currently still playing a different map\n§cUse §b/pk leave§c to leave this map.");
                            }
                        }else{
                            $sender->sendMessage("§cThe map '§b".strtolower($args[1])."§c' doesn't exist.");
                        }
                    }else{
                        $sender->sendMessage("§cUsage: §b/pk join [map name]");
                    }
                }else
                if($args[0]=="leave"){
                    if(isset($this->activeplayers[$sender->getName()])){
                        unset($this->activeplayers[$sender->getName()]);
                        $sender->sendMessage("§cYou have left your current map.");
                    }else{
                        $sender->sendMessage("§cYou aren't currently playing any maps.");
                    }
                }else
                if($args[0]=="info"){
                    if(isset($args[1])){
                        if(isset($this->arenas[strtolower($args[1])])){
                            $map = $this->arenas[strtolower($args[1])];
                            $currentlyplaying = array();
                            foreach($this->activeplayers as $player=>$name){
                                if($name['current-map'] == strtolower($args[1])){
                                    array_push($currentlyplaying, $player);
                                }
                            }
                            $sender->sendMessage("§cMap info:\n§cMap name: §b".$map->getMapName()."\n§cMap maker: §b".$map->getMapMaker()."\n§cDate of creation: §b".$map->getDateOfCreation()."\n§cMap world name: §b".$map->getMapLevel()->getName()."\n§cYour current highscore: §b".($map->getTime($sender) === null ? "none" : $map->getTime($sender))."\n§cCurrently playing: §b".implode(", ",$currentlyplaying)."\n§cFor leaderboards, please type §b/pk topten ".strtolower($args[1]));
                        }else{
                            $sender->sendMessage("§cThis map doesn't exist.");
                        }
                    }else{
                        $sender->sendMessage("§cUsage: §b/pk info [map name]");
                    }
                }else
                if($args[0]=="topten"){
                    if(isset($args[1])){
                        if(isset($this->arenas[strtolower($args[1])])){
                            $sender->sendMessage("§cTop ten laps of the map §b".$this->arenas[strtolower($args[1])]->getMapName()."§c:");
                            $count = 0;
                            foreach($this->arenas[strtolower($args[1])]->getTopTen() as $player){
                                $count++;
                                $sender->sendMessage("§c".$count.") §b".$player['username']."§c with §b".$player['highscore']);
                            }
                        }else{
                            $sender->sendMessage("§cThis map doesn't exist.");
                        }
                    }else{
                        $sender->sendMessage("§cUsage: §b/pk topten [map name]");
                    }
                }
                if($args[0]=="create"){
                    if(isset($args[1])){
                        $this->arenaconf->set($args[1], array("date-of-creation"=>date("d/m/Y"),"map-maker"=>$sender->getName(),"level"=>$sender->getLevel()->getName(),"floor-y"=>0,"start-position"=>array("x"=>0,"y"=>0,"z"=>0,"yaw"=>360),"timer-block"=>array("x"=>0,"y"=>0,"z"=>0),"end-block"=>array("x"=>0,"y"=>0,"z"=>0)));
                        $sender->sendMessage("§cNew map '§b".$args[1]."§c' has been created.\n§cPlease use the follwing command to set the start position:\n§b/pk setstart ".strtolower($args[1]));
                    }else{
                        $sender->sendMessage("§cUsage: §b/pk create [map name]");
                    }
                }else
                if($args[0]=="setstart"){
                    if(isset($args[1])){
                        if($this->arenaconf->exists($args[1])){
                            $newmap = $this->arenaconf->get($args[1]);
                            $newmap['start-position'] = array("x"=>$sender->getFloorX(),"y"=>$sender->getFloorY(),"z"=>$sender->getFloorZ(),$sender->getYaw());
                            $this->arenaconf->set($args[1],$newmap);
                            $sender->sendMessage("§cAwesome! Now use the follwing command to set the floor that will reset you once you fall below/on it.\n§b/pk setfloor ".strtolower($args[1]));
                        }else{
                            $sender->sendMessage("§cThis map doesn't exist.");
                        }
                    }else{
                        $sender->sendMessage("§cUsage: §b/pk setfloor [map name]");
                    }
                }else
                if($args[0]=="setfloor"){
                    if(isset($args[1])){
                        if($this->arenaconf->exists($args[1])){
                            $newmap = $this->arenaconf->get($args[1]);
                            $newmap['floor-y'] = $sender->getFloorY();
                            $this->arenaconf->set($args[1],$newmap);
                            $sender->sendMessage("§cGreat! Now use the follwing command to set the timer start position.\n§b/pk settimer ".strtolower($args[1]));
                        }else{
                            $sender->sendMessage("§cThis map doesn't exist.");
                        }
                    }else{
                        $sender->sendMessage("§cUsage: §b/pk setfloor [map name]");
                    }
                }else
                if($args[0]=="settimer"){
                    if(isset($args[1])){
                        if($this->arenaconf->exists($args[1])){
                            $newmap = $this->arenaconf->get($args[1]);
                            $newmap['timer-block'] = array("x"=>$sender->getFloorX(),"y"=>$sender->getFloorY(),"z"=>$sender->getFloorZ());
                            $this->arenaconf->set($args[1],$newmap);
                            $sender->sendMessage("§cAlmost there! Use the following command to set the end of the map.\n§b/pk setend ".strtolower($args[1])."\n§cAfter that use §b/pk setcheckpoint ".strtolower($args[1])." §cfor every checkpoint you would like to set.");
                        }else{
                            $sender->sendMessage("§cThis map doesn't exist.");
                        }
                    }else{
                        $sender->sendMessage("§cUsage: §b/pk settimer [map name]");
                    }
                }else
                if($args[0]=="setend"){
                    if(isset($args[1])){
                        if($this->arenaconf->exists($args[1])){
                            $newmap = $this->arenaconf->get($args[1]);
                            $newmap['end-block'] = array("x"=>$sender->getFloorX(),"y"=>$sender->getFloorY(),"z"=>$sender->getFloorZ());
                            $this->arenaconf->set($args[1],$newmap);
                            $this->arenaconf->save();
                            $sender->sendMessage("§cAwesome! Your new map is now ready to use.\n§cRestart the server to use the new map.\n§c(Refreshing maps would cause too much confusion)");
                        }else{
                            $sender->sendMessage("§cThis map doesn't exist.");
                        }
                    }else{
                        $sender->sendMessage("§cUsage: §b/pk setend [map name]");
                    }
                }else
                if($args[0]=="setcheckpoint"){
                    if(isset($args[1])){
                        if($this->arenaconf->exists($args[1])){
                            $checkpoints = $this->arenaconf->get($args[1]);
                            $checkpoints['checkpoints'][(count($checkpoints['checkpoints']) + 1)] = array("yaw"=>$sender->getYaw(),"x"=>$sender->getFloorX(),"y"=>$sender->getFloorY(),"z"=>$sender->getFloorZ());
                            $this->arenaconf->set($args[1],$checkpoints);
                            $this->arenaconf->save();
                            $sender->sendMessage("§cNew checkpoint set. Restart the server to apply the new changes.");
                        }else{
                            $sender->sendMessage("§cThis map doesn't exist.");
                        }
                    }else{
                        $sender->sendMessage("§cUsage: §b/pk setcheckpoint [map name]");
                    }
                }
            }
        return true;
    }

    public function PlayerMoveEvent(PlayerMoveEvent $event){
        $player = $event->getPlayer();
        if(isset($this->activeplayers[$player->getName()])){
            if($player->getLevel() === $this->arenas[$this->activeplayers[$player->getName()]['current-map']]->getMapLevel()){
                $map = $this->arenas[$this->activeplayers[$player->getName()]['current-map']];
                $pos = $this->getPlayerPos($player);
                $checkpoints = $map->getCheckpoints();
                if($pos->equals($map->getStartBlock(),true)){
                    if((time() - $this->activeplayers[$player->getName()]['last-set']) > 2){
                        $this->activeplayers[$player->getName()]['current-checkpoint'] = 0;
                        $this->activeplayers[$player->getName()]['last-set'] = time();
                        $this->activeplayers[$player->getName()]['micro-seconds'] = round(microtime(true) * 1000);
                        $this->activeplayers[$player->getName()]['active'] = true;
                        $player->sendMessage("§cTimer reset.");
                    }
                }
                if($player->getFloorY() <= $map->getFloorY()){
                    if($this->activeplayers[$player->getName()]['current-checkpoint'] == 0){
                        $player->teleport($map->getStartPosition());
                        $player->setRotation($map->getStartYaw(), $player->getPitch());
                        $this->activeplayers[$player->getName()]['active'] = true;
                    }else{
                        $player->teleport($checkpoints[$this->activeplayers[$player->getName()]['current-checkpoint']]['position']);
                        $player->setRotation($checkpoints[$this->activeplayers[$player->getName()]['current-checkpoint']]['yaw'], $player->getPitch());
                    }
                }
                if($this->activeplayers[$player->getName()]['active']){
                    foreach($checkpoints as $checkpoint=>$info){
                        if($pos->equals($info['position'])){
                            if($checkpoint > $this->activeplayers[$player->getName()]['current-checkpoint']){
                                $this->activeplayers[$player->getName()]['current-checkpoint'] = $checkpoint;
                                $player->sendMessage("§cCheckpoint §b".$checkpoint."#");
                            }
                        }
                    }
                    if($this->activeplayers[$player->getName()]['current-checkpoint'] === count($checkpoints)){
                        if($pos->equals($map->getEndPosition(),true)){
                            $time = (round(microtime(true) * 1000) - $this->activeplayers[$player->getName()]['micro-seconds']) / 1000;
                            $player->sendMessage("§cWell done! You have completed the map in §b".$time."§c seconds!");
                            if($map->getTime($player) == null){
                                $map->setTime($player, $time);
                            }else
                            if($map->getTime($player) > $time){
                                $player->sendMessage("§cYou have beaten your old record of §b".$map->getTime($player)."§c!");
                                $map->setTime($player, $time);
                            }
                            $player->teleport($map->getStartPosition());
                            $this->activeplayers[$player->getName()]['current-checkpoint'] = 0;    
                            $this->activeplayers[$player->getName()]['last-set'] = time();
                            $this->activeplayers[$player->getName()]['micro-seconds'] = round(microtime(true) * 1000);
                            $this->activeplayers[$player->getName()]['active'] = false;
                        }
                    }
                }
            }
        }
    }

    public function comparePositions($pos1,$pos2){
        if($pos1->getFloorX() == $pos2->getFloorX() and $pos1->getFloorY() == $pos2->getFloorY() and $pos1->getFloorZ() == $pos2->getFloorZ()){
            return true;
        }else{
            return false;
        }
    }

    public function getPlayerPos($player){
        return (new Position($player->getFloorX(),$player->getFloorY(),$player->getFloorZ(),$player->getLevel()));
    }
}

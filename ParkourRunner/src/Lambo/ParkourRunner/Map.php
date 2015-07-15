<?php

namespace Lambo\ParkourRunner;

use Lambo\ParkourRunner\Runner;

use pocketmine\Player;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\block\Block;

class Map extends Runner{
	
	private $name;
	private $mapmaker;
	private $date;
	private $level;
	private $floory;
	private $startblock;
	private $startpos;
	private $startyaw;
	private $endpos;
	private $checkpoints;

	public function __construct($name, $mapmaker, $date, Level $level, $floory, Position $startblock, $startyaw, Position $startpos, Position $endpos, array $checkpoints){
		$this->name = $name;
		$this->mapmaker = $mapmaker;
		$this->date = $date;
		$this->startyaw = $startyaw;
		$this->level = $level;
		$this->floory = $floory;
		$this->startblock = $startblock;
		$this->startpos = $startpos;
		$this->endpos = $endpos;
		$this->checkpoints = $checkpoints;
	}

	public function getMapName(){
		return $this->name;
	}

	public function getStartYaw(){
		return $this->startyaw;
	}

	public function getMapLevel(){
		return $this->level;
	}

	public function getMapMaker(){
		return $this->mapmaker;
	}

	public function getFloorY(){
		return $this->floory;
	}

	public function getDateOfCreation(){
		return $this->date;
	}

	public function getStartPosition(){
		return $this->startpos;
	}

	public function getEndPosition(){
		return $this->endpos;
	}

	public function getStartBlock(){
		return $this->startblock;
	}

	public function getCheckpoints(){
		return $this->checkpoints;
	}

	public function setTime(Player $player, $time){
		if(Runner::$mysql->query("SELECT * FROM ParkourRunner WHERE username = '".$player->getName()."';")->num_rows == 0){
			Runner::$mysql->query("INSERT INTO ParkourRunner
				VALUES('".$player->getName()."', '".$this->getMapName()."', ".$time.");");
		}else $query = Runner::$mysql->query("UPDATE ParkourRunner SET highscore = ".$time." WHERE username = '".$player->getName()."' AND map = '".$this->getMapName()."';");
	}

	public function getTime(Player $player){
		if(Runner::$mysql->query("SELECT * FROM ParkourRunner WHERE username = '".$player->getName()."';")->num_rows == 0){
			return null;
		}else{
			$query = Runner::$mysql->query("SELECT * FROM ParkourRunner WHERE username = '".$player->getName()."' AND map = '".$this->getMapName()."';");
			return (float)$query->fetch_assoc()['highscore'];
		}
	}

	public function getTopTen(){
		$query = Runner::$mysql->query("SELECT * FROM ParkourRunner WHERE map = '".$this->getMapName()."' ORDER BY highscore ASC LIMIT 10;");
		$topten = array();
		for($i = 0; $i<10; $i++){
			while($row = $query->fetch_assoc()){
				$topten[$i] = array("username"=>$row['username'],"highscore"=>$row['highscore']);
			}
		}
		return $topten;
	}

	public function getBestPlayer(){
		return $this->getTopTen()[1];
	}
}

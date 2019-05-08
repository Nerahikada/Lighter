<?php

declare(strict_types=1);

namespace Nerahikada\Lighter;

use pocketmine\block\Block;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\Listener;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase implements Listener{

	const LIGHT_VALUE = 1;
	const CHARGE_VALUE = 2;
	const MAX_FUEL = 200;
	const BAR_COUNT = 30;

	/** @var bool[] */
	private $light = [];

	/** @var int[] */
	private $fuel = [];

	public function onEnable() : void{
		// イベントを使うときは必須！
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		// スケジューラー
		$this->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this, "mainTick"]), 1);
	}

	// 1tick毎に呼び出される関数
	public function mainTick() : void{
		foreach($this->getServer()->getOnlinePlayers() as $player){
			$name = $player->getName();

			if(!isset($this->light[$name])){
				continue;
			}

			// ライターの燃料の処理
			if($this->light[$name]){
				$this->fuel[$name] -= self::LIGHT_VALUE;
				if($this->fuel[$name] < 0){
					$this->fuel[$name] = 0;
				}
				if($this->fuel[$name] === 0){
					$this->useLighter($player, false);
				}
			}

			// ライターを充電する処理
			if($player->getLevel()->getBlock($player, true, false)->getId() === Block::TORCH){
				$this->fuel[$name] += self::CHARGE_VALUE;
				if($this->fuel[$name] > self::MAX_FUEL){
					$this->fuel[$name] = self::MAX_FUEL;
				}
			}

			// 燃料が足りないメッセージ
			if($this->fuel[$name] === 0){
				$player->addTitle("§l⚠燃料が足りません⚠", "", 0, 20, 1);
			}

			// 燃料バーの処理
			$percent = $this->fuel[$name] / self::MAX_FUEL;
			if($percent < 0) $percent = 0;
			if($percent > 1) $percent = 1;
			$progress = (int) round(self::BAR_COUNT * $percent);
			//色を付ける
			if($percent >= 0.75) $color = "§e";
			elseif($percent >= 0.45) $color = "§6";
			elseif($percent >= 0.13) $color = "§c";
			else $color = "§4";
			//バーを生成
			$bar = "残りライター燃料: " . $color . str_repeat("⬛", $progress) . "§f" . str_repeat("⬛", self::BAR_COUNT - $progress);
			$player->sendTip($bar);
		}
	}

	public function useLighter(Player $player, ?bool $light = null) : bool{
		$name = $player->getName();
		if($light !== null){
			$this->light[$name] = $light;
		}

		if(!isset($this->light[$name])){
			return false;
		}

		$inventory = $player->getInventory();
		if($this->light[$name]){
			if($this->fuel[$name] > 0){
				// アイテムを操作
				$inventory->removeItem(Item::get(Item::STICK, 0, 1));
				$inventory->addItem(Item::get(Item::BLAZE_ROD, 0, 1));
				// エフェクトを付与
				$player->removeEffect(Effect::BLINDNESS);
				$effect = new EffectInstance(Effect::getEffect(Effect::NIGHT_VISION), INT32_MAX);
				$player->addEffect($effect);
				return true;
			}else{
				$this->light[$name] = false;
				return false;
			}
		}else{
			// アイテムを操作
			$inventory->removeItem(Item::get(Item::BLAZE_ROD, 0, 1));
			$inventory->addItem(Item::get(Item::STICK, 0, 1));
			// エフェクトを付与
			$player->removeEffect(Effect::NIGHT_VISION);
			$effect = new EffectInstance(Effect::getEffect(Effect::BLINDNESS), INT32_MAX);
			$player->addEffect($effect);
			return true;
		}
	}

	// イベント
	public function onLogin(PlayerLoginEvent $event){
		$player = $event->getPlayer();
		$name = $player->getName();
		if(!isset($this->fuel[$name])){
			$this->fuel[$name] = self::MAX_FUEL;
		}
		$this->useLighter($player, false);
	}

	public function onDrop(PlayerDropItemEvent $event){
		$player = $event->getPlayer();
		$name = $player->getName();
		$itemId = $event->getItem()->getId();

		// ON -> OFF
		if($this->light[$name] && $itemId === Item::BLAZE_ROD){
			$event->setCancelled();
			$this->useLighter($player, false);
			return;
		}

		// OFF -> ON
		if(!$this->light[$name] && $itemId === Item::STICK){
			$event->setCancelled();
			$this->useLighter($player, true);
			return;
		}
	}

	public function onRespawn(PlayerRespawnEvent $event){
		$player = $event->getPlayer();
		$this->getScheduler()->scheduleDelayedTask(new CallbackTask([$this, "useLighter"], [$player]), 1);
	}
}

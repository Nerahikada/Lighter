<?php

declare(strict_types=1);

namespace Nerahikada\Lighter;

use pocketmine\scheduler\Task;

class CallbackTask extends Task{

	/** @var callable */
	private $callable;

	/** @var array */
	private $args;

	public function __construct(callable $callable, array $args = []){
		$this->callable = $callable;
		$this->args = $args;
	}

	public function onRun(int $currentTick) : void{
		($this->callable)(...$this->args);
	}
}

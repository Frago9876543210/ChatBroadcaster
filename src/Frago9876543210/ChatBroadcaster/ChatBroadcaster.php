<?php

declare(strict_types=1);

namespace Frago9876543210\ChatBroadcaster;

use Closure;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\snooze\SleeperNotifier;
use pocketmine\utils\Utils;
use const PTHREADS_INHERIT_NONE;

class ChatBroadcaster extends PluginBase implements Listener{
	/** @var self */
	private static $instance;
	/** @var UnixSocketThread */
	private $thread;

	protected function onEnable() : void{
		self::$instance = $this;
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public static function getInstance() : self{
		return self::$instance;
	}

	public function registerChatHandler(Closure $handler, int ...$broadcastPorts) : void{
		Utils::validateCallableSignature(function(string $sender, string $message) : void{}, $handler);

		$sleeper = $this->getServer()->getTickSleeper();
		$notifier = new SleeperNotifier();

		$sleeper->addNotifier($notifier, function() use ($handler): void{
			($handler)($this->thread->sender, $this->thread->message);
			$this->thread->synchronized(function(UnixSocketThread $thread){
				$thread->notify();
			}, $this->thread);
		});

		$this->thread = new UnixSocketThread($notifier, $this->getServer()->getPort(), ...$broadcastPorts);
		$this->thread->start(PTHREADS_INHERIT_NONE);
	}

	/**
	 * @param PlayerChatEvent $e
	 * @priority MONITOR
	 */
	public function onMessage(PlayerChatEvent $e) : void{
		$sender = $e->getPlayer()->getName();
		$message = $e->getMessage();
		$this->thread->queue[] = "$sender\x00$message";
	}

	protected function onDisable() : void{
		if($this->thread !== null){
			$this->thread->close();
		}
	}
}
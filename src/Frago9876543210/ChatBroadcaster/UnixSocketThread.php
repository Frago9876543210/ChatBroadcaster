<?php

declare(strict_types=1);

namespace Frago9876543210\ChatBroadcaster;

use pocketmine\snooze\SleeperNotifier;
use pocketmine\thread\Thread;
use RuntimeException;
use Threaded;
use function array_search;
use function array_values;
use function file_exists;
use function microtime;
use function mkdir;
use function socket_bind;
use function socket_close;
use function socket_create;
use function socket_last_error;
use function socket_recvfrom;
use function socket_select;
use function socket_sendto;
use function socket_set_nonblock;
use function socket_strerror;
use function strlen;
use function trim;
use function unlink;
use const AF_UNIX;
use const SOCK_DGRAM;

class UnixSocketThread extends Thread{
	private const PATH = "/tmp/servers/";
	private const TPS = 20;
	private const TIME_PER_TICK = 1 / self::TPS;

	/** @var int */
	private $port;
	/** @var int[] */
	private $broadcastPorts;
	/** @var resource */
	private $socket;
	/** @var bool */
	private $isRunning = true;
	/** @var SleeperNotifier */
	private $notifier;

	/** @var string */
	public $message;
	/** @var string */
	public $sender;

	/** @var Threaded */
	public $queue;

	public function __construct(SleeperNotifier $notifier, int $port, int ...$broadcastPorts){
		$this->port = $port;
		if(($key = array_search($port, $broadcastPorts)) !== false){
			unset($broadcastPorts[$key]);
			$broadcastPorts = array_values($broadcastPorts);
		}
		$this->broadcastPorts = $broadcastPorts;
		$this->socket = socket_create(AF_UNIX, SOCK_DGRAM, 0);

		@mkdir(self::PATH);

		if(file_exists($filename = self::PATH . $port)){
			@unlink($filename);
		}
		if(!@socket_bind($this->socket, $filename)){
			throw new RuntimeException("Failed to bind socket: " . trim(socket_strerror(socket_last_error($this->socket))));
		}
		socket_set_nonblock($this->socket);

		$this->notifier = $notifier;
		$this->queue = new Threaded();
	}

	protected function onRun() : void{
		$nextTick = microtime(true);

		while($this->isRunning){
			$nextTick += self::TIME_PER_TICK;
			if(($broadcast = $this->queue->shift()) !== null){
				foreach($this->broadcastPorts as $port){
					if(file_exists($filename = self::PATH . $port)){
						@socket_sendto($this->socket, $broadcast, strlen($broadcast), 0, $filename);
					}
				}
			}

			if(($now = microtime(true)) < $nextTick){
				while($this->isRunning and ($now = microtime(true)) < $nextTick){
					$r = [$this->socket];
					if(@socket_select($r, $w, $e, 0, (int) (($nextTick - $now) * 1000000)) !== false){
						if(@socket_recvfrom($this->socket, $buf, 65535, 0, $name, $port) !== false){
							list($this->sender, $this->message) = explode("\x00", $buf, 2);
							$this->synchronized(function(){
								$this->notifier->wakeupSleeper();
								$this->wait();
							});
						}
					}
				}
			}else{
				$nextTick = $now;
			}
		}
	}

	public function close() : void{
		$this->isRunning = false;
		socket_close($this->socket);
	}
}

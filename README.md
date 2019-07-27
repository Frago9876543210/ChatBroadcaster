# ChatBroadcaster
PocketMine-MP plugin for broadcasting chat between servers

### Example
```php
ChatBroadcaster::getInstance()->registerChatHandler(function(string $sender, string $message) : void{
	$this->getServer()->broadcastMessage("$sender: $message");
}, 19132, 19133);
```
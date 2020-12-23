<?php 

class Chat
{
	public $server;
	public $file = __DIR__ . '/chatLog.txt';
	public function __construct()
	{
		//创建WebSocket Server对象，监听0.0.0.0:9502端口
		$this->server = new Swoole\WebSocket\Server('0.0.0.0', 9502);

		//监听WebSocket连接打开事件
		$this->server->on('open', function (Swoole\WebSocket\Server $server, $request) {
			echo "server:{$request->fd} into chat\n";
			// 用户列表
			$userList = file_exists($this->file) ? array_filter(explode(",", file_get_contents($this->file))) : [];
			// 用户加入聊天室
			array_push($userList, $request->fd);
			file_put_contents($this->file, join(',', $userList), LOCK_EX);
		});

		//监听WebSocket消息事件
		$this->server->on('message', function (Swoole\WebSocket\Server $server, $frame) {
		    echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";
		    // 获取聊天用户
		    $userList = array_values(array_filter(explode(",", file_get_contents($this->file))));

		    // 组装消息数据
		    $msg = json_encode([
		    	'fd' => $frame->fd, // 客户ID
		    	'msg' => $frame->data, // 发送消息
		    	'total_num' => count($userList) // 在线人数
		    ], JSON_UNESCAPED_UNICODE);

		    // 发送信息
		    foreach ($userList as $fdId) {
		    	$server->push($fdId, $msg);
		    }
		    
		});

		//监听WebSocket连接关闭事件
		$this->server->on('close', function (Swoole\WebSocket\Server $server, $fd) {
		    
		    // 获取聊天用户
		    $userList = array_filter(explode(",", file_get_contents($this->file)));
			$userList = array_filter(array_diff($userList, [$fd]));

		    // 组装消息数据
		    $msg = json_encode([
		    	'fd' => $fd, // 客户ID
		    	'msg' => '离开聊天室', // 发送消息
		    	'total_num' => count($userList) // 在线人数
		    ], JSON_UNESCAPED_UNICODE);

		    // 发送信息
		    foreach ($userList as $fdId) {
		    	$server->push($fdId, $msg);
		    }

		    file_put_contents($this->file, join(',', $userList), LOCK_EX);
		    echo "client-{$fd} is closed\n";
		});

		$this->server->on('request', function ($request, $response) {
            // 接收http请求从get获取message参数的值，给用户推送
            // $this->server->connections 遍历所有websocket连接用户的fd，给所有用户推送
            foreach ($this->server->connections as $fd) {
                // 需要先判断是否是正确的websocket连接，否则有可能会push失败
                if ($this->server->isEstablished($fd)) {
                    $this->server->push($fd, $request->get['message']);
                }
            }
        });

		$this->server->start();
	}
}

new Chat();

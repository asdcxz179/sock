<?php
	
	include('tools.php');
	class Socket extends tools{


		public $hall=[];
		public $room=[];

		public function __construct($ip,$port){
			parent::__construct();
			$this->message = true;
			$this->ip = $ip;
			$this->port = $port;
		}

		public function run(){
			$this->master=socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
			socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1);
			socket_bind($this->master, $this->ip,$this->port);
			socket_listen($this->master);	

			$this->clients = [];
			$this->_logs("socket_logs.txt","啟動socket , ip:".$this->ip." ,port:".$this->port);
			$this->_logs("socket_logs.txt","主連接線  : ".$this->master);
			$this->_logs("socket_logs.txt","-------------------");
			$handshake=false;
			while (true) {
				$write = NULL;
		        $except = NULL;
		        $this->sockets = $this->clients;
		        $this->sockets[] = $this->master;
		        // print_r($this->sockets);
		        socket_select($this->sockets, $write, $except, NULL);

		        $i = 1; 
		        foreach ($this->sockets as $socket) {
		    		if($socket==$this->master)
		    		{
		    			$this->accept();
		    		}else{
		    			$this->write($socket);
		    		}
				}
				$this->_logs("socket_logs.txt","-------------------");
			}
		} 

		public function accept(){
			$this->client=socket_accept($this->master);
			$header = socket_read($this->client, 1024);
			$this->sockets[] = $this->client;
			$this->clients[] = $this->client;
			if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $header, $match))//冒号后面有个空格
		    {
		        $secKey = $match[1];
		        $secAccept = base64_encode(sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', TRUE));//握手算法固定的
		        $upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
		        "Upgrade: websocket\r\n" .
		        "Connection: Upgrade\r\n" .
		        "Sec-WebSocket-Accept:$secAccept\r\n\r\n";
		        socket_write($this->client, $upgrade, strlen($upgrade));
		    }
		}

		public function process_data($data){
			$data = json_decode($data,true);
			$this->_logs("socket_logs.txt","接收資料".json_encode($data));
			switch ($data['action']) {
				case 'into':
					if(in_array($data['id'],$this->hall)){
						$this->_logs("socket_logs.txt","使用者:".$data['id']."重複踢出該使用者");
						return $this->kick_out($this->client,json_encode(['action'=>'close','msg'=>'']));
					}else{
						$this->hall[$this->search_index($this->client)] = $data['id'];
						$return['action'] = 'list';
						$return['data'] = $this->hall;
						$return['room'] = $this->room;
						$this->_logs("socket_logs.txt","使用者:".$data['id']."進入大廳");
						$this->_logs("socket_logs.txt","大廳人員:".json_encode($this->hall));
					}
					break;
				case 'create':
					$room_number = substr(md5(uniqid(rand(), true)),0,10);
					$limit = 6;
					$user = $this->hall[$this->search_index($this->client)];
					unset($this->hall[$this->search_index($this->client)]);
					$this->room[$room_number]['name'] = "五子棋";
					$this->room[$room_number]['host'] = $user;
					$this->room[$room_number]['limit'] = $limit;
					$this->room[$room_number]['people'][] = $user;
					$this->room[$room_number]['total'] = count($this->room[$room_number]['people']);
					$this->_logs("socket_logs.txt","使用者:".$user."建立房間,編號:".$room_number);
					$return['action'] = 'list';
					$return['data'] = $this->hall;
					$return['room'] = $this->room;
				break;
				case 'join':

				break;
				case 'leave':
					$return['action'] = 'list';
					$return['data'] = $this->hall;
					$return['room'] = $this->room;
				break;
				default:
					$return = [];
					break;
			}
			return json_encode($return);
		}

		public function search_index($socket){
			return array_search($socket, $this->clients);
		}

		public function kick_out($socket,$text){
			
			$this->send_data($socket,$text);
			$index = $this->search_index($socket);
			$this->_logs("socket_logs.txt","連線號:".$index);
			unset($this->clients[$index]);
			socket_close($socket);
			return false;
		}

		public function leave_hall($socket){
			
			$index = $this->search_index($socket);
			$this->_logs("socket_logs.txt","連線號:".$index);
			$this->_logs("socket_logs.txt","使用者".$this->hall[$index]."離開大廳");
			unset($this->clients[$index]);
			unset($this->hall[$index]);
			$text = $this->process_data('{"action":"leave"}');
			$this->send_data($this->clients,$text);
			socket_close($socket);
		}

		public function write($socket){
			$bytes = @socket_recv($socket, $buffer, 1024, 0);
			$this->_logs("socket_logs.txt","資料長度:".$bytes);
			if($bytes<=8){
				$this->leave_hall($socket);
			}else{
    			$length = ord($buffer[1]) & 127;
			    if($length == 126) 
			    {
			        $masks = substr($buffer, 4, 4);
			        $data = substr($buffer, 8);
			    }
			    elseif($length == 127) 
			    {
			        $masks = substr($buffer, 10, 4);
			        $data = substr($buffer, 14);
			    }
			    else
			    {
			        $masks = substr($buffer, 2, 4);
			        $data = substr($buffer, 6);
			    }
			    $text = "";
			    for ($i = 0; $i < strlen($data); ++$i) 
			    {
			        $text .= $data[$i] ^ $masks[$i%4];
			    }
			    
			    $text = $this->process_data($text);
			    if($text){
			    	$this->send_data($this->clients,$text);	
			    }
			    
		    }
		}

		public function send_data($target,$text){
			$this->_logs("socket_logs.txt","傳送資料:".$text);
			$b1 = 0x80 | (0x1 & 0x0f);
		    $length = strlen($text);
		 
		    if($length <= 125)
		    {
		        $header = pack('CC', $b1, $length);
		    }
		    elseif($length > 125 && $length < 65536)
		    {
		        $header = pack('CCn', $b1, 126, $length);
		    }
		    elseif($length >= 65536)
		    {
		        $header = pack('CCNN', $b1, 127,0, $length);
		    }

			if(is_array($target)){
				foreach ($target as $a) {
			    	socket_write($a, $header.$text);
			    }	
			}else{
				socket_write($target, $header.$text);
			}
		}

	}

	$socket = new Socket('192.168.117.111','1024');
	$socket->run();


?>
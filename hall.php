<html>
<head>
<meta http-equiv="Content-Type" content="text/html" charset="utf-8">
<title>WebSocket</title>
<script type="text/javascript" src="jquery.min.js"></script>
<script type="text/javascript">
var name;
if(name==''){
	name = prompt("输入名称","");
}

var ws = new WebSocket("ws://192.168.117.111:1024");
var data = {};
ws.onopen = function(){
    console.log("握手成功");
    into();
};
ws.onmessage = function(e){
    console.log("message:" + e.data);
    data = JSON.parse(e.data);
    console.log(data);
    switch (data.action){
    	case 'list':
    		var str ='';
    		$.each(data.data,function(i,e){
    			str +='<p>'+e+'</p>';
    		});
    		$('.left_nav').html(str);
    	break;
    	case 'close':
    		alert('名稱重複,請重新命名');
    		name = '';
   			window.location.reload();
    	break;
    }
};
ws.onclose =function(){
	// alert("伺服器連線中斷");
	console.log("伺服器連線中斷");
}
ws.onerror = function(){
    console.log("error");
};
function into(){
	data.action = 'into';
	data.id = name;
	ws.send(JSON.stringify(data));
}
$(document).ready(function(){
	
	// $('#start').click(function(){
	// 	id = Math.floor(Math.random()*10000);
	// 	room = true;
	// 	player = 'master';
	// 	players++;
	// 	game = '{"player":"'+player+'","action":"create"}';
	// 	$('#room_number').text("房間代碼:"+id);
	// 	ready = 1;
	// 	ws.send(game);
	// });
});

</script>
<style type="text/css">
	.left_nav{
		width: 200px;
	    text-align: center;
	    float: left;
	    border: 1px solid;
	    height: 95%;
	    overflow-y: auto;
	}
	.hall{
		float: left;
	}
</style>
</head>

<body>
 	
 	<div class="nav">
 		大廳列表
 		<div class="fun">
 			<button id="create">建立房間</button>
 		</div>
 	</div>
 	<div class="left_nav"></div>
 	<div class="hall">
 		
 	</div>
 	<!-- <div id="room_number"></div>
 	<div id="start">建立遊戲</div>
 	<div id="join">加入遊戲</div>
 	<input type="text" name="join" id="room_name">
 	<div id="set_game" style="display:none;">
	 	<div id="sendnumber">送出你的數字</div>
	 	<input type="text" name="number" id="number">
	</div>
	<div id="play_game" style="display:none;">
		<div id="choice">送出你猜數字</div>
	 	<input type="text" name="choice" id="choicenumber">
	 	<div id="master"><h3>master</h3></div>
	 	<div id="custom"><h3>custom</h3></div>
	</div> -->
</body>
<script type="text/javascript">
	// $('.nav').html(name);
</script>
</html>
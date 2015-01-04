<!doctype HTML>
<html>
	<head>
		<meta charset="UTF-8" />
		<script src="https://code.jquery.com/jquery-2.1.3.min.js"></script>
		<link rel="stylesheet" href="style.css" type="text/css" />
		<link href='http://fonts.googleapis.com/css?family=Ubuntu+Mono' rel='stylesheet' type='text/css'>
		
	</head>
	
	<body>
		<div id="container"></div>
		<br />
		<form id="stdin">
			<label class="title">Input:</label>
			<input type="text" id="cmd" name="cmd">
			<button id="send">Send</button>
		</form>
		<div id="bottom">
			<button id="start">Start</button>
			<button id="kill">Force Kill</button>
		</div>
	</body>
	
	<script src="scripts.js"></script>
</html>

<!doctype HTML>
<html>
	<head>
		<style>
			html, body { height:100%;}
			#container
			{
				height: 50%;
				width: 30%;
				background-color: #FFCC99;
				overflow: auto;
			}
		</style>
		<script src="//code.jquery.com/jquery-2.1.3.min.js"></script>
	</head>
	
	<body>
		<div id="container"></div>
		<br />
		<form id="stdin">
			<label class="title">Command:</label>
			<input type="text" id="cmd" name="cmd">
			<button id="send">Send</button>
		</form>
		<br />
		<button id="start">Start</button>
		<button id="kill">Kill</button>
	</body>
	
	<script>
		var running = false;
		$("#send").click( function() {
			$("#container").append( $("#cmd").val() + "<br />" );
			var formData = new FormData();
			formData.append( 'data', $("#cmd").val() + "\n" );
			
			$.ajax({
				type: 'POST',
				url: 'op.php?type=write',
				data: formData,
				dataType: 'json',
				processData: false, 
				contentType: false,
				success: function( data )
				{
					if(data.status == 0)
					{
						$("#container").append( data.data.replace(/\n/g, "<br />") );
					}
					$("#container").animate( { scrollTop: $("#container").get(0).scrollHeight }, 750 );
				}
			});
			
			return false;
		});
		$("#start").click( function() {
			$.ajax({
				type: 'GET',
				dataType: 'json',
				url: 'op.php?type=start',
				success: function( data )
				{
					$("#container").append( data.data.replace(/\n/g, "<br />") );
					if(data.status == 1)
					{
						running = true;
						setTimeout(read, 500);
					}
					$("#container").animate( { scrollTop: $("#container").get(0).scrollHeight }, 750 );
				}
			});
			return false;
		});
		
		$("#kill").click( function() {
			running = false;
			$.ajax({
				type: 'GET',
				dataType: 'json',
				url: 'op.php?type=kill',
				success: function( data )
				{
					$("#container").append( data.data.replace(/\n/g, "<br />") );
					$("#container").animate( { scrollTop: $("#container").get(0).scrollHeight }, 750 );
				}
			});
			return false;
		});
		
		function read()
		{
			$.ajax({
				type: 'GET',
				dataType: 'json',
				url: 'op.php?type=read',
				success: function( data )
				{
					$("#container").append( data.data.replace(/\n/g, "<br />") );
					if(data.status == 1)
					{
						running = true;
					}
					else if(data.status == 0)
					{
						running = false;
					}
					
					if(running)
					{
						setTimeout(read, 500);
					}
					$("#container").animate( { scrollTop: $("#container").get(0).scrollHeight }, 750 );
				}
			});
		}
	</script>
</html>

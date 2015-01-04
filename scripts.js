var running = false;

function start()
{
	$.ajax({
		type: 'GET',
		dataType: 'json',
		url: 'op.php?type=start',
		success: function( data )
		{
			var str = data.data;
			str = str.replace(/\n/g, "<br />");
			str = str.replace("{s}", "<span id='sys'>");
			str = str.replace("{/s}", "</span>");
			
			$("#container").append( str );
			
			if(data.status == 1)
			{
				running = true;
				setTimeout(read, 500);
			}
			$("#container").animate( { scrollTop: $("#container").get(0).scrollHeight }, 750 );
		}
	});
	return false;
}

$("#send").click( function() {
	$("#container").append( $("#cmd").val() + "<br />" );
	var formData = new FormData();
	formData.append( 'data', $("#cmd").val() + "\n" );
	$("#cmd").val('');
			
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
					var str = data.data;
					str = str.replace(/\n/g, "<br />");
					str = str.replace("{s}", "<span id='sys'>");
					str = str.replace("{/s}", "</span>");
					$("#container").append( str );
				}
				$("#container").animate( { scrollTop: $("#container").get(0).scrollHeight }, 750 );
			}
		});
			
	return false;
});

$("#start").click( function() {
	start();
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
			var str = data.data;
			str = str.replace(/\n/g, "<br />");
			str = str.replace("{s}", "<span id='sys'>");
			str = str.replace("{/s}", "</span>");
			$("#container").append( str );
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
			var str = data.data;
			str = str.replace(/\n/g, "<br />");
			str = str.replace("{s}", "<span id='sys'>");
			str = str.replace("{/s}", "</span>");
			
			if(data.status == 1)
			{
				running = true;
			}
			else if(data.status == 0)
			{
				running = false;
			}
			
			$("#container").append( str );
			
			if(running)
			{
				setTimeout(read, 500);
			}
			$("#container").animate( { scrollTop: $("#container").get(0).scrollHeight }, 750 );
		}
	});
}

$( document ).ready(function() {
	start();
	return false;
});
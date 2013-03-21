$(function() {
	$('#button').bind('click', function() {
		$.ajax({
			headers: { 
        		Accept : "application/json; charset=utf-8",
    		},
			type: 'GET',
			url: '/pion/public/json',
			dataType: 'json',
		}).done(function(json) {
			console.log(json);
		})
	})
});
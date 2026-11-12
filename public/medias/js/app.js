var project = getUrlVars()["project"];

$(document).ready(function(e) {
    
	"use strict";
	
	$("#form-special").submit(function(e){
		
		e.preventDefault();
		
		$("table#result tbody").empty();
		$(".alert").remove();
		
		var form = $(this).serializeArray();
		
		$.ajax({
			type:'GET',
			url:'/index.php/firstsearch',
			crossDomain: true,
			data:form,
			dataType:"jsonp",
			async: true,
			jsonpCallback: 'MyFirstCallback'
		});
		return false;
	});
	
	$("#new-search-btn").bind('click',function(){
		$(".alert").remove();
		$('#result-box').addClass('hide');
		$("#search").show();
		$(this).hide();
	});
	
	if(project){
		$("#projet").val(project);
		$("#form-special").submit(); 
	}
	
});

function MyFirstCallback(data) { 

	"user strict";
	
	if(data.errors === 1){
		
		$("#search").hide();
		$("#result-box").removeClass('hide');
		
		var program = data.program;
		var html ='';
		var plan;
		
		jQuery.each(program,function(i, item){
			
			plan = '<a class="btn btn-primary btn-xs" href="/medias/programmes/'+item.projet_titre_key+'/plans/plan-'+item.numero.replace(/\s+/g,"")+'.pdf" target="_blank">Cliquez ici</a>';
			html += '<tr>';
				html += '<td>'+item.type+'</td>';
				html += '<td>'+item.numero+'</td>';
				html += '<td>'+item.superficie+' m²</td>';
				html += '<td>'+item.prix+' €</td>';
				html += '<td>'+item.exposition+'</td>';
				html += '<td>'+plan+'</td>';
			html += '</tr>';
			
		});
		
		$("table#result tbody").append(html);
		$('#new-search').show();
		
	}else{
		$('.container-fluid').append('<div class="alert alert-warning">'+data.errors+'</div>');
		$('#new-search').hide();
	}
	
	$("#new-search-btn").show();
	
}

function getUrlVars() {
	
	"use strict";
	
	var vars = {};
	window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function(m,key,value) {
		vars[key] = value;
	});
	return vars;
}
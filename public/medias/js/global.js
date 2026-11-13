var SITE = "/";
var IMG = SITE+"medias/images/";

$(document).ready(function(){
  
  	$(".tablesorter-green").tablesorter({
		dateFormat: 'ddmmyyyy',
		theme: 'blue',
		widgets: ["zebra", "filter"],
		widgetOptions : {
		  filter_external : '.search',
		  filter_defaultFilter: { 0 : '~{query}' },
		  filter_columnFilters: true,
		  filter_placeholder: { search : 'Recherche...' },
		  filter_saveFilters : true,
		  filter_reset: '.reset'
		}
	});
  
	$(document).on('focus', '.datepicker', function() {
		if ($(this).hasClass('hasDatepicker')) {
			return;
		}
	  	$(this).datepicker({
			dateFormat: 'dd.mm.yy',
			altField: $(this).closest('div').find('.datepickeralt'),
			altFormat: 'yy-mm-dd 08:00:00',
			onSelect: function(){
				var explode = $(this).attr('id').split('_');
				var id = explode[1];
				var type = explode[2];
				var time = $(this).closest('div').find('.datepickeralt').val();
				$.ajax({
		  			type: "POST",
		  			url: SITE+"index.php/json/UpdateDate/",
					data: {idlot:id,time:time,type:type},
					success: function(callback){
						var call = eval('(' + callback + ')');
						if(call.errors==1){
							alert('La date a été modifiée');
							window.location.reload();
						 }
					}
				});
	   		}
	  	}).datepicker('show');
	});  
  
  $('.view_privilege').click(function(){
	  var box_privileges = $(this).attr('alt').replace('vignette_','privileges_');
	  $('#'+box_privileges).toggle();
  });
  
  $('.file').click(function(){
  	var url = $(this).attr('rel');
	$.ajax({
		  type: "POST",
		  url: SITE+"index.php/json/DeleteFile/",
		  data: {url:url},
		  success: function(callback){
			 var call = eval('(' + callback + ')');
			 if(call.errors==1){
				alert('Suppression effectuée');
			 	window.location.reload();
			 }else if(call.errors==0){
				alert('bad request');
			 }
		  }
	});
	return false;
  });
  
  $(".delete_denonciation").click(function(){
  	var referral = $(this).attr('rel');
	$.confirm({
		'title'		: 'Supprimer une dénonciation',
		'message'	: 'Vous êtes sur le point de supprimer cette dénonciation.<br />Continuez ?',
		'buttons'	: {
			'Oui'	: {
				'class'	: 'blue',
				'action': function(){
					$.ajax({
						  type: "POST",
						  url: SITE+"index.php/json/DeleteDenonciation/",
						  data: {denonciation:referral},
						  success: function(callback){
							 var call = eval('(' + callback + ')');
							 if(call.errors==1){
								alert('Suppression effectuée');
								window.location.reload();
							 }else if(call.errors==0){
								alert('bad request');
							 }
						  }
					});
				}
			},
			'Non'	: {
				'class'	: 'gray',
				'action': function(){}
			}
		}
	});
	return false;
  });
  
  $('#supprimer_all').click(function(){
  	var project = $(this).attr('rel');
	$.confirm({
		'title'		: 'Supprimer les lots',
		'message'	: 'Vous êtes sur le point de supprimer les lots.<br />Continuez ?',
		'buttons'	: {
			'Oui'	: {
				'class'	: 'blue',
				'action': function(){
					$.ajax({
						  type: "POST",
						  url: SITE+"index.php/json/DeleteAllInProject/",
						  data: {project:project},
						  success: function(callback){
							 var call = eval('(' + callback + ')');
							 if(call.errors==1){
								alert('Suppression effectuée');
								window.location.reload();
							 }else if(call.errors==0){
								alert('bad request');
							 }
						  }
					});
				}
			},
			'Non'	: {
				'class'	: 'gray',
				'action': function(){}
			}
		}
	});
	return false;
  });
  
  $('.push-act').click(function(){
  	var data = $(this).attr('rel').split('-');
	var type = data[0];
	var idlot = data[1];
	$.confirm({
		'title'		: 'Acter le dossier',
		'message'	: 'Vous êtes sur le point d\'acter le dossier.<br />Continuez ?',
		'buttons'	: {
			'Oui'	: {
				'class'	: 'blue',
				'action': function(){
					$.ajax({
					  type: "POST",
					  url: SITE+"index.php/json/Acter/",
					  data: {type:type,idlot:idlot},
					  success: function(callback){
						 var call = eval('(' + callback + ')');
						 if(call.errors==1){
							$(".call").html('<div class="success">Le dossier a bien été acté</div>');
							setTimeout( "window.location.reload()", 3000 ); 
						 }else if(call.errors==0){
							$(".call").html('<div class="error">Une erreur interne est survenue. Veuillez réessayer SVP</div>');
						 }
					  }
					});
				}
			},
			'Non'	: {
				'class'	: 'gray',
				'action': function(){}
			}
		}
	});
	return false;
  });
  
  $('[data-action="delete-selected-files"]').bind('click',function(){
	  
	  var files = $('[name="selectfile[]"]:checked');
	  var count = files.length;
	  var nb = 0;
	  
	  $(files).each(function(){
		 $.ajax({
			type: "POST",
			url: SITE+"index.php/json/DeleteFile/",
			data: { url:$(this).val() },
			success:function(callback){
				var callback = eval('(' + callback + ')');
				
				if(callback.errors == 1 || callback.errors == "1"){
					nb++;
				}
				
				if(count === nb){
					window.location.reload();
	 			}
				
			}
		 });
	  });
	  
  });
	
  
  $(".delete_lot").click(function(){
	 var data = $(this).attr("rel").split("-");
	 var programId = data[0];
	 var idlot = data[1];
	 $.confirm({
		'title'		: 'Supprimer ce lot',
		'message'	: 'Vous êtes sur le point de supprimer le lot.<br />Continuez ?',
		'buttons'	: {
			'Oui'	: {
				'class'	: 'blue',
				'action': function(){
					$.ajax({
					  type: "POST",
					  url: SITE+"index.php/json/SupprimerLot/",
					  data: {idprojet:programId,idlot:idlot},
					  success: function(callback){
						 var call = eval('(' + callback + ')');
						 if(call.errors==1){
							$(".call").html('<div class="success">La suppression a bien été effectuée</div>');
							setTimeout( "window.location.reload()", 3000 ); 
						 }else if(call.errors==0){
							$(".call").html('<div class="error">Une erreur interne est survenue. Veuillez réessayer SVP</div>');
						 }
					  }
					});
				}
			},
			'Non'	: {
				'class'	: 'gray',
				'action': function(){}
			}
		}
	});
	return false;
  });
  
  $("#submit_f_annuler_la_vente").click(function(){	 
	var data = $("#f_annuler_la_vente").serialize();
	var btn = $(this).prop('disabled', true);
	$.ajax({
	  type: "POST",
	  url: SITE+"index.php/json/AnnulerLaVente/",
	  data: data,
	  error: function(){ btn.prop('disabled', false); },
	  success: function(callback){
		 var call = eval('(' + callback + ')');
		 if(call.errors!=1){ btn.prop('disabled', false); }
		 if(call.errors==1){
			$(".call").html('<div class="success">La vente a bien été annulée</div>');
		 }else if(call.errors==0){
		 	$(".call").html('<div class="error">Une erreur interne est survenue. Veuillez réessayer SVP</div>');
		 }
	  }
	});
	return false;
  });
  
  $('.annuler_vente').click(function(){
  	var data = $(this).attr('rel').split('-');
	var seller = data[1];
	var idlot = data[0];
	$.confirm({
		'title'		: 'Annuler la vente',
		'message'	: 'Vous êtes sur le point d\'annuler la vente.<br />Continuez ?',
		'buttons'	: {
			'Oui'	: {
				'class'	: 'blue',
				'action': function(){
					$.colorbox({
						innerWidth:740,
						innerHeight:580,
						inline:true,
						href: "#annuler_la_vente_box",
						onComplete: function(){
							$(".call").empty();
							$("#annuler_la_vente_idlot").val(idlot);
							$("#annuler_la_vente_vendeur").val(seller);
						},onClosed: function(){
							window.document.location = SITE+"ventes";
						}
				  });
				}
			},
			'Non'	: {
				'class'	: 'gray',
				'action': function(){}
			}
		}
	});
	return false;
  });
  
  $("#submit_f_passer_en_act").click(function(){	 
	var data = $("#f_passer_en_act").serialize();
	var btn = $(this).prop('disabled', true);
	$.ajax({
	  type: "POST",
	  url: SITE+"index.php/json/PasserEnActer/",
	  data: data,
	  error: function(){ btn.prop('disabled', false); },
	  success: function(callback){
		 var call = eval('(' + callback + ')');
		 if(call.errors!=1){ btn.prop('disabled', false); }
		 if(call.errors==1){
			$(".call").html('<div class="success">Le dossier a bien été acté</div>');
		 }else if(call.errors==0){
		 	$(".call").html('<div class="error">Une erreur interne est survenue. Veuillez réessayer SVP</div>');
		 }
	  }
	});
	return false;
  });
  
  $('.passer_en_act').click(function(){
  	var data = $(this).attr('rel').split('-');
	var type = data[1];
	var idlot = data[0];
	$.confirm({
		'title'		: 'Acter le dossier',
		'message'	: 'Vous êtes sur le point d\'acter le dossier.<br />Continuez ?',
		'buttons'	: {
			'Oui'	: {
				'class'	: 'blue',
				'action': function(){
					$.colorbox({
						innerWidth:740,
						innerHeight:580,
						inline:true,
						href: "#passer_en_act_box",
						onComplete: function(){
							$(".call").empty();
							$("#passer_en_act_type").val(type);
							$("#passer_en_act_lot").val(idlot);
						},onClosed: function(){
							window.document.location = SITE+"reservations";
						}
				  });
				}
			},
			'Non'	: {
				'class'	: 'gray',
				'action': function(){}
			}
		}
	});
	return false;
  });
  
  $("#submit_f_refuser_ask_resa").click(function(){	 
	var data = $("#f_refuser_ask_resa").serialize();
	var btn = $(this).prop('disabled', true);
	$.ajax({
	  type: "POST",
	  url: SITE+"index.php/json/RejetAddResa/",
	  data: data,
	  error: function(){ btn.prop('disabled', false); },
	  success: function(callback){
		 var call = eval('(' + callback + ')');
		 if(call.errors!=1){ btn.prop('disabled', false); }
		 if(call.errors==1){
			$(".call").html('<div class="success">La demande de réservation a bien été refusée</div>');
		 }else if(call.errors==0){
		 	$(".call").html('<div class="error">Une erreur interne est survenue. Veuillez réessayer SVP</div>');
		 }
	  }
	});
	return false;
  });
  
  $(".rejeter_ask_resa").click(function(){
	  var data = $(this).attr("rel").split("-");
	  var idlot = data[0];
	  var user = data[1];
	  $.confirm({
		'title'		: 'Rejeter la réservation',
		'message'	: 'Vous êtes sur le point de refuser la demande.<br />Continuez ?',
		'buttons'	: {
			'Oui'	: {
				'class'	: 'blue',
				'action': function(){
					$.colorbox({
						innerWidth:740,
						innerHeight:580,
						inline:true,
						href: "#rejeter_ask_resa_box",
						onComplete: function(){
							$(".call").empty();
							$("#f_refuser_ask_resa_user").val(user);
							$("#f_refuser_ask_resa_lot").val(idlot);
						},onClosed: function(){
							window.document.location = SITE+"ask_reservations";
						}
				  });
				}
			},
			'Non'	: {
				'class'	: 'gray',
				'action': function(){}
			}
		}
	});
	return false;
  });
  
  $("#submit_f_confirm_ask_resa").click(function(){	 
	var data = $("#f_confirm_ask_resa").serialize();
	var btn = $(this).prop('disabled', true);
	$.ajax({
	  type: "POST",
	  url: SITE+"index.php/json/AcceptAddResa/",
	  data: data,
	  error: function(){ btn.prop('disabled', false); },
	  success: function(callback){
		 var call = eval('(' + callback + ')');
		 if(call.errors!=1){ btn.prop('disabled', false); }
		 if(call.errors==1){
			$("#call").html('<div class="success">La validation de la réservation a bien été enregistrée</div>');
		 }else if(call.errors==0){
		 	$("#call").html('<div class="error">Une erreur interne est survenue. Veuillez réessayer SVP</div>');
		 }
	  }
	});
	return false;
  });
  
  $("#submit_f_confirm_ask_denonciation").click(function(){	 
	var data = $("#f_confirm_ask_denonciation").serialize();
	var btn = $(this).prop('disabled', true);
	$.ajax({
	  type: "POST",
	  url: SITE+"index.php/json/AcceptAddDenonciation/",
	  data: data,
	  error: function(){ btn.prop('disabled', false); },
	  success: function(callback){
		 var call = eval('(' + callback + ')');
		 if(call.errors!=1){ btn.prop('disabled', false); }
		 if(call.errors==1){
			$("#call").html('<div class="success">La validation de la dénonciation a bien été enregistrée</div>');
		 }else if(call.errors==0){
		 	$("#call").html('<div class="error">Une erreur interne est survenue. Veuillez réessayer SVP</div>');
		 }
	  }
	});
	return false;
  });
  
  $("#submit_f_refuser_ask_denonciation").click(function(){	 
	var data = $("#f_refuser_ask_denonciation").serialize();
	var btn = $(this).prop('disabled', true);
	$.ajax({
	  type: "POST",
	  url: SITE+"index.php/json/RejetAddDenonciation/",
	  data: data,
	  error: function(){ btn.prop('disabled', false); },
	  success: function(callback){
		 var call = eval('(' + callback + ')');
		 if(call.errors!=1){ btn.prop('disabled', false); }
		 if(call.errors==1){
			$(".call").html('<div class="success">La demande de dénonciation a bien été refusée</div>');
		 }else if(call.errors==0){
		 	$(".call").html('<div class="error">Une erreur interne est survenue. Veuillez réessayer SVP</div>');
		 }
	  }
	});
	return false;
  });
  
  $(".valider_ask_denonciations").click(function(){
	  var data = $(this).attr("rel").split("-");
	  var idlot = data[0];
	  var user = data[1];
	  $.confirm({
		'title'		: 'Confirmation de validation',
		'message'	: 'Vous êtes sur le point de valider la demande.<br />Continuez ?',
		'buttons'	: {
			'Oui'	: {
				'class'	: 'blue',
				'action': function(){
					$.colorbox({
						innerWidth:740,
						innerHeight:580,
						inline:true,
						href: "#valid_ask_denonciation_box",
						onComplete: function(){
							$("#call").empty();
							$("#f_confirm_ask_denonciation_user").val(user);
							$("#f_confirm_ask_denonciation_lot").val(idlot);
						},onClosed: function(){
							window.document.location = SITE+"ask_denonciations";
						}
				  });
				}
			},
			'Non'	: {
				'class'	: 'gray',
				'action': function(){}
			}
		}
	});
	return false;
  });
  
  $(".rejeter_ask_denonciations").click(function(){
	  var data = $(this).attr("rel").split("-");
	  var idlot = data[0];
	  var user = data[1];
	  $.confirm({
		'title'		: 'Rejeter la dénonciation',
		'message'	: 'Vous êtes sur le point de refuser la demande.<br />Continuez ?',
		'buttons'	: {
			'Oui'	: {
				'class'	: 'blue',
				'action': function(){
					$.colorbox({
						innerWidth:740,
						innerHeight:580,
						inline:true,
						href: "#rejeter_ask_denonciation_box",
						onComplete: function(){
							$(".call").empty();
							$("#f_refuser_ask_denonciation_user").val(user);
							$("#f_refuser_ask_denonciation_lot").val(idlot);
						},onClosed: function(){
							window.document.location = SITE+"ask_denonciations";
						}
				  });
				}
			},
			'Non'	: {
				'class'	: 'gray',
				'action': function(){}
			}
		}
	});
	return false;
  });
  
  
  $(".valider_ask_resa").click(function(){
	  var data = $(this).attr("rel").split("-");
	  var idlot = data[0];
	  var user = data[1];
	  $.confirm({
		'title'		: 'Confirmation de validation',
		'message'	: 'Vous êtes sur le point de valider la demande.<br />Continuez ?',
		'buttons'	: {
			'Oui'	: {
				'class'	: 'blue',
				'action': function(){
					$.colorbox({
						innerWidth:740,
						innerHeight:580,
						inline:true,
						href: "#valid_ask_resa_box",
						onComplete: function(){
							$("#call").empty();
							$("#f_confirm_ask_resa_user").val(user);
							$("#f_confirm_ask_resa_lot").val(idlot);
						},onClosed: function(){
							window.document.location = SITE+"ask_reservations";
						}
				  });
				}
			},
			'Non'	: {
				'class'	: 'gray',
				'action': function(){}
			}
		}
	});
	return false;
  });
  
  $(".annuler_resa").click(function(){
	  var data = $(this).attr("rel").split("-");
	  var idlot = data[0];
	  var user = data[1];
	  $.confirm({
		'title'		: 'Annuler la réservation',
		'message'	: 'Vous êtes sur le point d\'annuler la réservation.<br />Continuez ?',
		'buttons'	: {
			'Oui'	: {
				'class'	: 'blue',
				'action': function(){
					$.colorbox({
						innerWidth:740,
						innerHeight:580,
						inline:true,
						href: "#annuler_resa_box",
						onComplete: function(){
							$(".call").empty();
							$("#f_annuler_resa_user").val(user);
							$("#f_annuler_resa_lot").val(idlot);
						},onClosed: function(){
							window.document.location = SITE+"reservations";
						}
				  });
				}
			},
			'Non'	: {
				'class'	: 'gray',
				'action': function(){}
			}
		}
	});
	return false;
  });
  
  $("#submit_f_annuler_resa").click(function(){	 
	var data = $("#f_annuler_resa").serialize();
	var btn = $(this).prop('disabled', true);
	$.ajax({
	  type: "POST",
	  url: SITE+"index.php/json/AnnulerResa/",
	  data: data,
	  error: function(){ btn.prop('disabled', false); },
	  success: function(callback){
		 var call = eval('(' + callback + ')');
		 if(call.errors!=1){ btn.prop('disabled', false); }
		 if(call.errors==1){
			$(".call").html('<div class="success">La réservation a bien été annulée</div>');
		 }else if(call.errors==0){
		 	$(".call").html('<div class="error">Une erreur interne est survenue. Veuillez réessayer SVP</div>');
		 }
	  }
	});
	return false;
  });
  
  $("#submit_f_passer_en_resa").click(function(){	 
	var data = $("#f_passer_en_resa").serialize();
	var btn = $(this).prop('disabled', true);
	$.ajax({
	  type: "POST",
	  url: SITE+"index.php/json/PasserEnResa/",
	  data: data,
	  error: function(){ btn.prop('disabled', false); },
	  success: function(callback){
		 var call = eval('(' + callback + ')');
		 if(call.errors!=1){ btn.prop('disabled', false); }
		 if(call.errors==1){
			$(".call").html('<div class="success">L\'option a bien été passée en réservation</div>');
		 }else if(call.errors==0){
		 	$(".call").html('<div class="error">Une erreur interne est survenue. Veuillez réessayer SVP</div>');
		 }
	  }
	});
	return false;
  });
  
  $(".passer_en_resa").click(function(){
	  var data = $(this).attr("rel").split("-");
	  var idlot = data[0];
	  var user = data[1];
	  
	  $("#f_passer_en_resa_user").val('');
	  $("#f_passer_en_resa_lot").val('');
	  
	  $.confirm({
		'title'		: 'Passer l\'option en réservation',
		'message'	: 'Vous êtes sur le point de passer l\'option en réservation.<br />Continuez ?',
		'buttons'	: {
			'Oui'	: {
				'class'	: 'blue',
				'action': function(){
					$.colorbox({
						innerWidth:740,
						innerHeight:580,
						inline:true,
						href: "#passer_en_resa_box",
						onComplete: function(){
							$(".call").empty();
							$("#f_passer_en_resa_user").val(user);
							$("#f_passer_en_resa_lot").val(idlot);
						},onClosed: function(){
							window.document.location = SITE+"options";
						}
				  });
				}
			},
			'Non'	: {
				'class'	: 'gray',
				'action': function(){}
			}
		}
	});
	return false;
  });
  
  $("#submit_f_annuler_option").click(function(){	 
	var data = $("#f_annuler_option").serialize();
	var btn = $(this).prop('disabled', true);
	$.ajax({
	  type: "POST",
	  url: SITE+"index.php/json/AnnulerOption/",
	  data: data,
	  error: function(){ btn.prop('disabled', false); },
	  success: function(callback){
		 var call = eval('(' + callback + ')');
		 if(call.errors!=1){ btn.prop('disabled', false); }
		 if(call.errors==1){
			$(".call").html('<div class="success">L\'option a bien été annulée</div>');
		 }else if(call.errors==0){
		 	$(".call").html('<div class="error">Une erreur interne est survenue. Veuillez réessayer SVP</div>');
		 }
	  }
	});
	return false;
  });
  
  $(".annuler_option").click(function(){
	  var data = $(this).attr("rel").split("-");
	  var idlot = data[0];
	  var user = data[1];
	  $.confirm({
		'title'		: 'Annuler l\'option',
		'message'	: 'Vous êtes sur le point d\'annuler l\'option.<br />Continuez ?',
		'buttons'	: {
			'Oui'	: {
				'class'	: 'blue',
				'action': function(){
					$.colorbox({
						innerWidth:740,
						innerHeight:580,
						inline:true,
						href: "#annuler_option_box",
						onComplete: function(){
							$(".call").empty();
							$("#f_annuler_option_user").val(user);
							$("#f_annuler_option_lot").val(idlot);
						},onClosed: function(){
							window.document.location = SITE+"options";
						}
				  });
				}
			},
			'Non'	: {
				'class'	: 'gray',
				'action': function(){}
			}
		}
	});
	return false;
  });
  
  $("#submit_f_refuser_ask_option").click(function(){	 
	var data = $("#f_refuser_ask_option").serialize();
	var btn = $(this).prop('disabled', true);
	$.ajax({
	  type: "POST",
	  url: SITE+"index.php/json/RejetAddOption/",
	  data: data,
	  error: function(){ btn.prop('disabled', false); },
	  success: function(callback){
		 var call = eval('(' + callback + ')');
		 if(call.errors!=1){ btn.prop('disabled', false); }
		 if(call.errors==1){
			$(".call").html('<div class="success">La demande d\'option a bien été refusée</div>');
		 }else if(call.errors==0){
		 	$(".call").html('<div class="error">Une erreur interne est survenue. Veuillez réessayer SVP</div>');
		 }
	  }
	});
	return false;
  });
  
  $(".rejeter_ask_option").click(function(){
	  var data = $(this).attr("rel").split("-");
	  var idlot = data[0];
	  var user = data[1];
	  $.confirm({
		'title'		: 'Rejeter l\'option',
		'message'	: 'Vous êtes sur le point de refuser la demande.<br />Continuez ?',
		'buttons'	: {
			'Oui'	: {
				'class'	: 'blue',
				'action': function(){
					$.colorbox({
						innerWidth:740,
						innerHeight:580,
						inline:true,
						href: "#rejeter_ask_option_box",
						onComplete: function(){
							$(".call").empty();
							$("#f_refuser_ask_option_user").val(user);
							$("#f_refuser_ask_option_lot").val(idlot);
						},onClosed: function(){
							window.document.location = SITE+"ask_options";
						}
				  });
				}
			},
			'Non'	: {
				'class'	: 'gray',
				'action': function(){}
			}
		}
	});
	return false;
  });
  
  $("#submit_f_confirm_ask_option").click(function(){	 
	var data = $("#f_confirm_ask_option").serialize();
	var btn = $(this).prop('disabled', true);
	$.ajax({
	  type: "POST",
	  url: SITE+"index.php/json/AcceptAddOption/",
	  data: data,
	  error: function(){ btn.prop('disabled', false); },
	  success: function(callback){
		 var call = eval('(' + callback + ')');
		 if(call.errors!=1){ btn.prop('disabled', false); }
		 if(call.errors==1){
			$("#call").html('<div class="success">La validation de l\'option a bien été enregistrée</div>');
		 }else if(call.errors==0){
		 	$("#call").html('<div class="error">Une erreur interne est survenue. Veuillez réessayer SVP</div>');
		 }
	  }
	});
	return false;
  });
  
  $(".valider_ask_option").click(function(){
	  var data = $(this).attr("rel").split("-");
	  var idlot = data[0];
	  var user = data[1];
	  $.confirm({
		'title'		: 'Confirmation de validation',
		'message'	: 'Vous êtes sur le point de valider la demande.<br />Continuez ?',
		'buttons'	: {
			'Oui'	: {
				'class'	: 'blue',
				'action': function(){
					$.colorbox({
						innerWidth:740,
						innerHeight:580,
						inline:true,
						href: "#valid_ask_option_box",
						onComplete: function(){
							$("#call").empty();
							$("#f_confirm_ask_option_user").val(user);
							$("#f_confirm_ask_option_lot").val(idlot);
						},onClosed: function(){
							window.document.location = SITE+"ask_options";
						}
				  });
				}
			},
			'Non'	: {
				'class'	: 'gray',
				'action': function(){}
			}
		}
	});
	return false;
  });
  
  
  $(".see_sms").click(function(){
	$(this).parent().parent().next("tr").toggleClass('hide');
	return false; 
  });
  
  $(".see_sms2").click(function(){
	var target = $(this).attr('rel');
	$("#see_sms_"+target).toggleClass('hide');
	return false; 
  });
  
  $("#submit-option").click(function(){
  	var data = $("#f_add_option").serialize();
	$("#call-option").empty();
	$.ajax({
	  type: "POST",
	  url: SITE+"index.php/json/AddOption/",
	  data: data,
	  success: function(callback){
		 var call = eval('(' + callback + ')');
		 if(call.errors==1){
			$("#call-option").html('<div class="alert alert-success">La demande d\'option a été enregistrée, elle doit être validée par l\'administrateur </div>');
		 }else if(call.errors==0){
		 	$("#call-option").html('<div class="alert alert-warning">Tous les champs sont obligatoires</div>');
		 }else if(call.errors==2){
		 	$("#call-option").html('<div class="alert alert-warning">Lot non disponible</div>');
		 }
	  }
	});
	return false;
  });
  
  $("#submit-reservation").click(function(){
  	var data = $("#f_add_reservation").serialize();
	$("#call-reservation").empty();
	$.ajax({
	  type: "POST",
	  url: SITE+"index.php/json/AddReservation/",
	  data: data,
	  success: function(callback){
		 var call = eval('(' + callback + ')');
		 if(call.errors==1){
			$("#call-reservation").html('<div class="alert alert-success">La demande de réservation a été enregistrée, elle doit être validée par l\'administrateur </div>');
		 }else if(call.errors==0){
		 	$("#call-reservation").html('<div class="alert alert-warning">Tous les champs sont obligatoires</div>');
		 }else if(call.errors==2){
		 	$("#call-reservation").html('<div class="alert alert-warning">Lot non disponible</div>');
		 }
	  }
	});
	return false;
  });
  $("#submit_add_promoteur").bind('click',function(){
  	var data = $("#f_add_promoteur").serialize();
	$.ajax({
	  type: "POST",
	  url: SITE+"index.php/json/AddPromoteur/",
	  data: data,
	  success: function(callback){
		 var call = eval('(' + callback + ')');
		 if(parseInt(call.errors) === 1){
			$("#addPromoteurBox").find('#call').html('<div class="success">Le compte a bien été ajouté. Vous pouvez à présent affecter les autorisations d\'accès aux différents projets en cliquant <a href="'+SITE+'gestion_privilege">ici</a>.</div>');
		 }else if(parseInt(call.errors) === 0){
		 	$("#addPromoteurBox").find('#call').html('<div class="error">Tous les champs sont obligatoires</div>');
		 }else if(parseInt(call.errors) === 2){
		 	$("#addPromoteurBox").find('#call').html('<div class="error">L\'adresse E-mail saisie n\'est pas une adresse valide</div>');
		 }
	  }
	});
	return false;
  });
  
  $("#addPromoteur").bind('click',function(){
  	$.colorbox({
		  innerWidth:740,
		  innerHeight:580,
		  inline:true,
		  href: "#addPromoteurBox",
		  onComplete: function(){
			  $("#addPromoteurBox").find('#call').empty();
		  }
	});
  });
  
  $(".delPromoteur").click(function(){
	  var data = $(this).attr('rel');
	  $.confirm({
		  'title' : 'Confirmation de désactivation',
		  'message'	: 'Vous êtes sur le point de désactiver ce promoteur<br />Continuer ?',
		  'buttons'	: {
			  'Oui'	: {
				  'class'	: 'blue',
				  'action': function(){
					  $.ajax({
						type: "POST",
						url: SITE+"index.php/json/DeletePromoteur/",
						data: {key:data},
						success: function(callback){
						   document.location.reload();
						}
					  });
				  }
			  },
			  'Non'	: {
				  'class'	: 'gray',
				  'action': function(){}
			  }
		  }
	  });
	  return false;
  });
	
  $(".trashPromoteur").click(function(){
	  var data = $(this).attr('rel');
	  $.confirm({
		  'title' : 'Confirmation de suppression',
		  'message'	: 'Vous êtes sur le point de supprimer ce promoteur<br />Continuer ?',
		  'buttons'	: {
			  'Oui'	: {
				  'class'	: 'blue',
				  'action': function(){
					  $.ajax({
						type: "POST",
						url: SITE+"index.php/json/TrashPromoteur/",
						data: {key:data},
						success: function(callback){
						   document.location.reload();
						}
					  });
				  }
			  },
			  'Non'	: {
				  'class'	: 'gray',
				  'action': function(){}
			  }
		  }
	  });
	  return false;
  });
  
  $(".activPromoteur").click(function(){
	  var data = $(this).attr('rel');
	  $.confirm({
		  'title'		: 'Confirmation d\'activation',
		  'message'	: 'Vous êtes sur le point d\'activer ce promoteur<br />Continuer ?',
		  'buttons'	: {
			  'Oui'	: {
				  'class'	: 'blue',
				  'action': function(){
					  $.ajax({
						type: "POST",
						url: SITE+"index.php/json/ActivPromoteur/",
						data: {key:data},
						success: function(callback){
						   document.location.reload();
						}
					  });
				  }
			  },
			  'Non'	: {
				  'class'	: 'gray',
				  'action': function(){}
			  }
		  }
	  });
	  return false;
  });
  
  $("#submitPrivileges").click(function(){
  	var data = $("#f_gestion_privileges").serialize();
	$.ajax({
	  type: "POST",
	  url: SITE+"index.php/json/UpdateUserPrivilege/",
	  data: data,
	  success: function(callback){
		 var call = eval('(' + callback + ')');
		 if(call.errors==1){
			$("#call").html('<div class="alert alert-success">Les privilèges de l\'utilisateur ont bien été modifiés</div>');
			$('#privileges').animate({scrollTop: 0}, 1000);
		 }
	  }
	});
	return false;
  });
  
  $(".allcheck_project").click(function(e) {
  	var projectTarget = $(this).attr("rel");
	$("#projectCheck"+projectTarget).prop('checked', true);
	$("#acces_table_"+projectTarget+" input").prop('checked', true);
  });
  
  $(".allcheckout_project").click(function(e) {
  	var projectTarget = $(this).attr("rel");
	$("#projectCheck"+projectTarget).prop('checked', false);
	$("#acces_table_"+projectTarget+" input").prop('checked', false);  
  });
  
  $(".push-privileges").click(function(){
	  var data = $(this).attr('rel');
	  
	  $('#privileges').modal('show');
	  
	  $("#call").empty();
	  $("#hiddenUser").val(data);
	  $('input[type=checkbox]').each(function(index) {
		$(this).prop('checked', false);
	  });
	  $.ajax({
		type: "POST",
		url: SITE+"index.php/json/GetUserPrivilege/",
		data: {key:data},
		success: function(callback){
		   var call = eval('(' + callback + ')');
		   if(call.errors==1){
			  var response = call.call;
			  $.each(response, function(index, item){
				  $("#projectCheck"+item.idprojet).prop('checked', true);
			  });
		   }
		}
	  });
			
	  $.ajax({
		type: "POST",
		url: SITE+"index.php/json/GetUserPrivilegeLot/",
		data: {key:data},
		success: function(callback){
		   var call = eval('(' + callback + ')');
		   if(call.errors==1){
			  var response = call.call;
			  $.each(response, function(index, item){
				  $("#lotCheck"+item.idlot).prop('checked', true);
			  });
		   }
		}
	  });
	  
  });
  
  $('.view-privileges').click(function(){
	  $(this).parent().parent().next("tr").toggleClass("hide").removeClass('filtered');
	  return false; 
  });
  
  $(".manageAcces").click(function(){
	  var data = $(this).attr('rel');
	  $.colorbox({
		  innerWidth:740,
		  innerHeight:580,
		  inline:true,
		  href: "#gestionAcces",
		  onComplete: function(){
			$("#call").empty();
			$("#hiddenUser").val(data);
		    $('input[type=checkbox]').each(function(index) {
			  $(this).prop('checked', false);
		    });
		  	$.ajax({
			  type: "POST",
			  url: SITE+"index.php/json/GetUserPrivilege/",
			  data: {key:data},
			  success: function(callback){
				 var call = eval('(' + callback + ')');
				 if(call.errors==1){
					var response = call.call;
					$.each(response, function(index, item){
						$("#projectCheck"+item.idprojet).prop('checked', true);
					});
				 }
			  }
			});
			$.ajax({
			  type: "POST",
			  url: SITE+"index.php/json/GetUserPrivilegeLot/",
			  data: {key:data},
			  success: function(callback){
				 var call = eval('(' + callback + ')');
				 if(call.errors==1){
					var response = call.call;
					$.each(response, function(index, item){
						$("#lotCheck"+item.idlot).prop('checked', true);
					});
				 }
			  }
			});
		  }
	  });
  });
  
  $(".delete_diapo").click(function(){
	  var data = $(this).attr('rel');
	  $.confirm({
		  'title'		: 'Confirmation de suppression',
		  'message'	: 'Vous êtes sur le point de supprimer cette image<br />Continuer ?',
		  'buttons'	: {
			  'Oui'	: {
				  'class'	: 'blue',
				  'action': function(){
					  $.ajax({
						type: "POST",
						url: SITE+"index.php/json/DeleteDiapo/",
						data: {key:data},
						success: function(callback){
						   document.location.reload();
						}
					  });
				  }
			  },
			  'Non'	: {
				  'class'	: 'gray',
				  'action': function(){}
			  }
		  }
	  });
	  return false;
  });
  
  $(".push_archivage").click(function(){
	  var data = $(this).attr('rel');
	  $.confirm({
		  'title'		: 'Confirmation d\'archivage',
		  'message'	: 'Vous êtes sur le point d\'archiver ce projet<br />Continuer ?',
		  'buttons'	: {
			  'Oui'	: {
				  'class'	: 'blue',
				  'action': function(){
					  $.ajax({
						type: "POST",
						url: SITE+"index.php/json/ArchivageProjet/",
						data: {key:data},
						success: function(callback){
						   document.location.reload();
						}
					  });
				  }
			  },
			  'Non'	: {
				  'class'	: 'gray',
				  'action': function(){}
			  }
		  }
	  });
	  return false;
  });
  
  $(".push_desarchivage").click(function(){
	  var data = $(this).attr('rel');
	  $.confirm({
		  'title'		: 'Confirmation de réactivation',
		  'message'	: 'Vous êtes sur le point réactiver ce projet<br />Continuer ?',
		  'buttons'	: {
			  'Oui'	: {
				  'class'	: 'blue',
				  'action': function(){
					  $.ajax({
						type: "POST",
						url: SITE+"index.php/json/DesarchivageProjet/",
						data: {key:data},
						success: function(callback){
						   document.location.reload();
						}
					  });
				  }
			  },
			  'Non'	: {
				  'class'	: 'gray',
				  'action': function(){}
			  }
		  }
	  });
	  return false;
  });
  
  $("#f_recherche_denonciation_submit").click(function(){
	  
	  $('.alert-warning').remove();
	  
	  var data = $("#f_recherche_denonciation").serialize();
	  $.ajax({
		type: "POST",
		url: SITE+"index.php/json/SearchProspect/",
		data: data,
		success:function(callback){
			var call = eval('(' + callback + ')');
			$("#f_recherche_denonciation_result tbody").empty();
			if(call.errors==1){
				var modulo = 0;
				var response = call.call;
				$.each(response, function(index, item){
					if(item.smspromoteur!=''){
						var message = '<a href="javascript:void(0)" class="see_sms btn btn-primary btn-xs" rel="'+item.idprospect+'">Message</a>';
					}else{
						var message = '';
					}
					$('#f_recherche_denonciation_result tbody').append(
					'<tr>' +
						'<td>'+item.date+'</td>' +
						'<td>'+item.nomprospect+'</td><td>'+item.cpprospect+'</td>' +
						'<td>'+item.villeprospect+'</td>' +
						'<td>'+item.projet_titre+'</td>' +
						'<td>'+item.prescripteur+'</td>'+
						'<td align="center">'+message+'</td>'+
					'</tr>' +
					'<tr class="hide" id="see_sms_'+item.idprospect+'">' +
						'<td colspan="7" class="sms">' +
							item.smspromoteur +
						'</td>' +
					'</tr>'
					);
					modulo++;
				});
				$(".see_sms").click(function(){
					var idprospect = $(this).attr('rel');
					$(this).parent().parent().next("tr#"+idprospect).toggleClass("hide");
					return false; 
				  });
			}else if(call.errors==0){
				$("#f_recherche_denonciation_result").before('<div class="alert alert-warning">Veuillez saisir un mot clé pour effectuer une recherche</div>');
			}else if(call.errors==2){
				$("#f_recherche_denonciation_result").before('<div class="alert alert-warning">La recherche n\'a retourné aucun résultat</div>');
			}
			$(".tablesorter-green").trigger("update");
			$(".tablesorter-green").trigger("sorton");
		}
	})
	return false;
  });
  
  $("#submit_f_new_prospect").click(function(){
	  $("#denonciations_call").empty();
	  var data = $("#f_new_prospect").serialize();
	  var btn = $(this).prop('disabled', true);
	  $.ajax({
		type: "POST",
		url: SITE+"index.php/json/AddProspect/",
		data: data,
		complete: function(){ btn.prop('disabled', false); },
		success:function(callback){
			var call = eval('(' + callback + ')');
			if(call.errors==1){
			 	$("#denonciations_call").html('<div class="alert alert-success">Le prospect a bien été enregistré</div>');
			}else if(call.errors==0){
				$("#denonciations_call").html('<div class="alert alert-warning">Les champs marqués d\'une astérisque doivent être renseignés</div>');
			}else if(call.errors==3){
				$("#denonciations_call").html('<div class="alert alert-warning">Votre session a expirée. Merci de vous reconnecter.</div>');
			}
		}
	})
	return false;
  });
  
  $(".checkboxvide").bind('click',function(){
			$(this).addClass("checkboxplein");
			$(this).removeClass("checkboxvide");
			$("#delete_message").val($("#delete_message").val()+$(this).attr("name")+'|');
			});
			
$(".checkboxplein").bind('click',function(){ 
			$(this).addClass("checkboxvide");
			$(this).removeClass("checkboxplein");
			var value=$("#delete_message").val();
			var name=$(this).attr("name");
			var value= value.replace(name+'|','');
			$("#delete_message").val(value);
			});

$(".tout_cocher").bind('click',function (){
	for (var i=0; i < $("span.checkboxvide").length;i++)
		{
			$("#delete_message").val($("#delete_message").val()+$("span.checkboxvide").eq(i).attr("name")+'|');
		}
	$("span.checkboxvide").addClass("checkboxplein");
	$("span.checkboxvide").removeClass("checkboxvide");
});

$(".tout_decocher").bind('click',function (){
	$("#delete_message").val("");
	$("span.checkboxplein").addClass("checkboxvide");
	$("span.checkboxplein").removeClass("checkboxplein");
});

$('a#print-preview').printPreview();
  
  $('a#reload').click(function(){
	  window.location.reload();
  });
  
  $('a#topmenulink').click(function(){
	  $(this).css("display","none");
	  $("#header").css("margin-top","20px");
	  $("#topmenu").slideDown();
  });
  
  $('div#topmenu_content a.link').click(function(){
	$("li.sous-item").css("display","none");
	$("div#topmenu_content a").removeClass("actif-item");
	$(this).toggleClass("actif-item")
	$(this).parent().siblings("li").css("display","block");
	return false;
  });
  
  $('div#topmenu_content a.target').click(function(){
	$("li.sous-item").css("display","none");
	$("div#topmenu_content a").removeClass("actif-item");
	$(this).toggleClass("actif-item")
	$(this).parent().siblings("li").css("display","block");
  });
  
  $("#close").bind('click',function(){
	  $("li.sous-item").css("display","none");
	  $("div#topmenu_content a").removeClass("actif-item");
	  $("#header").css("margin-top","0px");
	  $("#topmenu").slideUp("fast",function(){
		  $('a#topmenulink').slideDown();
	  });
  });
  
  $("#informations").hover(function(){
  	return true;
  },function(){
	$(".menu-informations").removeClass("actif-item");
  	$("#informations li.sous-item").css("display","none");
  });
  
  $("#lost-password-submit").bind('click',function(){
		
		$("#lost-password .alert").remove();
		
		var data = $("#lost-password form").serializeArray();
		
		$.ajax({
			type: "POST",
			url: SITE+"index.php/json/CheckIfExist/",
			data: data,
			beforeSend: function(){},
			success:function(strCallback){
				var callback = eval('(' + strCallback + ')');
				if(callback.errors==1){
					$("#lost-password .modal-body").append('<div class="alert alert-warning">'+callback.response+'</div>');
				}else if(callback.errors==0){
					var confirm = '<a rel="'+callback.id + '" id="lost-password-confirm" href="javascript:void(0)" class="btn btn-primary btn-xs">CONFIRMER LA R&Eacute;INITIALISATION DE VOTRE MOT DE PASSE</a>';
					$("#lost-password .modal-body").append('<div class="alert alert-info"><p>'+callback.response + '</p><p>' +confirm+'</p></div>');
					
					$("#lost-password-confirm").bind('click',function(){
						var idclient = $(this).attr('rel');
						var randomstring = Math.random().toString(36).slice(-8);
						$.ajax({
						   type: "POST",
						   url: SITE+"index.php/json/NewAccountSetting/",
						   data: {idClient : idclient, pwdClient : randomstring},
						   beforeSend: function(){},
						   success: function(strCallback){
							 var callback = eval('(' + strCallback + ')');
							 $("#lost-password .modal-body").html(callback.response);
							 if(callback.errors == 0){
							 	$("#lost-password .modal-footer button").hide();
							 }
						   }
						});
					});
					
				}
			}
		});
		return false;
	});
	
});

function CheckResponse(){
	var data = $("#form-question-response").serializeArray();
	$.ajax({
		type: "POST",
		url: SITE+"index.php/json/CheckAnswer/",
		data: data,
		beforeSend: function(){
			$("#forgottenpassword-response-submit").css("display","none");
    		$("img.loader").css("display","block");
  		},
		success:function(strCallback){
			$("#forgottenpassword-response-submit").css("display","block");
    		$("img.loader").css("display","none");
			var callback = eval('(' + strCallback + ')');
			if(callback.errors==1){
				$("#answer-response-error").fadeIn()
							  			   .html(callback.response);
			}else if(callback.errors==0){
				$("#answer-response-error").css("display","none");
				$(".response").remove();
				NewAccountSetting(callback.id,callback.pwd);
			}
		}
	});
	return false;
}

function NewAccountSetting(id,pwd){
	$.ajax({
	   type: "POST",
	   url: SITE+"index.php/json/NewAccountSetting/",
	   data: {idClient : id, pwdClient : pwd},
	   beforeSend: function(){
		   var beforeData = '<div style="text-align:center; margin-top:120px;" class="loader"><img src="'+IMG+'loader.gif" width="16" height="16" alt="Patientez SVP" /></div>';
		   $("#answer").append(beforeData);
  		},
	   success: function(strCallback){
		 var callback = eval('(' + strCallback + ')');
		 var Response = '<div style="text-align:center; margin-top:120px;" class="loader">'+callback.response+'</div>';
		 if(callback.errors==1){
			$("#backforgottenpassword").remove();
		 	$(".loader").html(Response+'<br /><a href="'+SITE+'" style="color:#999;font-size:12px;font-style:italic;">Retour au formulaire de connexion</a>');
		 }else if(callback.errors==0){
		 	$("#backforgottenpassword").remove();
			$(".loader").html(Response+'<br /><a href="'+SITE+'" style="color:#999;font-size:12px;font-style:italic;">Retour au formulaire de connexion</a>');
		 }
	   }
	 });
}

function Recherche(){
	var data = $("#form-recherche").serializeArray();
	$.ajax({
		type: "POST",
		url: SITE+"index.php/json/Recherche/",
		data: data,
		success:function(strCallback){
			var callback = eval('(' + strCallback + ')');
			
			if(callback.errors==0){
				var search_response='';
					for (var i = 0 ;i < callback.response.length;i ++)
					{
				var search_response= search_response +'nom: '+callback.response[i].nom+'<br />ville: '+callback.response[i].ville+'<br />type: '+callback.response[i].type+'<br />prix: '+callback.response[i].prix+'<br /><br/>';
					}
				$(".reponse_recherche").html(search_response);
				$(".reponse_recherche").css("display","block");
			}
			else {
				$(".reponse_recherche").html('pas de résultats');
				$(".reponse_recherche").css("display","block");
			}
		}
	});
	return false;
}

function Search_City(){
	var data = $("#form-recherche").serializeArray();
	$.ajax({
		type: "POST",
		url: SITE+"index.php/json/Recherche_Ville/",
		data: data,
		success:function(strCallback){
			var city_callback = eval('(' + strCallback + ')');
			if(city_callback.errors==0){
				var cities='villes: ';
				for (var i = 0 ;i < city_callback.response.length;i ++)
					{
						for(var y = 0;y < city_callback.response[i].length;y ++)
							{
								var city = ' '+city_callback.response[i][y].ville+'<input type="checkbox" name="ville[]" value="'+ city_callback.response[i][y].ville+'" onclick="Recherche()"/><br />';
								var cities = cities.concat(city);
							}
					}
				$(".villes").html(cities);
				$(".villes").css("display","block");
			}
			else {
				$(".villes").html('pas de villes');
				$(".villes").css("display","block");
			}
		}
	});
	return false;
}

function ShowToolbar(){
	$.ajax({
		url: SITE+"index.php/json/ShowToolbar/",
		success:function(strCallback){
			var callback = eval('(' + strCallback + ')');
			if(callback.errors==0){
				document.location.reload();
			}
		}
	});
	return false;
}

function HideToolbar(){
	$.ajax({
		url: SITE+"index.php/json/HideToolbar/",
		success:function(strCallback){
			var callback = eval('(' + strCallback + ')');
			if(callback.errors==0){
				document.location.reload();
			}
		}
	});
	return false;
}

function confirm_delete_messages(){
	var InputValue = $("#delete_message").val();
	if(InputValue!=""){
		var countMessage = $(".checkboxplein").length;
		var plural = '';
		var message = ' '+countMessage+' message';
		if(countMessage>1){
			plural = 's';
		}else{
			message = ' message';
		}
		if(confirm('Voulez-vous supprimer ce'+plural+message+plural+' ?')){
			document.getElementById('form_messagerie').submit();
		}
	}else{
		alert("Aucun message selectionné");
	}
}

function confirm_delete_all_messages(){
	
	
		var countMessage = $(".checkboxvide").length;
		var plural = '';
		var message = ' '+countMessage+' message';
		if(countMessage>1){
			plural = 's';
		}else{
			message = ' message';
		}
		if(confirm('Voulez-vous supprimer ce'+plural+message+plural+' ?')){
			document.getElementById('form_allmessages').submit();
		}
	
}

@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>Clients</h1>
</div>
@if (isset($error))
<div class="alert alert-warning" role="alert">{!! htmlspecialchars($error, ENT_NOQUOTES) !!}</div>
@elseif (isset($success))
<div class="alert alert-success" role="alert">{!! htmlspecialchars($success, ENT_NOQUOTES) !!}</div>
@endif
<h2>Consultation de vos clients <small>Rechercher un client</small></h2>

<div class="form-group">
    <a href="/gestion_clients/ajouter_client" class="btn btn-primary">AJOUTER UN CLIENT</a>
    <a href="/gestion_clients/ajouter_clients" class="btn btn-primary">AJOUTER EN MASSE</a>
    <a href="/gestion_clients/telecharger_clients" class="btn btn-primary">T&Eacute;L&Eacute;CHARGER CSV</a>
    <div class="clearfix"></div>
</div>


<div class="form-group">
    <form name="f_recherche_client" id="f_recherche_client" method="post" action="#" class="form-inline">
        <div class="form-group">
            <label for="f_recherche_client_search">MOT CL&Eacute;</label>
            <input type="text" name="f_recherche_client_search" id="f_recherche_client_search" value="" />
        </div>
        <input type="hidden" name="f_recherche_client_type" checked="checked" value="1" />
        <input type="submit" value="Rechercher" id="f_recherche_client_submit" class="btn btn-primary" />
    </form>
</div>

<div class="clearfix"></div>

<div class="return"></div>

<table width="100%" cellpadding="5" cellspacing="5" class="tablesorter-green" id="f_recherche_client_result">
	<thead> 
    <tr> 
        <th data-sorter="shortDate" data-date-format="ddmmyyyy">Date</th> 
        <th>Source</th> 
        <th>Projet</th> 
        <th>Contact</th> 
        <th>Adresse</th>
        <th>Tel</th>
        <th>E-mail</th>
        <th>Type d'achat</th>
        <th>Type logement</th>
        <th>budget</th>
        <th></th>
    </tr> 
    </thead> 
    <tbody> 
    </tbody>
</table>
<form id="f_supprimer_client" method="post" action="#" class="hide">@csrf</form>
<script type="text/javascript" nonce="{{ Vite::cspNonce() }}">
document.addEventListener('click', function (e) {
	var link = e.target.closest ? e.target.closest('.supprimer_client') : null;
	if (!link) {
		return;
	}
	e.preventDefault();
	e.stopPropagation();
	$.confirm({
		'title'		: 'Supprimer ce client',
		'message'	: 'Vous êtes sur le point de supprimer le client.<br />Continuez ?',
		'buttons'	: {
			'Oui'	: {
				'class'	: 'blue',
				'action': function(){
					var form = document.getElementById('f_supprimer_client');
					form.action = link.getAttribute('href');
					form.submit();
				}
			},
			'Non'	: {
				'class'	: 'gray',
				'action': function(){return false;}
			}
		}
	});
}, true);
</script>
@endsection

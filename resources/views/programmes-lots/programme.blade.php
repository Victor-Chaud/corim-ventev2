@extends('layouts.template')

@section('content')
<div style="padding:10px;text-align:justify">
	<p style="float:left; margin-right:20px; margin-bottom:20px; margin-top:4px; text-align:center;">
    	<img width="120" alt="vignette" src="{{ $image }}" /><br /><br />
@foreach ($Get_Diapo as $diapo)<a rel="slideshow" class="noprint" href="{{ $diapo }}">@if ($loop->first)[Diaporama]@endif</a>@endforeach
    </p>

    <div style="font-size:18px; font-weight:bold;">{{ $libelle }}</div>
    <div class="form-group">
    	<a href="javascript:void(0)" class="noprint btn btn-primary" data-toggle="modal" data-target="#telechargements">Téléchargements</a>
        <a href="javascript:void(0)" class="noprint btn btn-primary" data-toggle="modal" data-target="#denonciations">Dénoncer un prospect</a>
    </div>
    <div class="form-group">
    	<div style="line-height:1.7; margin-top:10px">{{ \App\Http\Controllers\ProgrammesLots\Description::html((string) $programme->projet_description) }}</div>
    </div>

</div>

<div class="modal fade" id="telechargements" tabindex="-1" role="dialog" aria-labelledby="myModalTelechargements">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="myModalTelechargements">Téléchargements</h4>
      </div>
      <div class="modal-body">
        <h3>VISUELS</h3>@foreach ($Get_Visus as $fichier)<a href="{{ $fichier['url'] }}" target="_blank">{{ $fichier['name'] }}</a><br />@endforeach
        <h3>DOCUMENTS</h3>@foreach ($Get_Docs as $fichier)<a href="{{ $fichier['url'] }}" target="_blank">{{ $fichier['name'] }}</a><br />@endforeach
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="denonciations" tabindex="-1" role="dialog" aria-labelledby="myModalDenonciations">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="myModalDenonciations">Dénonciation d'un prospect</h4>
      </div>
      <div class="modal-body">
        <div id="denonciations_call"></div>
        <form name="f_new_prospect" id="f_new_prospect" action="#" method="post">
            @csrf
            <div class="form-group">
            	<div class="col-xs-12 col-sm-12 col-md-12">
                	<label>Civilité *</label>
                </div>
                <div class="col-xs-6 col-sm-3 col-md-3">
                    <div class="radio">
                      <label>
                        <input type="radio" value="Mme & M." name="f_new_prospect_civilite" checked="checked" />
                        Mme & M.
                      </label>
                    </div>
                </div>
                <div class="col-xs-6 col-sm-3 col-md-3">
                    <div class="radio">
                      <label>
                        <input type="radio" value="Mme" name="f_new_prospect_civilite" />
                        Mme
                      </label>
                    </div>
                </div>
                <div class="col-xs-6 col-sm-3 col-md-3">
                    <div class="radio">
                      <label>
                        <input type="radio" value="M" name="f_new_prospect_civilite" />
                        M
                      </label>
                    </div>
                </div>
                <div class="col-xs-6 col-sm-3 col-md-3">
                    <div class="radio">
                      <label>
                        <input type="radio" value="Melle" name="f_new_prospect_civilite" />
                        Melle
                      </label>
                    </div>
                </div>
            </div>
            <div class="col-xs-12 col-sm-6 col-md-6">
                <div class="form-group">
                    <label for="f_new_prospect_name">Nom du prospect *</label>
                    <input type="text" name="f_new_prospect_name" id="f_new_prospect_name" value="" class="form-control" />
                </div>
                <div class="form-group">
                    <label for="f_new_prospect_cp">Code postal du prospect *</label>
                    <input type="text" name="f_new_prospect_cp" id="f_new_prospect_cp" value="" class="form-control" />
                </div>
            </div>
            <div class="col-xs-12 col-sm-6 col-md-6">
                <div class="form-group">
                    <label for="f_new_prospect_prenom">Prénom du prospect *</label>
                    <input type="text" name="f_new_prospect_prenom" id="f_new_prospect_prenom" value="" class="form-control" />
                </div>
                <div class="form-group">
                    <label for="f_new_prospect_ville">Ville du prospect *</label>
                    <input type="text" name="f_new_prospect_ville" id="f_new_prospect_ville" class="form-control" />
                </div>
            </div>
            @if ($isAdmin)
            <div class="form-group">
            	<label for="prescripteur_select">Choix du prescripteur</label>
                <select name="prescripteur_select" id="prescripteur_select" class="form-control">
                    <option value="0">Choisir</option>
                    @foreach ($Prescripteurs as $Prescripteur)
                    <option value="{{ $Prescripteur->id }}">{{ $Prescripteur->name }}</option>
                    @endforeach
                </select>
            </div>
			@endif
            <div class="form-group">
            	<label for="f_new_prospect_sms">Message au promoteur</label>
                <textarea name="f_new_prospect_sms" id="f_new_prospect_sms" rows="10" class="form-control"></textarea>
            </div>
            <input type="hidden" name="f_new_prospect_projet" value="{{ $programme->projet_id }}" />
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Fermer</button>
        <button type="button" class="btn btn-primary" id="submit_f_new_prospect">Valider</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="choix-actions" tabindex="-1" role="dialog" aria-labelledby="myModalChoixActions">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="myModalChoixActions">Choisissez une action</h4>
      </div>
      <div class="modal-body">
        <a href="javascript:void(0)" class="btn btn-info btn-block">Optionner</a>
        <a href="javascript:void(0)" class="btn btn-info btn-block">Réserver</a>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Fermer</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="optionner" tabindex="-1" role="dialog" aria-labelledby="myModalOptionner">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="myModalOptionner">Optionner un lot</h4>
      </div>
      <div class="modal-body">
      	<div id="call-option"></div>
        <form action="#" method="post" id="f_add_option">
            @csrf
        	<div class="form-group">
            	<div class="col-xs-12 col-sm-12 col-md-12">
                	<label>Civilité *</label>
                </div>
                <div class="col-xs-6 col-sm-3 col-md-3">
                    <div class="radio">
                      <label>
                        <input type="radio" value="Mme & M." name="civilite" checked="checked" />
                        Mme & M.
                      </label>
                    </div>
                </div>
                <div class="col-xs-6 col-sm-3 col-md-3">
                    <div class="radio">
                      <label>
                        <input type="radio" value="Mme" name="civilite" />
                        Mme
                      </label>
                    </div>
                </div>
                <div class="col-xs-6 col-sm-3 col-md-3">
                    <div class="radio">
                      <label>
                        <input type="radio" value="M" name="civilite" />
                        M
                      </label>
                    </div>
                </div>
                <div class="col-xs-6 col-sm-3 col-md-3">
                    <div class="radio">
                      <label>
                        <input type="radio" value="Melle" name="civilite" />
                        Melle
                      </label>
                    </div>
                </div>
            </div>
            <div class="col-xs-12 col-sm-6 col-md-6">
                <div class="form-group">
                    <label for="name">Nom de l'acquéreur *</label>
                    <input type="text" name="name" id="name" value="" class="form-control" />
                </div>
                <div class="form-group">
                    <label for="cp">Code postal de l'acquéreur *</label>
                    <input type="text" name="cp" id="cp" value="" class="form-control" />
                </div>
            </div>
            <div class="col-xs-12 col-sm-6 col-md-6">
                <div class="form-group">
                    <label for="prenom">Prénom de l'acquéreur *</label>
                    <input type="text" name="prenom" id="prenom" value="" class="form-control" />
                </div>
                <div class="form-group">
                    <label for="adr">Ville de l'acquéreur *</label>
                    <input type="text" name="adr" id="adr" class="form-control" />
                </div>
            </div>
        	@if ($isAdmin)
            <div class="form-group">
            	<label for="prescripteur_select">Choix du prescripteur</label>
                <select name="prescripteur_select" id="prescripteur_select" class="form-control">
                    <option value="0">Choisir</option>
                    @foreach ($Prescripteurs as $Prescripteur)
                    <option value="{{ $Prescripteur->id }}">{{ $Prescripteur->name }}</option>
                    @endforeach
                </select>
            </div>
			@endif
            <div class="form-group">
            	<label for="message">Message au promoteur</label>
                <textarea name="message" id="message" rows="10" class="form-control"></textarea>
            </div>
        	<input type="hidden" name="id_option" id="id_option" value="" />
        	<input type="hidden" name="id_projet" value="{{ $programme->projet_id }}" />
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Fermer</button>
        <button type="button" class="btn btn-primary" id="submit-option">Valider</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="reserver" tabindex="-1" role="dialog" aria-labelledby="myModalReserver">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="myModalReserver">Réserver un lot</h4>
      </div>
      <div class="modal-body">
      	<div id="call-reservation"></div>
        <form action="#" method="post" id="f_add_reservation">
            @csrf
        	<div class="form-group">
            	<div class="col-xs-12 col-sm-12 col-md-12">
                	<label>Civilité *</label>
                </div>
                <div class="col-xs-6 col-sm-3 col-md-3">
                    <div class="radio">
                      <label>
                        <input type="radio" value="Mme & M." name="civilite" checked="checked" />
                        Mme & M.
                      </label>
                    </div>
                </div>
                <div class="col-xs-6 col-sm-3 col-md-3">
                    <div class="radio">
                      <label>
                        <input type="radio" value="Mme" name="civilite" />
                        Mme
                      </label>
                    </div>
                </div>
                <div class="col-xs-6 col-sm-3 col-md-3">
                    <div class="radio">
                      <label>
                        <input type="radio" value="M" name="civilite" />
                        M
                      </label>
                    </div>
                </div>
                <div class="col-xs-6 col-sm-3 col-md-3">
                    <div class="radio">
                      <label>
                        <input type="radio" value="Melle" name="civilite" />
                        Melle
                      </label>
                    </div>
                </div>
            </div>
            <div class="col-xs-12 col-sm-6 col-md-6">
                <div class="form-group">
                    <label for="name">Nom de l'acquéreur *</label>
                    <input type="text" name="name" id="name" value="" class="form-control" />
                </div>
                <div class="form-group">
                    <label for="cp">Code postal de l'acquéreur *</label>
                    <input type="text" name="cp" id="cp" value="" class="form-control" />
                </div>
            </div>
            <div class="col-xs-12 col-sm-6 col-md-6">
                <div class="form-group">
                    <label for="prenom">Prénom de l'acquéreur *</label>
                    <input type="text" name="prenom" id="prenom" value="" class="form-control" />
                </div>
                <div class="form-group">
                    <label for="adr">Ville de l'acquéreur *</label>
                    <input type="text" name="adr" id="adr" class="form-control" />
                </div>
            </div>
        	@if ($isAdmin)
            <div class="form-group">
            	<label for="prescripteur_select">Choix du prescripteur</label>
                <select name="prescripteur_select" id="prescripteur_select" class="form-control">
                    <option value="0">Choisir</option>
                    @foreach ($Prescripteurs as $Prescripteur)
                    <option value="{{ $Prescripteur->id }}">{{ $Prescripteur->name }}</option>
                    @endforeach
                </select>
            </div>
			@endif
            <div class="form-group">
            	<label for="message">Message au promoteur</label>
                <textarea name="message" id="message" rows="10" class="form-control"></textarea>
            </div>
        	<input type="hidden" name="id_reserver" id="id_reserver" value="" />
        	<input type="hidden" name="id_projet" value="{{ $programme->projet_id }}" />
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Fermer</button>
        <button type="button" class="btn btn-primary" id="submit-reservation">Valider</button>
      </div>
    </div>
  </div>
</div>

</div>
<div class="clear"></div>
<table width="920" cellpadding="5" cellspacing="5">
<tr>
<td><img width="360" height="30" alt="Légende couleur" src="/medias/images/template/panel-couleur.png" class="noprint" /></td>
<td>&nbsp;</td>
<td align="center"><b style="color:#E01C18">LOTS ACTÉS :</b> {{ $CountHisAct }}</td>
<td align="center"><b style="color:#B70B61">RÉSERVATIONS :</b> {{ $CountHisResa }}</td>
<td align="center"><b style="color:#ADC42B">OPTIONS :</b> {{ $CountHisOpt }}</td>
</tr>
</table>
<div class="clear"><br /></div>
<h4>Pour optionner ou réserver un lot, cliquez sur celui-ci pour le mettre en surbrillance puis cliquez l'une des actions</h4>
<script type="text/javascript" nonce="{{ Vite::cspNonce() }}">
$(function()
	{
		$("#datagrid").flexigrid({
			url: '/index.php/json/JsonDatagrid/{{ $programme->projet_id }}/{{ $sessionId }}',
			dataType: 'json',
			colModel : [
				
				{display: 'Livraison', name : 'date', width : 60, sortable : true, align: 'center'},
				{display: 'Type', name : 'type', width : 60, sortable : true, align: 'center'},
				{display: 'Catégorie', name : 'cat', width : 110, sortable : true, align: 'center'},
				{display: 'Prix', name : 'prix', width : 90, sortable : true, align: 'center'},
				{display: 'Prix m2', name : 'prix_m2', width : 50, sortable : true, align: 'center', hide: true},
				{display: 'Eta.', name : 'etage', width : 50, sortable : true, align: 'center'},
				{display: 'RDC', name : 'rdc', width : 50, sortable : true, align: 'center', hide: true},
				{display: 'Jard.', name : 'jardin', width : 58, sortable : true, align: 'center'},
				{display: 'Terr.', name : 'terrasse', width : 58, sortable : true, align: 'center'},
				{display: 'Superficie', name : 'superficie', width : 100, sortable : true, align: 'center'},
				{display: 'Stationnement', name : 'stationnement', width : 98, sortable : true, align: 'center'},
				{display: 'Exposition', name : 'exposition', width : 80, sortable : true, align: 'center'},
				{display: 'Plans', name : 'actions', width : 90, sortable : true, align: 'center'},
				{display: 'Référence', name : 'numero', width : 90, sortable : true, align: 'center'},
				{display: 'Cellier', name : 'cellier', width : 90, sortable : true, align: 'center'},
				{display: 'Accession', name : 'accession', width : 80, sortable : true, align: 'center'}
				],
			buttons : [
                        
						{name: 'Optionner', bclass: 'btn btn-info btn-xs', onpress : doCommandAction},
						{separator: true},
						{name: 'Réserver', bclass: 'btn btn-info btn-xs', onpress : doCommandAction},
						{separator: true},
						{name: 'print', bclass: 'printgrid', onpress : doCommand}
                ],
			searchitems : [
				{display: 'Nom', name : 'nom', isdefault: true},
				{display: 'Dept.', name : 'dept'},
				{display: 'Type', name : 'type'},
				{display: 'Cat.', name : 'cat'},
				{display: 'Prix', name : 'prix'},
				{display: 'Prix m2', name : 'prix_m2'},
				{display: 'Accession', name : 'accession'},
				{display: 'Eta.', name : 'etage'},
				{display: 'RDC', name : 'rdc'},
				{display: 'Jard.', name : 'jardin'},
				{display: 'Terr.', name : 'terrasse'},
				{display: 'Sup.', name : 'superficie'},
				{display: 'Stationnement', name : 'stationnement'},
				{display: 'Exposition', name : 'exposition'},
				{display: 'Référence', name : 'numero'},
				{display: 'Cellier', name : 'cellier'}
				],
			sortname: "numero",
			sortorder: "asc",
			usepager: true,
			title: {{ \Illuminate\Support\Js::from($libelle) }},
			useRp: true,
			rp: 100,
			showTableToggleBtn: true,
			width: 1170,
			height: 440,
			singleSelect: true,
			onSuccess:itemState
		});
		
		$("a[rel='slideshow']").colorbox({slideshow:true});
	}
);

function itemState(){
	$("#datagrid tr").each(function(index) {
    	$('.opt').parents("tr").css('background','#ADC42B');
		$('.res').parents("tr").css('background','#B70B61');
		$('.act').parents("tr").css('background','#E01C18');
	});
}

function doCommandAction(com, grid) {
	$('.trSelected', grid).each(function() {
		var id = $(this).attr('id');
		id = id.substring(id.lastIndexOf('row')+3);
		var statut = $("#row"+id+" span").attr('class');
		if(statut != 'opt' && statut != 'res' && statut != 'act'){
			if(com == 'Optionner'){
				$("#call-option").empty();
				$("#id_option").val(id);
				$('#optionner').modal('show');
			}else if(com == 'Réserver'){
				$("#call-reservation").empty();
				$("#id_reserver").val(id);
				$('#reserver').modal('show');
			}
		}
	});
}

function doCommand(com, grid) {
	if (com == 'print'){
		$("div.cDrag").remove();
		$("div.fbutton").remove();
		$("div.pDiv").remove();
		$("div.sDiv").remove();
		$("div.mDiv").css({'font-family' : 'Arial,Helvetica,sans-serif', 'font-size': '11px','font-weight': 'bold','margin-bottom':'16px'});
		$("div.hDivBox table").css({'border': '1px','font-family':'Arial,Helvetica,sans-serif','font-size': '11px'});
		$("div.bDiv table").css({'border-color' : '#00000', 'border': '2px','font-family':'Arial,Helvetica,sans-serif','font-size': '11px'});
		var divToPrint= $(".flexigrid");
		newWin= window.open(this.href);
		newWin.document.write(divToPrint.html());
		newWin.print();
		location.reload();
		newWin.stop();
	}
}

</script>
<table id="datagrid"></table>

@endsection

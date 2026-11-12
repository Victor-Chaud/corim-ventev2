@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>Gestion des prescripteurs {{ $status }} <small><a href="javascript:void(0)" id="addPromoteur">Ajouter un compte</a></small></h1>
</div>
<div class="row">
	<div class="col-md-12 text-right form-group">
		@if (count($programmes) > 0)
		<form class="form-inline" method="post" action="/gestion_promoteurs">
		  @csrf
		  <div class="form-group">
			<select name="type" id="type" class="form-control selectpicker">
				<option value="0" >Contient au moins un programme sélectionné</option>
				<option value="1" {{ ($post['type'] ?? null) === '1' ? 'selected' : '' }}>Contient tous les programmes sélectionnés</option>
			</select>
		  </div>
		  <div class="form-group">
			<select name="projects[]" id="projects" class="form-control selectpicker" multiple>
			@foreach ($programmes as $programme)
				<option value="{{ $programme->projet_id }}" {{ in_array((string) $programme->projet_id, array_map('strval', (array) ($post['projects'] ?? [])), true) ? 'selected' : '' }}>{{ strtoupper(html_entity_decode((string) $programme->projet_titre, ENT_QUOTES)) }}</option>
			@endforeach
			</select>
		  </div>
		  <button type="submit" class="btn btn-default"><i class="glyphicon glyphicon-search"></i></button>
		</form>
		@endif
	</div>
	<div class="col-md-12 text-right">
	@if (count($clients) > 0)
		<table width="100%" cellpadding="5" cellspacing="5" class="tablesorter-green">
		<thead> 
			<tr>
				<td align="center"><b>NOM</b></td>
				<td align="center"><b>EMAIL</b></td>
				<td align="center"><b>IDENTIFIANT</b></td>
				<td align="center"><b>STATUT</b></td>
				<td align="center"><b>ACTIF</b></td>
				<td>&nbsp;</td>
				<td>&nbsp;</td>
			</tr>
		</thead>
		<tbody>
		@foreach ($clients as $client)
@php($email = html_entity_decode((string) $client->email, ENT_QUOTES))
<tr><td align="center">{{ html_entity_decode((string) $client->name, ENT_QUOTES) }}</td><td align="center"><a href="mailto:{{ $email }}">{{ $email }}</a></td><td align="center">{{ html_entity_decode((string) $client->pseudo, ENT_QUOTES) }}</td><td align="center">{{ match ((int) $client->acces) { 1 => 'Administrateur', 0 => 'Commercial', default => '' } }}</td><td align="center">@if ((int) $client->statut === 1)<span class="label label-success">OUI</span>@else<span class="label label-danger">NON</span>@endif</td><td align="center">@if ((int) $client->statut === 1)<a rel="{{ $client->id }}" class="delPromoteur btn btn-danger btn-xs" href="javascript:void(0)"><i class="glyphicon glyphicon-ban-circle"></i></a>@else<a rel="{{ $client->id }}" class="activPromoteur btn btn-success btn-xs" href="javascript:void(0)"><i class="glyphicon glyphicon-ok-circle"></i></a>@endif&nbsp;<a class="btn btn-primary btn-xs" href="/gestion_promoteurs/modifier/{{ $client->id }}"><i class="glyphicon glyphicon-pencil"></i></a>&nbsp;<a rel="{{ $client->id }}" class="push-privileges btn btn-info btn-xs" href="javascript:void(0)"><i class="glyphicon glyphicon-option-vertical"></i></a></td><td align="center"><a rel="{{ $client->id }}" class="trashPromoteur btn btn-danger btn-xs" href="javascript:void(0)"><i class="glyphicon glyphicon-trash"></i></a></td></tr>
		@endforeach
		</tbody>
		</table>
	@endif
	</div>
</div>
<div style="display:none">
	<div id="addPromoteurBox">
    	<h3>Ajouter un compte</h3><br /> 
        <div id="call"></div>
        <form name="f_add_promoteur" id="f_add_promoteur" action="#" method="post">
			@csrf
			<div class="form-group">
				<label for="f_add_promoteur_pseudo">Identifiant<span class="red">*</span></label>
				<input type="text" class="form-control" name="f_add_promoteur_pseudo" value="" />
			</div>
			<div class="form-group">
				<label for="f_add_promoteur_name">NOM Prénom<span class="red">*</span></label>
				<input type="text" class="form-control" name="f_add_promoteur_name" value="" />
			</div>
			<div class="form-group">
				<label for="f_add_promoteur_pwd">Mot de passe<span class="red">*</span></label>
				<input type="password" class="form-control" name="f_add_promoteur_pwd" value="" />
			</div>
			<div class="form-group">
				<label for="f_add_promoteur_email">E-mail<span class="red">*</span></label>
				<input type="text" class="form-control" name="f_add_promoteur_email" value="" />
			</div>
			<div class="form-group">
				<label for="f_add_promoteur_statut">Type de compte<span class="red">*</span></label>
				<select name="f_add_promoteur_statut" class="form-control">
					<option value="0">Commercial</option>
					<option value="1">Administrateur</option>
				</select>
			</div>
			<div class="form-group">
				<input type="submit" id="submit_add_promoteur" class="btn btn-primary" value="Enregistrer" />
			</div>
        </form>
    </div>
</div>
<div class="modal fade" id="privileges" tabindex="-1" role="dialog" aria-labelledby="myModalPrivileges">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="myModalPrivileges">Gestion des privilèges</h4>
      </div>
      <div class="modal-body">
      	<div id="infosUser"></div>
        <div class="alert alert-info text-center">CLIQUEZ SUR L'IMAGE POUR ACC&Eacute;DER AUX LOTS</div>
      	<div id="call"></div>
        @if (count($programmes) > 0)
            <form name="f_gestion_privileges" id="f_gestion_privileges" method="post" action="#">
            @csrf
        	<table cellpadding="5" width="100%" class="table">
            @foreach ($programmes as $start => $programme)
<tr><td align="center"><a href="javascript:void(0)" class="view-privileges"><img src="{{ $programme->thumb }}" alt="vignette_{{ $start }}" width="120" class="img-thumbnail" /></a></td><td align="center"><b>{{ strtoupper(html_entity_decode((string) $programme->projet_titre, ENT_QUOTES)) }}</b><br />{{ strtoupper(html_entity_decode((string) $programme->projet_lieu, ENT_QUOTES)) }}</td><td align="center"><input type="checkbox" id="projectCheck{{ $programme->projet_id }}" name="projet[]" value="{{ $programme->projet_id }}" /></td><td align="center"><a href="javascript:void(0)" class="allcheck_project" rel="{{ $programme->projet_id }}">TOUT COCHER</a>&nbsp;/&nbsp;<a href="javascript:void(0)" class="allcheckout_project" rel="{{ $programme->projet_id }}">D&Eacute;COCHER</a></td></tr><tr id="privileges_{{ $start }}" class="hide"><td colspan="4"><table cellpadding="5" id="acces_table_{{ $programme->projet_id }}" width="100%" class="table">
                	<tr>
                    	<td>&nbsp;</td>
                        <td align="center"><b>R&Eacute;F&Eacute;RENCE</b></td>
                        <td align="center"><b>TYPE</b></td>
                    </tr>
                @foreach ($lots->get((string) $programme->projet_id, []) as $lot)
<tr><td align="center"><input type="checkbox" id="lotCheck{{ $lot->id }}" name="lot[]" value="{{ $lot->id }}" /></td><td align="center">{{ html_entity_decode((string) $lot->numero, ENT_QUOTES) }}</td><td align="center">{{ html_entity_decode((string) $lot->type, ENT_QUOTES) }}</td></tr>
                @endforeach
</table></td></tr>
            @endforeach
            </table>
            <input type="hidden" name="user" id="hiddenUser" value="" />
            </form>
        @endif
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Fermer</button>
        <button type="button" class="btn btn-primary" id="submitPrivileges">Valider</button>
      </div>
    </div>
  </div>
</div>
@endsection

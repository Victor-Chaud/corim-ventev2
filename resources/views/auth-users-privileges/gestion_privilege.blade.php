@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>Gestion des privilèges</h1>
</div>
@if (count($clients) > 0)
    <table width="100%" cellpadding="5" cellspacing="5" class="tablesorter-green">
    <thead> 
    <tr> 
        <td align="center"><b>NOM</b></td>
        <td align="center"><b>EMAIL</b></td>
        <td align="center"><b>IDENTIFIANT</b></td>
        <td align="center"><b>STATUT</b></td>
        <td>&nbsp;</td>
    </tr> 
    </thead> 
    <tbody> 
    @foreach ($clients as $client)
<tr><td align="center">{{ html_entity_decode((string) $client->name, ENT_QUOTES) }}</td><td align="center">{{ html_entity_decode((string) $client->email, ENT_QUOTES) }}</td><td align="center">{{ html_entity_decode((string) $client->pseudo, ENT_QUOTES) }}</td><td align="center">{{ (string) $client->acces === '1' ? 'Administrateur' : 'Commercial' }}</td><td align="center"><a rel="{{ $client->id }}" class="push-privileges btn btn-primary btn-xs" href="javascript:void(0)">Privilèges</a></td></tr>
    @endforeach
    <tbody> 
	</table>
@endif
<div class="modal fade" id="privileges" tabindex="-1" role="dialog" aria-labelledby="myModalPrivileges">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="myModalPrivileges">Gestion des privilèges</h4>
      </div>
      <div class="modal-body">
      	<div id="infosUser"></div>
        <div class="alert alert-info text-center">CLIQUEZ SUR L'IMAGE POUR ACCÉDER AUX LOTS</div>
      	<div id="call"></div>
        @if (count($programmes) > 0)
            <form name="f_gestion_privileges" id="f_gestion_privileges" method="post" action="#">
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

@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>GÉRER LES LOTS</h1>
</div>
<div class="form-group pull-right">
	<a href="/ajouter_lot/show/{{ $GetProjet->projet_titre_key }}" class="btn btn-success">Ajouter un lot</a>
	<a href="#" class="btn btn-danger" id="supprimer_all" rel="{{ $GetProjet->projet_id }}">Supprimer les lots</a>
</div>
<div class="clearfix"></div>
<div class="call"></div>
@if (count($GetLots) > 0)
	<table width="100%" cellpadding="5" cellspacing="5" class="tablesorter-green">
    	<thead>
        <tr>
        	<td align="center"><b>RÉFÉRENCE</b></td>
            <td align="center"><b>STATUT</b></td>
            <td align="center"><b>PROJET</b></td>
            <td align="center"><b>PRIX</b></td>
            <td align="center"><b>ACCESSION</b></td>
            <td align="center"><b>CELLIER</b></td>
            <td align="center"><b>TYPE</b></td>
            <td align="center"><b>CAT.</b></td>
            <td align="center">&nbsp;</td>
        </tr>
        </thead>
        <tbody>
@foreach ($GetLots as $Lot)
        <tr>
        	<td align="center">{{ html_entity_decode((string) $Lot->numero, ENT_QUOTES) }}</td>
            <td align="center">{{ html_entity_decode((string) $Lot->statut, ENT_QUOTES) }}</td>
            <td align="center">{{ html_entity_decode((string) $GetProjet->projet_titre, ENT_QUOTES) }}</td>
            <td align="center">{{ html_entity_decode((string) $Lot->prix, ENT_QUOTES) }}</td>
            <td align="center">{{ html_entity_decode((string) $Lot->accession, ENT_QUOTES) }}</td>
            <td align="center">{{ html_entity_decode((string) $Lot->cellier, ENT_QUOTES) }}</td>
            <td align="center">{{ html_entity_decode((string) $Lot->type, ENT_QUOTES) }}</td>
            <td align="center">{{ html_entity_decode((string) $Lot->categorie, ENT_QUOTES) }}</td>
            <td align="center">
            	<a href="/gerer_lots/modifier/{{ $GetProjet->projet_titre_key }}-{{ $Lot->id }}" class="btn btn-primary btn-xs">Éditer</a><br />
                <a href="javascript:void(0)" class="delete_lot btn btn-danger btn-xs" rel="{{ $GetProjet->projet_id }}-{{ $Lot->id }}">Supprimer</a>
            </td>
        </tr>
@endforeach
        <tbody>
    </table>
@endif
@endsection

@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>Dénonciations</h1>
</div>
@include('workflow.partials.referral-tabs', ['active' => 'accepted'])
<h2>Consultation de vos dénonciations <small>Rechercher une dénonciation</small></h2>
<div class="form-group">
<form name="f_recherche_denonciation" id="f_recherche_denonciation" method="post" action="#" class="form-inline">
<div class="checkbox">
	<label>
    	<input type="radio" name="f_recherche_denonciation_type" checked="checked" value="1"> NOM ACQU&Eacute;REUR
  	</label>
</div>
<div class="checkbox">
	<label>
    	<input type="radio" name="f_recherche_denonciation_type" checked="checked" value="2"> R&Eacute;SIDENCE
  	</label>
</div>
@if ($admin)
<div class="checkbox">
	<label>
    	<input type="radio" name="f_recherche_denonciation_type" checked="checked" value="3"> PRESCRIPTEUR
  	</label>
</div>
@endif
<div class="form-group">
	<label class="sr-only" for="f_recherche_denonciation_search">Rechercher</label>
    <input type="text" class="form-control" id="f_recherche_denonciation_search" name="f_recherche_denonciation_search" placeholder="Rechercher">
</div>
<input type="submit" value="Rechercher" id="f_recherche_denonciation_submit" class="btn btn-primary" />
<a href="/denonciations" class="btn btn-primary">RESET</a>
</form>
</div>
<table width="100%" cellpadding="5" cellspacing="5" class="tablesorter-green" id="f_recherche_denonciation_result">
<thead> 
<tr> 
    <th data-sorter="shortDate" data-date-format="ddmmyyyy">Date</th> 
    <th>Nom</th> 
    <th>Code Postal</th> 
    <th>Ville</th> 
    <th>Programme</th>
    <th>Prescripteurs</th>
    <th>Action</th>
</tr> 
</thead> 
<tbody> 
@foreach ($rows as $referral)
        <tr>
@if ($admin)
                <td align="center">
                    <input size="10" type="text" class="datepicker" id="datepicker_{{ $referral->idprospect }}_den" value="{{ $referral->date }}" />
                    <input type="hidden" type="text" class="datepickeralt" value="" />
                </td>
@else
                <td>{{ $referral->date }}</td>
@endif
            <td>{{ $referral->nomprospect }}</td>
            <td>{{ $referral->cpprospect }}</td>
			<td>{{ $referral->villeprospect }}</td>
			<td>{{ $referral->projet_titre }}</td>
            <td>{{ $referral->prescripteur }}</td>
            <td align="center">
				@if ($referral->smspromoteur != '')<a href="javascript:void(0)" class="see_sms2 btn btn-primary btn-xs" rel="{{ $referral->idprospect }}">Message</a>@endif
@if ($admin)
                	<a href="javascript:void(0)" rel="{{ $referral->idprospect }}" class="delete_denonciation btn btn-danger btn-xs">Supprimer</a>
@endif
            </td>
		</tr>
        <tr class="hide" id="see_sms_{{ $referral->idprospect }}">
			<td colspan="7" class="sms">
				{{ \App\Http\Controllers\Workflow\PagesController::message($referral->smspromoteur) }}
            </td>
		</tr>
@endforeach
</tbody>
</table>
{{ $rows->links() }}
@endsection

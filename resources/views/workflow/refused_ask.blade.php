@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>Dénonciations refusées</h1>
</div>
<div class="form-group">
	<a href="/denonciations" class="pull-right btn btn-primary">Dénonciations</a>
	<div class="clearfix"></div>
</div>

<table width="100%" cellpadding="5" cellspacing="5" class="tablesorter-green">
<thead>
	<tr>
    	<td>Date</td>
        <td>Nom</td>
        <td>CP</td>
        <td>Ville</td>
        <td>Programme</td>
        <td>Prescripteur</td>
        <td>&nbsp;</td>
    </tr>
</thead>
<tbody> 
@foreach ($rows as $refused)
        <tr>
            <td>{{ $refused->date }}</td>
            <td>{{ $refused->nomprospect }}</td>
            <td>{{ $refused->cpprospect }}</td>
			<td>{{ $refused->villeprospect }}</td>
			<td>{{ $refused->projet_titre }}</td>
            <td>{{ $refused->prescripteur }}</td>
            <td align="center">@if ($refused->smspromoteur != '')<a href="javascript:void(0)" class="see_sms btn btn-primary btn-xs">Message</a>@endif</td>
		</tr>
        <tr class="hide">
			<td colspan="7" class="sms">
				{{ \App\Http\Controllers\Workflow\PagesController::message($refused->smspromoteur) }}
            </td>
		</tr>
@endforeach
</tbody>
</table>
@endsection

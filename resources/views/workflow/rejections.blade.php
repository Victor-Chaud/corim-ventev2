@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>{{ $heading }}</h1>
</div>
<div class="pull-right form-group">
@foreach ($links as $url => $label)
	<a href="{{ $url }}" class="btn btn-primary">{{ $label }}</a>
@endforeach
</div>
<div class="clearfix"></div>

<table width="100%" cellpadding="5" cellspacing="5" class="tablesorter-green">
<thead>
	<tr>
		<td align="center"><b>DATE DE LA DEMANDE</b></td>
		<td align="center"><b>DATE DU REFUS</b></td>
		<td align="center"><b>PROJET</b></td>
		<td align="center"><b>LIEU</b></td>
		<td align="center"><b>R&Eacute;F&Eacute;RENCE</b></td>
		<td align="center"><b>NOM ACQUEREUR</b></td>
		<td align="center"><b>ADRESSE ACQU&Eacute;REUR</b></td>
		<td align="center"><b>PRESCRIPTEUR</b></td>
		<td align="center"><b>MESSAGE</b></td>
	</tr>
</thead>
<tbody>
@foreach ($rows as $rejection)
	<tr>
		<td align="center">{{ $rejection->date }}</td>
		<td align="center">{{ $rejection->date_refus }}</td>
		<td align="center">{{ $rejection->projet_titre }}</td>
		<td align="center">{{ $rejection->projet_lieu }}</td>
		<td align="center">{{ $rejection->numero }}</td>
		<td align="center">{{ $rejection->nom }}</td>
		<td align="center">{{ $rejection->adresse }}</td>
		<td align="center">{{ $rejection->name }}</td>
		<td align="center">@if ($rejection->message != '')<a href="javascript:void(0)" class="see_sms btn btn-primary btn-xs">Message</a>@endif</td>
	</tr>
	<tr class="hide">
		<td colspan="9" class="sms">{{ \App\Http\Controllers\Workflow\PagesController::message($rejection->message) }}</td>
	</tr>
@endforeach
</tbody>
</table>
{{ $rows->links() }}
@endsection

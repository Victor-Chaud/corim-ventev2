<!DOCTYPE html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title>Corim promotion - {{ $nom }}</title>
	<link rel="stylesheet" href="/vendor/bootstrap-3.4.1/css/bootstrap.min.css">
	<link href="/vendor/questrial/questrial.css" rel="stylesheet" type="text/css" />
	<link href="/medias/tablesorter/css/theme.green.css" rel="stylesheet" type="text/css" />
	<link href="/medias/css/global.css" rel="stylesheet" type="text/css" />
</head>
<body>
<div class="container-fluid">
	<div class="page-header">
		<img src="/medias/images/logo-corim.png" height="40" alt="Corim Immobilier" />
		<h1>{{ $nom }} <small>mis à jour le {{ now()->format('d.m.Y à H:i') }}</small></h1>
	</div>
@if ($lots !== null)
	<h3>Lots</h3>
	<div class="table-responsive">
	<table width="100%" cellpadding="5" cellspacing="5" class="tablesorter-green">
		<thead><tr><td><b>PROGRAMME</b></td><td><b>VILLE</b></td><td><b>R&Eacute;F&Eacute;RENCE</b></td><td><b>TYPE</b></td><td><b>&Eacute;TAGE</b></td><td><b>SURFACE</b></td><td><b>PRIX</b></td><td><b>STATUT</b></td><td><b>LIVRAISON</b></td><td><b>PLAN</b></td></tr></thead>
		<tbody>
@foreach ($lots as $lot)
			<tr><td>{{ $lot['programme'] }}</td><td>{{ $lot['ville'] }}</td><td>{{ $lot['reference'] }}</td><td>{{ $lot['type'] }}</td><td>{{ $lot['etage'] }}</td><td>{{ $lot['surface'] }}</td><td>{{ $lot['prix'] !== null ? number_format((float) $lot['prix'], 0, ',', ' ').' €' : '' }}</td><td>{{ $lot['statut'] }}</td><td>{{ $lot['livraison'] }}</td><td>@if ($lot['plan'])<a href="{{ $lot['plan'] }}" target="_blank" rel="noopener" class="btn btn-info btn-xs">Plan</a>@else - @endif</td></tr>
@endforeach
		</tbody>
	</table>
	</div>
@endif
	<h3>Dénonciations</h3>
	<div class="table-responsive">
	<table width="100%" cellpadding="5" cellspacing="5" class="tablesorter-green">
		<thead><tr><td><b>DATE</b></td><td><b>STATUT</b></td><td><b>ACQU&Eacute;REUR</b></td><td><b>CP</b></td><td><b>VILLE</b></td><td><b>PROGRAMME</b></td><td><b>DATE DU REFUS</b></td></tr></thead>
		<tbody>
@foreach ($denonciations as $denonciation)
			<tr><td>{{ \Illuminate\Support\Carbon::parse($denonciation['date_demande'])->format('d.m.Y') }}</td><td>{{ $denonciation['statut'] }}</td><td>{{ $denonciation['acquereur'] }}</td><td>{{ $denonciation['code_postal'] }}</td><td>{{ $denonciation['ville'] }}</td><td>{{ $denonciation['programme'] }}</td><td>{{ $denonciation['date_refus'] ? \Illuminate\Support\Carbon::parse($denonciation['date_refus'])->format('d.m.Y') : '' }}</td></tr>
@endforeach
		</tbody>
	</table>
	</div>
</div>
</body>
</html>

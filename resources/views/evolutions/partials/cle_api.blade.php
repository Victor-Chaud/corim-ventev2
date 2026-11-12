<div class="col-xs-12 col-sm-12 col-md-12 form-group">
	<h3>Accès API (lecture seule)</h3>
@if (session('cle_erreur'))
	<div class="alert alert-warning" role="alert">{{ session('cle_erreur') }}</div>
@elseif (session('cle_message'))
	<div class="alert alert-success" role="alert">{{ session('cle_message') }}</div>
@endif
@if ((string) $client->acces !== '0')
	<p class="text-info">Aucune clé API pour un compte admin.</p>
@else
@php($apiKey = session('cle_api'))
@php($cleActive = \App\Http\Controllers\Evolutions\ApiKeyController::active((int) $client->id))
@if ($apiKey)
	<div class="alert alert-warning">
		<p><b>Clé API :</b> <code>{{ $apiKey }}</code></p>
		<p>Elle n'est affichée qu'une seule fois. Ces liens servent de mot de passe : ne les transmettez qu'au prescripteur ou à son prestataire.</p>
		<p>Page lisible dans le navigateur (à mettre en favori) :<br /><input type="text" readonly="readonly" class="form-control" onclick="this.select()" value="{{ url('/api/v1/lots') }}?cle={{ $apiKey }}" /></p>
		<p>Fichier à ouvrir ou brancher dans Excel :<br /><input type="text" readonly="readonly" class="form-control" onclick="this.select()" value="{{ url('/api/v1/lots') }}?cle={{ $apiKey }}&amp;format=csv" /></p>
		<p>Flux JSON pour un webmaster ou un logiciel :<br /><input type="text" readonly="readonly" class="form-control" onclick="this.select()" value="{{ url('/api/v1/lots') }}?cle={{ $apiKey }}&amp;format=json" /></p>
	</div>
@elseif ($cleActive)
	<p>Clé active depuis le {{ \Illuminate\Support\Carbon::parse($cleActive->created_at)->format('d.m.Y') }}, dernière utilisation : {{ $cleActive->last_used_at ? \Illuminate\Support\Carbon::parse($cleActive->last_used_at)->format('d.m.Y à H:i') : 'jamais' }}.</p>
@else
	<p>Aucune clé active.</p>
@endif
	<form action="/gestion_promoteurs/cle/{{ $client->id }}" method="post" style="display:inline">
		@csrf
		<input type="submit" value="{{ $cleActive ? 'Générer une nouvelle clé (coupe l\'ancien lien)' : 'Générer une clé' }}" class="btn btn-primary" />
	</form>
@if ($cleActive)
	<form action="/gestion_promoteurs/cle/{{ $client->id }}/revoquer" method="post" style="display:inline">
		@csrf
		<input type="submit" value="Révoquer la clé" class="btn btn-danger" />
	</form>
@endif
	<a href="/api/v1/documentation" target="_blank" class="btn btn-default">Documentation pour le prestataire</a>
@endif
</div>

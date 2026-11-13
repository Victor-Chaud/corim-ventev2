@extends('layouts.template')

@section('content')
<div class="page-header">
	<h1>Ajouter des lots en masse <small>Programme {{ html_entity_decode($program->projet_titre, ENT_QUOTES) }} à {{ html_entity_decode($program->projet_lieu, ENT_QUOTES) }}</small></h1>
</div>
@if (isset($error))
	<div class="alert alert-warning">{!! htmlspecialchars($error, ENT_NOQUOTES) !!}</div>
@elseif (isset($success))
	<div class="alert alert-success">{!! htmlspecialchars($success, ENT_NOQUOTES) !!}</div>
@endif
<div class="alert alert-info">
	<p>Un lot dont la référence existe déjà dans ce programme est mis à jour (son statut ne change pas) ; une nouvelle référence crée un lot. Aucun lot n'est supprimé par l'import.</p>
	<p>Partez du <a href="/ajouter_lots/modele" class="btn btn-primary btn-xs">modèle de fichier</a> (colonnes dans n'importe quel ordre), puis enregistrez-le dans Excel en « CSV (séparateur : point-virgule) ».</p>
	<p>Pour supprimer des lots déjà enregistrés pour ce projet, cliquez <a href="/gerer_lots/show/{{ $program->projet_titre_key }}" class="btn btn-primary btn-xs">ici</a></p>
</div>

<div class="col-xs-12 col-sm-12 col-md-8 form-group">
	<form action="/ajouter_lots/show/{{ $program->projet_titre_key }}" method="post" enctype="multipart/form-data" class="form-inline">
		@csrf
		<div class="col-xs-12 col-sm-6 col-md-6">
			<div class="form-group">
				<label for="csv_upload">Mettre en ligne votre fichier au format CSV</label>
				<input type="file" name="csv_upload" id="csv_upload" />
				<p class="help-block">Le fichier doit être au format CSV avec séparteur point-virgule</p>
			</div>
		</div>
		<div class="col-xs-12 col-sm-6 col-md-6 text-right">
			<input type="submit" value="Enregistrer en masse" class="btn btn-primary" />
		</div>
		<div class="clearfix"></div>

		<input type="hidden" name="name" value="{{ $program->projet_id }}" />
		<input type="hidden" name="departement" value="{{ html_entity_decode((string) $program->dept, ENT_QUOTES) }}" />
		<input type="hidden" name="ville" value="{{ html_entity_decode($program->projet_lieu, ENT_QUOTES) }}" />
		<input type="hidden" name="csv" value="{{ $csvRows !== null ? '1' : '0' }}" />
	</form>
</div>
@if ($csvRows !== null)
	<div class="col-xs-12 col-sm-12 col-md-4 form-group">
		<form action="/ajouter_lots/show/{{ $program->projet_titre_key }}" method="post" enctype="multipart/form-data" class="form-inline">
			@csrf
			<div class="col-xs-12 col-sm-12 col-md-12 text-left">
				<input type="hidden" name="csv_delete" value="true" />
				<input type="submit" value="Supprimer le fichier CSV du serveur" class="btn btn-danger" />
			</div>
		</form>
	</div>

	<div class="col-xs-12 col-sm-12 col-md-12 form-group">
		<h3>Fichier CSV sur le serveur</h3>
		<table width="100%" cellpadding="5" cellspacing="5" class="tablesorter-green special-small-size">
			<thead>
				<tr>
					<td align="center">Date de livraison</td>
					<td align="center">Type</td>
					<td align="center">Catégorie</td>
					<td align="center">Prix</td>
					<td align="center">Prix m&sup2;</td>
					<td align="center">Etage</td>
					<td align="center">RDC</td>
					<td align="center">Jardin</td>
					<td align="center">Terrasse</td>
					<td align="center">Superficie</td>
					<td align="center">Stationnement</td>
					<td align="center">Exposition</td>
					<td align="center">Description</td>
					<td align="center">Plan d'architecte</td>
					<td align="center">Référence</td>
					<td align="center">Cellier</td>
					<td align="center">Accession</td>
					<td align="center">Import</td>
				</tr>
			</thead>
			<tbody>
@foreach ($csvRows as $row)
<tr>
@foreach ($columns as $column)
<td align="center" {!! ($row[$column] ?? '') === '' ? 'style="background:#A30106"' : '' !!}>{{ $row[$column] ?? '' }}</td>
@endforeach
<td align="center">{{ $row['action'] }}</td>
</tr>
@endforeach
			</tbody>
		</table>
	</div>
@endif
@endsection

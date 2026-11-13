@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>Ajouter un lot</h1>
</div>
@if (isset($error))
<div class="alert alert-warning">{!! htmlspecialchars($error, ENT_NOQUOTES) !!}</div>
@elseif (isset($success))
<div class="alert alert-success">{!! htmlspecialchars($success, ENT_NOQUOTES) !!}</div>
@endif
<div class="col-xs-12 col-sm-12 col-md-12">
<table align="center" cellpadding="5" cellspacing="5">
<form action="/ajouter_lot/show/{{ $program->projet_titre_key }}" method="post" enctype="multipart/form-data">
@csrf

	<div class="form-group">
        <label for="name">Nom du projet</label>
        <input type="text" name="name" id="name" class="form-control" value="{{ html_entity_decode($program->projet_titre, ENT_QUOTES) }}" readonly />
    </div>
    
    <div class="form-group">
        <label for="date">Livraison</label>
        <input type="text" name="date" id="date" class="dateinput form-control" value="" />
    </div>
    
    <div class="form-group">
        <label for="departement">Département</label>
        <input type="text" name="departement" id="departement" class="form-control" value="{{ html_entity_decode((string) $program->dept, ENT_QUOTES) }}" readonly />
    </div>
    
    <div class="form-group">
        <label for="ville">Ville</label>
        <input type="text" name="ville" id="ville" class="form-control" value="{{ html_entity_decode($program->projet_lieu, ENT_QUOTES) }}" readonly />
    </div>
    
    <div class="form-group">
        <label for="type">Type</label>
        <select name="type" id="type" class="form-control">
@foreach (\App\Http\Controllers\ProgramsLots\LotsController::TYPES as $option)
			<option value="{{ $option }}">{{ $option }}</option>
@endforeach
		</select>
    </div>
    
    <div class="form-group">
        <label for="cat">Catégorie</label>
        <select name="cat" id="cat" class="form-control">
@foreach (\App\Http\Controllers\ProgramsLots\LotsController::CATEGORIES as $option)
			<option value="{{ $option }}">{{ $option }}</option>
@endforeach
		</select>
    </div>
    
    <div class="form-group">
        <label for="prix">Prix</label>
        <input type="text" name="prix" id="prix" class="form-control" value="" />
    </div>
    
    <div class="form-group">
        <label for="prix_m2">Prix m²</label>
        <input type="text" name="prix_m2" id="prix_m2" class="form-control" value="" />
    </div>
    
    <div class="form-group">
        <label>Spécificité</label>
    </div>
    <div class="form-group">
		<label class="checkbox-inline">
			<input type="checkbox" id="option-etage" name="option[]" value="etage"> Etage
		</label>
		<label class="checkbox-inline">
			<input type="checkbox" id="option-RDC" name="option[]" value="RDC"> RDC
		</label>
		<label class="checkbox-inline">
			<input type="checkbox" id="option-jardin" name="option[]" value="jardin"> Jardin
		</label>
		<label class="checkbox-inline">
			<input type="checkbox" id="option-terrain" name="option[]" value="terrasse"> Terrain
		</label>
	</div>
   
   	<div class="form-group">
        <label for="superficie">Superficie</label>
        <input type="text" name="superficie" id="superficie" class="form-control" value="" />
    </div>
   
    <div class="form-group">
        <label for="stationnement">Stationnement</label>
        <input type="text" name="stationnement" id="stationnement" class="form-control" value="" />
    </div>
    
    <div class="form-group">
        <label for="exposition">Exposition</label>
        <input type="text" name="exposition" id="exposition" class="form-control" value="" />
    </div>

    <div class="form-group">
        <label for="description">Description</label>
        <textarea name="description" id="description" rows="5" class="form-control"></textarea>
    </div>
    
    <div class="form-group">
        <label for="numero">Numéro</label>
        <input type="text" name="numero" id="numero" class="form-control" value="" />
    </div>
    <div class="form-group">
        <label for="cellier">Cellier</label>
        <input type="text" name="cellier" id="cellier" class="form-control" value="" />
    </div>
    <div class="form-group">
        <label for="accession">Accession</label>
        <input type="text" name="accession" id="accession" class="form-control" value="" />
    </div>
    
    <div class="form-group">
        <label for="plan">Plan d'architecte</label>
        <input type="file" name="plan" id="plan" />
    </div>
    
	<div class="form-group text-center">
		<input type="submit" value="ENREGISTRER" class="btn btn-primary" />
    </div>
   
    <input type="hidden" name="projet_id" value="{{ $program->projet_id }}" />
    <input type="hidden" name="departement" value="{{ html_entity_decode((string) $program->dept, ENT_QUOTES) }}" />
    <input type="hidden" name="ville" value="{{ html_entity_decode($program->projet_lieu, ENT_QUOTES) }}" />
    
</form>
</table>
</div>
@endsection

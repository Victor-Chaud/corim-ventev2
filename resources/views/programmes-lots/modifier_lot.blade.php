@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>Modifier un lot <small>Programme {{ html_entity_decode($GetProjet->projet_titre, ENT_QUOTES) }} à {{ html_entity_decode($GetProjet->projet_lieu, ENT_QUOTES) }}</small></h1>
</div>
@if (isset($error))
	<div class="alert alert-warning">{!! htmlspecialchars($error, ENT_NOQUOTES) !!}</div>
@elseif (isset($success))
	<div class="alert alert-success">{!! htmlspecialchars($success, ENT_NOQUOTES) !!} <a href="/gerer_lots/show/{{ $GetProjet->projet_titre_key }}">Retour à la liste des lots</a></div>
@endif
<div class="col-xs-12 col-sm-12 col-md-12">
<form action="/gerer_lots/modifier/{{ $GetProjet->projet_titre_key }}-{{ $GetLot->id }}" method="post" enctype="multipart/form-data">
@csrf
<div class="col-xs-12 col-sm-6 col-md-4">
    <div class="form-group">
        <label for="date">Livraison</label>
        <input type="text" name="date" id="date" value="{{ html_entity_decode((string) $GetLot->date, ENT_QUOTES) }}" class="form-control" />
    </div>
    <div class="form-group">
        <label for="date">Référence</label>
        <input type="text" name="numero" id="numero" value="{{ html_entity_decode((string) $GetLot->numero, ENT_QUOTES) }}" class="form-control" />
    </div>
    <div class="form-group">
        <label for="type">Type</label>
        <select name="type" id="type" class="form-control">
@foreach (in_array($GetLot->type, $types, true) ? $types : [...$types, $GetLot->type] as $option)
            <option value="{{ $option }}" {!! $GetLot->type === $option ? 'selected="selected"' : '' !!}>{{ $option }}</option>
@endforeach
        </select>
    </div>
    <div class="form-group">
        <label for="cat">Catégorie</label>
        <select name="cat" id="cat" class="form-control">
@foreach (in_array($GetLot->categorie, $categories, true) ? $categories : [...$categories, $GetLot->categorie] as $option)
            <option value="{{ $option }}" {!! $GetLot->categorie === $option ? 'selected="selected"' : '' !!}>{{ $option }}</option>
@endforeach
        </select>
    </div>
    <div class="form-group">
        <label for="prix">Prix</label>
        <input type="text" name="prix" id="prix" value="{{ html_entity_decode((string) $GetLot->prix, ENT_QUOTES) }}" class="form-control" />
    </div>
    <div class="form-group">
        <label for="prix_m2">Prix au m²</label>
        <input type="text" name="prix_m2" id="prix_m2" value="{{ html_entity_decode((string) $GetLot->prix_m2, ENT_QUOTES) }}" class="form-control" />
    </div>
</div> 
<div class="col-xs-12 col-sm-6 col-md-4">
	<div class="form-group">
        <label for="cellier">Cellier</label>
        <input type="text" name="cellier" id="cellier" value="{{ html_entity_decode((string) $GetLot->cellier, ENT_QUOTES) }}" class="form-control" />
    </div>
	<div class="form-group">
        <label for="accession">Accession</label>
        <input type="text" name="accession" value="{{ html_entity_decode((string) $GetLot->accession, ENT_QUOTES) }}" class="form-control" />
    </div>
    <div class="form-group">
        <label for="etage">Etage</label>
        <input type="text" name="etage" id="etage" value="{{ html_entity_decode((string) $GetLot->etage, ENT_QUOTES) }}" class="form-control" />
    </div>
    <div class="form-group">
        <label for="rdc">RDC</label>
        <input type="text" name="rdc" id="rdc" value="{{ html_entity_decode((string) $GetLot->RDC, ENT_QUOTES) }}" class="form-control" />
    </div>
    <div class="form-group">
        <label for="jardin">Jardin</label>
        <input type="text" name="jardin" id="jardin" value="{{ html_entity_decode((string) $GetLot->jardin, ENT_QUOTES) }}" class="form-control" />
    </div>
    <div class="form-group">
        <label for="terrasse">Terasse</label>
        <input type="text" name="terrasse" id="terrasse" value="{{ html_entity_decode((string) $GetLot->terrasse, ENT_QUOTES) }}" class="form-control" />
    </div>
</div>
<div class="col-xs-12 col-sm-6 col-md-4">
	<div class="form-group">
        <label for="superficie">Superficie en m²</label>
        <input type="text" name="superficie" id="superficie" value="{{ html_entity_decode((string) $GetLot->superficie, ENT_QUOTES) }}" class="form-control" />
    </div>
    <div class="form-group">
        <label for="stationnement">Stationnement</label>
        <input type="text" name="stationnement" id="stationnement" value="{{ html_entity_decode((string) $GetLot->stationnement, ENT_QUOTES) }}" class="form-control" />
    </div>
    <div class="form-group">
        <label for="exposition">Exposition</label>
        <input type="text" name="exposition" id="exposition" value="{{ html_entity_decode((string) $GetLot->exposition, ENT_QUOTES) }}" class="form-control" />
    </div>
    <div class="form-group">
        <label for="plan">Plan d'architecte</label>
        <input type="file" name="plan" id="plan" />
    </div>
    <div class="form-group text-center">
    	<input type="submit" value="Enregistrer" class="btn btn-primary" />
    </div>
</div>
<div class="clearfix"></div> 
<div class="col-xs-12 col-sm-12 col-md-12">
	<label for="description">Description</label>
    <textarea name="description" rows="10" class="form-control">{{ html_entity_decode((string) $GetLot->description, ENT_QUOTES) }}</textarea>
</div>  
    <input type="hidden" name="name" value="{{ $GetProjet->projet_id }}" />
    <input type="hidden" name="departement" value="{{ html_entity_decode((string) $GetProjet->dept, ENT_QUOTES) }}" />
    <input type="hidden" name="idlot" value="{{ $GetLot->id }}" />
    <input type="hidden" name="ville" value="{{ html_entity_decode($GetProjet->projet_lieu, ENT_QUOTES) }}" />
</form>
</div>
@endsection

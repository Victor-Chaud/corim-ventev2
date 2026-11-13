@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>Modifier un programme</h1>
</div>
@if (isset($message_valid))
<div class="alert alert-success">{!! htmlspecialchars($message_valid, ENT_NOQUOTES) !!}</div>
@endif
@if (isset($error))
<div class="alert alert-warning">{!! htmlspecialchars($error, ENT_NOQUOTES) !!}</div>
@endif
<div class="col-xs-12 col-sm-12 col-md-12 form-group">
    <h2>Informations générales</h2>
    <form action="/modifier_programme/show/{{ $GetProgram->projet_titre_key }}" method="post" enctype="multipart/form-data">
    @csrf
    <div class="col-xs-12 col-sm-6 col-md-6">
        <div class="form-group">
            <label for="titre">Titre</label>
            <input type="text" name="titre" id="titre" value="{{ html_entity_decode((string) $GetProgram->projet_titre, ENT_QUOTES) }}" class="form-control" />
        </div>
        <div class="form-group">
            <label for="livraison">Livraison</label>
            <input type="text" name="livraison" id="livraison" value="{{ html_entity_decode((string) $GetProgram->livraison, ENT_QUOTES) }}" class="form-control" />
        </div>
        <div class="form-group">
            <label for="ville">Ville</label>
            <input type="text" name="ville" id="ville"  value="{{ html_entity_decode((string) $GetProgram->projet_lieu, ENT_QUOTES) }}" class="form-control" />
        </div>
        <div class="form-group">
            <label for="dept">Département</label>
            <input type="text" name="dept" id="dept"  value="{{ html_entity_decode((string) $GetProgram->dept, ENT_QUOTES) }}" class="form-control" />
        </div>
    </div>
    <div class="col-xs-12 col-sm-6 col-md-6">
        <div class="form-group">
            <label for="description">Description</label>
            <textarea rows="12" type="text" name="description" id="description" class="form-control">{{ html_entity_decode((string) $GetProgram->projet_description, ENT_QUOTES) }}</textarea>
        </div>
    </div>
    <div class="clearfix"></div>
    <div class="col-xs-12 col-sm-6 col-md-6">
    	<div class="media">
          <div class="media-left">
@if ($thumb)
                    <img src="/medias/programmes/{{ $GetProgram->projet_titre_key }}/thumb/thumb.{{ $thumb }}" class="media-object" />
@else
                    Aucune image
@endif
          </div>
          <div class="media-body">
          	<h4 class="media-heading">Image de présentation</h4>
            <input type="file" name="thumb" id="thumb" />
          </div>
        </div>
    </div>
    <div class="col-xs-12 col-sm-6 col-md-6 text-center">
        <input type="submit" value="Enregistrer" class="btn btn-primary" />
    </div>
    	<div class="clearfix"></div>
    	<input type="hidden" name="key" value="{{ $GetProgram->projet_titre_key }}" />
    	<input type="hidden" name="idprojet" value="{{ $GetProgram->projet_id }}" />
		<input type="hidden" name="action" value="project" />
	</form>
</div>
<div class="col-xs-12 col-sm-12 col-md-12 well">
	<h2>Téléchargement de fichier</h2>
	<form action="/modifier_programme/show/{{ $GetProgram->projet_titre_key }}" method="post" enctype="multipart/form-data" class="form-inline">
		@csrf
		<div class="form-group">
			<input type="file" name="filer[]" value="" multiple />
		</div>
		<div class="form-group">
			<select name="type" class="form-control">
				<option value="">CHOISIR</option>
				<option value="visuels">VISUELS</option>
				<option value="documents">DOCUMENTS</option>
				<option value="plans">PLANS</option>
			</select>
		</div>
		<input type="submit" value="Envoyer" class="btn btn-primary" />
		<input type="hidden" name="action" value="uploader" />
	</form>
</div>
<div class="col-xs-12 col-sm-12 col-md-12 well">
	<h2>Suppression de fichier</h2>
	<a data-action="delete-selected-files" href="javascript:void(0);" class="btn btn-danger">Supprimer les fichiers sélectionnés</a>
</div>
<div class="col-xs-12 col-sm-12 col-md-12">
    <h2>Visuels</h2>
        <ul class="list-group">
@include('programs-lots.partials.files', ['files' => $Get_Visuals])
        </ul>
</div>
<div class="col-xs-12 col-sm-12 col-md-12">
    <h2>Documents</h2>
        <ul class="list-group">
@include('programs-lots.partials.files', ['files' => $Get_Docs])
        </ul>
</div>
<div class="col-xs-12 col-sm-12 col-md-12">
    <h2>Plans</h2>
        <ul class="list-group">
@include('programs-lots.partials.files', ['files' => $Get_Plans])
        </ul>
</div>
@endsection

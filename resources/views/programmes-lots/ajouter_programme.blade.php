@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>Ajouter un programme</h1>
</div>
@if (isset($error))
<div class="alert alert-warning">{!! htmlspecialchars($error, ENT_NOQUOTES) !!}</div>
@elseif (isset($message_valid))
<div class="alert alert-success">{!! htmlspecialchars($message_valid, ENT_NOQUOTES) !!}</div>
@endif
<div class="col-xs-12 col-sm-12 col-md-12">
<form action="/ajouter_programme" method="post" enctype="multipart/form-data">
@csrf
<div class="col-xs-12 col-sm-6 col-md-6">
    <div class="form-group">
        <label for="titre">Titre du programme</label>
        <input type="text" name="titre" id="titre" class="form-control" />
    </div>
    <div class="form-group">
        <label for="livraison">Livraison</label>
        <input type="text" name="livraison" id="livraison" class="form-control" />
        <p class="text-info">
            Indiquer par ex: <b style="color:#d1541d">2T2015</b> pour 2ème Trimestre 2015
        </p>
    </div>
    <div class="form-group">
        <label for="ville">Ville</label>
        <input type="text" name="ville" id="ville" class="form-control" />
    </div>
    <div class="form-group">
        <label for="thumb">Vignette</label>
        <input type="file" name="thumb" id="thumb" />
    </div>
</div>
<div class="col-xs-12 col-sm-6 col-md-6">
    <div class="form-group">
        <label for="description">Description</label>
        <textarea name="description" id="description" rows="10" class="form-control"></textarea>
    </div>
    <div class="form-group text-center">
		<input type="submit" value="VALIDER" class="btn btn-primary" />
    </div>
</div>
</form>
</div>
@endsection

@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>Ajouter un client</h1>
</div>
@if (isset($error))
	<div class="alert alert-warning">{!! htmlspecialchars($error, ENT_NOQUOTES) !!}</div>
@elseif (isset($success))
	<div class="alert alert-success">{!! htmlspecialchars($success, ENT_NOQUOTES) !!}</div>
@endif
<div class="col-xs-12 col-sm-12 col-md-12 form-group">
<form action="{{ url('gestion_clients/ajouter_client') }}" method="post">
	@csrf
	<div class="col-xs-12 col-sm-6 col-md-6">
    	<div class="form-group">
        	<label for="date">DATE</label>
            <input type="text" name="date" id="date" value="" class="form-control" />
        </div>
        <div class="form-group">
        	<label for="source">SOURCE</label>
            <input type="text" name="source" id="source" value="" class="form-control" />
        </div>
        <div class="form-group">
        	<label for="projets">PROJET(S)</label>
            <input type="text" name="projets" id="projets" value="" class="form-control" />
        </div>
        <div class="form-group">
        	<label for="civilite">CIVILIT&Eacute;</label>
            <input type="text" name="civilite" id="civilite" value="" class="form-control" />
        </div>
        <div class="form-group">
        	<label for="nom">NOM</label>
            <input type="text" name="nom" id="nom" value="" class="form-control" />
        </div>
        <div class="form-group">
        	<label for="adresse">ADRESSE</label>
            <input type="text" name="adresse" id="adresse" value="" class="form-control" />
        </div>
    </div>
    <div class="col-xs-12 col-sm-6 col-md-6">
    	<div class="form-group">
        	<label for="tel">T&Eacute;L</label>
            <input type="text" name="tel" id="tel" value="" class="form-control" />
        </div>
        <div class="form-group">
        	<label for="email">EMAIL</label>
            <input type="email" name="email" id="email" value="" class="form-control" />
        </div>
        <div class="form-group">
        	<label for="type_achat">TYPE D'ACHAT</label>
            <input type="text" name="type_achat" id="type_achat" value="" class="form-control" />
        </div>
        <div class="form-group">
        	<label for="type_logement">TYPE DE LOGEMENT</label>
            <input type="text" name="type_logement" id="type_logement" value="" class="form-control" />
        </div>
        <div class="form-group">
        	<label for="budget">BUDGET</label>
            <input type="text" name="budget" id="budget" value="" class="form-control" />
        </div>
    </div>
    <div class="clearfix"></div>
    <div class="col-xs-12 col-sm-6 col-md-6">
    	<div class="form-group">
        	<label for="observation">OBSERVATION</label>
            <textarea name="observation" id="observation" rows="8" class="form-control"></textarea>
        </div>
    </div>
    <div class="col-xs-12 col-sm-6 col-md-6">
    	<div class="form-group">
        	<label for="reponse">R&Eacute;PONSE</label>
            <textarea name="reponse" id="reponse" rows="8" class="form-control"></textarea>
        </div>
    </div>
    <div class="clearfix"></div>
    <input type="submit" value="Enregistrer le client" class="btn btn-primary" />
</form>
</div>
@endsection

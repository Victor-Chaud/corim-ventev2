@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>Nous contacter</h1>
</div>
@if ($erreurs)
<div class="alert alert-warning" role="alert">@foreach ($erreurs as $erreur){!! $erreur !!}@endforeach</div>
@endif
@if ($valid)
<div class="alert alert-success" role="alert">{{ $valid }}</div>
@endif
<form method="post" action="/contact">
    @csrf
    <div class="form-group">
        <label for="objet">Objet du message</label>
        <input type="text" value="" name="objet" id="objet" class="form-control">
    </div>
    <div class="form-group">
        <label for="programme">Programme concerné</label>
        <select name="programme" id="programme" class="form-control">
              <option value="0">Choisir un programme</option>
              @foreach ($programmes as $programme)
<option value="{{ html_entity_decode((string) $programme->projet_titre, ENT_QUOTES) }}">{{ \App\Http\Controllers\Communication\SafeHtml::clean($programme->projet_titre) }}</option>
              @endforeach
          </select>
    </div>
    <div class="form-group">
        <label for="message">Votre message</label>
        <textarea cols="80" rows="14" name="message" id="message" class="form-control"></textarea>
    </div>
    <input type="submit" value="ENVOYER LE MESSAGE" name="submit" class="btn btn-primary">
</form>
@endsection

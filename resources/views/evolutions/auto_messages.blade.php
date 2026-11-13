@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>Messages automatiques</h1>
</div>
@if ($success)
<div class="alert alert-success" role="alert">{{ $success }}</div>
@endif
@if ($errors->any())
<div class="alert alert-warning"><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<div class="alert alert-info">
	<p>Chaque texte pré-remplit la zone « Laisser un message au prescripteur » de la décision correspondante. Il reste modifiable au cas par cas avant l'envoi.</p>
	<p>Variables remplacées à l'envoi : <b>{prescripteur}</b>, <b>{programme}</b>, <b>{lot}</b>, <b>{acquereur}</b>. La signature est ajoutée automatiquement en bas de chaque mail au prescripteur.</p>
</div>
<form action="/messages_automatiques" method="post">
	@csrf
@foreach ($fields as $key => $label)
	<div class="col-xs-12 col-sm-6 col-md-6">
		<div class="form-group">
			<label for="{{ $key }}">{{ $label }}</label>
			<textarea name="{{ $key }}" id="{{ $key }}" rows="6" class="form-control">{{ old($key, $texts[$key]) }}</textarea>
		</div>
	</div>
@endforeach
	<div class="clearfix"></div>
	<div class="form-group text-center">
		<input type="submit" value="ENREGISTRER" class="btn btn-primary" />
	</div>
</form>
@endsection

@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>Ajouter des clients en masse</h1>
</div>
@if (isset($error))
	<div class="alert alert-warning">{!! htmlspecialchars($error, ENT_NOQUOTES) !!}</div>
@elseif (isset($success))
	<div class="alert alert-success">{!! htmlspecialchars($success, ENT_NOQUOTES) !!}</div>
@endif
<script type="text/javascript" nonce="{{ Vite::cspNonce() }}">
$(function() {
	$('#csv_upload_file').on('change', function() {
		if (!this.files.length) {
			return;
		}
		var data = new FormData();
		data.append('Filedata', this.files[0]);
		fetch('/gestion_clients/uploadify', {
			method: 'POST',
			headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
			body: data
		}).then(function(response) {
			return response.text();
		}).then(function(text) {
			if (text == 'Invalid file type.') {
				alert('Le format de fichier CSV n\'est pas reconnu');
			} else {
				document.location = '/gestion_clients/ajouter_clients';
			}
		});
	});
});
</script> 
<div class="col-xs-12 col-sm-12 col-md-12 form-group">
<form action="{{ url('gestion_clients/ajouter_clients') }}" method="post" enctype="multipart/form-data">
    @csrf
    <div class="col-xs-12 col-sm-6 col-md-6">
        <div class="form-group">
            <label for="csv_upload_file">Fichier au format CSV</label>
            <div id="csv_upload" class="uploadify" style="height: 30px; width: 120px;">
                <label for="csv_upload_file" class="uploadify-button" style="height: 30px; line-height: 30px; width: 120px;"><span class="uploadify-button-text">Parcourir</span></label>
                <input type="file" name="csv_upload" id="csv_upload_file" accept=".csv" class="hide" />
            </div>
        </div>
    </div>
    <div class="col-xs-12 col-sm-6 col-md-6">
    	<input type="submit" value="Enregistrer en masse" class="btn btn-primary" />
		@if ($rows !== null)
            <button type="submit" name="csv_delete" value="1" class="btn btn-danger">Supprimer le fichier CSV du serveur</button>
        @endif
    </div>
    <div class="clearfix"></div>
</div>
	<input type="hidden" name="csv" value="{{ $rows !== null ? '1' : '0' }}" />
</form>


@if ($rows !== null)
    <table width="100%" cellpadding="5" cellspacing="5" class="tablesorter-green special-small-size">
	<thead>
        <tr>
            <td align="center">Date</td>
            <td align="center">Source</td>
            <td align="center">Projets</td>
            <td align="center">Civilité</td>
            <td align="center">Nom</td>
            <td align="center">Adresse</td>
            <td align="center">Tél.</td>
            <td align="center">E-mail</td>
            <td align="center">Type achat</td>
            <td align="center">Type logement</td>
            <td align="center">Budget</td>
            <td align="center">Observation</td>
            <td align="center">Réponse</td>
        </tr>
    </thead>
    <tbody>
	@foreach ($rows as $ligne)
<tr>@foreach ($ligne as $cellule)<td align="center">{{ $cellule }}</td>@endforeach</tr>
	@endforeach
    </tbody>
    </table>
@endif
@endsection

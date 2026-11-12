@extends('layouts.template')

@section('content')
<h3>Gestion du diaporama</h3><br />
<div style="padding:10px;text-align:justify">
	<p style="float:left; margin-right:20px; margin-bottom:20px; margin-top:4px; text-align:center;">
    	<img width="120" alt="vignette" src="{{ $image }}" /><br /><br />
    </p>
    <div style="font-size:18px; font-weight:bold;">{{ strtoupper(html_entity_decode($programme->projet_titre.' - '.$programme->projet_lieu, ENT_QUOTES)) }}</div><br />{{ html_entity_decode((string) $programme->projet_description, ENT_QUOTES) }}<div class="clear"></div>    <form name="f_upload_diapo" action="/gestion_programmes/diaporama_projet/{{ $programme->projet_titre_key }}" method="post" enctype="multipart/form-data">
        @csrf
    	<label for="diapo"></label>
        <input type="file" name="diapo" />
        <input type="submit" value="Enregistrer" />
    </form><br />
@if (count($diapos) > 0)
        <table cellpadding="5" cellspacing="5" align="center">
        	<tr>
@foreach ($diapos as $diapo)
@if ($loop->index > 0 && $loop->index % 4 === 0)
</tr><tr>
@endif
<td align="center"><img src="{{ $diapo }}" width="170" /><br /><a rel="{{ $diapo }}" class="delete_diapo" href="#">Supprimer</a></td>
@endforeach
        	</tr>
        </table>
@endif
    <div class="clear"></div>
</div>
@endsection

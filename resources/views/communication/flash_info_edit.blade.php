@extends('layouts.template')

@php($d = \App\Http\Controllers\Communication\SafeHtml::clean(...))
@php($raw = fn ($v) => html_entity_decode((string) $v, ENT_QUOTES))
@section('content')
<div class="page-header">
  <h1>Modifier le Flash Info {{ $d($info->string1) }}</h1>
</div>
@if ($valid)
<div class="alert alert-success">{{ $valid }}</div>
@endif
@if ($error)
<div class="alert alert-warning">{{ $error }}</div>
@endif
<div class="col-xs-12 col-sm-12 col-md-12">
    <form action="/flash_info/edit/{{ $info->id }}" method="post" enctype="multipart/form-data">
    @csrf
    <div class="form-group">
        <label for="string1">Titre</label>
        <input type="text" name="string1" id="string1" value="{{ $raw($info->string1) }}" class="form-control" />
    </div>
    
    <div class="form-group">
        <label for="string1">Lien sur le titre</label>
        <input type="text" name="url" id="url" value="{{ $raw($info->url) }}" class="form-control" />
    </div>
    
    <div class="form-group">
        <label for="string2">Sous Titre</label>
        <input type="text" name="string2" id="string2" value="{{ $raw($info->string2) }}" class="form-control" />
    </div>
    
    <div class="form-group">
        <label for="string3">Info</label>
        <textarea rows="6" type="text" name="string3" id="string3" class="form-control">{{ $raw($info->string3) }}</textarea>
    </div>
    
    <div class="form-group">
        <label for="string3">Footer</label>
        <textarea rows="6" type="text" name="string4" id="string4" class="form-control">{{ $raw($info->string4) }}</textarea>
    </div>
    
    <div class="form-group">
    	<label for="thumb">Vignette</label>
        <div class="media">
          <div class="media-left">
            @if ((string) $info->thumb !== '')
                <img src="/medias/news/{{ $info->thumb }}.jpg" class="media-object" />
            @else
            Aucune image
            @endif
          </div>
          <div class="media-body">
            <select name="thumb" id="thumb" class="form-control">
					@foreach ($programmes as $programme)
                        <option value="{{ $programme->projet_id }}" {!! (string) $info->thumb === (string) $programme->projet_id ? 'selected="selected"' : '' !!}>{{ $d($programme->projet_titre) }}</option>
                    @endforeach
                <option value="0" {!! (string) $info->thumb === '0' ? 'selected="selected"' : '' !!}>AUTRE</option>
            </select>
          </div>
        </div>
    </div>
    
    <div class="form-group text-right">
        <input type="submit" value="Enregistrer" class="btn btn-primary" /> 
    </div>
    
    <input type="hidden" name="id" value="{{ $info->id }}" />
	</form>
</div>
<script>
CKEDITOR.replace( 'string3' );
CKEDITOR.replace( 'string4' );
</script>
@endsection

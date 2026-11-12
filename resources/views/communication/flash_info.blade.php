@extends('layouts.template')

@php($d = \App\Http\Controllers\Communication\SafeHtml::clean(...))
@section('content')
<div class="page-header">
  <h1>Flash Info pour Corim Promotion <small><a href="/flash_info/add">Ajouter un Flash Info</a></small></h1>
</div>
@if (count($infos) > 0)
    <table width="100%" cellpadding="5" cellspacing="5" class="tablesorter-green">
    <thead> 
    <tr> 
        <th width="10%" data-sorter="shortDate" data-date-format="ddmmyyyy">Date</th> 
        <th width="20%">Vignette</th> 
        <th width="50%">Titre</th> 
        <th width="10%"></th> 
        <th width="10%"></th> 
    </tr> 
    </thead> 
    <tbody> 
    @foreach ($infos as $info)
        <tr>
        	<td align="center" width="10%">{{ date('d/m/Y', (int) $info->date) }}</td>
            <td align="center" width="20%"><img src="/medias/news/{{ $info->thumb }}.jpg" height="160" /></td>
            <td align="left" width="50%">
                @if ((string) $info->url !== '' && \App\Http\Controllers\Communication\SafeHtml::safeUrl($info->url))
                	<h3><a href="{{ $info->url }}">{{ $d($info->string1) }}</a></h3>
                @else
                	<h3>{{ $d($info->string1) }}</h3>
                @endif
                <h4>{{ $d($info->string2) }}</h4>
                {{ $d($info->string3) }}
                {{ $d($info->string4) }}
            </td>
            <td align="center" width="10%">
                <a class="btn btn-info btn-xs" href="/flash_info/edit/{{ $info->id }}">Editer</a>
            </td>
            <td align="center" width="10%">
            	<a class="btn btn-danger btn-xs" href="/flash_info/delete/{{ $info->id }}" onclick="this.nextElementSibling.submit();return false;">Supprimer</a><form method="post" action="/flash_info/delete/{{ $info->id }}" hidden>@csrf</form>
            </td>
        </tr>
    @endforeach
    </tbody>
    </table>
@endif
@endsection

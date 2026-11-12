@extends('layouts.template')

@php($d = \App\Http\Controllers\Communication\SafeHtml::clean(...))
@section('content')
<div class="page-header">
  <h1>Messagerie privée {!! auth()->user()?->isAdmin() ? '<small><a href="/message/envoyer">Envoyer un message</a></small>' : '' !!} @if ($reading && (int) $reading->idemetteur !== (int) auth()->id())<small><a href="/message/repondre/{{ $reading->cle }}">Répondre au message</a></small>@endif</h1>
</div>
@if ($deleted !== null)
<div class="success">{{ $deleted }} {{ $deleted > 1 ? 'messages effacés' : 'message effacé' }} avec succès</div>
@endif
@if ($messages)
<form class="form" method="post" id="form_messagerie" action="/messagerie/supprimer">@csrf<input id="delete_message" type="hidden" name="delete_message" value="">
@foreach ($messages as $m)
<div class="{{ (int) $m->lu === 0 ? 'news' : 'news_read' }}"><div class="news-content"><span class="news-content-date">{!! str_replace(' ', '<br />', e($m->date_fr)) !!}</span><a href="/messagerie/consulter/{{ $m->cle }}"><b>{{ $d($m->name) }}</b><br />{{ $d($m->sujet) }}</a><div class="clearfix"></div></div><a href="/messagerie/consulter/{{ $m->cle }}" class="news-push">CONSULTER</a><div class="clearfix"></div></div>
@endforeach
</form>
@endif
@if ($reading)
<div class="last_message"><span class="news-content-date">{!! str_replace(' ', '<br />', e($reading->date_fr)) !!}</span><span class="news-content-name">{{ $d($reading->name) }}</span><br class="clear" /><br /><b>Objet du message :</b> {{ $d($reading->sujet) }}<br /><br />{{ $d($reading->message) }}</div>
@if ($parentMessage && $children)
<h4>Conversation</h4><div class="first_message"><span class="news-content-date">{!! str_replace(' ', '<br />', e($parentMessage->date_fr)) !!}</span><span class="news-content-name">{{ $d($parentMessage->name) }}</span><br class="clear" /><br /><b>Objet du message :</b> {{ $d($parentMessage->sujet) }}<br /><br />{{ $d($parentMessage->message) }}</div>
@endif
@foreach ($children as $child)
<div class="item_message"><span class="news-content-date">{!! str_replace(' ', '<br />', e($child->date_fr)) !!}</span><span class="news-content-name">{{ $d($child->name) }}</span><br class="clear" /><br />{{ $d($child->message) }}</div><div class="clearfix"></div>
@endforeach
@endif
@endsection

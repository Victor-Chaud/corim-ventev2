@extends('layouts.template')

@php($d = \App\Http\Controllers\Communication\SafeHtml::clean(...))
@php($v = fn ($k) => (string) ($callback[$k] ?? ''))
@section('content')
<h3>Envoyer un nouveau message</h3><br />
<div id="contact">
@if ($formErrors)
<div class="error">@foreach ($formErrors as $error){!! $error !!}@endforeach</div>
@endif
@if ($valid)
<div class="success">{{ $valid }}</div>
@endif
@if ($users)
<form name="f_new_message" method="post" action="/message/envoyer">
@csrf
<table width="940" cellspacing="5" cellpadding="5">
	<tbody>
    	<tr>
        	<td><label for="objet">Objet du message</label></td>
            <td align="left"><input type="text" value="{{ $v('objet') }}" name="objet" id="objet"></td>
        </tr>
        <tr>
        	<td><label for="destinataire">Destinataire</label></td>
            <td align="left">
            	<table>
                	<tr>
                    	<td valign="middle">
                        	<input type="radio" name="method" value="prescripteur" {!! $v('method') === 'prescripteur' ? 'checked="checked"' : '' !!} /> Prescripteur
                        </td>
                        <td>
                        	<select name="prescripteur_select">
                                <option value="0">Choisir</option>
                                <option value="all">Tous les prescripteurs</option>
                                @foreach ($users as $user)
<option value="{{ $user->id }}" {!! $v('prescripteur_select') === (string) $user->id ? 'selected="selected"' : '' !!}>{{ $d($user->name) }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                    <tr>
                    	<td valign="middle">
                        	<input type="radio" name="method" value="programme" {!! $v('method') === 'programme' ? 'checked="checked"' : '' !!} /> Programme
                        </td>
                        <td>
                        	<select name="programme_select">
                                <option value="0">Choisir</option>
                                @foreach ($programs as $program)
<option value="{{ $program->projet_id }}">{{ $d($program->projet_titre) }}</option>
                                @endforeach
                            </select>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
        	<td><label for="message">Votre message</label></td>
            <td align="left">
            	<textarea cols="80" rows="14" name="message" id="message">{{ $v('message') }}</textarea>
            </td>
        </tr>
        <tr>
        	<td align="center" colspan="2">
            	<input type="submit" value="ENVOYER LE MESSAGE" name="submit" id="submit">
            </td>
        </tr>
    </tbody>
</table>
</form>
@elseif ($message)
<div class="oldmsg"><b>Objet du message :</b> {{ $d($message->sujet) }}<br /><br />{{ $d($message->message) }}</div>
    <form name="f_new_message" method="post" action="/message/repondre/{{ $message->cle }}">
    @csrf
    <table width="940" cellspacing="5" cellpadding="5">
        <tbody>
             <tr>
                <td><label for="destinataire">Destinataire</label></td>
                <td align="left">
                	{{ $d($message->name) }}
                </td>
            </tr>
            <tr>
                <td><label for="message">Votre message</label></td>
                <td align="left">
                    <textarea cols="80" rows="14" name="message" id="message">{{ $v('message') }}</textarea>
                </td>
            </tr>
            <tr>
                <td align="center" colspan="2">
                    <input type="submit" value="ENVOYER LE MESSAGE" name="submit" id="submit">
                </td>
            </tr>
        </tbody>
    </table>
    <input type="hidden" value="{{ html_entity_decode((string) $message->sujet, ENT_QUOTES) }}" name="objet">
    <input type="hidden" value="{{ $message->idemetteur }}" name="destinataire">
    </form>
@endif
</div>
@endsection

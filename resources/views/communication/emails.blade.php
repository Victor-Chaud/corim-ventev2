@extends('layouts.template')

@section('content')
<div class="page-header">
  <h1>Emails provenant de Corim Promotion</h1>
</div>
@if (count($emails) > 0)
    <table width="100%" cellpadding="5" cellspacing="5" class="tablesorter-green">
    <thead> 
    <tr> 
        <th width="25%" data-sorter="shortDate" data-date-format="ddmmyyyy">Date</th> 
        <th width="25%">Email</th> 
        <th width="25%">Programme</th> 
        <th width="25%">Plan</th> 
    </tr> 
    </thead> 
    <tbody> 
    @foreach ($emails as $email)
        <tr>
        	<td align="center" width="25%">{{ date('d/m/Y', (int) $email->time) }}</td>
            <td width="25%">{{ html_entity_decode((string) $email->email, ENT_QUOTES) }}</td>
            <td align="center" width="25%">
				{{ html_entity_decode($email->program, ENT_QUOTES) }}
            </td>
            <td align="center" width="25%">{{ $email->plan }}</td>
        </tr>
    @endforeach
    </tbody>
    </table>
@endif
@endsection

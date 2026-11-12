@extends('layouts.template')

@section('content')
<style>
@media print {
	.black{color:rgba(0,0,0,1.00) !important}
	.gray{color:rgba(203,203,203,1.00) !important}
	.red{color:rgba(209,32,0,1.00) !important}
	.blue{color:rgba(0,169,183,1.00) !important}
	.green{color:rgba(62,177,0,1.00) !important}
	@media (orientation:landscape){
		.media{
			min-height:200px
		}
	}
	@media (orientation:portrait){
		.media{
			min-height:180px
		}
	}
}
</style>
<div class="page-header no-print">
  <h1>Report <small><a href="javascript:window.print()">IMPRIMER</a></small></h1>
</div>
@foreach ($infos as $info)
       	<div class="media form-group">
          <div class="media-left media-middle">
@if ($info['image'] !== null)
                    <img src="{{ $info['image'] }}" class="media-object" />
@else
                    Aucune image
@endif
          </div>
          <div class="media-body">
              <h4 class="media-heading">
                  {{ $info['titre'] }} 
              </h4>
              <table class="table">
              	<tr>
                	<td align="center" width="20%">{{ $info['nb'] }} LOT(S)</td>
                    <td align="center" width="20%">{{ $info['free'] }} LIBRE(S)</td>
                    <td align="center" width="20%">{{ $info['opt'] }} OPTION(S)</td>
                    <td align="center" width="20%">{{ $info['resa'] }} R&Eacute;SA(S)</td>
                    <td align="center" width="20%">{{ $info['act'] }} ACTE(S)</td>
                </tr>
                <tr>
                	<td align="center" width="20%">{{ $info['cellules']['sum']['montant'] }}€</td>
                    <td align="center" width="20%">{{ $info['cellules']['sum_free']['montant'] }}€</td>
                    <td align="center" width="20%">{{ $info['cellules']['sum_opt']['montant'] }}€</td>
                    <td align="center" width="20%">{{ $info['cellules']['sum_resa']['montant'] }}€</td>
                    <td align="center" width="20%">{{ $info['cellules']['sum_act']['montant'] }}€</td>
                </tr>
                <tr>
                	<td align="center" width="20%" style="background:rgba(0,0,0,1.00); color:#FFF" class="black">{{ $info['cellules']['sum']['pourcent'] }}%</td>
                    <td align="center" width="20%" style="background:rgba(203,203,203,1.00);" class="gray">{{ $info['cellules']['sum_free']['pourcent'] }}%</td>
                    <td align="center" width="20%" style="background:rgba(209,32,0,1.00); color:#FFF" class="red">{{ $info['cellules']['sum_opt']['pourcent'] }}%</td>
                    <td align="center" width="20%" style="background:rgba(0,169,183,1.00);color:#FFF" class="blue">{{ $info['cellules']['sum_resa']['pourcent'] }}%</td>
                    <td align="center" width="20%" style="background:rgba(62,177,0,1.00);color:#FFF" class="green">{{ $info['cellules']['sum_act']['pourcent'] }}%</td>
                </tr>
              </table>
          </div>
        </div> 
@endforeach
@endsection

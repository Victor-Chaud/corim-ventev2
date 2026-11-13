<div class="form-group">
<div class="btn-group" role="group" aria-label="Statut des dénonciations">
    <a href="/ask_denonciations" class="btn {{ $active === 'pending' ? 'btn-primary active' : 'btn-default' }}"@if ($active === 'pending') aria-current="page"@endif>En attente</a>
    <a href="/denonciations" class="btn {{ $active === 'accepted' ? 'btn-primary active' : 'btn-default' }}"@if ($active === 'accepted') aria-current="page"@endif>Acceptées</a>
    <a href="/refused_ask" class="btn {{ $active === 'refused' ? 'btn-primary active' : 'btn-default' }}"@if ($active === 'refused') aria-current="page"@endif>Refusées</a>
</div>
</div>

<?php

it('C1 la page prospects sans session redirige vers /connexion', function () {
    expect($this->get('/gestion_prospects')->isRedirect(url('/connexion')))->toBeTrue();
});

it('C2 un prescripteur est redirigé vers l\'accueil depuis la page prospects', function () {
    $commercial = makeCommercial();

    foreach (['get', 'post'] as $method) {
        expect($this->actingAs($commercial)->$method('/gestion_prospects')->isRedirect(url('/')))->toBeTrue($method);
    }
});

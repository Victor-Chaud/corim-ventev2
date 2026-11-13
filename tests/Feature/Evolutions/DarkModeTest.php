<?php

it('le mode sombre est proposé dans le menu et chargé sur les pages internes', function () {
    $html = (string) $this->actingAs(makeAdmin())->get('/gestion_programmes')->assertOk()->getContent();

    expect($html)->toContain('class="theme-toggle" title="Mode sombre"')
        ->toContain('/medias/css/dark-mode.css')
        ->toContain('/medias/js/dark-mode.js');
});

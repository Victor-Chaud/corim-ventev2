<?php

const CHECKB_GLOBAL_JS = __DIR__.'/../../../../public/medias/js/global.js';

const CHECKB_NOT_PORTED = ['Acter', 'Recherche', 'Recherche_Ville'];

function checkBJsActions(): array
{
    preg_match_all('#SITE\+"index\.php/json/([A-Za-z_]+)/"#', (string) file_get_contents(CHECKB_GLOBAL_JS), $m);

    return array_values(array_unique($m[1]));
}

function checkBJsPages(): array
{
    $js = (string) file_get_contents(CHECKB_GLOBAL_JS);
    preg_match_all('#location = SITE\+"([a-z_/]+)"#', $js, $locations);
    preg_match_all('#href="\'\+SITE\+\'([a-z_/]+)"#', $js, $links);

    return array_values(array_unique(array_merge($locations[1], $links[1])));
}

it('VB-J1 chaque action json/* appelée par global.js existe dans NEW en POST, sauf les actions non portées documentées', function () {
    $admin = corimAdmin();
    $actions = checkBJsActions();
    expect(count($actions))->toBeGreaterThan(35);

    foreach ($actions as $action) {
        $status = jsonAction($admin, $action)->getStatusCode();
        if (in_array($action, CHECKB_NOT_PORTED, true)) {
            expect($status)->toBe(404, $action);
        } else {
            expect($status)->toBe(200, $action);
        }
    }
});

it('VB-J2 les actions du workflow appelées par global.js renvoient un objet évaluable par eval(), en text/html', function () {
    legacyMailBase();
    $w = workflowWorld();
    $js = checkBJsActions();

    foreach (workflowActions($w) as $action => $data) {
        if (! in_array($action, $js, true) || in_array($action, CHECKB_NOT_PORTED, true)) {
            continue;
        }
        $user = in_array($action, ['AddOption', 'AddReservation', 'AddProspect', 'SearchProspect'], true) ? $w->prescriber : $w->corim;
        $response = jsonAction($user, $action, $data);
        expect($response->headers->get('Content-Type'))->toStartWith('text/html', $action)
            ->and((string) $response->getContent())->toMatch('/^\{"errors":\d.*\}$/', $action)
            ->and(json_decode((string) $response->getContent(), true))->toBeArray($action);
    }
});

it('VB-J3 les pages vers lesquelles global.js redirige ou pointe existent pour l\'admin', function () {
    $admin = corimAdmin();
    $pages = checkBJsPages();
    expect($pages)->toContain('ventes', 'reservations', 'ask_reservations', 'ask_denonciations', 'options', 'ask_options', 'gestion_privilege');

    foreach ($pages as $page) {
        app('auth')->forgetGuards();
        $status = test()->actingAs($admin)->get('/'.$page)->getStatusCode();
        expect($status)->toBe(200, $page);
    }
});

it('VB-J4 app.js interroge /index.php/firstsearch avec action=app en JSONP MyFirstCallback et pointe les plans sur /medias/programmes', function () {
    $js = (string) file_get_contents(__DIR__.'/../../../../public/medias/js/app.js');
    expect($js)->toContain("url:'/index.php/firstsearch'")
        ->toContain("jsonpCallback: 'MyFirstCallback'")
        ->toContain('href="/medias/programmes/')
        ->not->toContain('http://');

    $response = test()->get('/index.php/firstsearch?action=app&projet=&budget=');
    expect($response->getStatusCode())->toBe(200)
        ->and((string) $response->getContent())->toStartWith('MyFirstCallback(');
});

<?php

use Illuminate\Support\Facades\DB;

function grantPrograms(object $user, array $programs): void
{
    foreach ($programs as $program) {
        DB::table('acces')->insert(['iduser' => $user->id, 'idprojet' => $program->projet_id]);
    }
}

function listedEmails(string $content): array
{
    preg_match_all('#<a href="mailto:([^"]+)">#', $content, $m);

    return $m[1];
}

it('C4 la liste admin affiche tous les comptes ayant un privilège, triés par nom, avec les colonnes et le h1', function () {
    $admin = makeAdmin(['name' => 'Martin Admin', 'email' => 'martin@exemple.test']);
    makeCommercial(['name' => 'Bernard Test', 'email' => 'bernard@exemple.test']);
    makeCommercial(['name' => 'Alice Test', 'email' => 'alice@exemple.test', 'statut' => 0]);
    DB::table('user')->insert(['pseudo' => 'orphelin', 'name' => 'Aaron Sans Privilege', 'password' => 'x', 'email' => 'orphelin@exemple.test', 'question' => '', 'reponse' => '', 'statut' => 1]);

    $response = $this->actingAs($admin)->get('/gestion_promoteurs');

    $response->assertOk();
    $text = pageText($response);
    expect($text)->toContain('<title>Corim promotion - Gestion des promoteurs</title>')
        ->toMatch('#<h1>Gestion des prescripteurs\s+<small><a href="javascript:void\(0\)" id="addPromoteur">Ajouter un compte</a></small></h1>#')
        ->and(listedEmails($text))->toBe(['alice@exemple.test', 'bernard@exemple.test', 'martin@exemple.test']);
    expectInOrder($text, ['<b>NOM</b>', '<b>EMAIL</b>', '<b>IDENTIFIANT</b>', '<b>STATUT</b>', '<b>ACTIF</b>']);
});

it('C5 STATUT affiche Administrateur ou Commercial, ACTIF affiche OUI ou NON', function () {
    $admin = makeAdmin(['name' => 'A Admin']);
    makeCommercial(['name' => 'B Commercial actif']);
    makeCommercial(['name' => 'C Commercial inactif', 'statut' => 0]);

    $text = pageText($this->actingAs($admin)->get('/gestion_promoteurs')->assertOk());

    expectInOrder($text, ['A Admin', '<td align="center">Administrateur</td>', '<span class="label label-success">OUI</span>',
        'B Commercial actif', '<td align="center">Commercial</td>', '<span class="label label-success">OUI</span>',
        'C Commercial inactif', '<td align="center">Commercial</td>', '<span class="label label-danger">NON</span>']);
});

it('C6 compte actif → désactivation, inactif → activation, chaque ligne a modifier, privilèges et suppression', function () {
    $admin = makeAdmin(['name' => 'A Admin']);
    $inactive = makeCommercial(['name' => 'B Inactif', 'statut' => 0]);

    $text = pageText($this->actingAs($admin)->get('/gestion_promoteurs')->assertOk());

    expect($text)->toContain('<a rel="'.$admin->id.'" class="delPromoteur btn btn-danger btn-xs" href="javascript:void(0)"><i class="glyphicon glyphicon-ban-circle"></i></a>')
        ->toContain('<a rel="'.$inactive->id.'" class="activPromoteur btn btn-success btn-xs" href="javascript:void(0)"><i class="glyphicon glyphicon-ok-circle"></i></a>')
        ->not->toContain('<a rel="'.$inactive->id.'" class="delPromoteur')
        ->not->toContain('<a rel="'.$admin->id.'" class="activPromoteur');
    foreach ([$admin, $inactive] as $user) {
        expect($text)->toContain('<a class="btn btn-primary btn-xs" href="/gestion_promoteurs/modifier/'.$user->id.'"><i class="glyphicon glyphicon-pencil"></i></a>')
            ->toContain('<a rel="'.$user->id.'" class="push-privileges btn btn-info btn-xs" href="javascript:void(0)"><i class="glyphicon glyphicon-option-vertical"></i></a>')
            ->toContain('<td align="center"><a rel="'.$user->id.'" class="trashPromoteur btn btn-danger btn-xs" href="javascript:void(0)"><i class="glyphicon glyphicon-trash"></i></a></td>');
    }
});

it('C7 actifs et inactifs ne listent que statut 1 ou 0, h1 suffixé, sans filtre', function () {
    $admin = makeAdmin(['name' => 'B Admin', 'email' => 'admin@exemple.test']);
    makeCommercial(['name' => 'A Actif', 'email' => 'actif@exemple.test']);
    makeCommercial(['name' => 'C Inactif', 'email' => 'inactif@exemple.test', 'statut' => 0]);
    makeProgram();

    $activeUsers = pageText($this->actingAs($admin)->get('/gestion_promoteurs/actifs')->assertOk());
    $inactiveUsers = pageText($this->actingAs($admin)->get('/gestion_promoteurs/inactifs')->assertOk());

    expect($activeUsers)->toMatch('#<h1>Gestion des prescripteurs actifs\s+<small>#')
        ->and(listedEmails($activeUsers))->toBe(['actif@exemple.test', 'admin@exemple.test'])
        ->and($activeUsers)->not->toContain('name="projects[]"')
        ->and($inactiveUsers)->toMatch('#<h1>Gestion des prescripteurs inactifs\s+<small>#')
        ->and(listedEmails($inactiveUsers))->toBe(['inactif@exemple.test'])
        ->and($inactiveUsers)->not->toContain('name="projects[]"');
});

it('C8 filtre type=0 : comptes ayant accès à A ou B, une seule fois', function () {
    $admin = makeAdmin(['email' => 'admin@exemple.test']);
    [$a, $b, $c] = [makeProgram(), makeProgram(), makeProgram()];
    grantPrograms(makeCommercial(['name' => 'Deux', 'email' => 'deux@exemple.test']), [$a, $b]);
    grantPrograms(makeCommercial(['name' => 'Un', 'email' => 'un@exemple.test']), [$b]);
    grantPrograms(makeCommercial(['name' => 'Autre', 'email' => 'autre@exemple.test']), [$c]);

    $text = pageText($this->actingAs($admin)->post('/gestion_promoteurs', ['type' => '0', 'projects' => [$a->projet_id, $b->projet_id]])->assertOk());

    expect(listedEmails($text))->toBe(['deux@exemple.test', 'un@exemple.test']);
});

it('C9 filtre type=1 : comptes ayant accès à A et à B', function () {
    $admin = makeAdmin();
    [$a, $b] = [makeProgram(), makeProgram()];
    grantPrograms(makeCommercial(['name' => 'Deux', 'email' => 'deux@exemple.test']), [$a, $b]);
    grantPrograms(makeCommercial(['name' => 'Un', 'email' => 'un@exemple.test']), [$b]);

    $text = pageText($this->actingAs($admin)->post('/gestion_promoteurs', ['type' => '1', 'projects' => [$a->projet_id, $b->projet_id]])->assertOk());

    expect(listedEmails($text))->toBe(['deux@exemple.test']);
});

it('C10 le filtre propose les programmes non archivés en majuscules, id décroissant, et réaffiche la sélection', function () {
    $admin = makeAdmin();
    $a = makeProgram(['projet_titre' => 'Les Pins']);
    $b = makeProgram(['projet_titre' => 'Le Port']);
    makeProgram(['projet_titre' => 'Archive', 'archive' => 1]);

    $text = pageText($this->actingAs($admin)->post('/gestion_promoteurs', ['type' => '1', 'projects' => [$a->projet_id]])->assertOk());

    expectInOrder($text, ['name="projects[]"', '>LE PORT</option>', '>LES PINS</option>']);
    expect($text)->not->toContain('>ARCHIVE</option>')
        ->toMatch('#<option value="'.$a->projet_id.'"\s+selected\s*>LES PINS</option>#')
        ->toMatch('#<option value="'.$b->projet_id.'"\s*>LE PORT</option>#')
        ->toMatch('#<option value="1"\s+selected\s*>Contient tous les programmes sélectionnés</option>#');
});

it('C21 les trois confirmations ont les titres et messages legacy avec Oui/Non et rechargent la page', function () {
    $js = implode('', array_map('file_get_contents', glob(public_path('medias/js/*.js'))));

    foreach ([
        ['Confirmation de désactivation', 'Vous êtes sur le point de désactiver ce promoteur<br />Continuer ?', 'DeletePromoteur'],
        ["Confirmation d\\'activation", "Vous êtes sur le point d\\'activer ce promoteur<br />Continuer ?", 'ActivPromoteur'],
        ['Confirmation de suppression', 'Vous êtes sur le point de supprimer ce promoteur<br />Continuer ?', 'TrashPromoteur'],
    ] as [$title, $message, $action]) {
        expect($js)->toContain($title)->toContain($message)->toContain('json/'.$action);
    }
    expect($js)->toContain("'Oui'")->toContain("'Non'")->toContain('document.location.reload()');
});

it('C28 un nom contenant <script> saisi via modifier est échappé dans la liste et le formulaire', function () {
    $admin = makeAdmin();
    $user = makeCommercial();

    $this->actingAs($admin)->post("/gestion_promoteurs/modifier/$user->id", editPayload($user, ['f_edit_promoteur_name' => '<script>alert(1)</script>']));
    expect(decoded(DB::table('user')->where('id', $user->id)->value('name')))->toBe('<script>alert(1)</script>');

    foreach (['/gestion_promoteurs', "/gestion_promoteurs/modifier/$user->id"] as $url) {
        $response = $this->actingAs($admin)->get($url)->assertOk();
        expect((string) $response->getContent())->not->toContain('<script>alert(1)</script>')
            ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
    }
});

it('C31 la modale privilèges est rendue dans la liste avec un bouton par compte', function () {
    $admin = makeAdmin();
    $user = makeCommercial();
    $program = makeProgram(['projet_titre' => 'PROGRAMME MODALE']);
    makeLot($program->projet_id, ['numero' => 'LOTMODALE']);
    makeProgram(['projet_titre' => 'PROGRAMME ARCHIVE', 'archive' => 1]);

    $text = pageText($this->actingAs($admin)->get('/gestion_promoteurs')->assertOk());

    expect($text)->toContain('id="privileges"')->toContain('PROGRAMME MODALE')->toContain('LOTMODALE')
        ->toContain('name="projet[]"')->toContain('name="lot[]"')->toContain('TOUT COCHER')
        ->not->toContain('PROGRAMME ARCHIVE')
        ->and(substr_count($text, 'class="push-privileges'))->toBe(2);
});

it('C32 la liste et le formulaire reprennent le markup legacy (classes, libellés, icônes)', function () {
    $admin = makeAdmin();
    makeProgram();

    $list = pageText($this->actingAs($admin)->get('/gestion_promoteurs')->assertOk());
    expect($list)->toContain('<table width="100%" cellpadding="5" cellspacing="5" class="tablesorter-green">')
        ->toContain('<select name="type" id="type" class="form-control selectpicker">')
        ->toContain('<select name="projects[]" id="projects" class="form-control selectpicker" multiple>')
        ->toContain('<button type="submit" class="btn btn-default"><i class="glyphicon glyphicon-search"></i></button>');
    foreach (['f_add_promoteur_pseudo' => 'Identifiant', 'f_add_promoteur_name' => 'NOM Prénom', 'f_add_promoteur_pwd' => 'Mot de passe', 'f_add_promoteur_email' => 'E-mail', 'f_add_promoteur_statut' => 'Type de compte'] as $field => $label) {
        expect($list)->toContain('name="'.$field.'"')->toContain('<label for="'.$field.'">'.$label.'<span class="red">*</span></label>');
    }
    expect($list)->toContain('<option value="0">Commercial</option>')->toContain('<option value="1">Administrateur</option>');

    $form = pageText($this->actingAs($admin)->get("/gestion_promoteurs/modifier/$admin->id")->assertOk());
    foreach (['f_edit_promoteur_pseudo' => 'Identifiant de connexion', 'f_edit_promoteur_name' => 'Nom du prescripteur', 'f_edit_promoteur_email' => 'E-mail', 'f_edit_promoteur_pwd' => 'Mot de passe', 'f_edit_promoteur_statut' => 'Statut'] as $field => $label) {
        expect($form)->toContain('<label for="'.$field.'">'.$label.'</label>');
    }
    expect($form)->toContain('<p class="text-info">Si le champs est laissé vide, le mot de passe actuel sera conservé.</p>')
        ->toContain('<input type="submit" value="ENREGISTRER" class="btn btn-primary" />')
        ->toContain('<input type="email" name="f_edit_promoteur_email" id="f_edit_promoteur_email"');
});

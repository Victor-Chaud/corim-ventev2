<?php

it('C15 gestion_privilege liste les utilisateurs et la modale des programmes non archivés', function () {
    $admin = makeAdmin(['name' => 'Admin Test']);
    $users = [makeCommercial(['name' => 'Bernard Test']), makeCommercial(['name' => 'Alice Test'])];
    $p1 = makeProgram(['projet_titre' => 'PREMIER']);
    $p2 = makeProgram(['projet_titre' => 'SECOND']);
    $archived = makeProgram(['projet_titre' => 'ARCHIVE', 'archive' => 1]);
    $lot = makeLot($p2->projet_id, ['numero' => 'REF42']);

    $response = $this->actingAs($admin)->get('/gestion_privilege');

    $response->assertOk()
        ->assertSee('Corim promotion - Gestion des privilèges', false)
        ->assertSee('<h1>Gestion des privilèges</h1>', false)
        ->assertSeeInOrder(['NOM', 'EMAIL', 'IDENTIFIANT', 'STATUT'])
        ->assertSeeInOrder(['Admin Test', 'Alice Test', 'Bernard Test'])
        ->assertSee('Administrateur')
        ->assertSee('Commercial')
        ->assertSee('id="privileges"', false)
        ->assertSeeInOrder(['SECOND', 'PREMIER'])
        ->assertDontSee('ARCHIVE')
        ->assertSee('REF42')
        ->assertSee("CLIQUEZ SUR L'IMAGE POUR ACCÉDER AUX LOTS", false);
    expect(substr_count($response->getContent(), 'push-privileges'))->toBe(3);
    foreach ([$admin, ...$users] as $user) {
        $response->assertSee('rel="'.$user->id.'"', false);
    }
});

it('C16 GetUserPrivilege et GetUserPrivilegeLot renvoient les ids en chaînes ou errors 2', function () {
    $admin = makeAdmin();
    $user = makeCommercial();
    $program = makeProgram();
    $lot = makeLot($program->projet_id);
    DB::table('acces')->insert(['iduser' => $user->id, 'idprojet' => $program->projet_id]);
    DB::table('acces_lot')->insert(['iduser' => $user->id, 'idlot' => $lot->id]);

    expect($this->actingAs($admin)->post('/index.php/json/GetUserPrivilege', ['key' => $user->id])->getContent())
        ->toBe('{"errors":1,"call":[{"idprojet":"'.$program->projet_id.'"}]}')
        ->and($this->actingAs($admin)->post('/index.php/json/GetUserPrivilegeLot', ['key' => $user->id])->getContent())
        ->toBe('{"errors":1,"call":[{"idlot":"'.$lot->id.'"}]}');

    $none = makeCommercial();
    expect($this->actingAs($admin)->post('/index.php/json/GetUserPrivilege', ['key' => $none->id])->getContent())->toBe('{"errors":2}')
        ->and($this->actingAs($admin)->post('/index.php/json/GetUserPrivilegeLot', ['key' => $none->id])->getContent())->toBe('{"errors":2}');
});

it('C17 UpdateUserPrivilege remplace acces et acces_lot et envoie E1', function () {
    $admin = makeAdmin();
    $user = makeCommercial(['email' => 'prescripteur@exemple.test']);
    $old = makeProgram();
    $p1 = makeProgram();
    $p2 = makeProgram();
    $l1 = makeLot($p1->projet_id);
    $l2 = makeLot($p2->projet_id);
    DB::table('acces')->insert(['iduser' => $user->id, 'idprojet' => $old->projet_id]);
    DB::table('acces_lot')->insert(['iduser' => $user->id, 'idlot' => makeLot($old->projet_id)->id]);
    DB::table('acces')->insert(['iduser' => $admin->id, 'idprojet' => $old->projet_id]);

    $response = $this->actingAs($admin)->post('/index.php/json/UpdateUserPrivilege', [
        'user' => $user->id, 'projet' => [$p1->projet_id, $p2->projet_id], 'lot' => [$l1->id, $l2->id],
    ]);

    expect($response->getContent())->toBe('{"errors":1}')
        ->and(DB::table('acces')->where('iduser', $user->id)->orderBy('idprojet')->pluck('idprojet')->all())->toBe([$p1->projet_id, $p2->projet_id])
        ->and(DB::table('acces_lot')->where('iduser', $user->id)->orderBy('idlot')->pluck('idlot')->all())->toBe([$l1->id, $l2->id])
        ->and(DB::table('acces')->where('iduser', $admin->id)->count())->toBe(1);

    $mails = sentMails();
    expect($mails)->toHaveCount(1)
        ->and($mails[0]->getSubject())->toBe('[Corim Promotion] - Votre accès à la plateforme CORIM')
        ->and($mails[0]->getTo()[0]->getAddress())->toBe('prescripteur@exemple.test');
});

it('C18 UpdateUserPrivilege sans projet supprime tout et n\'envoie rien', function () {
    $admin = makeAdmin();
    $user = makeCommercial();
    $program = makeProgram();
    DB::table('acces')->insert(['iduser' => $user->id, 'idprojet' => $program->projet_id]);
    DB::table('acces_lot')->insert(['iduser' => $user->id, 'idlot' => makeLot($program->projet_id)->id]);

    expect($this->actingAs($admin)->post('/index.php/json/UpdateUserPrivilege', ['user' => $user->id])->getContent())->toBe('{"errors":1}')
        ->and(DB::table('acces')->where('iduser', $user->id)->count())->toBe(0)
        ->and(DB::table('acces_lot')->where('iduser', $user->id)->count())->toBe(0)
        ->and(sentMails())->toBeEmpty();
});

it('C19 UpdateUserPrivilege par un commercial est refusé sans modification', function () {
    $user = makeCommercial();
    $program = makeProgram();
    DB::table('acces')->insert(['iduser' => $user->id, 'idprojet' => $program->projet_id]);

    $response = $this->actingAs($user)->post('/index.php/json/UpdateUserPrivilege', ['user' => $user->id, 'projet' => [makeProgram()->projet_id]]);

    expect($response->getStatusCode())->toBeIn([302, 401, 403])
        ->and(DB::table('acces')->where('iduser', $user->id)->pluck('idprojet')->all())->toBe([$program->projet_id])
        ->and(sentMails())->toBeEmpty();
});

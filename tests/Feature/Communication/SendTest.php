<?php

use Illuminate\Support\Facades\DB;

beforeEach(fn () => legacyMailBase());

function sendMessage(object $admin, array $data)
{
    app('auth')->forgetGuards();

    return test()->actingAs($admin)->post('/message/envoyer', array_merge(['objet' => 'Objet test', 'message' => 'Texte du message'], $data));
}

it('C12 message/envoyer : messages d\'erreur exacts, un mp par prescripteur (tous, programme), programme sans accès sans 500', function () {
    $admin = corimAdmin();
    $p1 = makeCommercial(['name' => 'Alice Active']);
    $p2 = makeCommercial(['name' => 'Bruno Inactif', 'statut' => 0]);
    $program = makeProgram(['projet_titre' => 'LES OLIVIERS']);
    $empty = makeProgram(['projet_titre' => 'SANS ACCES']);
    DB::table('acces')->insert([['iduser' => $p1->id, 'idprojet' => $program->projet_id], ['iduser' => $admin->id, 'idprojet' => $program->projet_id]]);

    $form = pageText($this->actingAs($admin)->get('/message/envoyer')->assertOk());
    expect($form)->toContain('<title>Corim Promotion - Envoyer un nouveau message</title>')
        ->toContain('<h3>Envoyer un nouveau message</h3>')
        ->toContain('<option value="all">Tous les prescripteurs</option>')
        ->not->toContain('Tous les clients')
        ->toMatch('#<option value="'.$p2->id.'"\s*>Bruno Inactif</option>#')
        ->toMatch('#<option value="'.$program->projet_id.'">LES OLIVIERS</option>#')
        ->toContain('ENVOYER LE MESSAGE');

    $errors = [
        'Le champ objet est requis <br />' => ['objet' => '', 'method' => 'prescripteur', 'prescripteur_select' => 'all'],
        'Le champ message est requis <br />' => ['message' => '', 'method' => 'prescripteur', 'prescripteur_select' => 'all'],
        'Veuillez choisir un destinataire' => ['prescripteur_select' => 'all'],
        'Veuillez choisir un prescripteur' => ['method' => 'prescripteur', 'prescripteur_select' => '0'],
        'Veuillez choisir un programme' => ['method' => 'programme', 'programme_select' => '0'],
    ];
    foreach ($errors as $message => $data) {
        expect(pageText(sendMessage($admin, $data)))->toContain('<div class="error">'.$message.'</div>', $message);
    }
    expect(DB::table('mp')->count())->toBe(0);

    expect(pageText(sendMessage($admin, ['method' => 'prescripteur', 'prescripteur_select' => 'all'])))->toContain('<div class="success">Votre message à bien été envoyé</div>');
    expect(DB::table('mp')->orderBy('idreceveur')->pluck('idreceveur')->map(fn ($id) => (int) $id)->all())->toBe(prescriberIds());
    $row = DB::table('mp')->first();
    expect((int) $row->idemetteur)->toBe($admin->id)
        ->and(decoded($row->sujet))->toBe('Objet test')
        ->and(decoded($row->message))->toBe('Texte du message');

    DB::table('mp')->delete();
    sendMessage($admin, ['method' => 'prescripteur', 'prescripteur_select' => (string) $p2->id]);
    expect(DB::table('mp')->pluck('idreceveur')->map(fn ($id) => (int) $id)->all())->toBe([$p2->id]);

    DB::table('mp')->delete();
    sendMessage($admin, ['method' => 'programme', 'programme_select' => (string) $program->projet_id]);
    expect(DB::table('mp')->pluck('idreceveur')->map(fn ($id) => (int) $id)->all())->toBe([$p1->id]);

    DB::table('mp')->delete();
    $response = sendMessage($admin, ['method' => 'programme', 'programme_select' => (string) $empty->projet_id]);
    expect($response->getStatusCode())->toBe(200)
        ->and((string) $response->getContent())->not->toContain('SQLSTATE')
        ->and(DB::table('mp')->count())->toBe(0)
        ->and(sentMails())->toBeEmpty();
});

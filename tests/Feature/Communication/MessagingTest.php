<?php

use Illuminate\Support\Facades\DB;

beforeEach(fn () => legacyMailBase());

function messageLine(object $message, string $name, string $class): string
{
    return '#<div class="'.$class.'">\s*<div class="news-content">\s*<span class="news-content-date">04\.03\.2026<br />09:15</span>\s*<a href="[^"]*/messagerie/consulter/'.$message->cle.'"><b>'.preg_quote($name, '#').'</b><br />'.preg_quote(decoded($message->sujet), '#').'</a>.*?<a href="[^"]*/messagerie/consulter/'.$message->cle.'" class="news-push">CONSULTER</a>#su';
}

it('C3 /messagerie prescripteur liste ses messages émis et reçus, id décroissant, date, nom, sujet, classe news / news_read', function () {
    $admin = corimAdmin();
    $prescriber = makeCommercial(['name' => 'Paul Prescripteur']);
    $other = makeCommercial(['name' => 'Autre Personne']);
    $received = makeMessage($admin->id, $prescriber->id, ['sujet' => 'Bienvenue']);
    $sent = makeMessage($prescriber->id, $admin->id, ['sujet' => 'Question lot', 'lu' => 1]);
    $foreign = makeMessage($admin->id, $other->id, ['sujet' => 'Pas pour toi']);
    $read = makeMessage($admin->id, $prescriber->id, ['sujet' => 'Déjà lu', 'lu' => 1]);

    $response = $this->actingAs($prescriber)->get('/messagerie');

    $response->assertOk();
    $text = pageText($response);
    expect($text)->toContain('<title>Corim Promotion - Messagerie privée</title>')
        ->toContain('Messagerie privée')
        ->not->toContain('Envoyer un message')
        ->not->toContain('Pas pour toi')
        ->toMatch(messageLine($received, 'CORIM Admin', 'news'))
        ->toMatch(messageLine($sent, 'Paul Prescripteur', 'news_read'))
        ->toMatch(messageLine($read, 'CORIM Admin', 'news_read'));
    expectInOrder($text, ['Déjà lu', 'Question lot', 'Bienvenue']);

    expect(pageText($this->actingAs($admin)->get('/messagerie')))->toContain('message/envoyer">Envoyer un message</a>');
});

it('C4 /messagerie admin exclut les 12 sujets système ; badge non-lus admin / prescripteur, absent à 0', function () {
    $admin = corimAdmin();
    $prescriber = makeCommercial();
    $idle = makeCommercial();
    makeMessage($prescriber->id, $admin->id, ['sujet' => 'Vraie question']);
    makeMessage($prescriber->id, $admin->id, ['sujet' => 'Vous avez une dénonciation en attente']);
    makeMessage($prescriber->id, $admin->id, ['sujet' => 'Votre dossier a été acté']);
    makeMessage($prescriber->id, $admin->id, ['sujet' => 'Ancienne question', 'lu' => 1]);
    makeMessage($admin->id, $prescriber->id, ['sujet' => 'Votre dossier a été acté']);
    makeMessage($admin->id, $prescriber->id, ['sujet' => 'Info']);

    $page = pageText($this->actingAs($admin)->get('/messagerie')->assertOk());
    expect($page)->toContain('Vraie question')->toContain('Ancienne question')
        ->not->toContain('Vous avez une dénonciation en attente')->not->toContain('Votre dossier a été acté')
        ->toContain('messagerie">Messagerie <span class="label label-info">1</span></a>');

    expect(pageText($this->actingAs($prescriber)->get('/messagerie')))->toContain('messagerie">Messagerie <span class="label label-info">2</span></a>')
        ->and(pageText($this->actingAs($idle)->get('/messagerie')))->toContain('messagerie">Messagerie </a>')->not->toContain('label-info');
});

it('C5 les messages dont l\'émetteur n\'existe plus n\'apparaissent pas', function () {
    $admin = corimAdmin();
    $prescriber = makeCommercial();
    makeMessage(999999, $prescriber->id, ['sujet' => 'Message orphelin']);
    makeMessage($admin->id, $prescriber->id, ['sujet' => 'Message visible']);

    $text = pageText($this->actingAs($prescriber)->get('/messagerie')->assertOk());

    expect($text)->toContain('Message visible')->not->toContain('Message orphelin');
});

it('C6 consulter un message reçu le passe à lu=1 pour ce destinataire seulement ; un message envoyé reste inchangé', function () {
    $admin = corimAdmin();
    $prescriber = makeCommercial();
    $received = makeMessage($admin->id, $prescriber->id, ['sujet' => 'À lire', 'message' => 'Contenu à lire']);
    $sent = makeMessage($prescriber->id, $admin->id, ['sujet' => 'Envoyé']);

    $response = $this->actingAs($prescriber)->get("/messagerie/consulter/$received->cle");
    $response->assertOk();
    expect(pageText($response))->toContain('<title>Corim Promotion - Consultation des messages privés</title>')
        ->toMatch('#<div class="last_message">\s*<span class="news-content-date">04\.03\.2026<br />09:15</span>\s*<span class="news-content-name">CORIM Admin</span>\s*<br class="clear" /><br />\s*<b>Objet du message :</b> À lire<br /><br />\s*Contenu à lire\s*</div>#u');
    expect((int) DB::table('mp')->where('id', $received->id)->value('lu'))->toBe(1);

    $this->actingAs($prescriber)->get("/messagerie/consulter/$sent->cle")->assertOk();
    expect((int) DB::table('mp')->where('id', $sent->id)->value('lu'))->toBe(0);
});

it('C7 consulter le message d\'un tiers (non admin) renvoie 404 ou 403 sans le marquer lu', function () {
    $admin = corimAdmin();
    $owner = makeCommercial();
    $intruder = makeCommercial();
    $message = makeMessage($admin->id, $owner->id, ['message' => 'Contenu privé']);
    expectRouteExists($this->actingAs($owner)->get("/messagerie/consulter/$message->cle"), 'consulter');
    DB::table('mp')->where('id', $message->id)->update(['lu' => 0]);

    $response = $this->actingAs($intruder)->get("/messagerie/consulter/$message->cle");

    expect($response->getStatusCode())->toBeIn([403, 404])
        ->and((string) $response->getContent())->not->toContain('Contenu privé')
        ->and((int) DB::table('mp')->where('id', $message->id)->value('lu'))->toBe(0);
});

it('C8 consulter affiche la conversation (racine puis réponses) et « Répondre au message » seulement si je ne suis pas l\'émetteur', function () {
    $admin = corimAdmin();
    $prescriber = makeCommercial(['name' => 'Paul Prescripteur']);
    $root = makeMessage($admin->id, $prescriber->id, ['sujet' => 'Fil', 'message' => 'Message racine']);
    $child = makeMessage($prescriber->id, $admin->id, ['sujet' => 'Fil', 'message' => 'Première réponse', 'parent' => $root->id]);
    $alone = makeMessage($admin->id, $prescriber->id, ['sujet' => 'Seul', 'message' => 'Sans réponse']);

    $text = pageText($this->actingAs($prescriber)->get("/messagerie/consulter/$root->cle")->assertOk());
    expect($text)->toContain('<h4>Conversation</h4>')
        ->toMatch('#<div class="first_message">.*?Message racine\s*</div>#su')
        ->toMatch('#<div class="item_message">.*?Paul Prescripteur.*?Première réponse\s*</div>#su')
        ->toMatch('#<small><a href="[^"]*/message/repondre/'.$root->cle.'">Répondre au message</a></small>#');

    $mine = pageText($this->actingAs($prescriber)->get("/messagerie/consulter/$child->cle")->assertOk());
    expect($mine)->toContain('<h4>Conversation</h4>')->not->toContain('Répondre au message');

    expect(pageText($this->actingAs($prescriber)->get("/messagerie/consulter/$alone->cle")))->not->toContain('Conversation')->toContain('Sans réponse');
});

it('C9 repondre : message manquant → erreur ; réponse vers l\'émetteur d\'origine même si destinataire falsifié, sans e-mail', function () {
    $admin = corimAdmin();
    $prescriber = makeCommercial();
    $victim = makeCommercial();
    $original = makeMessage($admin->id, $prescriber->id, ['sujet' => 'Question du jour', 'message' => 'Original']);

    $form = pageText($this->actingAs($prescriber)->get("/message/repondre/$original->cle")->assertOk());
    expect($form)->toContain('<title>Corim Promotion - Répondre à un message</title>')
        ->toContain('<h3>Envoyer un nouveau message</h3>')
        ->toMatch('#<div class="oldmsg"><b>Objet du message :</b> Question du jour<br /><br />Original</div>#')
        ->toContain('ENVOYER LE MESSAGE');

    $count = DB::table('mp')->count();
    $error = $this->actingAs($prescriber)->post("/message/repondre/$original->cle", ['objet' => 'Question du jour', 'message' => '', 'destinataire' => $admin->id]);
    expect(pageText($error))->toContain('<div class="error">Le champ message est requis <br /></div>')
        ->and(DB::table('mp')->count())->toBe($count);

    $ok = $this->actingAs($prescriber)->post("/message/repondre/$original->cle", ['objet' => 'Question du jour', 'message' => 'Ma réponse', 'destinataire' => $victim->id]);
    expect(pageText($ok))->toContain('<div class="success">Votre message à bien été envoyé</div>');
    $reply = DB::table('mp')->orderByDesc('id')->first();
    expect((int) $reply->idemetteur)->toBe($prescriber->id)
        ->and((int) $reply->idreceveur)->toBe($admin->id)
        ->and(decoded($reply->sujet))->toBe('Question du jour')
        ->and(decoded($reply->message))->toBe('Ma réponse')
        ->and((int) $reply->parent)->toBe($original->id)
        ->and((int) $reply->lu)->toBe(0)
        ->and($reply->cle)->not->toBe($original->cle)
        ->and(sentMails())->toBeEmpty();
});

it('C10 messagerie/supprimer efface seulement mes messages reçus aux clés données ; une clé avec \' est sans effet', function () {
    $admin = corimAdmin();
    $prescriber = makeCommercial();
    $k1 = makeMessage($admin->id, $prescriber->id);
    $k2 = makeMessage($admin->id, $prescriber->id);
    $keep = makeMessage($admin->id, $prescriber->id);
    $sentByMe = makeMessage($prescriber->id, $admin->id);

    $response = $this->actingAs($prescriber)->post('/messagerie/supprimer', ['delete_message' => "$k1->cle|$k2->cle|$sentByMe->cle|"]);

    $response->assertOk();
    expect(pageText($response))->toContain('<div class="success">2 messages effacés avec succès</div>')
        ->and(DB::table('mp')->whereIn('id', [$k1->id, $k2->id])->count())->toBe(0)
        ->and(DB::table('mp')->whereIn('id', [$keep->id, $sentByMe->id])->count())->toBe(2);

    $before = DB::table('mp')->count();
    $evil = $this->actingAs($prescriber)->post('/messagerie/supprimer', ['delete_message' => "x') OR ('1'='1|"]);
    expect($evil->getStatusCode())->toBe(200)
        ->and((string) $evil->getContent())->not->toContain('SQLSTATE')
        ->and(DB::table('mp')->count())->toBe($before);
});

it('C11 messagerie/supprimer_tout en POST supprime tous mes messages reçus et affiche le nombre ; GET sans effet', function () {
    $admin = corimAdmin();
    $prescriber = makeCommercial();
    makeMessage($admin->id, $prescriber->id);
    makeMessage($admin->id, $prescriber->id);
    makeMessage($admin->id, $prescriber->id);
    $sentByMe = makeMessage($prescriber->id, $admin->id);

    $this->actingAs($prescriber)->get('/messagerie/supprimer_tout');
    expect(DB::table('mp')->where('idreceveur', $prescriber->id)->count())->toBe(3);

    $response = $this->actingAs($prescriber)->post('/messagerie/supprimer_tout');
    expect(pageText($response))->toContain('<div class="success">3 messages effacés avec succès</div>')
        ->and(DB::table('mp')->where('idreceveur', $prescriber->id)->count())->toBe(0)
        ->and(DB::table('mp')->where('id', $sentByMe->id)->exists())->toBeTrue();
});

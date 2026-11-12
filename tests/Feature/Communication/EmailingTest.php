<?php

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => legacyMailBase());

function emailing(object $admin, array $data)
{
    app('auth')->forgetGuards();

    return test()->actingAs($admin)->post('/emailing', array_merge(['objet' => 'Objet emailing', 'message' => '<p>Bonjour <b>à tous</b></p>'], $data));
}

function flushMails(): void
{
    Mail::mailer('array')->getSymfonyTransport()->flush();
}

it('C14 /emailing affiche chaque erreur exacte en alert-warning et le succès en alert-success', function () {
    $admin = corimAdmin();
    $empty = makeProjet();

    $form = pageText($this->actingAs($admin)->get('/emailing')->assertOk());
    expect($form)->toContain('<title>Corim Promotion - Envoyer un emailing</title>')
        ->toContain('<h1>Emailing</h1>')
        ->toContain('Prescripteur(s)')->toContain('Client(s)')->toContain('Prescripteur(s) par programme')->toContain('Recherche par Mots-clé')
        ->toContain('id="keyword_select_push" class="btn btn-primary">Rechercher</a>')
        ->toContain('id="vider_la_liste" class="btn btn-primary">Vider la liste</a>')
        ->not->toContain('src="/ckeditor/ckeditor.js"');

    $errors = [
        'Le champ objet est requis <br />' => ['objet' => '', 'method' => 'prescripteur', 'prescripteur_select' => 'all'],
        'Le champ message est requis <br />' => ['message' => '', 'method' => 'prescripteur', 'prescripteur_select' => 'all'],
        'Veuillez choisir un prescripteur' => ['method' => 'prescripteur', 'prescripteur_select' => '0'],
        'Veuillez choisir un programme' => ['method' => 'programme', 'programme_select' => '0'],
        "Aucun prescripteur n'est enregistré sur ce projet" => ['method' => 'programme', 'programme_select' => (string) $empty->projet_id],
        'Veuillez choisir un client' => ['method' => 'client', 'client_select' => '0'],
        "Attention, aucun client n'a été sélectionné" => ['method' => 'keywords', 'keyword_select' => ''],
        "Attention, aucun type de destinataire n'a été sélectionné" => [],
    ];
    foreach ($errors as $message => $data) {
        expect(pageText(emailing($admin, $data)))->toContain('<div class="alert alert-warning">'.$message.'</div>', $message);
    }
    expect(DB::table('emailing')->count())->toBe(0)
        ->and(sentMails())->toBeEmpty();

    expect(pageText(emailing($admin, ['method' => 'keywords', 'keyword_select' => 'a@exemple.test'])))
        ->toContain('<div class="alert alert-success">Votre message à bien été envoyé</div>');
});

it('C15 /emailing envoie un e-mail par destinataire (tous prescripteurs, programme, tous clients, liste ;) au gabarit legacy et historise dans emailing', function () {
    $admin = corimAdmin();
    $p1 = makeCommercial(['email' => 'p1@exemple.test']);
    $p2 = makeCommercial(['email' => 'p2@exemple.test', 'statut' => 0]);
    $projet = makeProjet();
    DB::table('acces')->insert([['iduser' => $p1->id, 'idprojet' => $projet->projet_id], ['iduser' => $admin->id, 'idprojet' => $projet->projet_id]]);
    makeClient(['email' => 'c1@exemple.test']);
    makeClient(['email' => 'c2@exemple.test']);
    makeClient(['email' => '']);
    $message = '<p>Bonjour <b>à tous</b></p>';
    $history = fn () => (array) DB::table('emailing')->orderByDesc('id')->first();

    emailing($admin, ['method' => 'prescripteur', 'prescripteur_select' => 'all']);
    expect(mailRecipients())->toBe(['p1@exemple.test', 'p2@exemple.test']);
    foreach (sentMails() as $mail) {
        expectBulkMail($mail, 'Objet emailing');
        expect(compactHtml((string) $mail->getHtmlBody()))->toBe(emailingBody('Objet emailing', $message));
    }
    $row = $history();
    expect([$row['type'], $row['destinataire'], $row['sujet'], $row['message']])->toBe(['prescripteur', 'all', 'Objet emailing', $message])
        ->and(abs(strtotime($row['date']) - time()))->toBeLessThan(120);

    flushMails();
    emailing($admin, ['method' => 'prescripteur', 'prescripteur_select' => 'p2@exemple.test']);
    expect(mailRecipients())->toBe(['p2@exemple.test'])
        ->and(array_slice($history(), 1, 2))->toBe(['type' => 'prescripteur', 'destinataire' => 'p2@exemple.test']);

    flushMails();
    emailing($admin, ['method' => 'programme', 'programme_select' => (string) $projet->projet_id]);
    expect(mailRecipients())->toBe([$admin->email, 'p1@exemple.test'])
        ->and(array_slice($history(), 1, 2))->toBe(['type' => 'programme', 'destinataire' => (string) $projet->projet_id]);

    flushMails();
    emailing($admin, ['method' => 'client', 'client_select' => 'all']);
    expect(mailRecipients())->toBe(['c1@exemple.test', 'c2@exemple.test'])
        ->and(array_slice($history(), 1, 2))->toBe(['type' => 'client', 'destinataire' => 'all']);

    flushMails();
    emailing($admin, ['method' => 'client', 'client_select' => 'c2@exemple.test']);
    expect(mailRecipients())->toBe(['c2@exemple.test'])
        ->and(array_slice($history(), 1, 2))->toBe(['type' => 'client', 'destinataire' => 'c2@exemple.test']);

    flushMails();
    emailing($admin, ['method' => 'keywords', 'keyword_select' => 'k1@exemple.test;k2@exemple.test;k3@exemple.test']);
    expect(mailRecipients())->toBe(['k1@exemple.test', 'k2@exemple.test', 'k3@exemple.test'])
        ->and(array_slice($history(), 1, 2))->toBe(['type' => 'autre', 'destinataire' => 'k1@exemple.test;k2@exemple.test;k3@exemple.test'])
        ->and(DB::table('emailing')->count())->toBe(6);
});

it('C16 l\'emailing passe par la file : un job par destinataire, réessais et limite de débit, historique écrit avant', function () {
    $admin = corimAdmin();
    Queue::fake();

    emailing($admin, ['method' => 'keywords', 'keyword_select' => 'k1@exemple.test;k2@exemple.test;k3@exemple.test']);

    expect(sentMails())->toBeEmpty()
        ->and(DB::table('emailing')->count())->toBe(1);
    $jobs = collect(Queue::pushedJobs())->flatten(1)->pluck('job');
    expect($jobs)->toHaveCount(3);
    foreach ($jobs as $job) {
        $retries = ($job->tries ?? 0) > 1 || ! empty($job->backoff) || method_exists($job, 'retryUntil') || method_exists($job, 'backoff');
        $middleware = collect(method_exists($job, 'middleware') ? $job->middleware() : ($job->middleware ?? []));
        expect($job)->toBeInstanceOf(ShouldQueue::class)
            ->and($retries)->toBeTrue('réessais')
            ->and($middleware->contains(fn ($m) => $m instanceof RateLimited || $m instanceof RateLimitedWithRedis || $m instanceof ThrottlesExceptions))->toBeTrue('limite de débit');
    }
});

it('C17 json/SearchClientEmailing : refusé hors admin, JSON pur pour l\'admin, callback ignoré', function () {
    $admin = corimAdmin();
    $presc = makeCommercial();
    makeClient(['nom' => 'MARTIN Paul', 'email' => 'paul.martin@exemple.test']);
    expectRouteExists(jsonAction($admin, 'SearchClientEmailing', ['keyword1' => 'MARTIN']), 'SearchClientEmailing');

    foreach ([null, $presc] as $user) {
        $response = jsonAction($user, 'SearchClientEmailing', ['keyword1' => 'MARTIN']);
        expect($response->getStatusCode())->toBeIn($user ? [302, 403] : [302, 401, 403])
            ->and((string) $response->getContent())->not->toContain('paul.martin@exemple.test');
    }

    app('auth')->forgetGuards();
    $found = $this->actingAs($admin)->post('/index.php/json/SearchClientEmailing/?callback=evil', ['keyword1' => 'MARTIN']);
    expect($found->headers->get('Content-Type'))->toStartWith('text/html')
        ->and((string) $found->getContent())->not->toContain('evil(')
        ->and($found->json('errors'))->toBe(1)
        ->and($found->json('call.0.email'))->toBe('paul.martin@exemple.test')
        ->and(decoded($found->json('call.0.nom')))->toBe('MARTIN Paul');

    expect(jsonAction($admin, 'SearchClientEmailing', ['keyword1' => 'INTROUVABLE'])->getContent())->toBe('{"errors":0}');
});

it('C18 la recherche par mots-clés couvre les 10 colonnes ; \' et % ne provoquent pas d\'erreur', function () {
    $admin = corimAdmin();
    $searched = ['date', 'source', 'projets', 'nom', 'adresse', 'tel', 'email', 'type_achat', 'type_logement', 'budget'];
    foreach (CLIENT_FIELDS as $field) {
        makeClient([$field => 'JETON'.strtoupper($field), 'email' => $field === 'email' ? 'JETONEMAIL' : "$field@exemple.test"]);
    }

    foreach (CLIENT_FIELDS as $field) {
        $response = jsonAction($admin, 'SearchClientEmailing', ['keyword1' => 'JETON'.strtoupper($field), 'keyword2' => '', 'keyword3' => '']);
        if (in_array($field, $searched, true)) {
            expect($response->json('errors'))->toBe(1, $field)->and($response->json('call'))->toHaveCount(1);
        } else {
            expect($response->getContent())->toBe('{"errors":0}', $field);
        }
    }

    foreach (["O'HARA", '100%', "'; DROP TABLE clients; --"] as $keyword) {
        $response = jsonAction($admin, 'SearchClientEmailing', ['keyword1' => $keyword]);
        expect($response->getStatusCode())->toBe(200, $keyword)
            ->and((string) $response->getContent())->not->toContain('SQLSTATE');
    }
    expect(DB::table('clients')->count())->toBe(count(CLIENT_FIELDS));
});

it('C19 la sélection de la modale remplit keyword_select (emails ;) et le compteur « N personne(s) dans la liste », « Vider la liste » remet à zéro', function () {
    $admin = corimAdmin();

    $form = pageText($this->actingAs($admin)->get('/emailing')->assertOk());
    expect($form)->toMatch('#<input type="hidden" name="keyword_select" id="keyword_select" value="" />#')
        ->toContain('id="callback_list_client"');

    $error = pageText(emailing($admin, ['objet' => '', 'method' => 'keywords', 'keyword_select' => 'a@exemple.test;b@exemple.test']));
    expect($error)->toContain('<b>2 personne(s) dans la liste</b>')
        ->toContain('value="a@exemple.test;b@exemple.test"');

    $js = implode('', array_map('file_get_contents', glob(public_path('medias/js/*.js'))));
    expect($js)->toContain('#vider_la_liste')->toContain('personne(s) dans la liste')->toContain('#keyword_select')
        ->toContain('VALIDER LA SELECTION')->toContain('Veuillez saisir au moins un mot clé pour effectuer une recherche de client.');
});

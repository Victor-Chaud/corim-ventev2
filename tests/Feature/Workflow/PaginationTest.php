<?php

use Illuminate\Support\Facades\DB;

it('P1 au-delà de 500 lignes, /denonciations est paginée côté serveur, du plus récent au plus ancien', function () {
    $admin = makeAdmin();
    $prescriber = makeCommercial();
    $program = makeProgram();
    $rows = [];
    for ($i = 1; $i <= 501; $i++) {
        $rows[] = prospectRow($program->projet_id, $prescriber->id, ['nomprospect' => sprintf('ACQ%03d', $i), 'date' => now()->subMinutes(1000 - $i)->format('Y-m-d H:i:s')]);
    }
    DB::table('prospects')->insert($rows);

    $first = pageText($this->actingAs($admin)->get('/denonciations')->assertOk());
    expect(substr_count($first, 'class="delete_denonciation'))->toBe(500)
        ->and($first)->toContain('ACQ501')->toContain('ACQ002')->not->toContain('ACQ001')
        ->toContain('class="pagination"')->toContain('/denonciations?page=2');

    $second = pageText($this->actingAs($admin)->get('/denonciations?page=2')->assertOk());
    expect(substr_count($second, 'class="delete_denonciation'))->toBe(1)
        ->and($second)->toContain('ACQ001')->not->toContain('ACQ501');
});

it('P2 une liste de 500 lignes ou moins reste sur une seule page sans pagination', function () {
    $admin = makeAdmin();
    $program = makeProgram();
    DB::table('prospects')->insert(prospectRow($program->projet_id, makeCommercial()->id));

    expect(pageText($this->actingAs($admin)->get('/denonciations')->assertOk()))->not->toContain('class="pagination"');
});

it('P3 la messagerie est paginée au-delà de 500 messages et les liens restent sur /messagerie', function () {
    $user = makeCommercial();
    $sender = makeAdmin();
    $rows = [];
    for ($i = 1; $i <= 501; $i++) {
        $rows[] = ['idemetteur' => $sender->id, 'idreceveur' => $user->id, 'sujet' => "Sujet $i", 'message' => 'Texte', 'date' => '2026-01-01 10:00:00', 'lu' => 0, 'cle' => bin2hex(random_bytes(20)), 'parent' => 0];
    }
    DB::table('mp')->insert($rows);

    $first = pageText($this->actingAs($user)->get('/messagerie')->assertOk());
    expect(substr_count($first, 'class="news-push"'))->toBe(500)->and($first)->toContain('/messagerie?page=2');

    $second = pageText($this->actingAs($user)->get('/messagerie?page=2')->assertOk());
    expect(substr_count($second, 'class="news-push"'))->toBe(1);
});

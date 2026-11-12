<?php

use Illuminate\Support\Facades\DB;

it('C18 DeletePromoteur désactive le compte, qui ne peut plus se connecter ; id inexistant → 0', function () {
    $admin = makeAdmin();
    $user = makeCommercial(['pseudo' => 'adesactiver']);
    expect(canLogin('adesactiver', 'secret'))->toBeTrue();

    expect(jsonAction($admin, 'DeletePromoteur', ['key' => $user->id])->getContent())->toBe('{"errors":1}')
        ->and((int) DB::table('user')->where('id', $user->id)->value('statut'))->toBe(0)
        ->and(canLogin('adesactiver', 'secret'))->toBeFalse()
        ->and(jsonAction($admin, 'DeletePromoteur', ['key' => 999999])->getContent())->toBe('{"errors":0}');
});

it('C19 ActivPromoteur réactive le compte', function () {
    $admin = makeAdmin();
    $user = makeCommercial(['pseudo' => 'areactiver', 'statut' => 0]);

    expect(jsonAction($admin, 'ActivPromoteur', ['key' => $user->id])->getContent())->toBe('{"errors":1}')
        ->and((int) DB::table('user')->where('id', $user->id)->value('statut'))->toBe(1)
        ->and(canLogin('areactiver', 'secret'))->toBeTrue()
        ->and(jsonAction($admin, 'ActivPromoteur', ['key' => 999999])->getContent())->toBe('{"errors":0}');
});

it('C20 TrashPromoteur supprime le compte et ses privileges, acces, acces_lot', function () {
    $admin = makeAdmin();
    $user = makeCommercial();
    $keep = makeCommercial();
    $projet = makeProjet();
    $lot = makeLot($projet->projet_id);
    foreach ([$user, $keep] as $u) {
        DB::table('acces')->insert(['iduser' => $u->id, 'idprojet' => $projet->projet_id]);
        DB::table('acces_lot')->insert(['iduser' => $u->id, 'idlot' => $lot->id]);
    }

    expect(jsonAction($admin, 'TrashPromoteur', ['key' => $user->id])->getContent())->toBe('{"errors":1}');
    foreach (['user' => 'id', 'privileges' => 'iduser', 'acces' => 'iduser', 'acces_lot' => 'iduser'] as $table => $column) {
        expect(DB::table($table)->where($column, $user->id)->count())->toBe(0, $table)
            ->and(DB::table($table)->where($column, $keep->id)->count())->toBe(1, $table);
    }
    expect(jsonAction($admin, 'TrashPromoteur', ['key' => $user->id])->getContent())->toBe('{"errors":0}');
});

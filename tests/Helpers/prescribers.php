<?php

const PRESCRIBER_PAGES = ['/gestion_promoteurs', '/gestion_promoteurs/actifs', '/gestion_promoteurs/inactifs', '/gestion_promoteurs/modifier/%d'];

const PRESCRIBER_ACTIONS = ['AddPromoteur', 'DeletePromoteur', 'TrashPromoteur', 'ActivPromoteur'];

function prescriberPayload(array $overrides = []): array
{
    return array_merge([
        'f_add_promoteur_pseudo' => 'nouveau',
        'f_add_promoteur_name' => 'NOUVEAU Compte',
        'f_add_promoteur_pwd' => 'MotDePasse1',
        'f_add_promoteur_email' => 'nouveau@exemple.test',
        'f_add_promoteur_statut' => '0',
    ], $overrides);
}

function editPayload(object $user, array $overrides = []): array
{
    return array_merge([
        'f_edit_promoteur_pseudo' => $user->pseudo,
        'f_edit_promoteur_name' => $user->name,
        'f_edit_promoteur_email' => $user->email,
        'f_edit_promoteur_pwd' => '',
        'f_edit_promoteur_statut' => (string) $user->statut,
    ], $overrides);
}

function accessMail(string $verb, string $pseudo, string $extra, string $footer): string
{
    return '<table width="500"><tr><td align="left"><img src="'.CORIM_BASE.'medias/images/logo-corim.png" /></td></tr><tr><td align="left">&nbsp;</td></tr><tr><td>Bonjour,<br /><br />Votre accès à la plateforme Corim Promotion a été '.$verb.'.<br /><br />Identifiant : '.$pseudo.'<br /><br />'.$extra.' Pour y accéder, vous pouvez cliquer <a href="'.CORIM_BASE.'">ici</a></td></tr><tr><td align="left">&nbsp;</td></tr><tr><td align="left">Immeuble "Le Cristal"<br />1475 av.Albert Einstein<br />34000  MONTPELLIER<br />Tél : 04 99 58 86 86<br />'.$footer.'</td></tr></table>';
}

function canLogin(string $pseudo, string $password): bool
{
    app('auth')->forgetGuards();
    app('session')->flush();
    $response = test()->post('/connexion', ['action' => 'connexion', 'identifiant' => $pseudo, 'password' => $password]);
    $ok = $response->isRedirect(url('/')) && auth()->check() && auth()->user()->pseudo === $pseudo;
    app('auth')->forgetGuards();
    app('session')->flush();

    return $ok;
}

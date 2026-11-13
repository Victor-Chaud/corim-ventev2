<?php

use Illuminate\Support\Facades\DB;

const SEPARATOR = '<div class="separator"></div>';

const WORKFLOW_TABLES = ['appartements', 'd_options', 'options', 'd_reservations', 'reservations', 'ventes', 'd_prospects', 'prospects', 'r_prospects', 'mp'];

const WORKFLOW_ACTIONS = ['AddOption', 'AddReservation', 'AddProspect', 'AcceptAddOption', 'RejetAddOption', 'AcceptAddResa', 'RejetAddResa', 'AcceptAddDenonciation', 'RejetAddDenonciation', 'PasserEnResa', 'AnnulerOption', 'AnnulerResa', 'PasserEnActer', 'Acter', 'AnnulerLaVente', 'DeleteDenonciation', 'UpdateDate', 'SearchProspect'];

function requestRow(object $lot, int $userId, array $attributes = []): array
{
    return array_merge([
        'iduser' => $userId,
        'nom' => 'Mme ACQUEREUR Anne',
        'adresse' => 'MONTPELLIER 34000',
        'message' => 'Message du prescripteur',
        'idprojet' => (int) $lot->lot,
        'date' => '2026-01-10 10:00:00',
    ], $attributes);
}

function prospectRow(int $programId, int $userId, array $attributes = []): array
{
    return array_merge([
        'nomprospect' => 'M PROSPECT Paul',
        'cpprospect' => '34000',
        'villeprospect' => 'MONTPELLIER',
        'smspromoteur' => 'Message de dénonciation',
        'idprojet' => $programId,
        'iduser' => $userId,
        'date' => '2026-01-20 10:00:00',
    ], $attributes);
}

function workflowWorld(): object
{
    $corim = corimAdmin();
    $prescriber = makeCommercial(['name' => 'Paul Prescripteur', 'email' => 'prescripteur@exemple.test']);
    $program = makeProgram(['projet_titre' => 'LES JARDINS TEST', 'projet_lieu' => 'MONTPELLIER']);
    DB::table('acces')->insert(['iduser' => $prescriber->id, 'idprojet' => $program->projet_id]);

    $lots = [];
    foreach (['libre' => '', 'askopt' => 'opt', 'askres' => 'res', 'opt' => 'opt', 'res' => 'res', 'act' => 'act'] as $name => $status) {
        $lots[$name] = makeLot($program->projet_id, ['statut' => $status, 'numero' => strtoupper($name).'-1']);
        DB::table('acces_lot')->insert(['iduser' => $prescriber->id, 'idlot' => $lots[$name]->id]);
    }
    DB::table('appartements')->where('id', $lots['act']->id)->update(['client' => 'Mme ACHETEUR Anne', 'vendeur' => $prescriber->id]);
    $lots['act'] = DB::table('appartements')->find($lots['act']->id);

    DB::table('d_options')->insert(requestRow($lots['askopt'], $prescriber->id) + ['iddlot' => $lots['askopt']->id]);
    DB::table('d_reservations')->insert(requestRow($lots['askres'], $prescriber->id) + ['iddlot' => $lots['askres']->id]);
    DB::table('options')->insert(requestRow($lots['opt'], $prescriber->id, ['date' => '2026-01-11 10:00:00']) + ['idlot' => $lots['opt']->id, 'statut' => 1]);
    DB::table('reservations')->insert(requestRow($lots['res'], $prescriber->id, ['date' => '2026-01-12 10:00:00']) + ['idlot' => $lots['res']->id, 'statut' => 1]);
    DB::table('ventes')->insert(['iduser' => $prescriber->id, 'idlot' => $lots['act']->id, 'date' => '2026-02-01 10:00:00', 'statut' => 1, 'date_resa' => '2026-01-05 10:00:00']);
    $askProspect = DB::table('d_prospects')->insertGetId(prospectRow($program->projet_id, $prescriber->id), 'idprospect');
    $prospect = DB::table('prospects')->insertGetId(prospectRow($program->projet_id, $prescriber->id, ['nomprospect' => 'Mme VALIDEE Julie']), 'idprospect');

    return (object) compact('corim', 'prescriber', 'program', 'lots', 'askProspect', 'prospect');
}

function optionPayload(object $lot, array $overrides = [], string $idKey = 'id_option'): array
{
    return array_merge([
        'civilite' => 'Mme',
        'name' => 'DUPONT',
        'prenom' => 'Claire',
        'adr' => 'MONTPELLIER',
        'cp' => '34000',
        'message' => 'Merci de bloquer ce lot',
        $idKey => $lot->id,
        'id_projet' => $lot->lot,
    ], $overrides);
}

function prospectPayload(int $programId, array $overrides = []): array
{
    return array_merge([
        'f_new_prospect_civilite' => 'Mme & M.',
        'f_new_prospect_name' => 'MARTIN',
        'f_new_prospect_prenom' => 'Louis',
        'f_new_prospect_cp' => '34070',
        'f_new_prospect_ville' => 'MONTPELLIER',
        'f_new_prospect_sms' => 'Clients intéressés par un T3',
        'f_new_prospect_projet' => $programId,
    ], $overrides);
}

function workflowActions(object $w): array
{
    $u = $w->prescriber->id;
    $l = $w->lots;

    return [
        'AddOption' => optionPayload($l['libre']),
        'AddReservation' => optionPayload($l['libre'], [], 'id_reserver'),
        'AddProspect' => prospectPayload($w->program->projet_id),
        'AcceptAddOption' => ['f_confirm_ask_option_lot' => $l['askopt']->id, 'f_confirm_ask_option_user' => $u, 'f_confirm_ask_option_sms' => 'Ok'],
        'RejetAddOption' => ['f_refuser_ask_option_lot' => $l['askopt']->id, 'f_refuser_ask_option_user' => $u, 'f_refuser_ask_option_sms' => 'Non'],
        'AcceptAddResa' => ['f_confirm_ask_resa_lot' => $l['askres']->id, 'f_confirm_ask_resa_user' => $u, 'f_confirm_ask_resa_sms' => 'Ok'],
        'RejetAddResa' => ['f_refuser_ask_resa_lot' => $l['askres']->id, 'f_refuser_ask_resa_user' => $u, 'f_refuser_ask_resa_sms' => 'Non'],
        'AcceptAddDenonciation' => ['f_confirm_ask_denonciation_lot' => $w->askProspect, 'f_confirm_ask_denonciation_user' => $u, 'f_confirm_ask_denonciation_sms' => 'Ok'],
        'RejetAddDenonciation' => ['f_refuser_ask_denonciation_lot' => $w->askProspect, 'f_refuser_ask_denonciation_user' => $u, 'f_refuser_ask_denonciation_sms' => 'Non'],
        'PasserEnResa' => ['f_passer_en_resa_lot' => $l['opt']->id, 'f_passer_en_resa_user' => $u, 'f_passer_en_resa_sms' => 'Ok'],
        'AnnulerOption' => ['f_annuler_option_lot' => $l['opt']->id, 'f_annuler_option_user' => $u, 'f_annuler_option_sms' => 'Non'],
        'AnnulerResa' => ['f_annuler_resa_lot' => $l['res']->id, 'f_annuler_resa_user' => $u, 'f_annuler_resa_sms' => 'Non'],
        'PasserEnActer' => ['passer_en_act_type' => 'resa', 'passer_en_act_lot' => $l['res']->id, 'f_passer_en_act_sms' => 'Ok'],
        'Acter' => ['type' => 'resa', 'idlot' => $l['res']->id],
        'AnnulerLaVente' => ['f_annuler_la_vente_idlot' => $l['act']->id, 'f_annuler_la_vente_vendeur' => $u, 'f_annuler_la_vente_sms' => 'Non'],
        'DeleteDenonciation' => ['denonciation' => $w->prospect],
        'UpdateDate' => ['idlot' => $l['opt']->id, 'type' => 'option', 'time' => '2026-05-05 08:00:00'],
        'SearchProspect' => ['f_recherche_denonciation_search' => 'VALIDEE', 'f_recherche_denonciation_type' => '1'],
    ];
}

function workflowState(): string
{
    return tablesState(WORKFLOW_TABLES);
}

function lotStatus(int $id): ?string
{
    return DB::table('appartements')->where('id', $id)->value('statut');
}

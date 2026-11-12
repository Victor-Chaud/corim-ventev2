<?php

use Illuminate\Support\Facades\DB;

const CLIENT_FIELDS = ['date', 'source', 'projets', 'civilite', 'nom', 'adresse', 'tel', 'email', 'type_achat', 'type_logement', 'budget', 'observation', 'reponse'];

const CLIENT_PAGES = ['/gestion_clients', '/gestion_clients/ajouter_client', '/gestion_clients/ajouter_clients', '/gestion_clients/edition_client/%d', '/gestion_clients/supprimer_client/%d', '/gestion_clients/telecharger_clients', '/gestion_prospects'];

function clientValues(array $overrides = []): array
{
    return array_merge([
        'date' => '01/02/2026',
        'source' => 'Salon',
        'projets' => 'LES JARDINS',
        'civilite' => 'Mme',
        'nom' => 'DUPONT Claire',
        'adresse' => '1 rue Test 34000 MONTPELLIER',
        'tel' => '0400000000',
        'email' => 'claire@exemple.test',
        'type_achat' => 'Investissement',
        'type_logement' => 'T2',
        'budget' => '200000',
        'observation' => 'Rappeler en mars',
        'reponse' => 'Intéressée',
    ], $overrides);
}

function makeClient(array $overrides = []): object
{
    $id = DB::table('clients')->insertGetId(clientValues($overrides), 'idclient');

    return DB::table('clients')->where('idclient', $id)->first();
}

function clientPages(int $id): array
{
    return array_map(fn ($url) => sprintf($url, $id), CLIENT_PAGES);
}

function clientCsv(array ...$rows): string
{
    return implode('', array_map(fn ($row) => implode(';', $row)."\r\n", $rows));
}

<?php

function dashboardCounters(string $content): array
{
    $labels = ['LOTS ACTÉ', "DEMANDES D'OPTIONS", 'OPTIONS', 'DEMANDES DE RÉSERVATIONS', 'RÉSERVATIONS', 'DEMANDES DE DÉNONCIATIONS', 'DÉNONCIATIONS'];
    $out = [];
    foreach ($labels as $label) {
        preg_match('#<a href="([^"]*)" class="list-group-item[^"]*">\s*<span class="badge">(\d+)</span>\s*(?:<span[^>]*></span>)?\s*'.preg_quote($label, '#').'\s*</a>#u', $content, $m);
        $out[$label] = $m ? [(int) $m[2], $m[1]] : null;
    }

    return $out;
}

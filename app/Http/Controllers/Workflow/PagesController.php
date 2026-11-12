<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

class PagesController extends Controller
{
    public function askOptions(Request $request): View
    {
        return $this->liste($request, 'ask_options', "Les demandes d'options", $this->demandes('d_options', 'iddoption'), 'd_options.iduser');
    }

    public function askReservations(Request $request): View
    {
        return $this->liste($request, 'ask_reservations', 'Les demandes de réservations', $this->demandes('d_reservations', 'iddresa'), 'd_reservations.iduser');
    }

    public function askDenonciations(Request $request): View
    {
        $query = DB::table('d_prospects')
            ->join('projet', 'd_prospects.idprojet', '=', 'projet.projet_id')
            ->join('user', 'd_prospects.iduser', '=', 'user.id')
            ->select('d_prospects.idprospect', 'd_prospects.iduser', 'd_prospects.nomprospect', 'd_prospects.cpprospect', 'd_prospects.villeprospect', 'd_prospects.smspromoteur', 'projet.projet_titre', 'projet.projet_lieu', 'user.name')
            ->selectRaw("DATE_FORMAT(d_prospects.date,'%d.%m.%Y') AS date")
            ->orderByDesc('d_prospects.date');

        return $this->liste($request, 'ask_denonciations', 'Les demandes de dénonciations', $query, 'd_prospects.iduser');
    }

    public function options(Request $request): View
    {
        return $this->optionsListe($request, 1);
    }

    private function optionsListe(Request $request, int $type): View
    {
        return $this->liste($request, 'options', $type === 1 ? 'Options acceptées' : 'Options annulées', $this->valides('options', 'idoption', $type), 'options.iduser', ['type' => $type]);
    }

    public function optionsRefused(Request $request): View
    {
        return $this->optionsListe($request, 0);
    }

    public function reservations(Request $request): View
    {
        return $this->reservationsListe($request, 1);
    }

    private function reservationsListe(Request $request, int $type): View
    {
        return $this->liste($request, 'reservations', $type === 1 ? 'Réservations acceptées' : 'Réservations annulées', $this->valides('reservations', 'idresa', $type), 'reservations.iduser', ['type' => $type]);
    }

    public function reservationsRefused(Request $request): View
    {
        return $this->reservationsListe($request, 0);
    }

    public function ventes(Request $request): View
    {
        $query = DB::table('appartements')
            ->join('projet', 'appartements.lot', '=', 'projet.projet_id')
            ->join('ventes', 'appartements.id', '=', 'ventes.idlot')
            ->select('appartements.*', 'projet.projet_titre')
            ->selectRaw("DATE_FORMAT(ventes.date, '%d.%m.%Y') AS date, DATE_FORMAT(ventes.date_resa, '%d.%m.%Y') AS resa")
            ->where('appartements.statut', 'act')
            ->where('ventes.statut', 1)
            ->orderBy('ventes.date');

        return $this->liste($request, 'ventes', 'Ventes', $query, 'appartements.vendeur');
    }

    public function denonciations(Request $request): View
    {
        $query = DB::table('prospects')
            ->join('projet', 'prospects.idprojet', '=', 'projet.projet_id')
            ->join('user', 'prospects.iduser', '=', 'user.id')
            ->select('prospects.*', 'projet.projet_titre', 'user.name AS prescripteur')
            ->selectRaw("DATE_FORMAT(prospects.date, '%d.%m.%Y') AS date")
            ->orderByDesc('prospects.date');

        return $this->liste($request, 'denonciations', 'Dénonciations', $query, 'prospects.iduser');
    }

    public function refusedAsk(Request $request): View
    {
        $query = DB::table('r_prospects')
            ->join('projet', 'r_prospects.idprojet', '=', 'projet.projet_id')
            ->join('user', 'r_prospects.iduser', '=', 'user.id')
            ->select('r_prospects.*', 'projet.projet_titre', 'user.name AS prescripteur')
            ->selectRaw("DATE_FORMAT(r_prospects.date, '%d.%m.%Y') AS date")
            ->orderByDesc('r_prospects.date');

        return $this->liste($request, 'refused_ask', 'Dénonciations refusées', $query, 'r_prospects.iduser');
    }

    public function optionsRefusees(Request $request): View
    {
        return $this->refus($request, 'r_options', 'Options refusées', ['/options' => 'Options acceptées', '/options/refused' => 'Options annulées']);
    }

    public function reservationsRefusees(Request $request): View
    {
        return $this->refus($request, 'r_reservations', 'Réservations refusées', ['/reservations' => 'Réservations acceptées', '/reservations/refused' => 'Réservations annulées']);
    }

    private function refus(Request $request, string $table, string $titre, array $liens): View
    {
        $query = DB::table($table)
            ->join('projet', "$table.idprojet", '=', 'projet.projet_id')
            ->join('user', "$table.iduser", '=', 'user.id')
            ->select("$table.*", 'projet.projet_titre', 'projet.projet_lieu', 'user.name')
            ->selectRaw("DATE_FORMAT($table.date, '%d.%m.%Y') AS date, DATE_FORMAT($table.date_refus, '%d.%m.%Y') AS date_refus")
            ->orderByDesc("$table.date_refus");

        return $this->liste($request, 'refus', $titre, $query, "$table.iduser", ['titre' => $titre, 'liens' => $liens]);
    }

    public function report(): View
    {
        $sum = "SUM(REPLACE(prix, ' ', ''))";
        $infos = DB::table('projet')->where('archive', 0)->orderByDesc('projet_id')->get()->map(function ($projet) use ($sum) {
            $stats = DB::table('appartements')->where('lot', (string) $projet->projet_id)->selectRaw(
                "COUNT(id) AS nb, $sum AS sum,
                COUNT(CASE WHEN statut = '' THEN id END) AS free, SUM(CASE WHEN statut = '' THEN REPLACE(prix, ' ', '') END) AS sum_free,
                COUNT(CASE WHEN statut = 'opt' THEN id END) AS opt, SUM(CASE WHEN statut = 'opt' THEN REPLACE(prix, ' ', '') END) AS sum_opt,
                COUNT(CASE WHEN statut = 'res' THEN id END) AS resa, SUM(CASE WHEN statut = 'res' THEN REPLACE(prix, ' ', '') END) AS sum_resa,
                COUNT(CASE WHEN statut = 'act' THEN id END) AS act, SUM(CASE WHEN statut = 'act' THEN REPLACE(prix, ' ', '') END) AS sum_act"
            )->first();
            $total = (float) ($stats->sum ?? 0);
            $cellules = [];
            foreach (['sum', 'sum_free', 'sum_opt', 'sum_resa', 'sum_act'] as $cle) {
                $valeur = (float) ($stats->{$cle} ?? 0);
                $cellules[$cle] = [
                    'montant' => $valeur > 0 ? number_format($valeur, 2, ',', ' ') : '0',
                    'pourcent' => $valeur > 0 && $total > 0 ? (string) round($valeur / $total * 100, 2) : '0',
                ];
            }

            return [
                'titre' => html_entity_decode((string) $projet->projet_titre, ENT_QUOTES),
                'image' => $this->vignette((string) $projet->projet_titre_key),
                'nb' => (int) ($stats->nb ?? 0), 'free' => (int) ($stats->free ?? 0), 'opt' => (int) ($stats->opt ?? 0),
                'resa' => (int) ($stats->resa ?? 0), 'act' => (int) ($stats->act ?? 0),
                'cellules' => $cellules,
            ];
        });

        return view('workflow.report', ['title' => 'Corim promotion - Report', 'infos' => $infos]);
    }

    public static function message(?string $message): HtmlString
    {
        $parts = explode(JsonController::SEPARATOR, (string) $message);

        return new HtmlString(implode(JsonController::SEPARATOR, array_map(fn ($part) => e(html_entity_decode($part, ENT_QUOTES)), $parts)));
    }

    private function vignette(string $key): ?string
    {
        foreach (['jpg', 'JPG', 'png', 'gif'] as $ext) {
            if (Storage::disk('programmes')->exists("$key/thumb/thumb.$ext")) {
                return "/medias/programmes/$key/thumb/thumb.$ext";
            }
        }

        return null;
    }

    private function demandes(string $table, string $id): Builder
    {
        return DB::table($table)
            ->join('projet', "$table.idprojet", '=', 'projet.projet_id')
            ->join('user', "$table.iduser", '=', 'user.id')
            ->join('appartements', "$table.iddlot", '=', 'appartements.id')
            ->select("$table.$id", "$table.iduser", "$table.nom", "$table.adresse", "$table.message", "$table.iddlot", 'projet.projet_titre', 'projet.projet_lieu', 'user.name', 'appartements.numero')
            ->selectRaw("DATE_FORMAT($table.date,'%d.%m.%Y') AS date")
            ->orderByDesc("$table.date");
    }

    private function valides(string $table, string $id, int $statut): Builder
    {
        return DB::table($table)
            ->join('projet', "$table.idprojet", '=', 'projet.projet_id')
            ->join('user', "$table.iduser", '=', 'user.id')
            ->join('appartements', "$table.idlot", '=', 'appartements.id')
            ->select("$table.$id", "$table.iduser", "$table.nom", "$table.adresse", "$table.message", "$table.idlot", 'projet.projet_titre', 'projet.projet_lieu', 'user.name', 'appartements.numero')
            ->selectRaw("DATE_FORMAT($table.date, '%d.%m.%Y') AS date")
            ->where("$table.statut", $statut)
            ->orderByDesc("$table.date");
    }

    private function liste(Request $request, string $vue, string $titre, Builder $query, string $colonneUser, array $data = []): View
    {
        $user = $request->user();
        $admin = $user instanceof User && $user->isAdmin();
        if (! $admin) {
            $query->where($colonneUser, $user instanceof User ? $user->id : 0);
        }

        return view('workflow.'.$vue, $data + [
            'title' => 'Corim promotion - '.$titre,
            'admin' => $admin,
            'rows' => $this->decode($query->get()),
        ]);
    }

    private function decode(Collection $rows): Collection
    {
        return $rows->map(function ($row) {
            foreach ((array) $row as $cle => $valeur) {
                if (is_string($valeur) && ! in_array($cle, ['message', 'smspromoteur'], true)) {
                    $row->{$cle} = html_entity_decode($valeur, ENT_QUOTES);
                }
            }

            return $row;
        });
    }
}

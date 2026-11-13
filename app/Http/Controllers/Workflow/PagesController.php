<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

class PagesController extends Controller
{
    public const PER_PAGE = 500;

    public function askOptions(Request $request): View
    {
        return $this->renderList($request, 'ask_options', "Les demandes d'options", $this->pendingRequests('d_options', 'iddoption'), 'd_options.iduser');
    }

    public function askReservations(Request $request): View
    {
        return $this->renderList($request, 'ask_reservations', 'Les demandes de réservations', $this->pendingRequests('d_reservations', 'iddresa'), 'd_reservations.iduser');
    }

    public function askReferrals(Request $request): View
    {
        $query = DB::table('d_prospects')
            ->join('projet', 'd_prospects.idprojet', '=', 'projet.projet_id')
            ->join('user', 'd_prospects.iduser', '=', 'user.id')
            ->select('d_prospects.idprospect', 'd_prospects.iduser', 'd_prospects.nomprospect', 'd_prospects.cpprospect', 'd_prospects.villeprospect', 'd_prospects.smspromoteur', 'projet.projet_titre', 'projet.projet_lieu', 'user.name')
            ->selectRaw("DATE_FORMAT(d_prospects.date,'%d.%m.%Y') AS date")
            ->orderByDesc('d_prospects.date')
            ->orderByDesc('d_prospects.idprospect');

        return $this->renderList($request, 'ask_referrals', 'Les demandes de dénonciations', $query, 'd_prospects.iduser');
    }

    public function options(Request $request): View
    {
        return $this->optionsList($request, 1);
    }

    private function optionsList(Request $request, int $type): View
    {
        return $this->renderList($request, 'options', $type === 1 ? 'Options acceptées' : 'Options annulées', $this->approved('options', 'idoption', $type), 'options.iduser', ['type' => $type]);
    }

    public function optionsRefused(Request $request): View
    {
        return $this->optionsList($request, 0);
    }

    public function reservations(Request $request): View
    {
        return $this->reservationsList($request, 1);
    }

    private function reservationsList(Request $request, int $type): View
    {
        return $this->renderList($request, 'reservations', $type === 1 ? 'Réservations acceptées' : 'Réservations annulées', $this->approved('reservations', 'idresa', $type), 'reservations.iduser', ['type' => $type]);
    }

    public function reservationsRefused(Request $request): View
    {
        return $this->reservationsList($request, 0);
    }

    public function sales(Request $request): View
    {
        $query = DB::table('appartements')
            ->join('projet', 'appartements.lot', '=', 'projet.projet_id')
            ->join('ventes', 'appartements.id', '=', 'ventes.idlot')
            ->select('appartements.*', 'projet.projet_titre')
            ->selectRaw("DATE_FORMAT(ventes.date, '%d.%m.%Y') AS date, DATE_FORMAT(ventes.date_resa, '%d.%m.%Y') AS resa")
            ->where('appartements.statut', 'act')
            ->where('ventes.statut', 1)
            ->orderBy('ventes.date')
            ->orderBy('ventes.idvente');

        return $this->renderList($request, 'sales', 'Ventes', $query, 'appartements.vendeur');
    }

    public function referrals(Request $request): View
    {
        $query = DB::table('prospects')
            ->join('projet', 'prospects.idprojet', '=', 'projet.projet_id')
            ->join('user', 'prospects.iduser', '=', 'user.id')
            ->select('prospects.*', 'projet.projet_titre', 'user.name AS prescripteur')
            ->selectRaw("DATE_FORMAT(prospects.date, '%d.%m.%Y') AS date")
            ->orderByDesc('prospects.date')
            ->orderByDesc('prospects.idprospect');

        return $this->renderList($request, 'referrals', 'Dénonciations', $query, 'prospects.iduser');
    }

    public function refusedAsk(Request $request): View
    {
        $query = DB::table('r_prospects')
            ->join('projet', 'r_prospects.idprojet', '=', 'projet.projet_id')
            ->join('user', 'r_prospects.iduser', '=', 'user.id')
            ->select('r_prospects.*', 'projet.projet_titre', 'user.name AS prescripteur')
            ->selectRaw("DATE_FORMAT(r_prospects.date, '%d.%m.%Y') AS date")
            ->orderByDesc('r_prospects.date')
            ->orderByDesc('r_prospects.idprospect');

        return $this->renderList($request, 'refused_ask', 'Dénonciations refusées', $query, 'r_prospects.iduser');
    }

    public function rejectedOptions(Request $request): View
    {
        return $this->rejections($request, 'r_options', 'Options refusées', ['/options' => 'Options acceptées', '/options/refused' => 'Options annulées']);
    }

    public function rejectedReservations(Request $request): View
    {
        return $this->rejections($request, 'r_reservations', 'Réservations refusées', ['/reservations' => 'Réservations acceptées', '/reservations/refused' => 'Réservations annulées']);
    }

    private function rejections(Request $request, string $table, string $title, array $links): View
    {
        $query = DB::table($table)
            ->join('projet', "$table.idprojet", '=', 'projet.projet_id')
            ->join('user', "$table.iduser", '=', 'user.id')
            ->select("$table.*", 'projet.projet_titre', 'projet.projet_lieu', 'user.name')
            ->selectRaw("DATE_FORMAT($table.date, '%d.%m.%Y') AS date, DATE_FORMAT($table.date_refus, '%d.%m.%Y') AS date_refus")
            ->orderByDesc("$table.date_refus")
            ->orderByDesc("$table.id");

        return $this->renderList($request, 'rejections', $title, $query, "$table.iduser", ['heading' => $title, 'links' => $links]);
    }

    public function report(): View
    {
        $sum = "SUM(REPLACE(prix, ' ', ''))";
        $infos = DB::table('projet')->where('archive', 0)->orderByDesc('projet_id')->get()->map(function ($program) use ($sum) {
            $stats = DB::table('appartements')->where('lot', (string) $program->projet_id)->selectRaw(
                "COUNT(id) AS nb, $sum AS sum,
                COUNT(CASE WHEN statut = '' THEN id END) AS free, SUM(CASE WHEN statut = '' THEN REPLACE(prix, ' ', '') END) AS sum_free,
                COUNT(CASE WHEN statut = 'opt' THEN id END) AS opt, SUM(CASE WHEN statut = 'opt' THEN REPLACE(prix, ' ', '') END) AS sum_opt,
                COUNT(CASE WHEN statut = 'res' THEN id END) AS resa, SUM(CASE WHEN statut = 'res' THEN REPLACE(prix, ' ', '') END) AS sum_resa,
                COUNT(CASE WHEN statut = 'act' THEN id END) AS act, SUM(CASE WHEN statut = 'act' THEN REPLACE(prix, ' ', '') END) AS sum_act"
            )->first();
            $total = (float) ($stats->sum ?? 0);
            $cells = [];
            foreach (['sum', 'sum_free', 'sum_opt', 'sum_resa', 'sum_act'] as $key) {
                $value = (float) ($stats->{$key} ?? 0);
                $cells[$key] = [
                    'montant' => $value > 0 ? number_format($value, 2, ',', ' ') : '0',
                    'pourcent' => $value > 0 && $total > 0 ? (string) round($value / $total * 100, 2) : '0',
                ];
            }

            return [
                'titre' => html_entity_decode((string) $program->projet_titre, ENT_QUOTES),
                'image' => $this->thumbnail((string) $program->projet_titre_key),
                'nb' => (int) ($stats->nb ?? 0), 'free' => (int) ($stats->free ?? 0), 'opt' => (int) ($stats->opt ?? 0),
                'resa' => (int) ($stats->resa ?? 0), 'act' => (int) ($stats->act ?? 0),
                'cellules' => $cells,
            ];
        });

        return view('workflow.report', ['title' => 'Corim promotion - Report', 'infos' => $infos]);
    }

    public static function message(?string $message): HtmlString
    {
        $parts = explode(JsonController::SEPARATOR, (string) $message);

        return new HtmlString(implode(JsonController::SEPARATOR, array_map(fn ($part) => e(html_entity_decode($part, ENT_QUOTES)), $parts)));
    }

    private function thumbnail(string $key): ?string
    {
        foreach (['jpg', 'JPG', 'png', 'gif'] as $ext) {
            if (Storage::disk('programmes')->exists("$key/thumb/thumb.$ext")) {
                return "/medias/programmes/$key/thumb/thumb.$ext";
            }
        }

        return null;
    }

    private function pendingRequests(string $table, string $id): Builder
    {
        return DB::table($table)
            ->join('projet', "$table.idprojet", '=', 'projet.projet_id')
            ->join('user', "$table.iduser", '=', 'user.id')
            ->join('appartements', "$table.iddlot", '=', 'appartements.id')
            ->select("$table.$id", "$table.iduser", "$table.nom", "$table.adresse", "$table.message", "$table.iddlot", 'projet.projet_titre', 'projet.projet_lieu', 'user.name', 'appartements.numero')
            ->selectRaw("DATE_FORMAT($table.date,'%d.%m.%Y') AS date")
            ->orderByDesc("$table.date")
            ->orderByDesc("$table.$id");
    }

    private function approved(string $table, string $id, int $status): Builder
    {
        return DB::table($table)
            ->join('projet', "$table.idprojet", '=', 'projet.projet_id')
            ->join('user', "$table.iduser", '=', 'user.id')
            ->join('appartements', "$table.idlot", '=', 'appartements.id')
            ->select("$table.$id", "$table.iduser", "$table.nom", "$table.adresse", "$table.message", "$table.idlot", 'projet.projet_titre', 'projet.projet_lieu', 'user.name', 'appartements.numero')
            ->selectRaw("DATE_FORMAT($table.date, '%d.%m.%Y') AS date")
            ->where("$table.statut", $status)
            ->orderByDesc("$table.date")
            ->orderByDesc("$table.$id");
    }

    private function renderList(Request $request, string $view, string $title, Builder $query, string $userColumn, array $data = []): View
    {
        $user = $request->user();
        $admin = $user instanceof User && $user->isAdmin();
        if (! $admin) {
            $query->where($userColumn, $user instanceof User ? $user->id : 0);
        }

        return view('workflow.'.$view, $data + [
            'title' => 'Corim promotion - '.$title,
            'admin' => $admin,
            'rows' => $query->paginate(self::PER_PAGE)->withQueryString()->through($this->decode(...)),
        ]);
    }

    private function decode(object $row): object
    {
        foreach ((array) $row as $key => $value) {
            if (is_string($value) && ! in_array($key, ['message', 'smspromoteur'], true)) {
                $row->{$key} = html_entity_decode($value, ENT_QUOTES);
            }
        }

        return $row;
    }
}

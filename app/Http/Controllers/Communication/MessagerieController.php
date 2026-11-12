<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MessagerieController extends Controller
{
    public static function query(): Builder
    {
        return DB::table('mp')->join('user', 'mp.idemetteur', '=', 'user.id')
            ->select('mp.*', 'user.name', DB::raw("DATE_FORMAT(mp.date, '%d.%m.%Y %H:%i') AS date_fr"));
    }

    public static function readable(User $user, string $cle): ?object
    {
        $query = self::query()->where('mp.cle', $cle)->orderBy('mp.id');
        if (! $user->isAdmin()) {
            $query->where(fn (Builder $q) => $q->where('mp.idemetteur', $user->id)->orWhere('mp.idreceveur', $user->id));
        }

        return $query->first();
    }

    public static function send(int $from, int $to, string $sujet, string $message, int $parent = 0): void
    {
        DB::table('mp')->insert([
            'idemetteur' => $from,
            'idreceveur' => $to,
            'sujet' => $sujet,
            'message' => $message,
            'date' => now()->format('Y-m-d H:i:s'),
            'lu' => 0,
            'cle' => bin2hex(random_bytes(20)),
            'parent' => $parent,
        ]);
    }

    private function inbox(User $user): array
    {
        $query = self::query()
            ->where(fn (Builder $q) => $q->where('mp.idreceveur', $user->id)->orWhere('mp.idemetteur', $user->id))
            ->orderByDesc('mp.id');
        if ($user->isAdmin()) {
            $query->whereNotIn('mp.sujet', AppServiceProvider::ADMIN_HIDDEN_SUBJECTS);
        }

        return $query->get()->all();
    }

    private function page(string $title, array $data = []): View
    {
        return view('communication.messagerie', $data + [
            'title' => $title,
            'messages' => null,
            'reading' => null,
            'parentMessage' => null,
            'children' => [],
            'deleted' => null,
        ]);
    }

    public function index(Request $request): View
    {
        return $this->page('Corim Promotion - Messagerie privée', ['messages' => $this->inbox($this->user($request))]);
    }

    public function consulter(Request $request, string $cle): View
    {
        $user = $this->user($request);
        $reading = self::readable($user, $cle);
        abort_if($reading === null, 404);

        $root = (int) $reading->parent === 0 ? (int) $reading->id : (int) $reading->parent;
        $parentMessage = self::query()->where('mp.id', $root)->first();
        $children = $parentMessage ? self::query()->where('mp.parent', $root)->where('mp.parent', '<>', 0)->orderBy('mp.id')->get()->all() : [];

        DB::table('mp')->where('idreceveur', $user->id)->where('cle', $cle)->update(['lu' => 1]);

        return $this->page('Corim Promotion - Consultation des messages privés', [
            'reading' => $reading,
            'parentMessage' => $parentMessage,
            'children' => $children,
        ]);
    }

    public function supprimer(Request $request): View
    {
        $user = $this->user($request);
        $cles = explode('|', (string) $request->input('delete_message', ''));
        array_pop($cles);
        $deleted = $cles === [] ? 0 : DB::table('mp')->where('idreceveur', $user->id)->whereIn('cle', $cles)->delete();

        return $this->page("Corim Promotion - Suppression d'un message", [
            'deleted' => $deleted,
            'messages' => $this->inbox($user),
        ]);
    }

    public function supprimerTout(Request $request): View
    {
        $user = $this->user($request);

        return $this->page('Corim Promotion - Suppression des messages', [
            'deleted' => DB::table('mp')->where('idreceveur', $user->id)->delete(),
        ]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}

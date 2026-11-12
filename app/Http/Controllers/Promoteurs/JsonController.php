<?php

namespace App\Http\Controllers\Promoteurs;

use App\Http\Controllers\Controller;
use App\Mail\Promoteurs\AccesMail;
use App\Support\LegacyJson;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class JsonController extends Controller
{
    private function json(array $data): Response
    {
        return LegacyJson::respond($data);
    }

    public function add(Request $request): Response
    {
        if ($request->post() === []) {
            return response('');
        }

        $pseudo = (string) $request->input('f_add_promoteur_pseudo', '');
        $name = (string) $request->input('f_add_promoteur_name', '');
        $pwd = (string) $request->input('f_add_promoteur_pwd', '');
        $email = (string) $request->input('f_add_promoteur_email', '');
        $type = (string) $request->input('f_add_promoteur_statut', '');

        if ($pseudo === '' || $name === '' || $pwd === '' || $email === '' || ! in_array($type, ['0', '1'], true)) {
            return $this->json(['errors' => 0, 'message' => 'Merci de renseigner tous les champs obligatoires']);
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['errors' => 2, 'message' => 'Adresse email non valide']);
        }

        DB::transaction(function () use ($pseudo, $name, $pwd, $email, $type) {
            $id = DB::table('user')->insertGetId([
                'pseudo' => htmlspecialchars($pseudo, ENT_QUOTES),
                'name' => htmlspecialchars($name, ENT_QUOTES),
                'password' => Hash::make($pwd),
                'email' => htmlspecialchars($email, ENT_QUOTES),
                'question' => '',
                'reponse' => '',
                'statut' => 1,
            ]);
            DB::table('privileges')->insert(['iduser' => $id, 'acces' => (int) $type]);
        });

        Mail::to($email)->send(new AccesMail($pseudo, $pwd));

        return $this->json(['errors' => 1, 'message' => 'Création réussie']);
    }

    public function deactivate(Request $request): Response
    {
        return $this->setStatut($request, 0);
    }

    public function activate(Request $request): Response
    {
        return $this->setStatut($request, 1);
    }

    public function destroy(Request $request): Response
    {
        if ($request->post() === []) {
            return response('');
        }

        $id = (int) $request->input('key');

        $deleted = DB::transaction(function () use ($id) {
            $deleted = DB::table('user')->where('id', $id)->delete();
            if ($deleted === 1) {
                DB::table('privileges')->where('iduser', $id)->delete();
                DB::table('acces')->where('iduser', $id)->delete();
                DB::table('acces_lot')->where('iduser', $id)->delete();
                DB::table('api_keys')->where('user_id', $id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
            }

            return $deleted;
        });

        return $this->json(['errors' => $deleted === 1 ? 1 : 0]);
    }

    private function setStatut(Request $request, int $statut): Response
    {
        if ($request->post() === []) {
            return response('');
        }

        $updated = DB::table('user')->where('id', (int) $request->input('key'))->update(['statut' => $statut]);

        return $this->json(['errors' => $updated === 1 ? 1 : 0]);
    }
}

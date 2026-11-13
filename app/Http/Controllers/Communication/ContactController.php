<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Mail\Communication\ContactMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $errors = [];
        $valid = null;
        if ($request->isMethod('post')) {
            $errors = MessageController::required($request, ['objet', 'message']);
            if ($errors === []) {
                $program = (string) $request->input('programme', '0');
                $message = (string) $request->input('message');
                if ($program !== '0' && $program !== '') {
                    $message = '<b>Programme concerné :</b> '.$program.'<br /><br />'.$message;
                }
                MessagingController::send($user->id, 1, MessageController::normalize((string) $request->input('objet')), MessageController::normalize($message));
                Mail::to((string) config('mail.corim.address'))->send(new ContactMail);
                $valid = 'Votre message à bien été envoyé';
            }
        }

        return view('communication.contact', [
            'title' => 'Corim promotion - Nous contacter',
            'formErrors' => $errors,
            'valid' => $valid,
            'programs' => DB::table('acces')->join('projet', 'acces.idprojet', '=', 'projet.projet_id')
                ->where('acces.iduser', $user->id)->orderByDesc('projet.projet_id')->select('projet.projet_titre')->get(),
        ]);
    }
}

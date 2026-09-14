<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificacionController extends Controller
{
    public function index(Request $request): View
    {
        return view('notificaciones.index', [
            'notificaciones' => $request->user()->notifications()->latest()->limit(50)->get(),
        ]);
    }

    public function marcarLeidas(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    /** La X de una notificación puntual: no desaparece sola, solo al pedirlo. */
    public function marcarLeida(Request $request, DatabaseNotification $notificacion): RedirectResponse
    {
        abort_unless($notificacion->notifiable_id === $request->user()->id, 403);

        $notificacion->markAsRead();

        return back();
    }
}

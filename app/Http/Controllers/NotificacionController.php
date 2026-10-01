<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
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

    /**
     * Cuántas hay sin leer. Lo consulta la campana cada tanto para enterarse
     * de las que llegan con la página abierta: sin esto el contador solo se
     * movía al recargar, y no había un momento de «llegada» en el que avisar.
     */
    public function contador(Request $request): JsonResponse
    {
        return response()->json([
            'sin_leer' => $request->user()->unreadNotifications()->count(),
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

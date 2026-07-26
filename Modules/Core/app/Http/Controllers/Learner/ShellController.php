<?php

namespace Modules\Core\Http\Controllers\Learner;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Sert le shell du SPA apprenant pour toutes les routes History API.
 * Le client décide (session locale IndexedDB) d'afficher login ou l'app.
 */
class ShellController extends Controller
{
    public function __invoke(): View
    {
        return view('core::learner.app');
    }
}

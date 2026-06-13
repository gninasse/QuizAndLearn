<?php

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * Afficher la page de profil
     */
    public function index()
    {
        $user = Auth::user();

        return view('core::profile.index', compact('user'));
    }

    /**
     * Mettre à jour l'avatar
     */
    public function updateAvatar(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ]);

        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar && file_exists(public_path($user->avatar))) {
                unlink(public_path($user->avatar));
            }

            // Upload new avatar
            $destinationPath = public_path('avatars');
            if (! file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            $avatar = $request->file('avatar');
            $cleanUserName = str_replace(' ', '_', strtolower($user->user_name));
            $avatarName = time().'_'.$cleanUserName.'.'.$avatar->extension();
            $avatar->move($destinationPath, $avatarName);
            $user->avatar = 'avatars/'.$avatarName;
            $user->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Avatar mis à jour avec succès',
            'avatar_url' => $user->avatar_url,
        ]);
    }

    /**
     * Modifier le mot de passe
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ], [
            'current_password.current_password' => 'Le mot de passe actuel est incorrect.',
            'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Déconnexion automatique
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe modifié avec succès. Vous allez être déconnecté.',
            'redirect' => route('login'),
        ]);
    }
}

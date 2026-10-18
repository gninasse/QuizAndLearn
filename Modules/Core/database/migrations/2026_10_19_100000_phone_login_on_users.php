<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Support\PhoneNumber;

/**
 * Connexion par téléphone : e-mail facultatif, téléphone normalisé et unique,
 * indicateur de changement de mot de passe obligatoire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->boolean('must_change_password')->default(false);
        });

        $seen = [];
        foreach (DB::table('users')->whereNotNull('phone')->orderBy('id')->get(['id', 'phone']) as $row) {
            $normalized = PhoneNumber::normalize($row->phone, config('showcase.default_country_code', '225'));

            if ($normalized !== null && isset($seen[$normalized])) {
                Log::warning('Téléphone en doublon après normalisation, valeur retirée.', [
                    'user_id' => $row->id,
                    'kept_user_id' => $seen[$normalized],
                    'phone' => $row->phone,
                ]);
                $normalized = null;
            } elseif ($normalized !== null) {
                $seen[$normalized] = $row->id;
            } else {
                Log::warning('Téléphone invalide, valeur retirée.', ['user_id' => $row->id, 'phone' => $row->phone]);
            }

            if ($normalized !== $row->phone) {
                DB::table('users')->where('id', $row->id)->update(['phone' => $normalized]);
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn('must_change_password');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrections du schéma des demandes d'accès :
 * - le téléphone du payeur est facultatif (comme dans la validation) ;
 * - supprimer un administrateur ne bloque plus sur les demandes qu'il a
 *   traitées ni sur les paiements qu'il a saisis (référence remise à NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('access_request_payments', function (Blueprint $table) {
            $table->string('payer_phone', 20)->nullable()->change();
            $table->dropForeign(['recorded_by']);
        });

        Schema::table('access_request_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('recorded_by')->nullable()->change();
            $table->foreign('recorded_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('access_requests', function (Blueprint $table) {
            $table->dropForeign(['handled_by']);
        });

        Schema::table('access_requests', function (Blueprint $table) {
            $table->foreign('handled_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('access_requests', function (Blueprint $table) {
            $table->dropForeign(['handled_by']);
        });

        Schema::table('access_requests', function (Blueprint $table) {
            $table->foreign('handled_by')->references('id')->on('users');
        });

        Schema::table('access_request_payments', function (Blueprint $table) {
            $table->dropForeign(['recorded_by']);
        });

        Schema::table('access_request_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('recorded_by')->nullable(false)->change();
            $table->foreign('recorded_by')->references('id')->on('users');
            $table->string('payer_phone', 20)->nullable(false)->change();
        });
    }
};

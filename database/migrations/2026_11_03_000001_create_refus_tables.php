<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['r_options', 'r_reservations'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->increments('id');
                $table->integer('iduser')->index();
                $table->string('nom', 250);
                $table->string('adresse', 250);
                $table->text('message');
                $table->integer('idlot')->index();
                $table->integer('idprojet')->index();
                $table->string('numero', 150);
                $table->dateTime('date');
                $table->dateTime('date_refus');
            });
        }

        Schema::table('r_prospects', function (Blueprint $table) {
            $table->dateTime('date_refus')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('r_prospects', function (Blueprint $table) {
            $table->dropColumn('date_refus');
        });
        Schema::dropIfExists('r_reservations');
        Schema::dropIfExists('r_options');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user', function (Blueprint $table) {
            $table->increments('id');
            $table->string('pseudo', 100);
            $table->string('name', 250);
            $table->string('password', 250);
            $table->string('email', 250);
            $table->string('question', 250)->default('');
            $table->string('reponse', 250)->default('');
            $table->integer('statut')->default(1);
        });

        Schema::create('privileges', function (Blueprint $table) {
            $table->integer('iduser')->unique();
            $table->integer('acces');
        });

        Schema::create('acces', function (Blueprint $table) {
            $table->integer('iduser')->index();
            $table->integer('idprojet')->index();
        });

        Schema::create('acces_lot', function (Blueprint $table) {
            $table->integer('iduser');
            $table->integer('idlot')->index();
            $table->unique(['iduser', 'idlot'], 'id');
        });

        Schema::create('projet', function (Blueprint $table) {
            $table->increments('projet_id');
            $table->string('projet_titre', 100);
            $table->string('projet_titre_key', 100);
            $table->string('projet_lieu', 100);
            $table->string('projet_image', 100);
            $table->integer('projet_nb_appartements');
            $table->text('projet_description');
            $table->unsignedTinyInteger('archive');
            $table->string('dept', 10);
            $table->string('livraison', 100);
        });

        Schema::create('appartements', function (Blueprint $table) {
            $table->increments('id');
            $table->string('lot', 20)->index();
            $table->string('date', 200);
            $table->integer('dept')->nullable();
            $table->string('ville', 100);
            $table->string('type', 20);
            $table->string('categorie', 20);
            $table->string('prix', 100);
            $table->string('prix_m2', 100);
            $table->string('etage', 50)->default('0');
            $table->string('RDC', 50)->default('0');
            $table->string('jardin', 50)->default('0');
            $table->string('terrasse', 50)->default('0');
            $table->string('superficie', 50);
            $table->string('stationnement', 100);
            $table->string('exposition', 100);
            $table->text('description');
            $table->string('statut', 100);
            $table->string('client', 100);
            $table->integer('vendeur')->nullable();
            $table->string('numero', 150);
            $table->string('cellier', 150);
            $table->string('accession', 250);
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->increments('idclient');
            foreach (['date' => 20, 'source' => 250, 'projets' => 250, 'civilite' => 20, 'nom' => 250, 'adresse' => 255, 'tel' => 70, 'email' => 200, 'type_achat' => 200, 'type_logement' => 200, 'budget' => 200] as $column => $length) {
                $table->string($column, $length)->default('X');
            }
            $table->text('observation');
            $table->text('reponse');
        });

        foreach (['d_options' => 'iddoption', 'd_reservations' => 'iddresa'] as $name => $key) {
            Schema::create($name, function (Blueprint $table) use ($key, $name) {
                $table->increments($key);
                $table->smallInteger('iduser')->default(1);
                $table->string('nom', 100);
                $table->string('adresse', $name === 'd_options' ? 150 : 100);
                $table->text('message');
                $table->integer('iddlot')->unique('iddlot');
                $table->integer('idprojet');
                $table->dateTime('date');
            });
        }

        foreach (['options' => 'idoption', 'reservations' => 'idresa'] as $name => $key) {
            Schema::create($name, function (Blueprint $table) use ($key) {
                $table->increments($key);
                $table->integer('iduser')->index();
                $table->string('nom', 250);
                $table->string('adresse', 250);
                $table->text('message');
                $table->integer('idlot')->index();
                $table->integer('idprojet');
                $table->dateTime('date');
                $table->integer('statut')->default(1);
            });
        }

        Schema::create('ventes', function (Blueprint $table) {
            $table->increments('idvente');
            $table->integer('iduser')->index();
            $table->integer('idlot')->index();
            $table->dateTime('date');
            $table->integer('statut')->default(1);
            $table->dateTime('date_resa');
        });

        foreach (['prospects', 'd_prospects', 'r_prospects'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->increments('idprospect');
                $table->string('nomprospect', 150);
                $table->string('cpprospect', 6);
                $table->string('villeprospect', 150);
                $table->text('smspromoteur');
                $table->integer('idprojet')->index();
                $name === 'd_prospects' ? $table->integer('iduser')->default(1) : $table->integer('iduser')->index();
                $table->dateTime('date');
            });
        }

        Schema::create('mp', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('idemetteur');
            $table->integer('idreceveur')->index();
            $table->string('sujet', 250);
            $table->text('message');
            $table->dateTime('date');
            $table->integer('lu')->default(0);
            $table->string('cle', 250)->index();
            $table->integer('parent');
        });

        Schema::create('emailing', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type', 100);
            $table->longText('destinataire');
            $table->text('sujet');
            $table->longText('message');
            $table->dateTime('date');
        });

        Schema::create('news', function (Blueprint $table) {
            $table->increments('id');
            foreach (['string1', 'string2', 'string3', 'string4', 'thumb'] as $column) {
                $table->string($column, 250);
            }
            $table->integer('date');
            $table->string('url', 250);
        });

        Schema::create('agenda', function (Blueprint $table) {
            $table->increments('agenda_id');
            $table->integer('user_id');
            $table->string('agenda_titre', 250);
            $table->text('agenda_description');
            $table->string('agenda_date', 8);
            $table->string('agenda_heure', 10);
        });

        Schema::create('docs', function (Blueprint $table) {
            $table->increments('iddocs');
            $table->text('email');
            $table->text('url');
            $table->string('time', 20);
        });

        Schema::create('villes', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('dept');
            $table->string('ville', 250);
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        foreach (['sessions', 'villes', 'docs', 'agenda', 'news', 'emailing', 'mp', 'r_prospects', 'd_prospects', 'prospects', 'ventes', 'reservations', 'options', 'd_reservations', 'd_options', 'clients', 'appartements', 'projet', 'acces_lot', 'acces', 'privileges', 'user'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};

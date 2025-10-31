<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('crm_origin'); // imobzi | imoview
            $table->string('external_id'); // property_id ou codigo
            $table->string('crm_code')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();

            // Valores
            $table->decimal('sale_value', 15, 2)->nullable();
            $table->decimal('rental_value', 15, 2)->nullable();
            $table->decimal('condominio', 15, 2)->nullable();
            $table->decimal('iptu', 15, 2)->nullable();

            // Tipos e finalidade
            $table->string('property_type')->nullable();
            $table->string('finality')->nullable(); // residencial, comercial, aluguel, etc
            $table->string('destaque')->boolean();
            $table->string('status')->nullable();



            // Endereço detalhado
            $table->string('address')->nullable();
            $table->string('address_complement')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zipcode')->nullable();
            $table->string('country')->nullable();

            // Áreas e cômodos
            $table->decimal('area_total', 10, 2)->nullable();
            $table->decimal('area_useful', 10, 2)->nullable();
            $table->integer('bedroom')->nullable();
            $table->integer('bathroom')->nullable();
            $table->integer('suite')->nullable();
            $table->integer('garage')->nullable();

            // Mídia
            $table->text('cover_photo')->nullable();
            $table->text('videos')->nullable();

            // Localização
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->timestamps();

            $table->unique(['crm_origin', 'external_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('properties');
    }
};

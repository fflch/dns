<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dominios', function (Blueprint $table) {
            $table->id();
            // Sem o sufixo fixo (config dns.sufixo): "abc" ou "anc.aaa"
            $table->string('nome')->index();
            $table->string('ip', 45);
            $table->text('motivo');
            // Servidor ou docente que pediu
            $table->foreignId('user_id')->constrained('users');
            // Admin que aprovou ou rejeitou por último
            $table->foreignId('avaliador_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dominios');
    }
};

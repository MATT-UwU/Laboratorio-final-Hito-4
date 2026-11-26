<?php
// © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Placas detectadas por la app que cumplen el filtro del estudiante
        // (números empiezan y terminan en 2, letras contienen M).
        Schema::create('placas_filtradas', function (Blueprint $table) {
            $table->id();
            $table->string('placa', 20)->index();
            $table->string('numeros', 20);
            $table->string('letras', 20);
            $table->decimal('confianza_ocr', 5, 3)->nullable();
            $table->string('origen', 20)->default('caseta'); // caseta | consulta
            $table->foreignId('paso_id')->nullable()->constrained('pasos')->nullOnDelete();
            $table->foreignId('caseta_id')->nullable()->constrained('casetas')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('imagen_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('placas_filtradas');
    }
};

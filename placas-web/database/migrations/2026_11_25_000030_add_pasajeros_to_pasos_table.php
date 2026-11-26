<?php
// © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Bonificación: número de pasajeros visibles dentro del vehículo (segmentación de personas).
    public function up(): void
    {
        Schema::table('pasos', function (Blueprint $table) {
            $table->unsignedSmallInteger('pasajeros')->nullable()->after('ejes_detectados');
        });
    }

    public function down(): void
    {
        Schema::table('pasos', function (Blueprint $table) {
            $table->dropColumn('pasajeros');
        });
    }
};

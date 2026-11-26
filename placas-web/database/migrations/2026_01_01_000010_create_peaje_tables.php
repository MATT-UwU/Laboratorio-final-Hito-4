<?php
// © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ajustar tabla users (ya existe, agregar columnas)
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'email_google')) {
                $table->string('google_id')->nullable()->after('id');
                $table->string('avatar')->nullable()->after('google_id');
                $table->enum('rol', ['admin', 'operador'])->default('operador')->after('email');
                $table->boolean('activo')->default(true)->after('rol');
                $table->string('password')->nullable()->change();
            }
        });

        // 2. Casetas
        Schema::create('casetas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('ubicacion')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        // 3. Vehículos
        Schema::create('vehiculos', function (Blueprint $table) {
            $table->id();
            $table->string('placa', 20)->unique();
            $table->string('propietario')->nullable();
            $table->enum('categoria', ['moto', 'auto', 'minibus', 'camion', 'bus'])->default('auto');
            $table->unsignedTinyInteger('ejes')->default(2);
            $table->decimal('saldo', 10, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // 4. Tarifas
        Schema::create('tarifas', function (Blueprint $table) {
            $table->id();
            $table->enum('categoria', ['moto', 'auto', 'minibus', 'camion', 'bus']);
            $table->unsignedTinyInteger('ejes')->default(2);
            $table->decimal('monto', 8, 2);
            $table->boolean('activa')->default(true);
            $table->timestamps();
            $table->unique(['categoria', 'ejes']);
        });

        // 5. Pasos
        Schema::create('pasos', function (Blueprint $table) {
            $table->id();
            $table->string('placa_leida', 20)->nullable();
            $table->string('placa_normalizada', 20)->nullable()->index();
            $table->decimal('confianza_ocr', 5, 2)->nullable();
            $table->enum('categoria_detectada', ['moto', 'auto', 'minibus', 'camion', 'bus'])->nullable();
            $table->unsignedTinyInteger('ejes_detectados')->nullable();
            $table->decimal('monto', 8, 2)->nullable();
            $table->foreignId('caseta_id')->nullable()->constrained('casetas')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('imagen_url')->nullable();
            $table->enum('estado', ['ok', 'alerta', 'sin_saldo', 'no_registrado'])->default('ok');
            $table->json('inferencia_raw')->nullable(); // JSON completo del endpoint
            $table->timestamps();
        });

        // 6. Alertas
        Schema::create('alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paso_id')->constrained('pasos')->cascadeOnDelete();
            $table->enum('tipo', ['no_registrado', 'sin_saldo', 'evasion', 'otro'])->default('otro');
            $table->boolean('atendida')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas');
        Schema::dropIfExists('pasos');
        Schema::dropIfExists('tarifas');
        Schema::dropIfExists('vehiculos');
        Schema::dropIfExists('casetas');
    }
};

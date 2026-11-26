<?php
// © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4

namespace Database\Seeders;

use App\Models\Caseta;
use App\Models\Tarifa;
use App\Models\Vehiculo;
use App\Models\User;
use Illuminate\Database\Seeder;

class PeajeSeeder extends Seeder
{
    public function run(): void
    {
        // Casetas
        $casetas = [
            ['nombre' => 'Caseta Norte', 'ubicacion' => 'Autopista Norte Km 12'],
            ['nombre' => 'Caseta Sur',   'ubicacion' => 'Autopista Sur Km 8'],
            ['nombre' => 'Caseta Este',  'ubicacion' => 'Ruta Nacional Este Km 5'],
        ];
        foreach ($casetas as $c) {
            Caseta::firstOrCreate(['nombre' => $c['nombre']], $c);
        }

        // Tarifas por categoría y ejes
        $tarifas = [
            ['categoria' => 'moto',    'ejes' => 2, 'monto' => 5.00],
            ['categoria' => 'auto',    'ejes' => 2, 'monto' => 10.00],
            ['categoria' => 'minibus', 'ejes' => 2, 'monto' => 15.00],
            ['categoria' => 'camion',  'ejes' => 2, 'monto' => 20.00],
            ['categoria' => 'camion',  'ejes' => 3, 'monto' => 30.00],
            ['categoria' => 'camion',  'ejes' => 4, 'monto' => 40.00],
            ['categoria' => 'bus',     'ejes' => 2, 'monto' => 18.00],
            ['categoria' => 'bus',     'ejes' => 3, 'monto' => 25.00],
        ];
        foreach ($tarifas as $t) {
            Tarifa::firstOrCreate(['categoria' => $t['categoria'], 'ejes' => $t['ejes']], $t);
        }

        // 20 vehículos de prueba
        $vehiculos = [
            ['placa' => '2M12',   'propietario' => 'Juan Perez',       'categoria' => 'auto',    'ejes' => 2, 'saldo' => 100.00],
            ['placa' => '27M2',   'propietario' => 'Maria Lopez',      'categoria' => 'auto',    'ejes' => 2, 'saldo' => 50.00],
            ['placa' => '2BM2',   'propietario' => 'Carlos Ruiz',      'categoria' => 'moto',    'ejes' => 2, 'saldo' => 30.00],
            ['placa' => '2XM72', 'propietario' => 'Ana Flores',        'categoria' => 'minibus', 'ejes' => 2, 'saldo' => 200.00],
            ['placa' => '2CM52', 'propietario' => 'Roberto Silva',     'categoria' => 'camion',  'ejes' => 3, 'saldo' => 500.00],
            ['placa' => '2DM42', 'propietario' => 'Laura Torres',      'categoria' => 'bus',     'ejes' => 2, 'saldo' => 150.00],
            ['placa' => '2EM32', 'propietario' => 'Pedro Morales',     'categoria' => 'auto',    'ejes' => 2, 'saldo' => 80.00],
            ['placa' => '2FM22', 'propietario' => 'Sofia Mendez',      'categoria' => 'auto',    'ejes' => 2, 'saldo' => 60.00],
            ['placa' => '2GM92', 'propietario' => 'Diego Castro',      'categoria' => 'moto',    'ejes' => 2, 'saldo' => 25.00],
            ['placa' => '2HM82', 'propietario' => 'Valentina Rios',    'categoria' => 'camion',  'ejes' => 4, 'saldo' => 800.00],
            ['placa' => '2IM62', 'propietario' => 'Nicolas Vargas',    'categoria' => 'auto',    'ejes' => 2, 'saldo' => 0.00],
            ['placa' => '2JM52', 'propietario' => 'Camila Gutierrez',  'categoria' => 'minibus', 'ejes' => 2, 'saldo' => 120.00],
            ['placa' => '2KM42', 'propietario' => 'Sebastian Herrera', 'categoria' => 'bus',     'ejes' => 3, 'saldo' => 300.00],
            ['placa' => '2LM32', 'propietario' => 'Isabella Jimenez',  'categoria' => 'auto',    'ejes' => 2, 'saldo' => 45.00],
            ['placa' => '2MM22', 'propietario' => 'Mateo Reyes',       'categoria' => 'camion',  'ejes' => 2, 'saldo' => 250.00],
            ['placa' => '2NM12', 'propietario' => 'Lucia Ortega',      'categoria' => 'auto',    'ejes' => 2, 'saldo' => 90.00],
            ['placa' => '2OM02', 'propietario' => 'Emilio Ramos',      'categoria' => 'moto',    'ejes' => 2, 'saldo' => 15.00],
            ['placa' => '2PM92', 'propietario' => 'Fernanda Cruz',     'categoria' => 'auto',    'ejes' => 2, 'saldo' => 70.00],
            ['placa' => '2QM82', 'propietario' => 'Alejandro Vega',    'categoria' => 'minibus', 'ejes' => 2, 'saldo' => 180.00],
            ['placa' => '2RM72', 'propietario' => 'Daniela Soto',      'categoria' => 'camion',  'ejes' => 3, 'saldo' => 400.00],
        ];
        foreach ($vehiculos as $v) {
            Vehiculo::firstOrCreate(['placa' => $v['placa']], $v);
        }

        // Usuarios autorizados (Lista Blanca)
        User::firstOrCreate(
            ['email' => 'mercierrengel@gmail.com'],
            [
                'name'      => 'Matias Mercier (Admin)',
                'google_id' => null,
                'rol'       => 'admin',
                'activo'    => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'doc.alvaro.pinto.ma@unifranz.edu.bo'],
            [
                'name'      => 'Álvaro Pinto (Docente)',
                'google_id' => null,
                'rol'       => 'admin',
                'activo'    => true,
            ]
        );
    }
}

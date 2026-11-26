<?php
// © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4

namespace Tests\Feature;

use App\Models\Caseta;
use App\Models\Paso;
use App\Models\Tarifa;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PasosFrameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name' => 'Operador de prueba',
            'email' => 'operador-frame@example.test',
            'password' => 'password',
            'rol' => 'operador',
            'activo' => true,
        ]));
    }

    public function test_repeated_frames_do_not_create_or_charge_duplicate_steps(): void
    {
        $caseta = Caseta::create(['nombre' => 'Caseta Norte']);
        $vehiculo = Vehiculo::create([
            'placa' => '2M12',
            'saldo' => 100,
            'categoria' => 'auto',
            'ejes' => 2,
        ]);
        Tarifa::create(['categoria' => 'auto', 'ejes' => 2, 'monto' => 10]);
        Storage::fake('public');
        config([
            'services.inference.url' => 'https://notebook.example',
            'services.inference.token' => 'test-inference-token',
        ]);
        Http::fake([
            'notebook.example/predict' => Http::response([
                'placa' => '2M12',
                'confianza' => 0.95,
                'categoria' => 'auto',
                'ejes' => 2,
            ]),
        ]);

        $sendFrame = function () use ($caseta) {
            return $this->withHeader('Accept', 'application/json')->post('/api/v1/pasos/frame', [
                'file' => UploadedFile::fake()->create('frame.jpg', 10, 'image/jpeg'),
                'caseta_id' => $caseta->id,
            ]);
        };

        $sendFrame()->assertCreated();
        $sendFrame()->assertOk()->assertJsonPath('duplicado', true);

        $this->assertSame(1, Paso::count());
        $this->assertSame(90.0, (float) $vehiculo->fresh()->saldo);
        Http::assertSent(fn ($request) => in_array(
            'test-inference-token',
            $request->header('X-API-Token'),
            true
        ));
    }

    public function test_frame_without_a_plate_is_not_saved_as_an_unregistered_vehicle(): void
    {
        $caseta = Caseta::create(['nombre' => 'Caseta Norte']);
        config([
            'services.inference.url' => 'https://notebook.example',
            'services.inference.token' => 'test-inference-token',
        ]);
        Http::fake([
            'notebook.example/predict' => Http::response([
                'placa' => null,
                'confianza' => 0,
                'categoria' => 'auto',
                'ejes' => 2,
            ]),
        ]);

        $this->withHeader('Accept', 'application/json')
            ->post('/api/v1/pasos/frame', [
                'file' => UploadedFile::fake()->create('frame.jpg', 10, 'image/jpeg'),
                'caseta_id' => $caseta->id,
            ])
            ->assertOk()
            ->assertJsonPath('estado', 'sin_placa');

        $this->assertSame(0, Paso::count());
    }

    public function test_a_new_passage_is_registered_after_the_plate_leaves_the_camera(): void
    {
        $caseta = Caseta::create(['nombre' => 'Caseta Norte']);
        $vehiculo = Vehiculo::create([
            'placa' => '2M12',
            'saldo' => 100,
            'categoria' => 'auto',
            'ejes' => 2,
        ]);
        Tarifa::create(['categoria' => 'auto', 'ejes' => 2, 'monto' => 10]);
        Storage::fake('public');
        config([
            'services.inference.url' => 'https://notebook.example',
            'services.inference.token' => 'test-inference-token',
        ]);

        $resultadoConPlaca = [
            'placa' => '2M12',
            'confianza' => 0.95,
            'categoria' => 'auto',
            'ejes' => 2,
        ];
        $resultadoSinPlaca = [
            'placa' => null,
            'confianza' => 0,
            'categoria' => 'auto',
            'ejes' => 2,
        ];
        Http::fake([
            'notebook.example/predict' => Http::sequence()
                ->push($resultadoConPlaca)
                ->push($resultadoSinPlaca)
                ->push($resultadoSinPlaca)
                ->push($resultadoSinPlaca)
                ->push($resultadoConPlaca),
        ]);

        $sendFrame = function () use ($caseta) {
            return $this->withHeader('Accept', 'application/json')->post('/api/v1/pasos/frame', [
                'file' => UploadedFile::fake()->create('frame.jpg', 10, 'image/jpeg'),
                'caseta_id' => $caseta->id,
            ]);
        };

        $sendFrame()->assertCreated();
        $sendFrame()->assertOk()->assertJsonPath('estado', 'sin_placa');
        $sendFrame()->assertOk()->assertJsonPath('estado', 'sin_placa');
        $sendFrame()->assertOk()->assertJsonPath('estado', 'sin_placa');
        $sendFrame()->assertCreated();

        $this->assertSame(2, Paso::count());
        $this->assertSame(80.0, (float) $vehiculo->fresh()->saldo);
    }
}

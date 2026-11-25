<?php

namespace Tests\Feature;

use App\Models\Caseta;
use App\Models\Paso;
use App\Models\User;
use App\Models\Vehiculo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgenteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name' => 'Operador de prueba',
            'email' => 'operador@example.test',
            'password' => 'password',
            'rol' => 'operador',
            'activo' => true,
        ]));
    }

    public function test_chat_reports_the_four_plate_states_and_ocr_suggestions(): void
    {
        $registradaConPaso = Vehiculo::create(['placa' => '2M12']);
        Vehiculo::create(['placa' => '27M2']);
        Vehiculo::create(['placa' => '2M10']);
        $caseta = Caseta::create(['nombre' => 'Caseta Norte']);
        Paso::create([
            'placa_leida' => '2M12',
            'placa_normalizada' => $registradaConPaso->placa,
            'categoria_detectada' => 'auto',
            'ejes_detectados' => 2,
            'monto' => 10,
            'caseta_id' => $caseta->id,
        ]);
        Paso::create([
            'placa_leida' => '9Z99',
            'placa_normalizada' => '9Z99',
            'categoria_detectada' => 'auto',
            'ejes_detectados' => 2,
            'monto' => 10,
            'caseta_id' => $caseta->id,
            'estado' => 'no_registrado',
        ]);

        $this->postJson('/chat', ['mensaje' => 'Dime el estado de la placa 2M12'])
            ->assertOk()
            ->assertJsonPath('datos.estado', 'registrada_con_paso');

        $this->postJson('/chat', ['mensaje' => 'Consulta la placa 27M2'])
            ->assertOk()
            ->assertJsonPath('datos.estado', 'registrada_sin_paso');

        $this->postJson('/chat', ['mensaje' => 'Consulta la placa 9Z99'])
            ->assertOk()
            ->assertJsonPath('datos.estado', 'identificada_no_registrada');

        $this->postJson('/chat', ['mensaje' => 'Consulta la placa 2M1O'])
            ->assertOk()
            ->assertJsonPath('datos.estado', 'no_existe')
            ->assertJsonPath('datos.sugerencias', ['2M10']);
    }

    public function test_chat_asks_for_a_plate_instead_of_matching_an_ordinary_word(): void
    {
        $this->postJson('/chat', ['mensaje' => 'Dime el estado de mi vehículo'])
            ->assertOk()
            ->assertJsonPath('respuesta', 'Por favor escribe la placa que deseas consultar (ej: 2M12, ABC123).');
    }

    public function test_web_image_consultation_forwards_to_the_external_notebook_api(): void
    {
        Vehiculo::create(['placa' => '2M12']);
        config(['services.inference.url' => 'https://notebook.example/']);
        config(['services.inference.token' => 'test-inference-token']);
        Http::fake([
            'notebook.example/predict' => Http::response([
                'placa' => '2M12',
                'confianza' => 0.94,
            ]),
        ]);

        $this->get('/chat')->assertOk();

        $this->post('/chat/imagen', [
            'file' => UploadedFile::fake()->create('placa.jpg', 10, 'image/jpeg'),
        ])
            ->assertOk()
            ->assertJsonPath('placa_detectada', '2M12')
            ->assertJsonPath('estado', 'registrada_sin_paso');

        Http::assertSent(fn ($request) => $request->url() === 'https://notebook.example/predict'
            && in_array('test-inference-token', $request->header('X-API-Token'), true));
    }
}

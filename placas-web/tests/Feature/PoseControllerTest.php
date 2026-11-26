<?php
// © 2026 Matías Darío Mercier Rengel — Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados. https://github.com/MATT-UwU/Laboratorio-final-Hito-4

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PoseControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name' => 'Operador de prueba',
            'email' => 'operador-pose@example.test',
            'password' => 'password',
            'rol' => 'operador',
            'activo' => true,
        ]));
    }

    public function test_pose_endpoint_forwards_the_image_and_names_visible_coco_keypoints(): void
    {
        config([
            'services.pose_colab.url' => 'https://pose-colab.example/',
            'services.pose_colab.token' => 'test-pose-token',
        ]);

        $keypoints = array_fill(0, 17, [0, 0, 0.1]);
        $keypoints[0] = [25.0, 12.0, 0.9];
        $keypoints[5] = [30.0, 20.0, 0.8];

        Http::fake([
            'pose-colab.example/predict' => Http::response([
                'modelo' => 'detectron2/keypoint_rcnn_R_50_FPN_3x',
                'inferencia_ms' => 42.5,
                'personas' => [[
                    'caja' => [10, 5, 80, 120],
                    'score' => 0.97,
                    'keypoints' => $keypoints,
                ]],
            ]),
        ]);

        $this->post('/api/v1/pose/analizar', [
            'file' => UploadedFile::fake()->create('vehiculo.jpg', 10, 'image/jpeg'),
        ])
            ->assertOk()
            ->assertJsonPath('estado', 'personas_detectadas')
            ->assertJsonPath('total_personas', 1)
            ->assertJsonPath('personas.0.keypoints.nose.x', 25)
            ->assertJsonPath('personas.0.keypoints.left_shoulder.confianza', 0.8)
            ->assertJsonPath('personas.0.total_partes_visibles', 2);

        Http::assertSent(fn ($request) => $request->url() === 'https://pose-colab.example/predict'
            && in_array('test-pose-token', $request->header('X-API-Token'), true));
    }

    public function test_pose_endpoint_returns_an_empty_result_when_colab_detects_no_person(): void
    {
        config([
            'services.pose_colab.url' => 'https://pose-colab.example',
            'services.pose_colab.token' => 'test-pose-token',
        ]);
        Http::fake([
            'pose-colab.example/predict' => Http::response([
                'inferencia_ms' => 15.0,
                'personas' => [],
            ]),
        ]);

        $this->post('/api/v1/pose/analizar', [
            'file' => UploadedFile::fake()->create('vehiculo.jpg', 10, 'image/jpeg'),
        ])
            ->assertOk()
            ->assertJsonPath('estado', 'sin_personas')
            ->assertJsonPath('total_personas', 0);
    }
}

// © 2026 Matías Darío Mercier Rengel — App móvil del Sistema Inteligente de Control de Peaje (UNIFRANZ). Todos los derechos reservados.
// Repositorio original: https://github.com/MATT-UwU/Laboratorio-final-Hito-4
import 'dart:async';
import 'dart:io' show SocketException;
import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:camera/camera.dart';
import 'package:http/http.dart' as http;
import 'package:image/image.dart' as img;
import 'package:image_picker/image_picker.dart';
import 'package:google_sign_in/google_sign_in.dart';

// Configuración de la API del Peaje (backend Laravel en el VPS, por HTTPS).
// Se puede cambiar al compilar con --dart-define=API_BASE_URL=...
const String kBaseApiUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'https://peaje.xn--camaracomercioespaola-tbc.tech',
);

// Client ID *Web* de Google Cloud (el mismo GOOGLE_CLIENT_ID de Laravel).
// Sin él, Android no entrega idToken y Laravel no puede validar la cuenta.
const String kGoogleWebClientId = String.fromEnvironment('GOOGLE_WEB_CLIENT_ID');

// Botón "Operador Demo" solo en compilaciones de prueba (--dart-define=DEMO_LOGIN=true).
const bool kDemoLogin = bool.fromEnvironment('DEMO_LOGIN', defaultValue: false);

// Botón "Analizar pose de personas": no es parte de la rúbrica del peaje (--dart-define=POSE=true).
const bool kPose = bool.fromEnvironment('POSE', defaultValue: false);

// Tiempo máximo de espera de las llamadas a la API (la inferencia corre en Colab).
const Duration kTimeoutFrame = Duration(seconds: 20);
const Duration kTimeoutApi = Duration(seconds: 30);

// Ancho máximo del frame que se envía a la API y calidad JPEG.
const int kAnchoMaximoFrame = 960;
const int kCalidadJpeg = 80;

/// true si la URL de la API apunta a un recurso local o sin HTTPS (la rúbrica no lo acepta).
bool urlApiEsLocal(String url) {
  final u = url.toLowerCase();
  return u.startsWith('http://') ||
      u.contains('localhost') ||
      u.contains('127.0.0.1') ||
      u.contains('://10.') ||
      u.contains('192.168.');
}

final GlobalKey<NavigatorState> navigatorKey = GlobalKey<NavigatorState>();

GoogleSignIn crearGoogleSignIn() => GoogleSignIn(
      scopes: const ['email'],
      serverClientId: kGoogleWebClientId.isEmpty ? null : kGoogleWebClientId,
    );

/// Cierra la sesión de Google y vuelve al login borrando toda la pila de pantallas.
Future<void> volverAlLogin({String? mensaje}) async {
  try {
    await crearGoogleSignIn().signOut();
  } catch (_) {
    // Si Google no responde, igual se vuelve al login.
  }
  navigatorKey.currentState?.pushAndRemoveUntil(
    MaterialPageRoute(builder: (_) => AuthWrapperScreen(mensajeInicial: mensaje)),
    (_) => false,
  );
}

/// Convierte números que llegan como num o como texto ("10.00") desde la API.
num? aNumero(dynamic valor) {
  if (valor is num) return valor;
  if (valor is String) return num.tryParse(valor);
  return null;
}

bool _sesionExpirando = false;

/// Si la API responde 401, el token ya no es válido: se vuelve al login una sola vez.
bool manejarSesionExpirada(int statusCode) {
  if (statusCode != 401) return false;
  if (!_sesionExpirando) {
    _sesionExpirando = true;
    volverAlLogin(mensaje: 'Sesión expirada, inicia sesión de nuevo').whenComplete(() => _sesionExpirando = false);
  }
  return true;
}

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const PeajeApp());
}

class PeajeApp extends StatelessWidget {
  const PeajeApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Control de Peaje Inteligente',
      debugShowCheckedModeBanner: false,
      navigatorKey: navigatorKey,
      theme: ThemeData.dark().copyWith(
        scaffoldBackgroundColor: const Color(0xFF111827),
        primaryColor: const Color(0xFF4F46E5),
        appBarTheme: const AppBarTheme(
          backgroundColor: Color(0xFF1F2937),
          elevation: 0,
        ),
      ),
      home: const AuthWrapperScreen(),
    );
  }
}

// ─── PANTALLA DE AUTENTICACIÓN GOOGLE ─────────────────────────────────────────

class AuthWrapperScreen extends StatefulWidget {
  final String? mensajeInicial;
  const AuthWrapperScreen({super.key, this.mensajeInicial});

  @override
  State<AuthWrapperScreen> createState() => _AuthWrapperScreenState();
}

class _AuthWrapperScreenState extends State<AuthWrapperScreen> {
  final GoogleSignIn _googleSignIn = crearGoogleSignIn();
  bool _isLoading = false;
  String? _errorMsg;

  @override
  void initState() {
    super.initState();
    _errorMsg = widget.mensajeInicial;
  }

  // Iniciar sesión con Google OAuth 2.0
  Future<void> _iniciarSesionConGoogle() async {
    setState(() {
      _isLoading = true;
      _errorMsg = null;
    });

    try {
      final googleAccount = await _googleSignIn.signIn();
      if (googleAccount == null) {
        setState(() => _isLoading = false);
        return; // Usuario canceló
      }

      final googleAuth = await googleAccount.authentication;
      // Laravel solo valida el idToken: no se usa el accessToken como respaldo.
      final idToken = googleAuth.idToken;
      if (idToken == null) {
        setState(() => _errorMsg = 'Google no devolvió un token. Revisa GOOGLE_WEB_CLIENT_ID y el SHA-1 en Google Cloud.');
        return;
      }

      // Enviar token de Google a Laravel: POST /api/v1/auth/google
      final res = await http.post(
        Uri.parse('$kBaseApiUrl/api/v1/auth/google'),
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
        body: jsonEncode({'token': idToken}),
      ).timeout(const Duration(seconds: 10));

      if (res.statusCode == 200) {
        final data = jsonDecode(res.body);
        final token = data['token'] as String;
        final user = data['user'] as Map<String, dynamic>;

        if (mounted) {
          Navigator.pushReplacement(
            context,
            MaterialPageRoute(
              builder: (_) => MainNavigationScreen(token: token, usuario: user),
            ),
          );
        }
      } else {
        final err = jsonDecode(res.body);
        setState(() => _errorMsg = err['error'] ?? 'Acceso denegado: cuenta no autorizada.');
      }
    } catch (e) {
      // Modo demostración offline/fallback si no hay Google Play Services configurado
      setState(() => _errorMsg = 'Error conectando con autenticación: $e');
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  // Bypass para pruebas directas en laboratorio
  void _entrarModoDemo() {
    Navigator.pushReplacement(
      context,
      MaterialPageRoute(
        builder: (_) => const MainNavigationScreen(
          token: "demo_token_operador",
          usuario: {"name": "Operador Caseta", "rol": "operador", "email": "operador@peaje.com"},
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 90,
                height: 90,
                decoration: BoxDecoration(
                  color: const Color(0xFF4F46E5),
                  borderRadius: BorderRadius.circular(24),
                ),
                child: const Icon(Icons.toll, size: 50, color: Colors.white),
              ),
              const SizedBox(height: 24),
              const Text(
                'Peaje Inteligente',
                style: TextStyle(fontSize: 26, fontWeight: FontWeight.bold, color: Colors.white),
              ),
              const SizedBox(height: 8),
              const Text(
                'Sistema de Detección de Vehículos y Placas',
                style: TextStyle(fontSize: 14, color: Colors.grey),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 6),
              const Text(
                '© 2026 Matías Darío Mercier Rengel',
                style: TextStyle(fontSize: 11, color: Colors.white38),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 40),

              if (urlApiEsLocal(kBaseApiUrl))
                Container(
                  margin: const EdgeInsets.only(bottom: 20),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.red.shade900,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Text(
                    'La API apunta a un recurso local o sin HTTPS ($kBaseApiUrl). '
                    'La rúbrica no acepta recursos locales: compila con --dart-define=API_BASE_URL=https://...',
                    style: TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.bold),
                  ),
                ),

              if (_errorMsg != null)
                Container(
                  margin: const EdgeInsets.only(bottom: 20),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.red.withOpacity(0.2),
                    border: Border.all(color: Colors.redAccent),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(_errorMsg!, style: const TextStyle(color: Colors.redAccent, fontSize: 13)),
                ),

              if (_isLoading)
                const CircularProgressIndicator()
              else ...[
                ElevatedButton.icon(
                  onPressed: _iniciarSesionConGoogle,
                  icon: const Icon(Icons.login, color: Colors.black87),
                  label: const Text(
                    'Iniciar sesión con Google',
                    style: TextStyle(color: Colors.black87, fontWeight: FontWeight.bold),
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.white,
                    minimumSize: const Size(double.infinity, 50),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
                if (kDemoLogin) ...[
                  const SizedBox(height: 14),
                  TextButton(
                    onPressed: _entrarModoDemo,
                    child: const Text('Entrar como Operador Demo', style: TextStyle(color: Colors.grey)),
                  ),
                ],
              ],
            ],
          ),
        ),
      ),
    );
  }
}

// ─── NAVEGACIÓN PRINCIPAL (CASETA Y CONSULTA) ────────────────────────────────

class MainNavigationScreen extends StatefulWidget {
  final String token;
  final Map<String, dynamic> usuario;

  const MainNavigationScreen({super.key, required this.token, required this.usuario});

  @override
  State<MainNavigationScreen> createState() => _MainNavigationScreenState();
}

class _MainNavigationScreenState extends State<MainNavigationScreen> {
  int _currentIndex = 0;

  @override
  Widget build(BuildContext context) {
    final List<Widget> pages = [
      ModoCasetaScreen(token: widget.token),
      ModoConsultaScreen(token: widget.token),
    ];

    return Scaffold(
      appBar: AppBar(
        title: Text(_currentIndex == 0 ? 'Modo Caseta' : 'Modo Consulta'),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 16),
            child: Center(
              child: Text(
                widget.usuario['name'] ?? 'Usuario',
                style: const TextStyle(fontSize: 12, color: Colors.indigoAccent),
              ),
            ),
          ),
          IconButton(
            tooltip: 'Cerrar sesión',
            icon: const Icon(Icons.logout),
            onPressed: () => volverAlLogin(),
          ),
        ],
      ),
      body: pages[_currentIndex],
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: _currentIndex,
        onTap: (index) => setState(() => _currentIndex = index),
        backgroundColor: const Color(0xFF1F2937),
        selectedItemColor: const Color(0xFF818CF8),
        unselectedItemColor: Colors.grey,
        items: const [
          BottomNavigationBarItem(icon: Icon(Icons.videocam), label: 'Caseta'),
          BottomNavigationBarItem(icon: Icon(Icons.search), label: 'Consulta Agente'),
        ],
      ),
    );
  }
}

// ─── 4.1 MODO CASETA: VIDEO EN TIEMPO REAL A LA API ──────────────────────────

class ModoCasetaScreen extends StatefulWidget {
  final String token;
  const ModoCasetaScreen({super.key, required this.token});

  @override
  State<ModoCasetaScreen> createState() => _ModoCasetaScreenState();
}

/// Conversión YUV420 → JPEG. Corre en un isolate (compute) para no congelar la interfaz.
/// Recibe copias de los planos, reduce a [kAnchoMaximoFrame] px de ancho y rota según la
/// orientación del sensor y del dispositivo para que la placa quede derecha.
Uint8List? convertirFrameAJpeg(Map<String, Object> datos) {
  try {
    final int ancho = datos['ancho'] as int;
    final int alto = datos['alto'] as int;
    final Uint8List yBytes = datos['y'] as Uint8List;
    final Uint8List uBytes = datos['u'] as Uint8List;
    final Uint8List vBytes = datos['v'] as Uint8List;
    final int yRowStride = datos['yRowStride'] as int;
    final int yPixelStride = datos['yPixelStride'] as int;
    final int uvRowStride = datos['uvRowStride'] as int;
    final int uvPixelStride = datos['uvPixelStride'] as int;
    final int rotacion = datos['rotacion'] as int;
    final int anchoMaximo = datos['anchoMaximo'] as int;
    final int calidad = datos['calidad'] as int;

    // Reducción por muestreo directo: se convierte solo los píxeles que quedan en la salida.
    final int anchoSalida = ancho > anchoMaximo ? anchoMaximo : ancho;
    final int altoSalida = (alto * anchoSalida / ancho).round();
    final double paso = ancho / anchoSalida;

    final imagen = img.Image(width: anchoSalida, height: altoSalida);
    for (int oy = 0; oy < altoSalida; oy++) {
      final int y = (oy * paso).floor().clamp(0, alto - 1);
      for (int ox = 0; ox < anchoSalida; ox++) {
        final int x = (ox * paso).floor().clamp(0, ancho - 1);
        final int yIndex = y * yRowStride + x * yPixelStride;
        final int uvIndex = (y ~/ 2) * uvRowStride + (x ~/ 2) * uvPixelStride;
        if (yIndex >= yBytes.length || uvIndex >= uBytes.length || uvIndex >= vBytes.length) {
          continue;
        }
        final int yp = yBytes[yIndex];
        final int up = uBytes[uvIndex] - 128;
        final int vp = vBytes[uvIndex] - 128;

        final int r = (yp + 1.402 * vp).round().clamp(0, 255);
        final int g = (yp - 0.344136 * up - 0.714136 * vp).round().clamp(0, 255);
        final int b = (yp + 1.772 * up).round().clamp(0, 255);
        imagen.setPixelRgb(ox, oy, r, g, b);
      }
    }

    final img.Image salida = rotacion == 0 ? imagen : img.copyRotate(imagen, angle: rotacion);
    return Uint8List.fromList(img.encodeJpg(salida, quality: calidad));
  } catch (_) {
    return null;
  }
}

class _ModoCasetaScreenState extends State<ModoCasetaScreen> {
  CameraController? _cameraController;
  bool _isProcessingFrame = false;
  int _casetaId = 1;
  DateTime? _ultimoFrameEnviado;

  // Casetas disponibles (GET /api/v1/casetas). Si la API falla se usan 1, 2 y 3.
  List<Map<String, dynamic>> _casetas = <Map<String, dynamic>>[
    <String, dynamic>{'id': 1, 'nombre': 'Caseta 1'},
    <String, dynamic>{'id': 2, 'nombre': 'Caseta 2'},
    <String, dynamic>{'id': 3, 'nombre': 'Caseta 3'},
  ];

  // Rúbrica 4.1: 2 a 5 fps. 400 ms = 2.5 fps como objetivo (limitado también por la latencia de la inferencia).
  static const Duration _intervaloFrames = Duration(milliseconds: 400);

  // Estado del último vehículo detectado
  String? _placaLeida;
  String? _categoria;
  int? _ejes;
  int? _pasajeros;
  double? _tarifa;
  String _estado = "ESPERANDO";
  bool _duplicado = false;
  bool _cumpleFiltro = false;
  int? _latenciaColabMs;
  int? _latenciaTotalMs;

  @override
  void initState() {
    super.initState();
    _cargarCasetas();
    _iniciarCamara();
  }

  Future<void> _cargarCasetas() async {
    try {
      final res = await http.get(
        Uri.parse('$kBaseApiUrl/api/v1/casetas'),
        headers: {'Accept': 'application/json', 'Authorization': 'Bearer ${widget.token}'},
      ).timeout(kTimeoutApi);
      if (manejarSesionExpirada(res.statusCode)) return;
      if (res.statusCode != 200) return;

      final lista = (jsonDecode(res.body) as List<dynamic>)
          .whereType<Map<String, dynamic>>()
          .where((c) => c['id'] is int)
          .map<Map<String, dynamic>>((c) => <String, dynamic>{'id': c['id'] as int, 'nombre': (c['nombre'] ?? 'Caseta ${c['id']}').toString()})
          .toList();
      if (lista.isEmpty || !mounted) return;

      setState(() {
        _casetas = lista;
        if (!_casetas.any((c) => c['id'] == _casetaId)) {
          _casetaId = _casetas.first['id'] as int;
        }
      });
    } catch (_) {
      // Se mantiene la lista de respaldo.
    }
  }

  String get _nombreCaseta {
    // Sin firstWhere/orElse: el tipo en tiempo de ejecución de la lista puede no ser Map<String, dynamic>.
    for (final c in _casetas) {
      if (c['id'] == _casetaId) return (c['nombre'] ?? 'Caseta $_casetaId').toString();
    }
    return 'Caseta $_casetaId';
  }

  Future<void> _iniciarCamara() async {
    final cameras = await availableCameras();
    if (cameras.isEmpty) return;

    _cameraController = CameraController(
      cameras[0],
      ResolutionPreset.medium,
      enableAudio: false,
      imageFormatGroup: ImageFormatGroup.yuv420,
    );

    await _cameraController!.initialize();
    if (!mounted) return;
    setState(() {});

    // Captura continua: el intervalo se comprueba ANTES de convertir, así los frames
    // descartados no gastan CPU.
    _cameraController!.startImageStream((CameraImage image) {
      if (_isProcessingFrame) return;
      final ahora = DateTime.now();
      if (_ultimoFrameEnviado != null && ahora.difference(_ultimoFrameEnviado!) < _intervaloFrames) {
        return;
      }
      _ultimoFrameEnviado = ahora;
      _isProcessingFrame = true;
      _procesarFrame(image);
    });
  }

  /// Grados que hay que rotar la imagen del sensor para que quede derecha en pantalla.
  int _calcularRotacion() {
    final controller = _cameraController;
    if (controller == null) return 0;
    final int sensor = controller.description.sensorOrientation;
    const gradosDispositivo = {
      DeviceOrientation.portraitUp: 0,
      DeviceOrientation.landscapeLeft: 90,
      DeviceOrientation.portraitDown: 180,
      DeviceOrientation.landscapeRight: 270,
    };
    final int dispositivo = gradosDispositivo[controller.value.deviceOrientation] ?? 0;
    final bool frontal = controller.description.lensDirection == CameraLensDirection.front;
    final int rotacion = frontal ? (sensor + dispositivo) % 360 : (sensor - dispositivo + 360) % 360;
    return rotacion;
  }

  Future<void> _procesarFrame(CameraImage cameraImage) async {
    try {
      final datos = <String, Object>{
        'ancho': cameraImage.width,
        'alto': cameraImage.height,
        'y': Uint8List.fromList(cameraImage.planes[0].bytes),
        'u': Uint8List.fromList(cameraImage.planes[1].bytes),
        'v': Uint8List.fromList(cameraImage.planes[2].bytes),
        'yRowStride': cameraImage.planes[0].bytesPerRow,
        'yPixelStride': cameraImage.planes[0].bytesPerPixel ?? 1,
        'uvRowStride': cameraImage.planes[1].bytesPerRow,
        'uvPixelStride': cameraImage.planes[1].bytesPerPixel ?? 1,
        'rotacion': _calcularRotacion(),
        'anchoMaximo': kAnchoMaximoFrame,
        'calidad': kCalidadJpeg,
      };

      final cronometro = Stopwatch()..start();
      final bytes = await compute(convertirFrameAJpeg, datos);
      if (bytes == null) return;

      final uri = Uri.parse('$kBaseApiUrl/api/v1/pasos/frame');
      final req = http.MultipartRequest('POST', uri);
      req.headers['Authorization'] = 'Bearer ${widget.token}';
      req.headers['Accept'] = 'application/json';
      req.fields['caseta_id'] = _casetaId.toString();
      req.files.add(http.MultipartFile.fromBytes('file', bytes, filename: 'frame.jpg'));

      final streamed = await req.send().timeout(kTimeoutFrame);
      final resp = await http.Response.fromStream(streamed).timeout(kTimeoutFrame);
      cronometro.stop();

      if (manejarSesionExpirada(resp.statusCode)) return;

      if (resp.statusCode == 200 || resp.statusCode == 201) {
        final data = jsonDecode(resp.body) as Map<String, dynamic>;
        final pasoRaw = data['paso'];
        final Map<String, dynamic> paso = pasoRaw is Map ? Map<String, dynamic>.from(pasoRaw) : <String, dynamic>{};
        final raw = paso['inferencia_raw'];
        final latenciaColab = raw is Map ? aNumero(raw['latencia_ms'])?.round() : null;

        if (mounted) {
          setState(() {
            _latenciaTotalMs = cronometro.elapsedMilliseconds;
            _latenciaColabMs = latenciaColab;
            _duplicado = data['duplicado'] == true;
            _cumpleFiltro = data['cumple_filtro'] == true;
            if (data['estado'] == 'sin_placa') {
              _placaLeida = null;
              _categoria = null;
              _ejes = null;
              _pasajeros = null;
              _tarifa = null;
              _estado = 'sin_placa';
            } else {
              // Laravel serializa los decimales como texto ("10.00"): se convierten con aNumero().
              _placaLeida = (paso['placa_normalizada'] ?? paso['placa_leida'])?.toString();
              _categoria = paso['categoria_detectada']?.toString();
              _ejes = aNumero(paso['ejes_detectados'])?.toInt();
              _pasajeros = aNumero(paso['pasajeros'])?.toInt();
              _tarifa = aNumero(paso['monto'] ?? data['tarifa'])?.toDouble();
              _estado = (paso['estado'] ?? 'ok').toString();
            }
          });
        }
      } else if (mounted) {
        setState(() {
          _duplicado = false;
          _cumpleFiltro = false;
          _estado = 'API ${resp.statusCode}';
        });
      }
    } on TimeoutException {
      _marcarError('SIN CONEXIÓN');
    } on http.ClientException {
      _marcarError('SIN CONEXIÓN');
    } on SocketException {
      _marcarError('SIN CONEXIÓN');
    } catch (e) {
      // Respuesta recibida pero no se pudo interpretar: no es un problema de red.
      debugPrint('Error procesando la respuesta del frame: $e');
      _marcarError('ERROR RESPUESTA');
    } finally {
      _isProcessingFrame = false;
    }
  }

  @override
  void dispose() {
    _cameraController?.dispose();
    super.dispose();
  }

  void _marcarError(String estado) {
    if (!mounted) return;
    setState(() {
      _duplicado = false;
      _cumpleFiltro = false;
      _estado = estado;
    });
  }

  Color _obtenerColorEstado() {
    switch (_estado) {
      case 'ok':
        return Colors.greenAccent;
      case 'sin_saldo':
        return Colors.orangeAccent;
      case 'no_registrado':
      case 'alerta':
      case 'SIN CONEXIÓN':
      case 'ERROR RESPUESTA':
        return Colors.redAccent;
      case 'sin_placa':
        return Colors.grey;
      default:
        return Colors.grey;
    }
  }

  Widget _etiqueta(String texto, Color color) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withOpacity(0.2),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(texto, style: TextStyle(color: color, fontWeight: FontWeight.bold, fontSize: 12)),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_cameraController == null || !_cameraController!.value.isInitialized) {
      return const Center(child: CircularProgressIndicator());
    }

    final latencia = 'Colab: ${_latenciaColabMs != null ? '$_latenciaColabMs ms' : '—'}'
        ' · Total: ${_latenciaTotalMs != null ? '$_latenciaTotalMs ms' : '—'}';

    return Stack(
      fit: StackFit.expand,
      children: [
        CameraPreview(_cameraController!),

        // Panel superior de selección de caseta
        Positioned(
          top: 16,
          left: 16,
          right: 16,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
            decoration: BoxDecoration(
              color: Colors.black.withOpacity(0.75),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Flexible(
                  child: Text(
                    "Caseta actual: $_nombreCaseta",
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                  ),
                ),
                DropdownButton<int>(
                  value: _casetaId,
                  dropdownColor: Colors.grey[900],
                  items: [
                    for (final caseta in _casetas)
                      DropdownMenuItem(value: caseta['id'] as int, child: Text(caseta['nombre'].toString())),
                  ],
                  onChanged: (val) => setState(() => _casetaId = val ?? _casetaId),
                )
              ],
            ),
          ),
        ),

        // Panel Inferior: Información del vehículo, categoría, ejes, tarifa y latencia
        Positioned(
          bottom: 20,
          left: 16,
          right: 16,
          child: Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.black.withOpacity(0.85),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: _obtenerColorEstado(), width: 2),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Flexible(
                      child: Text(
                        _placaLeida ?? "Buscando vehículo...",
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontSize: 22, fontWeight: FontWeight.bold, letterSpacing: 1.5, color: Colors.white),
                      ),
                    ),
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        if (_cumpleFiltro) ...[
                          _etiqueta('FILTRO ✓', Colors.limeAccent),
                          const SizedBox(width: 6),
                        ],
                        if (_duplicado) ...[
                          _etiqueta('MISMO VEHÍCULO', Colors.lightBlueAccent),
                          const SizedBox(width: 6),
                        ],
                        _etiqueta(_estado.toUpperCase(), _obtenerColorEstado()),
                      ],
                    ),
                  ],
                ),
                const Divider(color: Colors.white24, height: 16),
                Wrap(
                  spacing: 18,
                  runSpacing: 4,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    Text("Categoría: ${_categoria ?? '—'}", style: const TextStyle(color: Colors.white70)),
                    Text("Ejes: ${_ejes ?? '—'}", style: const TextStyle(color: Colors.white70)),
                    Text("Pasajeros: ${_pasajeros ?? '—'}", style: const TextStyle(color: Colors.white70)),
                    Text(
                      _tarifa != null ? "Tarifa: Bs ${_tarifa!.toStringAsFixed(2)}" : "Tarifa: —",
                      style: const TextStyle(color: Colors.greenAccent, fontWeight: FontWeight.bold, fontSize: 15),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Text(latencia, style: const TextStyle(color: Colors.white54, fontSize: 12)),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

// ─── 4.1 MODO CONSULTA: TEXTO O FOTO AL AGENTE CONVERSACIONAL ─────────────────

class ModoConsultaScreen extends StatefulWidget {
  final String token;
  const ModoConsultaScreen({super.key, required this.token});

  @override
  State<ModoConsultaScreen> createState() => _ModoConsultaScreenState();
}

class _ModoConsultaScreenState extends State<ModoConsultaScreen> {
  final TextEditingController _textoController = TextEditingController();
  final ImagePicker _picker = ImagePicker();
  bool _cargando = false;
  Map<String, dynamic>? _resultado;

  Future<void> _consultarPorTexto() async {
    final placa = _textoController.text.trim();
    if (placa.isEmpty) return;

    setState(() {
      _cargando = true;
      _resultado = null;
    });

    try {
      final res = await http.post(
        Uri.parse('$kBaseApiUrl/api/v1/placas/consultar'),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'Authorization': 'Bearer ${widget.token}',
        },
        body: jsonEncode({'placa': placa}),
      ).timeout(kTimeoutApi);

      if (manejarSesionExpirada(res.statusCode)) return;
      if (res.statusCode == 200) {
        setState(() => _resultado = jsonDecode(res.body));
      } else {
        setState(() => _resultado = {'mensaje': 'Error en la consulta HTTP ${res.statusCode}'});
      }
    } catch (e) {
      setState(() => _resultado = {'mensaje': 'Error de conexión: $e'});
    } finally {
      if (mounted) setState(() => _cargando = false);
    }
  }

  Future<void> _consultarPorFoto(ImageSource origen) async {
    final XFile? foto = await _picker.pickImage(source: origen, maxWidth: 1024);
    if (foto == null) return;

    setState(() {
      _cargando = true;
      _resultado = null;
    });

    try {
      final bytes = await foto.readAsBytes();
      final uri = Uri.parse('$kBaseApiUrl/api/v1/placas/consultar-imagen');
      final req = http.MultipartRequest('POST', uri);
      req.headers['Authorization'] = 'Bearer ${widget.token}';
      req.headers['Accept'] = 'application/json';
      req.files.add(http.MultipartFile.fromBytes('file', bytes, filename: 'placa.jpg'));

      final streamed = await req.send().timeout(kTimeoutApi);
      final resp = await http.Response.fromStream(streamed).timeout(kTimeoutApi);

      if (manejarSesionExpirada(resp.statusCode)) return;
      if (resp.statusCode == 200) {
        setState(() => _resultado = jsonDecode(resp.body));
      } else {
        final data = jsonDecode(resp.body);
        setState(() => _resultado = {
          'mensaje': data['error'] ?? data['message'] ?? 'Error del servicio HTTP ${resp.statusCode}.',
        });
      }
    } catch (e) {
      setState(() => _resultado = {'mensaje': 'Error al enviar foto: $e'});
    } finally {
      if (mounted) setState(() => _cargando = false);
    }
  }

  Future<void> _analizarPose(ImageSource origen) async {
    final XFile? foto = await _picker.pickImage(source: origen, maxWidth: 1024);
    if (!mounted || foto == null) return;

    setState(() {
      _cargando = true;
      _resultado = null;
    });

    try {
      final req = http.MultipartRequest(
        'POST',
        Uri.parse('$kBaseApiUrl/api/v1/pose/analizar'),
      );
      req.headers['Authorization'] = 'Bearer ${widget.token}';
      req.headers['Accept'] = 'application/json';
      req.files.add(http.MultipartFile.fromBytes(
        'file',
        await foto.readAsBytes(),
        filename: foto.name,
      ));

      final streamed = await req.send().timeout(const Duration(seconds: 50));
      final resp = await http.Response.fromStream(streamed);
      if (manejarSesionExpirada(resp.statusCode)) return;
      if (!mounted) return;
      final data = jsonDecode(resp.body);

      if (resp.statusCode == 200) {
        setState(() => _resultado = data);
      } else {
        setState(() => _resultado = {
          'estado': 'error',
          'mensaje': data['error'] ?? data['message'] ?? 'Error del servicio HTTP ${resp.statusCode}.',
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() => _resultado = {
          'estado': 'error',
          'mensaje': 'Error al analizar la pose: $e',
        });
      }
    } finally {
      if (mounted) setState(() => _cargando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Card(
            color: const Color(0xFF1F2937),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  TextField(
                    controller: _textoController,
                    textCapitalization: TextCapitalization.characters,
                    decoration: InputDecoration(
                      hintText: 'Ingresar placa (ej: 2M12)',
                      filled: true,
                      fillColor: const Color(0xFF374151),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
                      suffixIcon: IconButton(
                        icon: const Icon(Icons.search, color: Colors.indigoAccent),
                        onPressed: _consultarPorTexto,
                      ),
                    ),
                    onSubmitted: (_) => _consultarPorTexto(),
                  ),
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: () => _consultarPorFoto(ImageSource.camera),
                          icon: const Icon(Icons.camera_alt),
                          label: const Text('Tomar Foto'),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: () => _consultarPorFoto(ImageSource.gallery),
                          icon: const Icon(Icons.photo_library),
                          label: const Text('Galería'),
                        ),
                      ),
                    ],
                  ),
                  if (kPose) ...[
                    const SizedBox(height: 10),
                    OutlinedButton.icon(
                      onPressed: () => _analizarPose(ImageSource.camera),
                      icon: const Icon(Icons.accessibility_new),
                      label: const Text('Analizar pose de personas'),
                    ),
                  ],
                ],
              ),
            ),
          ),
          const SizedBox(height: 20),

          if (_cargando)
            const Center(child: Padding(padding: EdgeInsets.all(24), child: CircularProgressIndicator())),

          if (_resultado != null)
            Card(
              color: const Color(0xFF1F2937),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Icon(Icons.smart_toy, color: Colors.indigoAccent),
                        const SizedBox(width: 8),
                        Text(
                          "Estado: ${_resultado!['estado'] ?? 'Respuesta'}",
                          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.white),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Text(
                      _resultado!['mensaje'] ?? _resultado.toString(),
                      style: const TextStyle(fontSize: 15, color: Colors.white70, height: 1.4),
                    ),
                    if (_resultado!['personas'] is List) ...[
                      const Divider(color: Colors.white24, height: 24),
                      for (final persona in _resultado!['personas'] as List<dynamic>)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: Text(
                            'Persona ${persona['id']}: ${persona['total_partes_visibles']} partes visibles '
                            '(${(persona['partes_visibles'] as List<dynamic>).join(', ')}).',
                            style: const TextStyle(color: Colors.white70),
                          ),
                        ),
                    ],
                    if (_resultado!['cumple_filtro'] == true) ...[
                      const SizedBox(height: 10),
                      const Text(
                        'FILTRO ✓ (números 2…2 y letras con M): guardada en la web',
                        style: TextStyle(color: Colors.limeAccent, fontWeight: FontWeight.bold),
                      ),
                    ],
                    if (_resultado!['vehiculo'] != null) ...[
                      const Divider(color: Colors.white24, height: 24),
                      Text("Propietario: ${_resultado!['vehiculo']['propietario'] ?? 'N/A'}", style: const TextStyle(color: Colors.white70)),
                      Text("Saldo actual: Bs ${_resultado!['vehiculo']['saldo'] ?? '0.00'}", style: const TextStyle(color: Colors.greenAccent)),
                    ]
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

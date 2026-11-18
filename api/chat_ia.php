<?php
/**
 * =============================================================================
 * TutoresOnLine - Endpoint API: Asistente de Chat con IA (OpenRouter)
 * Procesa mensajes conversacionales conectándose directamente a OpenRouter
 * (vía cURL, Streams HTTP nativos o Socket TLS directo para compatibilidad total
 * con USBWebserver/XAMPP) y usando un System Prompt estructurado.
 * Compatible con PHP 7.4+
 * =============================================================================
 */

ob_start();
header('Content-Type: application/json; charset=UTF-8');
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/conexion.php';
require_once dirname(__DIR__) . '/includes/seguridad.php';

/**
 * Construye el System Prompt del Asistente Académico Virtual de TutoresOnLine
 *
 * @param string $nombre_usuario
 * @param string $rol_usuario
 * @return string
 */
function construir_system_prompt($nombre_usuario, $rol_usuario)
{
    $catalogo_texto = '';
    try {
        $pdo = obtener_conexion();
        $sql = "SELECT u.nombre AS tutor, u.ciudad, m.nombre AS materia, n.nombre AS nivel, tm.tarifa_hora
                FROM `tutor_materia` tm
                INNER JOIN `usuarios` u ON u.id = tm.tutor_id
                INNER JOIN `materias` m ON m.id = tm.materia_id
                INNER JOIN `niveles` n ON n.id = tm.nivel_id
                WHERE u.rol = 'tutor' AND u.correo_verificado = 1
                ORDER BY u.nombre ASC, m.nombre ASC
                LIMIT 25";
        $filas = $pdo->query($sql)->fetchAll();

        if (!empty($filas)) {
            $lineas = [];
            foreach ($filas as $f) {
                $ciudad = !empty($f['ciudad']) ? $f['ciudad'] : 'Colombia';
                $tarifa = number_format((float)$f['tarifa_hora'], 0, ',', '.');
                $lineas[] = "- {$f['tutor']} ({$ciudad}): {$f['materia']} [{$f['nivel']}] - \${$tarifa} COP/hora";
            }
            $catalogo_texto = "\n\nTUTORES Y MATERIAS DISPONIBLES ACTUALMENTE EN LA PLATAFORMA:\n"
                . implode("\n", $lineas);
        }
    } catch (Exception $e) {
        error_log('[Chat IA Catalogo]: ' . $e->getMessage());
    }

    $rol_legible = ($rol_usuario === 'tutor') ? 'tutor' : 'estudiante';
    $saludo_usuario = $nombre_usuario !== ''
        ? "Actualmente estás conversando con {$nombre_usuario} (su rol en la plataforma es {$rol_legible})."
        : "El usuario actual tiene el rol de {$rol_legible}.";

    return <<<PROMPT
Eres el Asistente Académico Virtual oficial de TutoresOnLine.
{$saludo_usuario}

TU IDENTIDAD Y CAPACIDADES PRINCIPALES:
1. Explicar temas académicos paso a paso con claridad y ejemplos (Matemáticas, Física, Química, Programación Web, Inglés, Cálculo Diferencial, Álgebra Lineal y cualquier otra asignatura).
2. Armar planes de estudio personalizados, guías de repaso y proponer ejercicios prácticos adaptados al tema y nivel educativo del estudiante (Primaria, Secundaria, Bachillerato o Universidad).
3. Orientar sobre el uso de TutoresOnLine desde la perspectiva del usuario:
   - Cómo buscar tutores filtrando por materia, nivel, ciudad o modalidad (virtual o presencial) en la sección "Buscar Tutores".
   - Cómo solicitar una reserva seleccionando materia, modalidad, fecha y horario.
   - Cómo ingresar a la videollamada directamente desde su Panel cuando la tutoría haya sido aceptada.
   - Cómo usar la sección "Mensajes" para chatear con su tutor o estudiante.
   - Cómo calificar de 1 a 5 estrellas las tutorías finalizadas desde su Panel o desde el perfil del tutor.
4. Recomendar tutores reales disponibles en la plataforma (indicando materia, nivel, ciudad y tarifa por hora) cuando el usuario pregunte quién enseña una asignatura o qué opciones tiene.

REGLAS ESTRICTAS DE COMPORTAMIENTO Y PROHIBICIONES:
- Responde siempre en español, con un tono amable, cercano, claro y bien estructurado.
- NUNCA menciones ningún detalle técnico de cómo está construida la página web. Está terminantemente prohibido hablar de PHP, MySQL, bases de datos, tablas, consultas SQL, código fuente, servidores, endpoints, APIs, OpenRouter, DeepSeek, modelos de IA, prompts, tokens o archivos .env.
- Si el usuario pregunta cómo está programada la página, qué tecnología, servidor o modelo de IA utiliza, responde amablemente que eres únicamente el asistente académico de TutoresOnLine y ofrece ayuda con sus materias, ejercicios o tutorías.
- No inventes tutores ni tarifas que no aparezcan en el catálogo listado abajo.{$catalogo_texto}
PROMPT;
}

/**
 * Decodifica un cuerpo HTTP con Transfer-Encoding: chunked
 *
 * @param string $cuerpo
 * @return string
 */
function decodificar_chunked_http($cuerpo)
{
    $resultado = '';
    $pos = 0;
    $len = strlen($cuerpo);

    while ($pos < $len) {
        $fin_linea = strpos($cuerpo, "\r\n", $pos);
        if ($fin_linea === false) {
            break;
        }
        $hex_tamano = trim(substr($cuerpo, $pos, $fin_linea - $pos));
        $semi = strpos($hex_tamano, ';');
        if ($semi !== false) {
            $hex_tamano = substr($hex_tamano, 0, $semi);
        }
        $tamano = hexdec($hex_tamano);
        if ($tamano <= 0) {
            break;
        }
        $pos = $fin_linea + 2;
        $resultado .= substr($cuerpo, $pos, $tamano);
        $pos += $tamano + 2;
    }

    return $resultado !== '' ? $resultado : $cuerpo;
}

/**
 * Ejecuta una petición POST JSON a OpenRouter con 3 niveles de transporte:
 * 1) cURL, 2) Streams HTTP nativos, 3) Socket TLS directo.
 *
 * @param string $url
 * @param string $body_json
 * @param array $cabeceras
 * @return array ['exito' => bool, 'codigo' => int, 'datos' => array|null, 'error' => string|null]
 */
function ejecutar_post_openrouter($url, $body_json, $cabeceras)
{
    $es_local = env('APP_ENV', 'local') === 'local';

    // MÉTODO 1: cURL (si la extensión está habilitada en PHP)
    if (function_exists('curl_init')) {
        $ch = curl_init();
        $opciones = [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body_json,
            CURLOPT_HTTPHEADER     => $cabeceras,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => !$es_local,
            CURLOPT_SSL_VERIFYHOST => $es_local ? 0 : 2,
        ];

        curl_setopt_array($ch, $opciones);
        $respuesta   = curl_exec($ch);
        $curl_error  = curl_error($ch);
        $codigo_http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($respuesta === false || $curl_error !== '') {
            return ['exito' => false, 'codigo' => 0, 'datos' => null, 'error' => "cURL: {$curl_error}"];
        }

        $json = json_decode($respuesta, true);
        if ($codigo_http < 200 || $codigo_http >= 300) {
            $msg = $json['error']['message'] ?? "HTTP {$codigo_http}";
            return ['exito' => false, 'codigo' => $codigo_http, 'datos' => $json, 'error' => (string)$msg];
        }

        return ['exito' => true, 'codigo' => $codigo_http, 'datos' => $json, 'error' => null];
    }

    // MÉTODO 2: Streams nativos de PHP (cuando curl no está activo en USBWebserver)
    if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN) || ini_get('allow_url_fopen') === '1') {
        $headers_stream = array_merge($cabeceras, ['Content-Length: ' . strlen($body_json)]);
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => implode("\r\n", $headers_stream),
                'content'       => $body_json,
                'ignore_errors' => true,
                'timeout'       => 30,
            ],
            'ssl' => [
                'verify_peer'       => !$es_local,
                'verify_peer_name'  => !$es_local,
                'allow_self_signed' => $es_local,
            ],
        ]);

        $respuesta = @file_get_contents($url, false, $ctx);
        if ($respuesta !== false) {
            $codigo_http = 200;
            if (isset($http_response_header[0]) && preg_match('#HTTP/\S+\s+(\d+)#', $http_response_header[0], $m)) {
                $codigo_http = (int)$m[1];
            }

            $json = json_decode($respuesta, true);
            if ($codigo_http < 200 || $codigo_http >= 300) {
                $msg = $json['error']['message'] ?? "HTTP {$codigo_http}";
                return ['exito' => false, 'codigo' => $codigo_http, 'datos' => $json, 'error' => (string)$msg];
            }

            return ['exito' => true, 'codigo' => $codigo_http, 'datos' => $json, 'error' => null];
        }
    }

    // MÉTODO 3: Socket TLS directo (fallback si allow_url_fopen está desactivado)
    $partes = parse_url($url);
    $host   = $partes['host'] ?? 'openrouter.ai';
    $path   = ($partes['path'] ?? '/api/v1/chat/completions') . (isset($partes['query']) ? '?' . $partes['query'] : '');

    $ctx_sock = stream_context_create([
        'ssl' => [
            'verify_peer'       => !$es_local,
            'verify_peer_name'  => !$es_local,
            'allow_self_signed' => $es_local,
        ],
    ]);

    $socket = @stream_socket_client("ssl://{$host}:443", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx_sock);
    if (!$socket) {
        return ['exito' => false, 'codigo' => 0, 'datos' => null, 'error' => "Socket TLS falló ({$errno}): {$errstr}"];
    }

    stream_set_timeout($socket, 30);

    $req  = "POST {$path} HTTP/1.1\r\n";
    $req .= "Host: {$host}\r\n";
    foreach ($cabeceras as $h) {
        $req .= "{$h}\r\n";
    }
    $req .= "Content-Length: " . strlen($body_json) . "\r\n";
    $req .= "Connection: close\r\n\r\n";
    $req .= $body_json;

    fwrite($socket, $req);

    $raw = '';
    while (!feof($socket)) {
        $chunk = fread($socket, 8192);
        if ($chunk === false || $chunk === '') {
            break;
        }
        $raw .= $chunk;
    }
    fclose($socket);

    $sep = strpos($raw, "\r\n\r\n");
    if ($sep === false) {
        return ['exito' => false, 'codigo' => 0, 'datos' => null, 'error' => 'Respuesta HTTP inválida desde OpenRouter'];
    }

    $raw_headers = substr($raw, 0, $sep);
    $cuerpo      = substr($raw, $sep + 4);

    if (stripos($raw_headers, 'Transfer-Encoding: chunked') !== false) {
        $cuerpo = decodificar_chunked_http($cuerpo);
    }

    $codigo_http = 0;
    if (preg_match('#^HTTP/\S+\s+(\d+)#m', $raw_headers, $m)) {
        $codigo_http = (int)$m[1];
    }

    $json = json_decode($cuerpo, true);
    if ($codigo_http < 200 || $codigo_http >= 300) {
        $msg = $json['error']['message'] ?? "HTTP {$codigo_http}";
        return ['exito' => false, 'codigo' => $codigo_http, 'datos' => $json, 'error' => (string)$msg];
    }

    return ['exito' => true, 'codigo' => $codigo_http, 'datos' => $json, 'error' => null];
}

/**
 * Envía la conversación a OpenRouter reintentando automáticamente con modelos
 * alternativos si el slug configurado no está disponible.
 *
 * @param array $mensajes_api
 * @return array ['exito' => bool, 'respuesta' => string|null, 'error' => string|null]
 */
function consultar_openrouter($mensajes_api)
{
    $api_key = trim((string)env('OPENROUTER_API_KEY', ''));
    if ($api_key === '') {
        return ['exito' => false, 'respuesta' => null, 'error' => 'OPENROUTER_API_KEY no configurada.'];
    }

    $modelo_principal = trim((string)env('OPENROUTER_MODEL', 'deepseek/deepseek-v4.1-flash'));
    if ($modelo_principal === '') {
        $modelo_principal = 'deepseek/deepseek-v4.1-flash';
    }

    $modelos_intentar = array_values(array_unique([
        $modelo_principal,
        'deepseek/deepseek-chat',
        'openrouter/auto',
    ]));

    $cabeceras = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $api_key,
        'HTTP-Referer: ' . APP_URL,
        'X-Title: TutoresOnLine',
    ];

    $ultimo_error = 'No se obtuvo respuesta de OpenRouter.';

    foreach ($modelos_intentar as $modelo) {
        $payload = [
            'model'       => $modelo,
            'messages'    => $mensajes_api,
            'temperature' => 0.7,
            'max_tokens'  => 900,
        ];

        $body_json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $res = ejecutar_post_openrouter('https://openrouter.ai/api/v1/chat/completions', $body_json, $cabeceras);

        if ($res['exito']) {
            $contenido = trim((string)($res['datos']['choices'][0]['message']['content'] ?? ''));
            if ($contenido !== '') {
                return ['exito' => true, 'respuesta' => $contenido, 'error' => null];
            }
            $ultimo_error = "Respuesta vacía del modelo {$modelo}.";
            error_log("[Chat IA OpenRouter]: {$ultimo_error}");
            continue;
        }

        $ultimo_error = $res['error'] ?: "Error HTTP {$res['codigo']}";
        error_log("[Chat IA OpenRouter ({$modelo})]: {$ultimo_error}");

        // Si el error es de modelo inválido o no encontrado, reintentar con el siguiente modelo
        if (in_array($res['codigo'], [400, 404, 422, 502, 503], true)) {
            continue;
        }

        // Si es 401/402/429 (key inválida, sin saldo o rate limit), detener reintentos de modelo
        break;
    }

    return ['exito' => false, 'respuesta' => null, 'error' => $ultimo_error];
}

iniciar_sesion_segura();

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['exito' => false, 'mensaje' => 'Debes iniciar sesión para usar el asistente.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['exito' => false, 'mensaje' => 'Método no permitido.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$csrf_recibido = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verificar_csrf($csrf_recibido)) {
    http_response_code(403);
    echo json_encode(['exito' => false, 'mensaje' => 'Sesión expirada o token de seguridad inválido.']);
    exit;
}

$mensaje = trim((string)($input['mensaje'] ?? ''));
if ($mensaje === '') {
    http_response_code(400);
    echo json_encode(['exito' => false, 'mensaje' => 'Escribe un mensaje para continuar.']);
    exit;
}

$mensaje = function_exists('mb_substr') ? mb_substr($mensaje, 0, 2000, 'UTF-8') : substr($mensaje, 0, 2000);
$historial_raw = $input['historial'] ?? [];
if (is_string($historial_raw)) {
    $historial_raw = json_decode($historial_raw, true);
}
$historial = is_array($historial_raw) ? $historial_raw : [];

$nombre_sesion = trim((string)($_SESSION['nombre'] ?? ''));
$rol_sesion    = trim((string)($_SESSION['rol'] ?? 'estudiante'));
$system_prompt = construir_system_prompt($nombre_sesion, $rol_sesion);

$mensajes_api = [
    ['role' => 'system', 'content' => $system_prompt],
];

// Conservar hasta los últimos 8 mensajes del historial para contexto
$ultimos = array_slice($historial, -8);
foreach ($ultimos as $item) {
    if (!is_array($item)) {
        continue;
    }
    $rol_raw = strtolower(trim((string)($item['rol'] ?? $item['role'] ?? 'user')));
    $texto   = trim((string)($item['texto'] ?? $item['content'] ?? ''));
    if ($texto === '') {
        continue;
    }
    $role = ($rol_raw === 'assistant' || $rol_raw === 'ia') ? 'assistant' : 'user';
    $mensajes_api[] = [
        'role'    => $role,
        'content' => function_exists('mb_substr') ? mb_substr($texto, 0, 1500, 'UTF-8') : substr($texto, 0, 1500),
    ];
}

$mensajes_api[] = ['role' => 'user', 'content' => $mensaje];

$resultado = consultar_openrouter($mensajes_api);

if (ob_get_length()) {
    ob_clean();
}

if (!$resultado['exito'] || $resultado['respuesta'] === null) {
    http_response_code(502);
    echo json_encode([
        'exito'   => false,
        'mensaje' => $resultado['error'] ?: 'No se pudo conectar con OpenRouter.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'exito'     => true,
    'respuesta' => $resultado['respuesta'],
], JSON_UNESCAPED_UNICODE);

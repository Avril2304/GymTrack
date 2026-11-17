<?php
// Archivos/Php/chat_coach.php — v2.0 con Function Calling (IA Agéntica)
// El Coach puede crear y guardar rutinas reales en la BD cuando el usuario lo pide

if (file_exists(__DIR__ . '/conexion.php')) {
    require_once __DIR__ . '/conexion.php';
} elseif (file_exists(__DIR__ . '/../Php/conexion.php')) {
    require_once __DIR__ . '/../Php/conexion.php';
}

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} elseif (file_exists(__DIR__ . '/../Php/config.php')) {
    require_once __DIR__ . '/../Php/config.php';
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Solo usuarios con sesion: evita gastar credito de OpenAI desde afuera y que se guarden rutinas a nombre de otros.
// El usuario es el de la sesion del servidor; el user_id que mande el cliente se ignora.
require_once __DIR__ . '/sesion.php';
gt_iniciar_sesion();
$userId        = gt_usuario_id();

$input         = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$mensaje       = trim($input['mensaje'] ?? '');
$historialChat = $input['historial'] ?? [];

if (empty($mensaje)) {
    echo json_encode(['ok' => false, 'msg' => 'El mensaje no puede estar vacío.']);
    exit;
}

// --- API Key y modelo ---
$apiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : getenv('OPENAI_API_KEY');
$model  = defined('OPENAI_MODEL')   ? OPENAI_MODEL   : 'gpt-4o-mini';

if (empty($apiKey) || $apiKey === 'tu_api_key_de_openai_aqui') {
    echo json_encode([
        'ok'    => true,
        'reply' => "👋 ¡Hola! Soy tu **Coach Virtual de GymTrack**.\n\nPara activar la IA personalizada, configurá la API Key de OpenAI en `config.php`."
    ]);
    exit;
}

// --- 1. Perfil del usuario ---
$perfilUsuario = "Usuario general de GymTrack.";
if ($userId && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.nombre, u.apellido, u.sexo, u.peso, u.altura, c.nombre AS clase_nombre, c.descripcion AS clase_desc
            FROM Usuario u LEFT JOIN Clase c ON u.ID_Clase = c.ID_Clase
            WHERE u.ID_Usuario = ?
        ");
        $stmt->execute([$userId]);
        $u = $stmt->fetch();
        if ($u) {
            $perfilUsuario = "Nombre: {$u['nombre']} {$u['apellido']} | Sexo: " . ($u['sexo'] ?: 'No especificado')
                           . " | Peso: " . ($u['peso'] ? $u['peso'] . ' kg' : 'No especificado')
                           . " | Altura: " . ($u['altura'] ? $u['altura'] . ' m' : 'No especificado')
                           . " | Objetivo/Clase: {$u['clase_nombre']} ({$u['clase_desc']})";
        }
    } catch (Exception $e) {}
}

// --- 2. Historial reciente ---
$historialReciente = "No hay entrenamientos recientes registrados aún.";
if ($userId && isset($pdo)) {
    try {
        $stmtH = $pdo->prepare("
            SELECT h.fecha, h.series, h.repeticiones, h.peso, e.nombre AS ejercicio_nombre
            FROM Historial h LEFT JOIN Ejercicio e ON h.ID_Ejercicio = e.ID_Ejercicio
            WHERE h.ID_Usuario = ? ORDER BY h.fecha DESC LIMIT 6
        ");
        $stmtH->execute([$userId]);
        $rows = $stmtH->fetchAll();
        if ($rows) {
            $lines = [];
            foreach ($rows as $r) {
                $ej = $r['ejercicio_nombre'] ?: 'Ejercicio';
                $lines[] = "- {$r['fecha']}: {$ej} | {$r['series']} series x {$r['repeticiones']} reps @ {$r['peso']} kg";
            }
            $historialReciente = implode("\n", $lines);
        }
    } catch (Exception $e) {}
}

// --- 3. Catálogo de ejercicios CON ID (esencial para Function Calling) ---
$catalogoEjercicios = "";
$catalogoArray      = [];
if (isset($pdo)) {
    try {
        $stmtE = $pdo->query("SELECT ID_Ejercicio, nombre, body_part_es, target_muscle_es, equipment_es FROM Ejercicio ORDER BY nombre ASC");
        $ejs   = $stmtE->fetchAll();
        foreach ($ejs as $e) {
            $catalogoArray[(int)$e['ID_Ejercicio']] = $e['nombre'];
            $catalogoEjercicios .= "• [ID:{$e['ID_Ejercicio']}] {$e['nombre']} (Zona: {$e['body_part_es']}, Músculo: {$e['target_muscle_es']}, Equipo: {$e['equipment_es']})\n";
        }
    } catch (Exception $e) {}
}

// --- 4. System Prompt ---
$systemPrompt = <<<EOT
Eres el Coach Virtual Inteligente de "GymTrack", una plataforma de gestión y seguimiento de entrenamientos de gimnasio. Tu propósito es ser un entrenador personal motivador, técnico, conciso, empático y práctico para los alumnos del gimnasio.

--- INFORMACIÓN DEL ALUMNO ACTUAL ---
{$perfilUsuario}

--- HISTORIAL RECIENTE DE ENTRENAMIENTO ---
{$historialReciente}

--- CATÁLOGO DE EJERCICIOS DE GYMTRACK (con IDs numéricos) ---
{$catalogoEjercicios}

--- TUS CAPACIDADES PRINCIPALES ---
1. RECOMENDACIÓN DE RUTINAS: Si el alumno indica qué grupos musculares quiere entrenar, brindá una rutina clara con series/repeticiones ideales para su objetivo.
2. SUSTITUCIÓN INTELIGENTE: Si el alumno quiere cambiar un ejercicio o una máquina está ocupada, sugerí sustitutos del catálogo que trabajen el mismo grupo muscular.
3. EXPLICACIÓN TÉCNICA PASO A PASO: Explicá postura, rango de movimiento, respiración y errores comunes.
4. NUTRICIÓN Y COMIDAS SALUDABLES: Recomendá recetas fitness, opciones pre/post entrenamiento adaptadas al objetivo del alumno.
5. CREAR Y GUARDAR RUTINAS (¡NUEVA CAPACIDAD!): Si el alumno pide que le CREES y GUARDES una rutina en su cuenta, usás la herramienta `guardar_rutina_usuario`. Elegís entre 4 y 8 ejercicios ÚNICAMENTE del catálogo de GymTrack listado arriba (usando sus IDs numéricos exactos).

--- REGLAS ESTRICTAS ---
- Eres EXCLUSIVAMENTE el Coach de GymTrack. Solo respondés sobre: gimnasio, ejercicios, rutinas, técnica, entrenamiento y nutrición deportiva.
- Para cualquier otro tema (política, tareas, historia, programación, etc.) respondés: "Lo siento, como tu Coach de GymTrack solo puedo ayudarte con tus rutinas, técnica de ejercicios y comidas saludables. ¿En qué te puedo asesorar hoy?"
- NUNCA diagnostiques lesiones médicas ni prescribas medicamentos.
- Usá Markdown con negritas y listas para respuestas claras y legibles en móvil.
EOT;

// --- 5. Definición de la Tool para Function Calling ---
$tools = [
    [
        'type' => 'function',
        'function' => [
            'name'        => 'guardar_rutina_usuario',
            'description' => 'Crea y guarda una rutina de entrenamiento personalizada en la cuenta del alumno en GymTrack. '
                           . 'Llamá a esta función ÚNICAMENTE cuando el alumno pida explícitamente crear Y guardar una rutina. '
                           . 'Elegí entre 4 y 8 ejercicios del catálogo. SIEMPRE usá IDs numéricos válidos del catálogo mostrado en el system prompt.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'nombre' => [
                        'type'        => 'string',
                        'description' => 'Nombre descriptivo de la rutina. Ej: "Pecho y Tríceps - Intermedio", "Piernas - Fuerza"'
                    ],
                    'ejercicios_ids' => [
                        'type'        => 'array',
                        'items'       => ['type' => 'integer'],
                        'description' => 'Array de IDs numéricos de los ejercicios seleccionados del catálogo de GymTrack'
                    ]
                ],
                'required' => ['nombre', 'ejercicios_ids']
            ]
        ]
    ]
];

// --- 6. Construir mensajes ---
$messages = [['role' => 'system', 'content' => $systemPrompt]];
if (is_array($historialChat)) {
    foreach (array_slice($historialChat, -6) as $msg) {
        if (isset($msg['role'], $msg['content']) && in_array($msg['role'], ['user', 'assistant'])) {
            $messages[] = ['role' => $msg['role'], 'content' => strval($msg['content'])];
        }
    }
}
$messages[] = ['role' => 'user', 'content' => $mensaje];

// --- 7. Primera llamada a OpenAI ---
$payload = [
    'model'       => $model,
    'messages'    => $messages,
    'tools'       => $tools,
    'temperature' => 0.4,
    'max_tokens'  => 1000
];

$response1 = llamarOpenAI($payload, $apiKey);

if (!$response1['ok']) {
    echo json_encode(['ok' => false, 'msg' => $response1['error']]);
    exit;
}

$choice = $response1['data']['choices'][0] ?? null;
if (!$choice) {
    echo json_encode(['ok' => false, 'msg' => 'Respuesta vacía de OpenAI.']);
    exit;
}

// --- 8. ¿OpenAI quiere ejecutar una herramienta? ---
if (($choice['finish_reason'] ?? '') === 'tool_calls' && !empty($choice['message']['tool_calls'])) {
    $toolCall = $choice['message']['tool_calls'][0];
    $toolName = $toolCall['function']['name'];
    $toolArgs = json_decode($toolCall['function']['arguments'], true);

    if ($toolName === 'guardar_rutina_usuario' && $userId && isset($pdo)) {
        $rutinaNombre  = trim($toolArgs['nombre']         ?? 'Mi rutina personalizada');
        $ejerciciosIds = $toolArgs['ejercicios_ids']      ?? [];

        // Validar que los IDs existan en el catálogo real de GymTrack
        $idsValidos = [];
        foreach ($ejerciciosIds as $id) {
            $idInt = (int)$id;
            if (isset($catalogoArray[$idInt])) {
                $idsValidos[] = $idInt;
            }
        }

        $toolResult = '';

        if (empty($idsValidos)) {
            $toolResult = json_encode(['ok' => false, 'msg' => 'No se encontraron ejercicios válidos en el catálogo de GymTrack.']);
        } else {
            try {
                $pdo->beginTransaction();

                // INSERT en Rutina
                $pdo->prepare(
                    "INSERT INTO Rutina (ID_Clase, ID_Usuario, nombre, descripcion) VALUES (1, ?, ?, 'Personalizada por el Coach IA')"
                )->execute([$userId, $rutinaNombre]);
                $newRutinaId = $pdo->lastInsertId();

                // INSERT en Ejercitacion (ejercicios de la rutina)
                $insEj = $pdo->prepare("INSERT INTO Ejercitacion (ID_Rutina, ID_Ejercicio) VALUES (?, ?)");
                foreach ($idsValidos as $ejId) {
                    $insEj->execute([$newRutinaId, $ejId]);
                }

                // INSERT en Usuario_RutinaDia (asociar rutina al usuario)
                $pdo->prepare(
                    "INSERT INTO Usuario_RutinaDia (ID_Usuario, ID_Rutina) VALUES (?, ?)"
                )->execute([$userId, $newRutinaId]);

                $pdo->commit();

                $nombresEjercicios = array_map(fn($id) => $catalogoArray[$id], $idsValidos);
                $toolResult = json_encode([
                    'ok'         => true,
                    'msg'        => 'Rutina creada y guardada exitosamente en la cuenta del usuario',
                    'id_rutina'  => $newRutinaId,
                    'nombre'     => $rutinaNombre,
                    'ejercicios' => $nombresEjercicios,
                    'cantidad'   => count($idsValidos)
                ]);

            } catch (Exception $eTx) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $toolResult = json_encode(['ok' => false, 'msg' => 'Error al guardar en base de datos: ' . $eTx->getMessage()]);
            }
        }

        // Segunda llamada a OpenAI para que genere la respuesta final al usuario
        $messages[] = $choice['message'];
        $messages[] = [
            'role'         => 'tool',
            'tool_call_id' => $toolCall['id'],
            'content'      => $toolResult
        ];

        $payload2 = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => 0.4,
            'max_tokens'  => 500
        ];

        $response2 = llamarOpenAI($payload2, $apiKey);
        $resultData = json_decode($toolResult, true);

        if ($response2['ok']) {
            $reply = $response2['data']['choices'][0]['message']['content']
                  ?? ($resultData['ok'] ? "✅ ¡Rutina **{$rutinaNombre}** guardada correctamente!" : "❌ No se pudo guardar la rutina.");
        } else {
            // Fallback si la segunda llamada falla
            if ($resultData['ok'] ?? false) {
                $listaEj = implode(', ', $resultData['ejercicios'] ?? []);
                $cant    = $resultData['cantidad'] ?? count($idsValidos);
                $reply   = "✅ **¡Rutina guardada exitosamente!**\n\n"
                         . "**Nombre:** {$rutinaNombre}\n"
                         . "**Ejercicios ({$cant}):** {$listaEj}\n\n"
                         . "Ya podés verla en la sección **Mis Rutinas** de GymTrack. 💪";
            } else {
                $reply = "❌ No se pudo guardar la rutina: " . ($resultData['msg'] ?? 'Error desconocido.');
            }
        }

        echo json_encode([
            'ok'            => true,
            'reply'         => $reply,
            'rutina_creada' => ($resultData['ok'] ?? false)
        ]);
        exit;
    }
}

// --- 9. Respuesta normal de texto (sin tool call) ---
$reply = $choice['message']['content'] ?? '';
if (empty($reply)) {
    echo json_encode(['ok' => false, 'msg' => 'Respuesta vacía del Coach.']);
    exit;
}

echo json_encode(['ok' => true, 'reply' => $reply]);

// --- Helper: llama a la API de OpenAI ---
function llamarOpenAI($payload, $apiKey) {
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . trim($apiKey)
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0
    ]);

    $body      = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) return ['ok' => false, 'error' => "Error cURL: {$curlError}"];
    if ($httpCode !== 200) {
        $errData = json_decode($body, true);
        $errMsg  = $errData['error']['message'] ?? "HTTP {$httpCode}";
        return ['ok' => false, 'error' => "OpenAI: {$errMsg}"];
    }

    $data = json_decode($body, true);
    if (!$data) return ['ok' => false, 'error' => 'JSON inválido de OpenAI.'];
    return ['ok' => true, 'data' => $data];
}
?>

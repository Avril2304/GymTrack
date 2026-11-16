<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\Ejercicio;
use App\Models\Historial;
use Illuminate\Http\Request;

class CoachController
{
    public function chat(Request $request)
    {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        header("Access-Control-Allow-Methods: POST, OPTIONS");
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        // Obtener datos del cuerpo JSON
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $request->all();
        }

        $mensaje = trim($input['mensaje'] ?? '');
        $userId = $input['user_id'] ?? null;
        $historialChat = $input['historial'] ?? [];

        if (empty($mensaje)) {
            return response()->json([
                'ok' => false,
                'msg' => 'El mensaje no puede estar vacío.'
            ], 400);
        }

        // Obtener API Key de OpenAI desde .env, getenv o config.php
        $apiKey = getenv('OPENAI_API_KEY') ?: ($_ENV['OPENAI_API_KEY'] ?? '');
        if (empty($apiKey) || $apiKey === 'tu_api_key_de_openai_aqui') {
            if (defined('OPENAI_API_KEY') && OPENAI_API_KEY !== 'tu_api_key_de_openai_aqui') {
                $apiKey = OPENAI_API_KEY;
            } else {
                $configPaths = [
                    __DIR__ . '/../../../../public_html/api/config.php',
                    __DIR__ . '/../../../../public_html/Php/config.php',
                    __DIR__ . '/../../../Archivos/Php/config.php'
                ];
                foreach ($configPaths as $cp) {
                    if (file_exists($cp)) {
                        require_once $cp;
                        if (defined('OPENAI_API_KEY') && OPENAI_API_KEY !== 'tu_api_key_de_openai_aqui') {
                            $apiKey = OPENAI_API_KEY;
                            break;
                        }
                    }
                }
            }
        }

        if (empty($apiKey) || $apiKey === 'tu_api_key_de_openai_aqui') {
            return response()->json([
                'ok' => true,
                'reply' => "👋 ¡Hola! Soy tu **Coach Virtual de GymTrack**.\n\nPara comenzar a responderte con inteligencia artificial personalizada, por favor ingresa tu API Key de OpenAI en el archivo de configuración `.env` o en `config.php`."
            ]);
        }

        $model = getenv('OPENAI_MODEL') ?: 'gpt-4o-mini';

        // 1. Obtener información del usuario para personalizar la respuesta
        $perfilUsuario = "Usuario general de GymTrack.";
        if ($userId) {
            try {
                $user = Usuario::with('clase')->find($userId);
                if ($user) {
                    $claseNombre = $user->clase ? $user->clase->nombre : 'Sin especificar';
                    $claseDesc = $user->clase ? $user->clase->descripcion : '';
                    $perfilUsuario = "Nombre: {$user->nombre} {$user->apellido}\n"
                        . "Sexo: " . ($user->sexo ?: 'No especificado') . "\n"
                        . "Peso: " . ($user->peso ? $user->peso . ' kg' : 'No especificado') . "\n"
                        . "Altura: " . ($user->altura ? $user->altura . ' cm' : 'No especificado') . "\n"
                        . "Objetivo/Clase asignada: {$claseNombre} ({$claseDesc})";
                }
            } catch (\Exception $e) {
                // Continuar sin romper si la BD falla
            }
        }

        // 2. Obtener historial reciente de entrenamientos del usuario
        $historialReciente = "No hay entrenamientos recientes registrados aún.";
        if ($userId) {
            try {
                $historiales = Historial::with('ejercicio')
                    ->where('ID_Usuario', $userId)
                    ->orderBy('fecha', 'desc')
                    ->take(6)
                    ->get();

                if ($historiales->isNotEmpty()) {
                    $lineas = [];
                    foreach ($historiales as $h) {
                        $ejNombre = $h->ejercicio ? $h->ejercicio->nombre : 'Ejercicio #' . $h->ID_Ejercicio;
                        $lineas[] = "- {$h->fecha}: {$ejNombre} | {$h->series} series x {$h->repeticiones} reps @ {$h->peso} kg";
                    }
                    $historialReciente = implode("\n", $lineas);
                }
            } catch (\Exception $e) {
                // Continuar sin romper
            }
        }

        // 3. Obtener catálogo resumido de ejercicios de la BD de GymTrack
        $catalogoEjercicios = "";
        try {
            $ejercicios = Ejercicio::select('nombre', 'body_part_es', 'target_muscle_es', 'equipment_es')->get();
            if ($ejercicios->isNotEmpty()) {
                $items = [];
                foreach ($ejercicios as $ej) {
                    $items[] = "• {$ej->nombre} (Zona: {$ej->body_part_es}, Músculo: {$ej->target_muscle_es}, Equipo: {$ej->equipment_es})";
                }
                $catalogoEjercicios = implode("\n", array_slice($items, 0, 50));
            }
        } catch (\Exception $e) {
            // Continuar
        }

        // 4. Construir System Prompt con Guardrails y Contexto de GymTrack
        $systemPrompt = <<<EOT
Eres el Coach Virtual Inteligente de "GymTrack", una plataforma de gestión y seguimiento de entrenamientos de gimnasio. Tu propósito es ser un entrenador personal motivador, técnico, conciso, empático y práctico para los alumnos del gimnasio.

--- INFORMACIÓN DEL ALUMNO ACTUAL ---
{$perfilUsuario}

--- HISTORIAL RECIENTE DE ENTRENAMIENTO ---
{$historialReciente}

--- CATÁLOGO DE EJERCICIOS Y EQUIPAMIENTO DE GYMTRACK ---
{$catalogoEjercicios}

--- TUS CAPACIDADES PRINCIPALES ---
1. RECOMENDACIÓN DE RUTINAS: Si el alumno indica qué grupos musculares quiere entrenar (ej: Piernas, Pecho y tríceps, Espalda, etc.), brinda una rutina clara, balanceada y con series/repeticiones ideales para su objetivo ({$perfilUsuario}).
2. SUSTITUCIÓN INTELIGENTE: Si el alumno te dice qué ejercicio quiere cambiar o qué máquina está ocupada, sugiere sustitutos exactos que trabajen el mismo grupo muscular y utilicen equipamiento disponible en el catálogo de GymTrack.
3. EXPLICACIÓN TÉCNICA PASO A PASO: Explica postura inicial, posición de pies/manos, rango de movimiento articular, respiración adecuada (inhalar en fase excéntrica, exhalar en concéntrica) y errores comunes a evitar del ejercicio consultado.
4. NUTRICIÓN Y COMIDAS SALUDABLES: Recomienda recetas fitness balanceadas, opciones de comidas pre/post entrenamiento, o ayuda al alumno a armar una comida saludable rápida y nutritiva indicando qué ingredientes tiene en su casa (adaptado a su objetivo: hipertrofia, déficit, mantenimiento).
5. PROGRESIÓN Y CONSEJOS: Brinda consejos sobre sobrecarga progresiva, tiempos de pausa sugeridos (60-90s hipertrofia, 2-3min fuerza) y calentamiento dinámico.

--- REGLAS ESTRICTAS DE SEGURIDAD Y LÍMITES (GUARDRAILS) ---
- Eres EXCLUSIVAMENTE el Coach de GymTrack. SOLO puedes responder preguntas vinculadas a: gimnasio, ejercicios, rutinas, ejecución técnica, entrenamiento físico, descanso y nutrición/comidas saludables fitness.
- Si el usuario te pregunta sobre CUALQUIER otro tema ajeno al gimnasio, entrenamiento o nutrición deportiva (por ejemplo: política, tareas escolares, historia, matemáticas, código/programación, noticias, farándula, etc.), DEBES RECHAZAR la consulta de forma amable y firme respondiendo exactamente:
"Lo siento, como tu Coach de GymTrack solo puedo ayudarte con tus rutinas, técnica de ejercicios, recomendaciones de entrenamiento y comidas saludables. ¿En qué te puedo asesorar hoy?"
- NUNCA diagnostiques lesiones médicas complejas ni prescribas medicamentos o fármacos. Si el alumno manifiesta dolor articular punzante o molestia severa, aconséjale suspender el movimiento de inmediato y acudir a un médico o traumatólogo.
- Respuestas claras, directas, usando viñetas y formato Markdown (negritas, listas) para que sea muy cómodo de leer en un teléfono móvil.
EOT;


        // 5. Preparar la estructura de mensajes para OpenAI (Sliding Window)
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];

        // Incluir hasta los últimos 6 mensajes del historial previo para mantener contexto de la conversación
        if (is_array($historialChat)) {
            $slice = array_slice($historialChat, -6);
            foreach ($slice as $msg) {
                if (isset($msg['role'], $msg['content']) && in_array($msg['role'], ['user', 'assistant'])) {
                    $messages[] = [
                        'role' => $msg['role'],
                        'content' => strval($msg['content'])
                    ];
                }
            }
        }

        // Agregar el mensaje actual del usuario
        $messages[] = ['role' => 'user', 'content' => $mensaje];

        // 6. Realizar la llamada cURL a OpenAI API
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.4,
            'max_tokens' => 800
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . trim($apiKey)
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return response()->json([
                'ok' => false,
                'msg' => 'Error de conexión con OpenAI: ' . $curlError
            ], 200);
        }

        $resData = json_decode($response, true);

        if ($httpCode !== 200 || !isset($resData['choices'][0]['message']['content'])) {
            $errorMsg = $resData['error']['message'] ?? 'Error al procesar la respuesta con el Coach de IA (HTTP ' . $httpCode . ').';
            return response()->json([
                'ok' => false,
                'msg' => 'OpenAI: ' . $errorMsg
            ], 200);
        }


        $coachReply = $resData['choices'][0]['message']['content'];

        return response()->json([
            'ok' => true,
            'reply' => $coachReply
        ]);
    }
}

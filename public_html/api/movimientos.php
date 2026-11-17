<?php
// api/movimientos.php - Registro de altas y bajas de socios (tabla Movimiento_Socio).
// Solo debe ser incluido por otros scripts PHP, nunca accedido directamente.
// Lo usan: gimnasio_lib.php, asignar_gimnasio.php, GimnasioController y los scripts de demo/.
//
// REGLA: cada vez que cambia Usuario.ID_Gimnasio de un socio, se anota un movimiento en la
// MISMA transaccion. Si no se puede anotar, el cambio de gimnasio tampoco se guarda.
if (__FILE__ === $_SERVER['SCRIPT_FILENAME']) {
    http_response_code(403);
    exit;
}

if (!function_exists('gt_registrar_movimiento')) {
    /**
     * Anota un alta o una baja de un socio en un gimnasio.
     *   $tipo   'alta' | 'baja'
     *   $origen 'solicitud' | 'admin' | 'socio' | 'carga_inicial' | 'simulador'
     *   $fecha  'AAAA-MM-DD HH:MM:SS' o null para ahora (hora del servidor de base de datos)
     */
    function gt_registrar_movimiento(PDO $pdo, $idUsuario, $idGimnasio, $tipo, $origen, $fecha = null) {
        if (!in_array($tipo, ['alta', 'baja'], true)) {
            throw new InvalidArgumentException("Tipo de movimiento invalido: $tipo");
        }
        if (!in_array($origen, ['solicitud', 'admin', 'socio', 'carga_inicial', 'simulador'], true)) {
            throw new InvalidArgumentException("Origen de movimiento invalido: $origen");
        }
        if ($fecha === null) {
            $st = $pdo->prepare("INSERT INTO Movimiento_Socio (ID_Usuario, ID_Gimnasio, tipo, origen) VALUES (?, ?, ?, ?)");
            $st->execute([(int) $idUsuario, (int) $idGimnasio, $tipo, $origen]);
        } else {
            $st = $pdo->prepare("INSERT INTO Movimiento_Socio (ID_Usuario, ID_Gimnasio, tipo, origen, fecha) VALUES (?, ?, ?, ?, ?)");
            $st->execute([(int) $idUsuario, (int) $idGimnasio, $tipo, $origen, $fecha]);
        }
    }
}

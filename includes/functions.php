<?php
require_once '../config/database.php';

// ============================================================================
// FUNCIONES BÁSICAS DE CONSULTA
// ============================================================================

function getAllLibros($conn) {
    $query = "SELECT * FROM libro WHERE disponible = 1 ORDER BY titulo";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAllComputadoras($conn) {
    $query = "SELECT * FROM computadora WHERE disponible = 1 ORDER BY numero";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getFacultades($conn) {
    $query = "SELECT * FROM facultad ORDER BY nombre_facultad";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTurnos($conn) {
    $query = "SELECT * FROM turno ORDER BY nombre_turno";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTiposUso($conn) {
    $query = "SELECT * FROM tipouso ORDER BY nombre_tipo_uso";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * FUNCIÓN AÑADIDA: Obtiene los tipos de reserva de la base de datos.
 */
function getTiposReserva($conn) {
    $query = "SELECT * FROM tiporeserva ORDER BY nombre_tipo_reserva";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function getNombreCompleto($usuario) {
    return trim($usuario['nombre'] . ' ' . $usuario['apellido']);
}

// ============================================================================
// FUNCIONES DE BIBLIOTECARIO
// ============================================================================

function getBibliotecarios($conn) {
    // Corregido: Ordenar por nombre_completo en lugar de nombre
    $query = "SELECT * FROM bibliotecario ORDER BY nombre_completo";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPrimerBibliotecario($conn) {
    $query = "SELECT id_bibliotecario FROM bibliotecario ORDER BY id_bibliotecario LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['id_bibliotecario'] : null;
}

// ============================================================================
// FUNCIONES DE USUARIOS (ACTUALIZADAS)
// ============================================================================

/**
 * FUNCIÓN ACTUALIZADA: Crea un usuario y sus detalles en tablas separadas usando una transacción.
 */
function crearUsuario($conn, $userData) {
    // Iniciar una transacción para asegurar que todo se inserte correctamente
    $conn->beginTransaction();

    try {
        // 1. Insertar en la tabla principal 'usuario'
        $sqlUsuario = "INSERT INTO usuario (nombre, apellido, cedula, id_afiliacion, id_tipo_usuario, fecha_registro) 
                       VALUES (:nombre, :apellido, :cedula, :id_afiliacion, :id_tipo_usuario, :fecha_registro)";
        
        $stmtUsuario = $conn->prepare($sqlUsuario);
        $stmtUsuario->execute([
            ':nombre' => $userData['nombre'],
            ':apellido' => $userData['apellido'],
            ':cedula' => $userData['cedula'],
            ':id_afiliacion' => $userData['id_afiliacion'],
            ':id_tipo_usuario' => $userData['id_tipo_usuario'], // Puede ser null
            ':fecha_registro' => $userData['fecha_registro']
        ]);

        // 2. Obtener el ID del usuario recién creado
        $id_usuario = $conn->lastInsertId();

        // 3. Insertar en la tabla de detalle correspondiente
            switch ($userData['id_afiliacion']) {
            case 1: // Universidad de Panamá
                // Solo insertar si hay facultad definida
                if (!empty($userData['id_facultad'])) {
                    $sqlDetalle = "INSERT INTO usuario_interno_detalle (id_usuario, id_facultad, id_carrera) 
                                   VALUES (:id_usuario, :id_facultad, :id_carrera)";
                    $stmtDetalle = $conn->prepare($sqlDetalle);
                    $stmtDetalle->execute([
                        ':id_usuario' => $id_usuario,
                        ':id_facultad' => $userData['id_facultad'],
                        ':id_carrera' => $userData['id_carrera'] ?? null
                    ]);
                }
                break;
            
           case 2: // Otra Universidad
                $sqlDetalle = "INSERT INTO usuario_externo_detalle (id_usuario, universidad_externa) 
                               VALUES (:id_usuario, :universidad_externa)";
                $stmtDetalle = $conn->prepare($sqlDetalle);
                $stmtDetalle->execute([
                    ':id_usuario' => $id_usuario,
                    ':universidad_externa' => $userData['universidad_externa']
                ]);
                break;

            case 3: // Particular
                $sqlDetalle = "INSERT INTO usuario_particular_detalle (id_usuario, celular) 
                               VALUES (:id_usuario, :celular)";
                $stmtDetalle = $conn->prepare($sqlDetalle);
                $stmtDetalle->execute([
                    ':id_usuario' => $id_usuario,
                    ':celular' => $userData['celular']
                ]);
                break;
        }

        // Si todo fue bien, confirmar la transacción
        $conn->commit();
        return true;

    } catch (Exception $e) {
        // Si algo falla, revertir la transacción
        $conn->rollBack();
        // Opcional: registrar el error para depuración -> error_log($e->getMessage());
        return false;
    }
}

/**
 * FUNCIÓN ACTUALIZADA: Verifica un usuario consultando la tabla principal y las tablas de detalle.
 */
function verificarUsuario($conn, $cedula) {
    $sql = "
        SELECT 
            u.id_usuario,
            u.nombre,
            u.apellido,
            CONCAT(u.nombre, ' ', u.apellido) AS nombre_completo,
            u.cedula,
            u.id_afiliacion,
            a.nombre_afiliacion,
            u.id_tipo_usuario,
            tu.nombre_tipo_usuario,
            
            -- Detalles de usuario interno
            uid.id_facultad,
            f.nombre_facultad,
            uid.id_carrera,
            c.nombre_carrera,

            -- Detalles de usuario externo
            ued.universidad_externa,

            -- Detalles de usuario particular
            upd.celular

        FROM usuario u
        LEFT JOIN afiliacion a ON u.id_afiliacion = a.id_afiliacion
        LEFT JOIN tipousuario tu ON u.id_tipo_usuario = tu.id_tipo_usuario
        LEFT JOIN usuario_interno_detalle uid ON u.id_usuario = uid.id_usuario
        LEFT JOIN facultad f ON uid.id_facultad = f.id_facultad
        LEFT JOIN carrera c ON uid.id_carrera = c.id_carrera
        LEFT JOIN usuario_externo_detalle ued ON u.id_usuario = ued.id_usuario
        LEFT JOIN usuario_particular_detalle upd ON u.id_usuario = upd.id_usuario
        WHERE u.cedula = :cedula
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([':cedula' => $cedula]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}


// ============================================================================
// FUNCIONES DE RESERVAS (✅ ACTUALIZADAS CON SOPORTE PARA 'origen')
// ============================================================================

// En includes/functions.php

/**
 * ✅ RESERVA DE LIBRO CON MENSAJES ESPECÍFICOS
 */
function reservarLibro($conn, $data) {
    try {
        // Iniciar transacción
        $conn->beginTransaction();
        
        // 🔒 BLOQUEAR Y VERIFICAR DISPONIBILIDAD
        $queryCheck = "SELECT disponible FROM libro WHERE id_libro = :id_libro FOR UPDATE";
        $stmtCheck = $conn->prepare($queryCheck);
        $stmtCheck->execute([':id_libro' => $data['libro']]);
        $libro = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        // ✅ Verificar si existe el libro
        if (!$libro) {
            $conn->rollBack();
            return ['success' => false, 'code' => 'LIBRO_NO_EXISTE'];
        }
        
        // ✅ Verificar si está disponible
        if ($libro['disponible'] != 1) {
            $conn->rollBack();
            return ['success' => false, 'code' => 'LIBRO_NO_DISPONIBLE'];
        }
        
        // Determinar origen
        $origen = isset($data['origen']) ? $data['origen'] : 'cliente';
        
        // Insertar reserva
        $queryReserva = "INSERT INTO reservalibro (id_libro, id_usuario, fecha, id_tipo_reserva, id_turno, origen) 
                        VALUES (:libro, :usuario, :fecha, :tipo_reserva, :id_turno, :origen)";
        $stmtReserva = $conn->prepare($queryReserva);
        $stmtReserva->execute([
            ':libro' => $data['libro'],
            ':usuario' => $data['usuario'],
            ':fecha' => $data['fecha'],
            ':tipo_reserva' => $data['tipo_reserva'],
            ':id_turno' => $data['id_turno'],
            ':origen' => $origen
        ]);
        
        // Marcar como no disponible
        $queryUpdate = "UPDATE libro SET disponible = 0 WHERE id_libro = :id_libro";
        $stmtUpdate = $conn->prepare($queryUpdate);
        $stmtUpdate->execute([':id_libro' => $data['libro']]);
        
        // Confirmar transacción
        $conn->commit();
        return ['success' => true, 'code' => 'RESERVA_EXITOSA'];
        
    } catch (PDOException $e) {
        // Revertir cambios
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        // ✅ Detectar tipo de error específico
        if (strpos($e->getMessage(), 'Lock wait timeout') !== false) {
            return ['success' => false, 'code' => 'TIMEOUT'];
        }
        
        if (strpos($e->getMessage(), 'Deadlock') !== false) {
            return ['success' => false, 'code' => 'DEADLOCK'];
        }
        
        error_log("Error en reservarLibro: " . $e->getMessage());
        return ['success' => false, 'code' => 'ERROR_SISTEMA'];
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Error general en reservarLibro: " . $e->getMessage());
        return ['success' => false, 'code' => 'ERROR_DESCONOCIDO'];
    }
}

/**
 * ✅ RESERVA DE COMPUTADORA CON MENSAJES ESPECÍFICOS
 */
function reservarComputadora($conn, $data) {
    try {
        // Iniciar transacción
        $conn->beginTransaction();
        
        // 🔒 BLOQUEAR Y VERIFICAR DISPONIBILIDAD
        $queryCheck = "SELECT disponible FROM computadora WHERE id_computadora = :id_computadora FOR UPDATE";
        $stmtCheck = $conn->prepare($queryCheck);
        $stmtCheck->execute([':id_computadora' => $data['computadora']]);
        $pc = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        // ✅ Verificar si existe la computadora
        if (!$pc) {
            $conn->rollBack();
            return ['success' => false, 'code' => 'PC_NO_EXISTE'];
        }
        
        // ✅ Verificar si está disponible
        if ($pc['disponible'] != 1) {
            $conn->rollBack();
            return ['success' => false, 'code' => 'PC_NO_DISPONIBLE'];
        }
        
        // Determinar origen
        $origen = isset($data['origen']) ? $data['origen'] : 'cliente';
        
        // Insertar reserva
        $queryReserva = "INSERT INTO reservacomputadora (id_usuario, id_computadora, fecha, id_turno, id_tipo_uso, hora_entrada, origen) 
                        VALUES (:usuario, :computadora, :fecha, :turno, :tipo_uso, :hora_entrada, :origen)";
        $stmtReserva = $conn->prepare($queryReserva);
        $stmtReserva->execute([
            ':usuario' => $data['usuario'],
            ':computadora' => $data['computadora'],
            ':fecha' => $data['fecha'],
            ':turno' => $data['turno'],
            ':tipo_uso' => $data['tipo_uso'],
            ':hora_entrada' => $data['hora_entrada'],
            ':origen' => $origen
        ]);
        
        // Marcar como no disponible
        $queryUpdate = "UPDATE computadora SET disponible = 0 WHERE id_computadora = :id_computadora";
        $stmtUpdate = $conn->prepare($queryUpdate);
        $stmtUpdate->execute([':id_computadora' => $data['computadora']]);
        
        // Confirmar transacción
        $conn->commit();
        return ['success' => true, 'code' => 'RESERVA_EXITOSA'];
        
    } catch (PDOException $e) {
        // Revertir cambios
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        
        // ✅ Detectar tipo de error específico
        if (strpos($e->getMessage(), 'Lock wait timeout') !== false) {
            return ['success' => false, 'code' => 'TIMEOUT'];
        }
        
        if (strpos($e->getMessage(), 'Deadlock') !== false) {
            return ['success' => false, 'code' => 'DEADLOCK'];
        }
        
        error_log("Error en reservarComputadora: " . $e->getMessage());
        return ['success' => false, 'code' => 'ERROR_SISTEMA'];
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("Error general en reservarComputadora: " . $e->getMessage());
        return ['success' => false, 'code' => 'ERROR_DESCONOCIDO'];
    }
}

// ============================================================================
// FUNCIONES DE VALIDACIÓN
// ============================================================================

function verificarDisponibilidadComputadora($conn, $computadora, $fecha, $turno) {
    $query = "SELECT COUNT(*) as count FROM reservacomputadora 
              WHERE id_computadora = :computadora AND fecha = :fecha AND id_turno = :turno";
    $stmt = $conn->prepare($query);
    $stmt->execute([
        ':computadora' => $computadora,
        ':fecha' => $fecha,
        ':turno' => $turno
    ]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] == 0;
}

// ============================================================================
// FUNCIONES AUXILIARES
// ============================================================================

function determinarTurno() {
    $hora = (int)date('H');
    if ($hora >= 6 && $hora < 12) return 1; // Matutino
    if ($hora >= 12 && $hora < 18) return 2; // Vespertino
    return 3; // Nocturno
}

?>
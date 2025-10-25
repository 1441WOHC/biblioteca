<?php
session_start();
header('Content-Type: application/json');

require_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();
$action = $_GET['action'] ?? '';

// FUNCIÓN PARA NORMALIZAR NOMBRES Y APELLIDOS
function normalizarNombre($texto) {
    // Convierte todo a minúsculas primero
    $texto = mb_strtolower(trim($texto), 'UTF-8');
    
    // Divide el texto en palabras
    $palabras = explode(' ', $texto);
    
    // Capitaliza la primera letra de cada palabra
    $palabras = array_map(function($palabra) {
        return mb_convert_case($palabra, MB_CASE_TITLE, 'UTF-8');
    }, $palabras);
    
    // Une las palabras nuevamente
    return implode(' ', $palabras);
}


// Se maneja la acción pública 'verificar_disponibilidad' ANTES de la verificación de seguridad.
if ($action === 'verificar_disponibilidad') {
    if (!isset($_GET['id_computadora'])) {
        echo json_encode(['error' => 'ID de computadora requerido']);
        exit;
    }
    
    $query = "SELECT disponible FROM computadora WHERE id_computadora = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':id' => $_GET['id_computadora']]);
    $computadora = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($computadora) {
        echo json_encode(['disponible' => $computadora['disponible'] == 1]);
    } else {
        echo json_encode(['disponible' => false]);
    }
    exit;
}

// Medida de seguridad: solo permitir el acceso a bibliotecarios autenticados para las demás acciones.
if (!isset($_SESSION['bibliotecario'])) {
    http_response_code(403); // Forbidden
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit;
}

switch ($action) {
    case 'get_reservas_libros':
        // Parámetros de búsqueda
        $search = $_GET['search'] ?? '';
        $estado = $_GET['estado'] ?? '';
        $tipo = $_GET['tipo'] ?? '';
        $fecha = $_GET['fecha'] ?? '';
        $fecha_inicio = $_GET['fecha_inicio'] ?? '';
        $fecha_fin = $_GET['fecha_fin'] ?? '';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        
        $whereConditions = [];
        $params = [];
        
        // Construir condiciones WHERE dinámicamente
        if (!empty($search)) {
            $whereConditions[] = "(CONCAT(u.nombre, ' ', u.apellido) LIKE :search 
                                 OR u.cedula LIKE :search 
                                 OR l.titulo LIKE :search 
                                 OR l.codigo_unico LIKE :search 
                                 OR l.autor LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        
        if (!empty($estado)) {
            if ($estado === 'Pendiente') {
                $whereConditions[] = "rl.fecha_hora_devolucion IS NULL"; // <-- CORREGIDO
            } elseif ($estado === 'Devuelto') {
                $whereConditions[] = "rl.fecha_hora_devolucion IS NOT NULL"; // <-- CORREGIDO
            }
        }
        
        if (!empty($tipo)) {
            $whereConditions[] = "tr.nombre_tipo_reserva = :tipo";
            $params[':tipo'] = $tipo;
        }

        if (!empty($fecha)) {
            $whereConditions[] = "rl.fecha = :fecha";
            $params[':fecha'] = $fecha;
        }
        
        if (!empty($fecha_inicio)) {
            $whereConditions[] = "rl.fecha >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }
        
        if (!empty($fecha_fin)) {
            $whereConditions[] = "rl.fecha <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin . ' 23:59:59';
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        $limitClause = ($limit > 0) ? "LIMIT $limit" : '';
        
        $query = "SELECT rl.*, l.titulo, l.autor, l.codigo_unico,
                         CONCAT(u.nombre, ' ', u.apellido) as usuario_nombre, 
                         u.cedula, tr.nombre_tipo_reserva
                  FROM reservalibro rl
                  JOIN libro l ON rl.id_libro = l.id_libro
                  JOIN usuario u ON rl.id_usuario = u.id_usuario
                  JOIN tiporeserva tr ON rl.id_tipo_reserva = tr.id_tipo_reserva
                  $whereClause
                  ORDER BY rl.fecha DESC, rl.id_reserva_libro DESC
                  $limitClause";
                  
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($reservas as &$reserva) {
            $reserva['estado'] = $reserva['fecha_hora_devolucion'] ? 'Devuelto' : 'Pendiente';
        }
        echo json_encode($reservas);
        break;

    case 'get_reservas_computadoras':
        // Parámetros de búsqueda
        $search = $_GET['search'] ?? '';
        $fecha = $_GET['fecha'] ?? '';
        $estado = $_GET['estado'] ?? '';
        $tipoUso = $_GET['tipo_uso'] ?? '';
        $fecha_inicio = $_GET['fecha_inicio'] ?? '';
        $fecha_fin = $_GET['fecha_fin'] ?? '';
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        
        $whereConditions = [];
        $params = [];
        
        // Construir condiciones WHERE dinámicamente
        if (!empty($search)) {
            $whereConditions[] = "(CONCAT(u.nombre, ' ', u.apellido) LIKE :search 
                                 OR u.cedula LIKE :search 
                                 OR c.numero LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }
        
        if (!empty($fecha)) {
            $whereConditions[] = "rc.fecha = :fecha";
            $params[':fecha'] = $fecha;
        }
        
        if (!empty($estado)) {
            // Suponiendo que la tabla 'reservacomputadora' tiene una columna 'hora_salida' para indicar que la sesión terminó.
            if ($estado === 'activo') {
                 $whereConditions[] = "rc.hora_salida IS NULL"; // <-- CORREGIDO (Alias 'rc' y columna correcta)
            } elseif ($estado === 'finalizado') {
                 $whereConditions[] = "rc.hora_salida IS NOT NULL"; // <-- CORREGIDO (Alias 'rc' y columna correcta)
            }
        }

        if (!empty($tipoUso)) {
            $whereConditions[] = "tu.nombre_tipo_uso = :tipo_uso";
            $params[':tipo_uso'] = $tipoUso;
        }
        
        if (!empty($fecha_inicio)) {
            $whereConditions[] = "rc.fecha >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }
        
        if (!empty($fecha_fin)) {
            $whereConditions[] = "rc.fecha <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin . ' 23:59:59';
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        $limitClause = ($limit > 0) ? "LIMIT $limit" : '';
        
        $query = "SELECT rc.*, c.numero as computadora_numero, 
                         CONCAT(u.nombre, ' ', u.apellido) as usuario_nombre, 
                         u.cedula, t.nombre_turno, tu.nombre_tipo_uso
                  FROM reservacomputadora rc
                  JOIN computadora c ON rc.id_computadora = c.id_computadora
                  JOIN usuario u ON rc.id_usuario = u.id_usuario
                  JOIN turno t ON rc.id_turno = t.id_turno
                  JOIN tipouso tu ON rc.id_tipo_uso = tu.id_tipo_uso
                  $whereClause
                  ORDER BY rc.fecha DESC, rc.hora_entrada DESC
                  $limitClause";
                  
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $reservas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($reservas);
        break;

    // ... (El resto del código no necesita cambios)

case 'get_usuarios':
    $search = $_GET['search'] ?? '';
    $tipo_usuario = $_GET['tipo_usuario'] ?? '';
    $fecha_inicio = $_GET['fecha_inicio'] ?? '';
    $fecha_fin = $_GET['fecha_fin'] ?? '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    
    $whereConditions = [];
    $params = [];
    
    if (!empty($search)) {
        // CORREGIDO: Eliminada referencia a facultad_externa
        $whereConditions[] = "(CONCAT(u.nombre, ' ', u.apellido) LIKE :search 
                             OR u.cedula LIKE :search 
                             OR a.nombre_afiliacion LIKE :search
                             OR ued.universidad_externa LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    
    if (!empty($tipo_usuario)) {
        $whereConditions[] = "tu.nombre_tipo_usuario = :tipo_usuario";
        $params[':tipo_usuario'] = $tipo_usuario;
    }
    
    if (!empty($fecha_inicio)) {
        $whereConditions[] = "u.fecha_registro >= :fecha_inicio";
        $params[':fecha_inicio'] = $fecha_inicio;
    }
    
    if (!empty($fecha_fin)) {
        $whereConditions[] = "u.fecha_registro <= :fecha_fin";
        $params[':fecha_fin'] = $fecha_fin . ' 23:59:59';
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    $limitClause = ($limit > 0) ? "LIMIT $limit" : '';
    
    // CORREGIDO: Eliminada columna facultad_externa del SELECT
    $query = "SELECT u.id_usuario, u.cedula, u.fecha_registro, 
                     CONCAT(u.nombre, ' ', u.apellido) AS nombre_completo,
                     tu.nombre_tipo_usuario,
                     COALESCE(f.nombre_facultad, 'N/A') as nombre_facultad,
                     COALESCE(c.nombre_carrera, 'N/A') as nombre_carrera,
                     CASE 
                         WHEN ued.id_usuario IS NOT NULL THEN ued.universidad_externa
                         ELSE a.nombre_afiliacion
                     END as nombre_afiliacion
              FROM usuario u
              JOIN tipousuario tu ON u.id_tipo_usuario = tu.id_tipo_usuario
              JOIN afiliacion a ON u.id_afiliacion = a.id_afiliacion
              LEFT JOIN usuario_interno_detalle uid ON u.id_usuario = uid.id_usuario
              LEFT JOIN facultad f ON uid.id_facultad = f.id_facultad
              LEFT JOIN carrera c ON uid.id_carrera = c.id_carrera
              LEFT JOIN usuario_externo_detalle ued ON u.id_usuario = ued.id_usuario
              $whereClause
              ORDER BY u.fecha_registro DESC, u.id_usuario DESC
              $limitClause";
                
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($usuarios);
    break;


// ===== NUEVOS ENDPOINTS DE ESTADÍSTICAS CORREGIDOS =====

    case 'get_stats_general':
        // Obtener parámetros específicos para estadísticas
        $fecha_inicio = $_GET['fecha_inicio'] ?? '';
        $fecha_fin = $_GET['fecha_fin'] ?? '';
        
        // Construir condiciones específicas para este caso
        $whereConditions = [];
        $params = [];
        
        if (!empty($fecha_inicio)) {
            $whereConditions[] = "fecha >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }
        
        if (!empty($fecha_fin)) {
            $whereConditions[] = "fecha <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin . ' 23:59:59';
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // 1. Consulta para libros
        $queryLibros = "SELECT DATE(fecha) as dia, COUNT(*) as total 
                        FROM reservalibro 
                        $whereClause 
                        GROUP BY dia ORDER BY dia ASC";
        $stmtLibros = $conn->prepare($queryLibros);
        $stmtLibros->execute($params);
        $librosDataRaw = $stmtLibros->fetchAll(PDO::FETCH_ASSOC);
        
        // Convertir a formato [dia => total]
        $librosData = [];
        foreach ($librosDataRaw as $row) {
            $librosData[$row['dia']] = (int)$row['total'];
        }

        // 2. Consulta para computadoras
        $queryPcs = "SELECT DATE(fecha) as dia, COUNT(*) as total 
                     FROM reservacomputadora 
                     $whereClause 
                     GROUP BY dia ORDER BY dia ASC";
        $stmtPcs = $conn->prepare($queryPcs);
        $stmtPcs->execute($params);
        $pcsDataRaw = $stmtPcs->fetchAll(PDO::FETCH_ASSOC);
        
        // Convertir a formato [dia => total]
        $pcsData = [];
        foreach ($pcsDataRaw as $row) {
            $pcsData[$row['dia']] = (int)$row['total'];
        }

        // 3. Procesar rangos de fechas para rellenar ceros
        $inicio = new DateTime($fecha_inicio ?: '30 days ago');
        $fin = new DateTime($fecha_fin ?: 'now');
        $intervalo = new DateInterval('P1D');
        $periodo = new DatePeriod($inicio, $intervalo, $fin->modify('+1 day'));

        $labels = [];
        $dataLibros = [];
        $dataPcs = [];

        foreach ($periodo as $fecha) {
            $diaStr = $fecha->format('Y-m-d');
            $labels[] = $diaStr;
            $dataLibros[] = $librosData[$diaStr] ?? 0;
            $dataPcs[] = $pcsData[$diaStr] ?? 0;
        }
        
        echo json_encode([
            'labels' => $labels,
            'libros' => $dataLibros,
            'computadoras' => $dataPcs
        ]);
        break;

    case 'get_stats_top_libros':
        // Obtener parámetros específicos
        $fecha_inicio = $_GET['fecha_inicio'] ?? '';
        $fecha_fin = $_GET['fecha_fin'] ?? '';
        
        // Construir condiciones específicas
        $whereConditions = [];
        $params = [];
        
        if (!empty($fecha_inicio)) {
            $whereConditions[] = "rl.fecha >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }
        
        if (!empty($fecha_fin)) {
            $whereConditions[] = "rl.fecha <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin . ' 23:59:59';
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $query = "SELECT l.titulo, COUNT(rl.id_reserva_libro) as total
                  FROM reservalibro rl 
                  JOIN libro l ON rl.id_libro = l.id_libro
                  $whereClause
                  GROUP BY l.id_libro, l.titulo
                  ORDER BY total DESC
                  LIMIT 5";
                  
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($results);
        break;

    case 'get_stats_tipo_usuario':
        // Obtener parámetros específicos
        $fecha_inicio = $_GET['fecha_inicio'] ?? '';
        $fecha_fin = $_GET['fecha_fin'] ?? '';
        
        // Construir condiciones específicas
        $whereConditions = [];
        $params = [];
        
        if (!empty($fecha_inicio)) {
            $whereConditions[] = "t.fecha >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }
        
        if (!empty($fecha_fin)) {
            $whereConditions[] = "t.fecha <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin . ' 23:59:59';
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $query = "SELECT tu.nombre_tipo_usuario, COUNT(*) as total
                  FROM (
                      SELECT u.id_tipo_usuario, rl.fecha
                      FROM reservalibro rl
                      JOIN usuario u ON rl.id_usuario = u.id_usuario
                      UNION ALL
                      SELECT u.id_tipo_usuario, rc.fecha
                      FROM reservacomputadora rc
                      JOIN usuario u ON rc.id_usuario = u.id_usuario
                  ) as t
                  JOIN tipousuario tu ON t.id_tipo_usuario = tu.id_tipo_usuario
                  $whereClause
                  GROUP BY tu.nombre_tipo_usuario
                  ORDER BY total DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($results);
        break;

    // NUEVOS ENDPOINTS AGREGADOS

    case 'get_stats_afiliacion':
        $fecha_inicio = $_GET['fecha_inicio'] ?? '';
        $fecha_fin = $_GET['fecha_fin'] ?? '';
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($fecha_inicio)) {
            $whereConditions[] = "t.fecha >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }
        
        if (!empty($fecha_fin)) {
            $whereConditions[] = "t.fecha <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin . ' 23:59:59';
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $query = "SELECT a.nombre_afiliacion, COUNT(*) as total
                  FROM (
                      SELECT u.id_afiliacion, rl.fecha
                      FROM reservalibro rl
                      JOIN usuario u ON rl.id_usuario = u.id_usuario
                      UNION ALL
                      SELECT u.id_afiliacion, rc.fecha
                      FROM reservacomputadora rc
                      JOIN usuario u ON rc.id_usuario = u.id_usuario
                  ) as t
                  JOIN afiliacion a ON t.id_afiliacion = a.id_afiliacion
                  $whereClause
                  GROUP BY a.nombre_afiliacion
                  ORDER BY total DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($results);
        break;

    case 'get_stats_top_facultades':
        $fecha_inicio = $_GET['fecha_inicio'] ?? '';
        $fecha_fin = $_GET['fecha_fin'] ?? '';
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($fecha_inicio)) {
            $whereConditions[] = "t.fecha >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }
        
        if (!empty($fecha_fin)) {
            $whereConditions[] = "t.fecha <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin . ' 23:59:59';
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // MODIFICADO: Se eliminan las facultades externas (ued) y se usan INNER JOIN 
        // para contar solo facultades internas (UP)
        $query = "SELECT f.nombre_facultad, 
                         COUNT(*) as total
                  FROM (
                      SELECT u.id_usuario, rl.fecha
                      FROM reservalibro rl
                      JOIN usuario u ON rl.id_usuario = u.id_usuario
                      UNION ALL
                      SELECT u.id_usuario, rc.fecha
                      FROM reservacomputadora rc
                      JOIN usuario u ON rc.id_usuario = u.id_usuario
                  ) as t
                  JOIN usuario u ON t.id_usuario = u.id_usuario
                  INNER JOIN usuario_interno_detalle uid ON u.id_usuario = uid.id_usuario
                  INNER JOIN facultad f ON uid.id_facultad = f.id_facultad
                  -- Se eliminó: LEFT JOIN usuario_externo_detalle ued ON u.id_usuario = ued.id_usuario
                  $whereClause
                  GROUP BY f.nombre_facultad
                  ORDER BY total DESC
                  LIMIT 5";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($results);
        break;

    case 'get_stats_turnos':
        $fecha_inicio = $_GET['fecha_inicio'] ?? '';
        $fecha_fin = $_GET['fecha_fin'] ?? '';
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($fecha_inicio)) {
            $whereConditions[] = "rc.fecha >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }
        
        if (!empty($fecha_fin)) {
            $whereConditions[] = "rc.fecha <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin . ' 23:59:59';
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $query = "SELECT t.nombre_turno, COUNT(*) as total
                  FROM reservacomputadora rc
                  JOIN turno t ON rc.id_turno = t.id_turno
                  $whereClause
                  GROUP BY t.nombre_turno
                  ORDER BY total DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($results);
        break;

    case 'get_stats_tipos_uso':
        $fecha_inicio = $_GET['fecha_inicio'] ?? '';
        $fecha_fin = $_GET['fecha_fin'] ?? '';
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($fecha_inicio)) {
            $whereConditions[] = "rc.fecha >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }
        
        if (!empty($fecha_fin)) {
            $whereConditions[] = "rc.fecha <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin . ' 23:59:59';
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $query = "SELECT tu.nombre_tipo_uso, COUNT(*) as total
                  FROM reservacomputadora rc
                  JOIN tipouso tu ON rc.id_tipo_uso = tu.id_tipo_uso
                  $whereClause
                  GROUP BY tu.nombre_tipo_uso
                  ORDER BY total DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($results);
        break;

    case 'get_stats_tipos_reserva':
        $fecha_inicio = $_GET['fecha_inicio'] ?? '';
        $fecha_fin = $_GET['fecha_fin'] ?? '';
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($fecha_inicio)) {
            $whereConditions[] = "rl.fecha >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }
        
        if (!empty($fecha_fin)) {
            $whereConditions[] = "rl.fecha <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin . ' 23:59:59';
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $query = "SELECT tr.nombre_tipo_reserva, COUNT(*) as total
                  FROM reservalibro rl
                  JOIN tiporeserva tr ON rl.id_tipo_reserva = tr.id_tipo_reserva
                  $whereClause
                  GROUP BY tr.nombre_tipo_reserva
                  ORDER BY total DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($results);
        break;

    case 'get_stats_categorias':
        $fecha_inicio = $_GET['fecha_inicio'] ?? '';
        $fecha_fin = $_GET['fecha_fin'] ?? '';
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($fecha_inicio)) {
            $whereConditions[] = "rl.fecha >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }
        
        if (!empty($fecha_fin)) {
            $whereConditions[] = "rl.fecha <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin . ' 23:59:59';
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $query = "SELECT COALESCE(c.nombre_categoria, 'Sin categoría') as nombre_categoria, COUNT(*) as total
                  FROM reservalibro rl
                  JOIN libro l ON rl.id_libro = l.id_libro
                  LEFT JOIN categoria c ON l.id_categoria = c.id_categoria
                  $whereClause
                  GROUP BY c.id_categoria, c.nombre_categoria
                  ORDER BY total DESC
                  LIMIT 8";
        
        $stmt = $conn->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($results);
        break;

    case 'get_stats_origen':
        $fecha_inicio = $_GET['fecha_inicio'] ?? '';
        $fecha_fin = $_GET['fecha_fin'] ?? '';
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($fecha_inicio)) {
            $whereConditions[] = "fecha >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }
        
        if (!empty($fecha_fin)) {
            $whereConditions[] = "fecha <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin . ' 23:59:59';
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // Construir WHERE con AND si hay condiciones
        $whereLibrosCliente = $whereClause;
        if (!empty($whereClause)) {
            $whereLibrosCliente .= " AND origen = 'cliente'";
        } else {
            $whereLibrosCliente = "WHERE origen = 'cliente'";
        }
        
        $whereLibrosAdmin = $whereClause;
        if (!empty($whereClause)) {
            $whereLibrosAdmin .= " AND origen = 'admin'";
        } else {
            $whereLibrosAdmin = "WHERE origen = 'admin'";
        }
        
        $wherePcsCliente = $whereClause;
        if (!empty($whereClause)) {
            $wherePcsCliente .= " AND origen = 'cliente'";
        } else {
            $wherePcsCliente = "WHERE origen = 'cliente'";
        }
        
        $wherePcsAdmin = $whereClause;
        if (!empty($whereClause)) {
            $wherePcsAdmin .= " AND origen = 'admin'";
        } else {
            $wherePcsAdmin = "WHERE origen = 'admin'";
        }

        // Libros por cliente
        $queryLibrosCliente = "SELECT COUNT(*) as total FROM reservalibro $whereLibrosCliente";
        $stmtLibrosCliente = $conn->prepare($queryLibrosCliente);
        $stmtLibrosCliente->execute($params);
        $librosCliente = $stmtLibrosCliente->fetch(PDO::FETCH_ASSOC)['total'];

        // Libros por admin
        $queryLibrosAdmin = "SELECT COUNT(*) as total FROM reservalibro $whereLibrosAdmin";
        $stmtLibrosAdmin = $conn->prepare($queryLibrosAdmin);
        $stmtLibrosAdmin->execute($params);
        $librosAdmin = $stmtLibrosAdmin->fetch(PDO::FETCH_ASSOC)['total'];

        // PCs por cliente
        $queryPcsCliente = "SELECT COUNT(*) as total FROM reservacomputadora $wherePcsCliente";
        $stmtPcsCliente = $conn->prepare($queryPcsCliente);
        $stmtPcsCliente->execute($params);
        $pcsCliente = $stmtPcsCliente->fetch(PDO::FETCH_ASSOC)['total'];

        // PCs por admin
        $queryPcsAdmin = "SELECT COUNT(*) as total FROM reservacomputadora $wherePcsAdmin";
        $stmtPcsAdmin = $conn->prepare($queryPcsAdmin);
        $stmtPcsAdmin->execute($params);
        $pcsAdmin = $stmtPcsAdmin->fetch(PDO::FETCH_ASSOC)['total'];

        echo json_encode([
            'libros_cliente' => (int)$librosCliente,
            'libros_admin' => (int)$librosAdmin,
            'pcs_cliente' => (int)$pcsCliente,
            'pcs_admin' => (int)$pcsAdmin
        ]);
        break;

    case 'get_stats_kpis':
        $fecha_inicio = $_GET['fecha_inicio'] ?? '';
        $fecha_fin = $_GET['fecha_fin'] ?? '';
        
        $whereConditions = [];
        $params = [];
        
        if (!empty($fecha_inicio)) {
            $whereConditions[] = "fecha >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }
        
        if (!empty($fecha_fin)) {
            $whereConditions[] = "fecha <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin . ' 23:59:59';
        }
        
        $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Total de reservas de libros
        $queryLibros = "SELECT COUNT(*) as total FROM reservalibro $whereClause";
        $stmtLibros = $conn->prepare($queryLibros);
        $stmtLibros->execute($params);
        $totalLibros = $stmtLibros->fetch(PDO::FETCH_ASSOC)['total'];

        // Total de reservas de PCs
        $queryPcs = "SELECT COUNT(*) as total FROM reservacomputadora $whereClause";
        $stmtPcs = $conn->prepare($queryPcs);
        $stmtPcs->execute($params);
        $totalPcs = $stmtPcs->fetch(PDO::FETCH_ASSOC)['total'];

        // Usuarios activos (que han hecho al menos una reserva)
        $queryUsuarios = "SELECT COUNT(DISTINCT id_usuario) as total 
                          FROM (
                              SELECT id_usuario, fecha FROM reservalibro $whereClause
                              UNION
                              SELECT id_usuario, fecha FROM reservacomputadora $whereClause
                          ) as reservas";
        $stmtUsuarios = $conn->prepare($queryUsuarios);
        $stmtUsuarios->execute(array_merge($params, $params)); // Parámetros duplicados para el UNION
        $usuariosActivos = $stmtUsuarios->fetch(PDO::FETCH_ASSOC)['total'];

        echo json_encode([
            'total_reservas' => (int)$totalLibros + (int)$totalPcs,
            'total_libros' => (int)$totalLibros,
            'total_pcs' => (int)$totalPcs,
            'usuarios_activos' => (int)$usuariosActivos
        ]);
        break;


        case 'get_all_stats':
        $fecha_inicio = $_GET['fecha_inicio'] ?? '';
        $fecha_fin = $_GET['fecha_fin'] ?? '';
        
        $params = [];
        $whereConditions = [];
        
        if (!empty($fecha_inicio)) {
            $whereConditions[] = "fecha >= :fecha_inicio";
            $params[':fecha_inicio'] = $fecha_inicio;
        }
        
        if (!empty($fecha_fin)) {
            $whereConditions[] = "fecha <= :fecha_fin";
            $params[':fecha_fin'] = $fecha_fin . ' 23:59:59';
        }
        
        $whereClauseGeneral = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
        
        // --- 1. KPIs ---
        $queryLibros = "SELECT COUNT(*) as total FROM reservalibro $whereClauseGeneral";
        $stmtLibros = $conn->prepare($queryLibros);
        $stmtLibros->execute($params);
        $totalLibros = $stmtLibros->fetch(PDO::FETCH_ASSOC)['total'];

        $queryPcs = "SELECT COUNT(*) as total FROM reservacomputadora $whereClauseGeneral";
        $stmtPcs = $conn->prepare($queryPcs);
        $stmtPcs->execute($params);
        $totalPcs = $stmtPcs->fetch(PDO::FETCH_ASSOC)['total'];

        $queryUsuarios = "SELECT COUNT(DISTINCT id_usuario) as total 
                          FROM (
                              SELECT id_usuario, fecha FROM reservalibro $whereClauseGeneral
                              UNION
                              SELECT id_usuario, fecha FROM reservacomputadora $whereClauseGeneral
                          ) as reservas";
        $stmtUsuarios = $conn->prepare($queryUsuarios);
        $stmtUsuarios->execute(array_merge($params, $params));
        $usuariosActivos = $stmtUsuarios->fetch(PDO::FETCH_ASSOC)['total'];
        
        $dataKPIs = [
            'total_reservas' => (int)$totalLibros + (int)$totalPcs,
            'total_libros' => (int)$totalLibros,
            'total_pcs' => (int)$totalPcs,
            'usuarios_activos' => (int)$usuariosActivos
        ];

        // --- 2. USO GENERAL ---
        $queryLibros = "SELECT DATE(fecha) as dia, COUNT(*) as total 
                        FROM reservalibro 
                        $whereClauseGeneral 
                        GROUP BY dia ORDER BY dia ASC";
        $stmtLibros = $conn->prepare($queryLibros);
        $stmtLibros->execute($params);
        $librosDataRaw = $stmtLibros->fetchAll(PDO::FETCH_ASSOC);
        $librosData = [];
        foreach ($librosDataRaw as $row) $librosData[$row['dia']] = (int)$row['total'];

        $queryPcs = "SELECT DATE(fecha) as dia, COUNT(*) as total 
                     FROM reservacomputadora 
                     $whereClauseGeneral 
                     GROUP BY dia ORDER BY dia ASC";
        $stmtPcs = $conn->prepare($queryPcs);
        $stmtPcs->execute($params);
        $pcsDataRaw = $stmtPcs->fetchAll(PDO::FETCH_ASSOC);
        $pcsData = [];
        foreach ($pcsDataRaw as $row) $pcsData[$row['dia']] = (int)$row['total'];

        $inicio = new DateTime($fecha_inicio ?: '30 days ago');
        $fin = new DateTime($fecha_fin ?: 'now');
        $intervalo = new DateInterval('P1D');
        $periodo = new DatePeriod($inicio, $intervalo, $fin->modify('+1 day'));
        $labels = []; $dataLibros = []; $dataPcs = [];
        foreach ($periodo as $fecha) {
            $diaStr = $fecha->format('Y-m-d');
            $labels[] = $diaStr;
            $dataLibros[] = $librosData[$diaStr] ?? 0;
            $dataPcs[] = $pcsData[$diaStr] ?? 0;
        }
        $dataGeneral = [
            'labels' => $labels,
            'libros' => $dataLibros,
            'computadoras' => $dataPcs
        ];

        // --- Definir WHERE clauses específicos para tablas con alias ---
        $whereClauseRL = str_replace('fecha', 'rl.fecha', $whereClauseGeneral);
        $whereClauseRC = str_replace('fecha', 'rc.fecha', $whereClauseGeneral);
        $whereClauseUnion = str_replace('fecha', 't.fecha', $whereClauseGeneral);

        // --- 3. TOP LIBROS ---
        $query = "SELECT l.titulo, COUNT(rl.id_reserva_libro) as total
                  FROM reservalibro rl 
                  JOIN libro l ON rl.id_libro = l.id_libro
                  $whereClauseRL
                  GROUP BY l.id_libro, l.titulo
                  ORDER BY total DESC LIMIT 5";
        $stmt = $conn->prepare($query); $stmt->execute($params);
        $dataLibros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- 4. TIPO USUARIO ---
        $query = "SELECT tu.nombre_tipo_usuario, COUNT(*) as total
                  FROM (
                      SELECT u.id_tipo_usuario, rl.fecha
                      FROM reservalibro rl JOIN usuario u ON rl.id_usuario = u.id_usuario
                      UNION ALL
                      SELECT u.id_tipo_usuario, rc.fecha
                      FROM reservacomputadora rc JOIN usuario u ON rc.id_usuario = u.id_usuario
                  ) as t
                  JOIN tipousuario tu ON t.id_tipo_usuario = tu.id_tipo_usuario
                  $whereClauseUnion
                  GROUP BY tu.nombre_tipo_usuario
                  ORDER BY total DESC";
        $stmt = $conn->prepare($query); $stmt->execute($params);
        $dataUsuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- 5. AFILIACIÓN ---
        $query = "SELECT a.nombre_afiliacion, COUNT(*) as total
                  FROM (
                      SELECT u.id_afiliacion, rl.fecha
                      FROM reservalibro rl JOIN usuario u ON rl.id_usuario = u.id_usuario
                      UNION ALL
                      SELECT u.id_afiliacion, rc.fecha
                      FROM reservacomputadora rc JOIN usuario u ON rc.id_usuario = u.id_usuario
                  ) as t
                  JOIN afiliacion a ON t.id_afiliacion = a.id_afiliacion
                  $whereClauseUnion
                  GROUP BY a.nombre_afiliacion
                  ORDER BY total DESC";
        $stmt = $conn->prepare($query); $stmt->execute($params);
        $dataAfiliacion = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- 6. TOP FACULTADES ---
        // MODIFICADO: Se eliminan las facultades externas (ued) y se usan INNER JOIN 
        // para contar solo facultades internas (UP)
        $query = "SELECT f.nombre_facultad, 
                         COUNT(*) as total
                  FROM (
                      SELECT u.id_usuario, rl.fecha
                      FROM reservalibro rl JOIN usuario u ON rl.id_usuario = u.id_usuario
                      UNION ALL
                      SELECT u.id_usuario, rc.fecha
                      FROM reservacomputadora rc JOIN usuario u ON rc.id_usuario = u.id_usuario
                  ) as t
                  JOIN usuario u ON t.id_usuario = u.id_usuario
                  INNER JOIN usuario_interno_detalle uid ON u.id_usuario = uid.id_usuario
                  INNER JOIN facultad f ON uid.id_facultad = f.id_facultad
                  -- Se eliminó: LEFT JOIN usuario_externo_detalle ued ON u.id_usuario = ued.id_usuario
                  $whereClauseUnion
                  GROUP BY f.nombre_facultad
                  ORDER BY total DESC LIMIT 5";
        $stmt = $conn->prepare($query); $stmt->execute($params);
        $dataFacultades = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- 7. TURNOS ---
        $query = "SELECT t.nombre_turno, COUNT(*) as total
                  FROM reservacomputadora rc
                  JOIN turno t ON rc.id_turno = t.id_turno
                  $whereClauseRC
                  GROUP BY t.nombre_turno
                  ORDER BY total DESC";
        $stmt = $conn->prepare($query); $stmt->execute($params);
        $dataTurnos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // --- 7b. TURNOS (LIBROS) ---
        $query = "SELECT t.nombre_turno, COUNT(*) as total
                  FROM reservalibro rl
                  JOIN turno t ON rl.id_turno = t.id_turno
                  $whereClauseRL
                  GROUP BY t.nombre_turno
                  ORDER BY total DESC";
        $stmt = $conn->prepare($query); $stmt->execute($params);
        $dataTurnosLibros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- 8. TIPOS DE USO (PC) ---
        $query = "SELECT tu.nombre_tipo_uso, COUNT(*) as total
                  FROM reservacomputadora rc
                  JOIN tipouso tu ON rc.id_tipo_uso = tu.id_tipo_uso
                  $whereClauseRC
                  GROUP BY tu.nombre_tipo_uso
                  ORDER BY total DESC";
        $stmt = $conn->prepare($query); $stmt->execute($params);
        $dataTiposUso = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- 9. TIPOS DE RESERVA (LIBRO) ---
        $query = "SELECT tr.nombre_tipo_reserva, COUNT(*) as total
                  FROM reservalibro rl
                  JOIN tiporeserva tr ON rl.id_tipo_reserva = tr.id_tipo_reserva
                  $whereClauseRL
                  GROUP BY tr.nombre_tipo_reserva
                  ORDER BY total DESC";
        $stmt = $conn->prepare($query); $stmt->execute($params);
        $dataTiposReserva = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- 10. CATEGORÍAS (LIBRO) ---
        $query = "SELECT COALESCE(c.nombre_categoria, 'Sin categoría') as nombre_categoria, COUNT(*) as total
                  FROM reservalibro rl
                  JOIN libro l ON rl.id_libro = l.id_libro
                  LEFT JOIN categoria c ON l.id_categoria = c.id_categoria
                  $whereClauseRL
                  GROUP BY c.id_categoria, c.nombre_categoria
                  ORDER BY total DESC LIMIT 8";
        $stmt = $conn->prepare($query); $stmt->execute($params);
        $dataCategorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- 11. ORIGEN (CLIENTE vs ADMIN) ---
        $whereLibrosCliente = $whereClauseGeneral . (empty($whereClauseGeneral) ? "WHERE origen = 'cliente'" : " AND origen = 'cliente'");
        $whereLibrosAdmin = $whereClauseGeneral . (empty($whereClauseGeneral) ? "WHERE origen = 'admin'" : " AND origen = 'admin'");
        $wherePcsCliente = $whereClauseGeneral . (empty($whereClauseGeneral) ? "WHERE origen = 'cliente'" : " AND origen = 'cliente'");
        $wherePcsAdmin = $whereClauseGeneral . (empty($whereClauseGeneral) ? "WHERE origen = 'admin'" : " AND origen = 'admin'");

        $queryLibrosCliente = "SELECT COUNT(*) as total FROM reservalibro $whereLibrosCliente";
        $stmtLibrosCliente = $conn->prepare($queryLibrosCliente); $stmtLibrosCliente->execute($params);
        $librosCliente = $stmtLibrosCliente->fetch(PDO::FETCH_ASSOC)['total'];

        $queryLibrosAdmin = "SELECT COUNT(*) as total FROM reservalibro $whereLibrosAdmin";
        $stmtLibrosAdmin = $conn->prepare($queryLibrosAdmin); $stmtLibrosAdmin->execute($params);
        $librosAdmin = $stmtLibrosAdmin->fetch(PDO::FETCH_ASSOC)['total'];

        $queryPcsCliente = "SELECT COUNT(*) as total FROM reservacomputadora $wherePcsCliente";
        $stmtPcsCliente = $conn->prepare($queryPcsCliente); $stmtPcsCliente->execute($params);
        $pcsCliente = $stmtPcsCliente->fetch(PDO::FETCH_ASSOC)['total'];

        $queryPcsAdmin = "SELECT COUNT(*) as total FROM reservacomputadora $wherePcsAdmin";
        $stmtPcsAdmin = $conn->prepare($queryPcsAdmin); $stmtPcsAdmin->execute($params);
        $pcsAdmin = $stmtPcsAdmin->fetch(PDO::FETCH_ASSOC)['total'];

        $dataOrigen = [
            'libros_cliente' => (int)$librosCliente,
            'libros_admin' => (int)$librosAdmin,
            'pcs_cliente' => (int)$pcsCliente,
            'pcs_admin' => (int)$pcsAdmin
        ];

        // --- COMPILAR RESPUESTA FINAL ---
        echo json_encode([
            'kpis' => $dataKPIs,
            'general' => $dataGeneral,
            'top_libros' => $dataLibros,
            'tipo_usuario' => $dataUsuarios,
            'afiliacion' => $dataAfiliacion,
            'top_facultades' => $dataFacultades,
            'turnos' => $dataTurnos,
            'turnos_libros' => $dataTurnosLibros,
            'tipos_uso' => $dataTiposUso,
            'tipos_reserva' => $dataTiposReserva,
            'categorias' => $dataCategorias,
            'origen' => $dataOrigen
        ]);
        break;
        }
?>
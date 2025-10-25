<?php
session_start();
if (!isset($_SESSION['bibliotecario'])) {
    header('Location: index.php');
    exit;
}

require_once '../config/database.php';
require_once '../includes/functions.php';

$database = new Database();
$conn = $database->getConnection();

// Función para validar cédula (si no existe ya)
if (!function_exists('validarCedulaPanama')) {
    function validarCedulaPanama($cedula) {
        $cedula = trim($cedula);
        $patron = '/^(?:[1-9]|1[0-3]|PE|E|N|[1-9]AV|[1-9]PI)-\d{1,5}-\d{1,6}$/';
        return preg_match($patron, $cedula);
    }
}

// AGREGAR ESTA FUNCIÓN AQUÍ
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

// ============================================
// MANEJO DE PETICIONES AJAX (Modal)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    // Verificar usuario por cédula vía AJAX
    if ($_POST['accion'] === 'verificar_usuario') {
        $cedula = trim($_POST['cedula']);
        if (!validarCedulaPanama($cedula)) {
            echo json_encode(['encontrado' => false, 'message' => 'Formato de cédula inválido.']);
            exit;
        }
        $usuario = verificarUsuario($conn, $cedula);
        if ($usuario) {
            echo json_encode(['encontrado' => true, 'usuario' => $usuario]);
        } else {
            echo json_encode(['encontrado' => false, 'message' => 'Usuario no encontrado. Por favor, complete el registro.']);
        }
        exit;
    }
    
    exit; // Salida para otras posibles acciones AJAX
}

// ============================================
// MANEJO DE RESERVA DE COMPUTADORA (Modal Submit)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tipo_reserva']) && $_POST['tipo_reserva'] === 'computadora') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    // ===== INICIO DE CÓDIGO A AGREGAR =====
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $response['message'] = 'Error de validación de seguridad (CSRF). Recargue la página.';
        echo json_encode($response);
        exit;
    }
    // ===== FIN DE CÓDIGO A AGREGAR =====
    
    $cedula = trim($_POST['cedula']);
    
    if (!validarCedulaPanama($cedula)) {
        $response['message'] = "Formato de cédula inválido. Formatos válidos: 1-1234-12345, PE-1234-12345, E-1234-123456, N-1234-1234, 1AV-1234-12345, 1PI-1234-12345";
    } else {
        $usuario = verificarUsuario($conn, $cedula);
        
        // Si el usuario no existe, se crea uno nuevo
        if (!$usuario) {
			
			$idTipoUsuario = null;
if (!empty($_POST['tipo_usuario'])) {
    $idTipoUsuario = $_POST['tipo_usuario'];
} elseif ($_POST['afiliacion'] == 3) {
    // Si es Particular y no se especificó tipo, asignar Visitante (id 4)
    $idTipoUsuario = 4;
}
			
            $userData = [
                    'nombre'        => normalizarNombre($_POST['nombre']),
                    'apellido'      => normalizarNombre($_POST['apellido']),
                    'cedula'        => $cedula,
                    'id_afiliacion' => $_POST['afiliacion'],
                    'id_tipo_usuario' => $idTipoUsuario,
                    'id_facultad'   => !empty($_POST['facultad']) ? $_POST['facultad'] : null,
                    'id_carrera'    => !empty($_POST['carrera']) ? $_POST['carrera'] : null,
                    'celular'       => !empty($_POST['celular']) ? $_POST['celular'] : null,
                    'universidad_externa' => !empty($_POST['universidad_externa']) ? $_POST['universidad_externa'] : null,
                    'fecha_registro'=> date('Y-m-d H:i:s')
                ];

            if (crearUsuario($conn, $userData)) {
                $usuario = verificarUsuario($conn, $cedula);
            } else {
                $response['message'] = "Error al registrar el nuevo usuario.";
            }
        }

        if ($usuario && empty($response['message'])) {
            $stmtDisp = $conn->prepare("SELECT disponible FROM computadora WHERE id_computadora = :id_computadora");
            $stmtDisp->execute([':id_computadora' => $_POST['computadora']]);
            $compInfo = $stmtDisp->fetch(PDO::FETCH_ASSOC);

            if (!$compInfo || !$compInfo['disponible']) {
                $response['message'] = "La computadora seleccionada ya no está disponible. Por favor, recargue la lista.";
            } else {
                $reservaData = [
                    'usuario'       => $usuario['id_usuario'],
                    'computadora'   => $_POST['computadora'],
                    'fecha'         => date('Y-m-d'),
                    'turno'         => determinarTurno(),
                    'tipo_uso'      => $_POST['tipo_uso'],
                    'hora_entrada'  => date('H:i'),
					'origen'        => 'admin'
                ];
                $resultado = reservarComputadora($conn, $reservaData);

if ($resultado['success']) {
    $response['success'] = true;
    $response['message'] = "Reserva de computadora realizada exitosamente";
} else {
    $mensajes = [
        'PC_NO_EXISTE' => 'La computadora seleccionada no existe en el sistema.',
        'PC_NO_DISPONIBLE' => 'Lo sentimos, esta computadora acaba de ser reservada por otro usuario. Por favor, seleccione otra computadora.',
        'TIMEOUT' => 'El sistema está ocupado procesando otra reserva de la misma computadora. Por favor, intente nuevamente en unos segundos.',
        'DEADLOCK' => 'Hubo un conflicto al procesar su reserva. Por favor, intente nuevamente.',
        'ERROR_SISTEMA' => 'Error técnico al procesar la reserva. Si el problema persiste, contacte al administrador.',
        'ERROR_DESCONOCIDO' => 'Error inesperado. Por favor, intente nuevamente.'
    ];
    
    $response['message'] = $mensajes[$resultado['code']] ?? 'Error al realizar la reserva de computadora.';
}
            }
        }
    }
    
    echo json_encode($response);
    exit;
}

// ============================================
// MANEJO DE FORMULARIOS TRADICIONALES (Sin cambios)
// ============================================
$mensaje = '';
$error = '';

// Recuperar mensajes flash de la sesión
if (isset($_SESSION['mensaje_flash'])) {
    $mensaje = $_SESSION['mensaje_flash'];
    unset($_SESSION['mensaje_flash']);
}
if (isset($_SESSION['error_flash'])) {
    $error = $_SESSION['error_flash'];
    unset($_SESSION['error_flash']);
}

$esAdministrador = isset($_SESSION['bibliotecario']['es_administrador']) && $_SESSION['bibliotecario']['es_administrador'];

// Token CSRF
if (empty($_SESSION['csrf_token'])) { 
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); 
}

function regenerarToken() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// REGISTRAR SALIDA
if (isset($_POST['registrar_salida'], $_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    try {
        $conn->beginTransaction();
        $stmtReserva = $conn->prepare("SELECT id_computadora FROM reservacomputadora WHERE id_reserva_pc = :id");
        $stmtReserva->execute([':id' => $_POST['reserva_id']]);
        $reservaInfo = $stmtReserva->fetch(PDO::FETCH_ASSOC);
        if ($reservaInfo) {
            $conn->prepare("UPDATE reservacomputadora SET hora_salida = NOW() WHERE id_reserva_pc = :id")->execute([':id' => $_POST['reserva_id']]);
            $conn->prepare("UPDATE computadora SET disponible = 1 WHERE id_computadora = :id_computadora")->execute([':id_computadora' => $reservaInfo['id_computadora']]);
            $conn->commit();
            $_SESSION['mensaje_flash'] = "Salida registrada y PC liberada correctamente.";
        } else { 
            $conn->rollback(); 
            $_SESSION['error_flash'] = "No se encontró la reserva especificada."; 
        }
    } catch (Exception $e) { 
        $conn->rollback(); 
        $_SESSION['error_flash'] = "Error al registrar la salida: " . $e->getMessage(); 
    }
    regenerarToken();
    header('Location: reservas_computadoras.php');
    exit;
}

// ELIMINAR RESERVA (Mantenido por si se necesita)
if (isset($_GET['eliminar'], $_GET['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
    try {
        $conn->beginTransaction();
        $stmtReserva = $conn->prepare("SELECT id_computadora FROM reservacomputadora WHERE id_reserva_pc = :id AND hora_salida IS NULL");
        $stmtReserva->execute([':id' => $_GET['eliminar']]);
        $reservaInfo = $stmtReserva->fetch(PDO::FETCH_ASSOC);
        if ($reservaInfo) {
            $conn->prepare("DELETE FROM reservacomputadora WHERE id_reserva_pc = :id")->execute([':id' => $_GET['eliminar']]);
            $conn->prepare("UPDATE computadora SET disponible = 1 WHERE id_computadora = :id_computadora")->execute([':id_computadora' => $reservaInfo['id_computadora']]);
            $conn->commit();
            $_SESSION['mensaje_flash'] = "Reserva eliminada y PC liberada correctamente.";
        } else { 
            $conn->rollBack(); 
            $_SESSION['error_flash'] = "No se encontró la reserva activa para eliminar o ya fue finalizada."; 
        }
    } catch (Exception $e) { 
        $conn->rollBack(); 
        $_SESSION['error_flash'] = "Error al eliminar la reserva: " . $e->getMessage(); 
    }
    regenerarToken();
    header('Location: reservas_computadoras.php');
    exit;
}

// ============================================
// CARGA DE DATOS PARA EL MODAL Y LA PÁGINA
// ============================================
$stmtFacultades = $conn->prepare("SELECT id_facultad, nombre_facultad FROM facultad WHERE activa = 1 ORDER BY nombre_facultad ASC");
$stmtFacultades->execute();
$facultades = $stmtFacultades->fetchAll(PDO::FETCH_ASSOC);
$tiposUso = getTiposUso($conn);

$stmtComputadoras = $conn->prepare("SELECT id_computadora, numero FROM computadora WHERE disponible = 1 ORDER BY numero ASC");
$stmtComputadoras->execute();
$computadoras = $stmtComputadoras->fetchAll(PDO::FETCH_ASSOC);

$stmtAfiliaciones = $conn->prepare("SELECT * FROM afiliacion ORDER BY id_afiliacion");
$stmtAfiliaciones->execute();
$afiliaciones = $stmtAfiliaciones->fetchAll(PDO::FETCH_ASSOC);

$stmtCarreras = $conn->prepare("SELECT c.id_carrera, c.nombre_carrera, fc.id_facultad FROM carrera c INNER JOIN facultadcarrera fc ON c.id_carrera = fc.id_carrera WHERE c.activa = 1 ORDER BY fc.id_facultad, c.nombre_carrera");
$stmtCarreras->execute();
$todasCarreras = $stmtCarreras->fetchAll(PDO::FETCH_ASSOC);

$carrerasPorFacultad = [];
foreach ($todasCarreras as $carrera) {
    $carrerasPorFacultad[$carrera['id_facultad']][] = $carrera;
}

// ---- INICIO DE BLOQUE A AGREGAR ----

$pageTitle = 'Reservas de Computadoras';
$activePage = 'reservas_computadoras'; // Para el menú lateral
$pageStyles = '
<style>
   .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); animation: fadeIn 0.3s ease; }
        .modal.show { display: flex; align-items: center; justify-content: center; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .modal-content { background-color: var(--bg-primary); border-radius: var(--border-radius-lg); padding: var(--spacing-xl); width: 90%; max-width: 700px; max-height: 90vh; overflow-y: auto; position: relative; animation: slideIn 0.3s ease; box-shadow: var(--shadow-lg); }
        @keyframes slideIn { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--spacing-lg); padding-bottom: var(--spacing-md); border-bottom: 1px solid var(--border-color); }
        .modal-header h2 { margin: 0; color: var(--text-primary); font-size: 1.4em; }
        .close-modal { background: none; border: none; font-size: 1.5em; color: var(--text-secondary); cursor: pointer; padding: 5px; border-radius: var(--border-radius); transition: all var(--transition); }
        .close-modal:hover { color: #dc3545; background-color: var(--light-gray); }
        .modal-form { display: flex; flex-direction: column; gap: var(--spacing-lg); }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: var(--spacing-sm); border-radius: var(--border-radius); margin-bottom: var(--spacing-md); }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: var(--spacing-sm); border-radius: var(--border-radius); margin-bottom: var(--spacing-md); }
        .btn-primary { position: relative; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-primary:disabled { background-color: #6c757d; border-color: #6c757d; cursor: not-allowed; opacity: 0.7; }
        .spinner { width: 16px; height: 16px; border: 2px solid transparent; border-top: 2px solid #ffffff; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .hidden-fields, .step-content { display: none; }
        .step-content.active { display: block; animation: fadeIn 0.5s; }
        .step-navigation { display: flex; justify-content: space-between; margin-top: var(--spacing-lg); }
        .step-indicators { display: flex; justify-content: center; gap: 20px; margin-bottom: var(--spacing-lg); border-bottom: 1px solid var(--border-color); padding-bottom: var(--spacing-md); }
        .step-indicator { color: var(--text-secondary); padding: 5px 10px; border-bottom: 3px solid transparent; font-weight: 500; }
        .step-indicator.active { color: var(--primary-color); border-bottom-color: var(--primary-color); font-weight: 700; }
        .step-indicator.completed { color: #28a745; }
        .user-found-info { background-color: var(--light-gray); padding: var(--spacing-md); border-radius: var(--border-radius); border-left: 5px solid var(--primary-color); margin-bottom: var(--spacing-md); }
        .user-found-info p { margin: 0; }
        .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .badge-success { background-color: #28a745; color: white; }
        .badge-warning { background-color: #ffc107; color: #212529; }
        .btn-sm { padding: 4px 8px; font-size: 12px; }
        @media (max-width: 768px) { .modal-content { width: 95%; padding: var(--spacing-lg); } .step-navigation { flex-direction: column; gap: 10px; } .step-navigation .btn { width: 100%; } }
    
	
        /* --- ESTILOS DE NOTIFICACIÓN ADAPTADOS --- */
        .simple-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: var(--bg-primary, #ffffff);
            color: var(--text-primary, #333333);
            padding: 16px 20px;
            box-shadow: var(--shadow-lg, 0 5px 15px rgba(0, 0, 0, 0.1));
            z-index: 99999;
            min-width: 320px;
            border-left: 5px solid #4facfe; /* El color se define en las clases específicas */
            animation: slideInRight 0.4s ease-out;
        }
        .simple-toast strong {
            display: block;
            font-size: 1em;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--text-primary, #333333);
        }
        .simple-toast small {
            font-size: 0.9em;
            opacity: 0.85;
            color: var(--text-secondary, #6c757d);
        }
        @keyframes slideInRight {
            from {
                transform: translateX(110%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        .simple-toast.hide {
            animation: slideOutRight 0.4s ease-in forwards;
        }
        @keyframes slideOutRight {
            to {
                transform: translateX(110%);
                opacity: 0;
            }
        }
</style>
';

// 4. Incluir el HEADER (que solo tiene HTML)
require_once 'templates/header.php'
?>

        <div class="header">
        <div class="container" style="margin-top: 20px;">
        <?php display_flash_message(); ?>
            <h1>Gestión de Reservas de Computadoras</h1></div></div>
        <div class="container">
            <?php if ($mensaje): ?><div class="alert alert-success"><?php echo $mensaje; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
            <div class="card">
                <h2>Generar Reporte PDF</h2>
                <form action="../generar_pdf.php" method="GET" target="_blank">
                    <input type="hidden" name="tipo" value="computadoras">
                    <div class="form-row">
                        <div class="form-group"><label for="fecha_inicio">Fecha de Inicio:</label><input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control"></div>
                        <div class="form-group"><label for="fecha_fin">Fecha de Fin:</label><input type="date" id="fecha_fin" name="fecha_fin" class="form-control"></div>
                    </div>
                    <div class="form-group"><button type="submit" class="btn btn-primary"><i class="fas fa-file-pdf"></i> Generar PDF</button><small style="display: block; margin-top: 5px;">Deje las fechas en blanco para generar un reporte completo.</small></div>
                </form>
            </div>
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>Reservas de Computadoras</h2>
                    <button type="button" class="btn btn-primary" onclick="openModal('modalComputadora')"><i class="fas fa-plus"></i> Nueva Reserva</button>
                </div>
                <div class="form-row">
                    <div class="form-group"><input type="text" id="filtroReserva"  placeholder="Buscar: nombre · cédula" aria-label="Buscar: nombre · cédula" class="form-control"></div>
                    <div class="form-group"><input type="date" id="filtroFecha" class="form-control"></div>
                    <div class="form-group"><select id="filtroEstado" class="form-control"><option value="">Todos los estados</option><option value="activo">En uso</option><option value="finalizado">Finalizado</option></select></div>
                    <div class="form-group"><select id="filtroTipoUso" class="form-control"><option value="">Todos los tipos</option><?php foreach ($tiposUso as $tipo): ?><option value="<?php echo htmlspecialchars($tipo['nombre_tipo_uso']); ?>"><?php echo htmlspecialchars($tipo['nombre_tipo_uso']); ?></option><?php endforeach; ?></select></div>
                </div>
                <div style="overflow-x: auto;">
                    <table class="table" id="tablaReservas">
                        <thead><tr><th>Usuario</th><th>Cédula</th><th>PC #</th><th>Fecha</th><th>Turno</th><th>Tipo Uso</th><th>Entrada</th><th>Salida</th><th>Estado</th></tr></thead>
                        <tbody id="reservas-pc-tbody"><tr><td colspan="9" style="text-align:center;">Cargando...</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- INICIO DEL MODAL DE RESERVA DE COMPUTADORA (REPLICADO DE DASHBOARD.PHP) -->
    <div id="modalComputadora" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Reservar Computadora</h2>
                <button class="close-modal" onclick="closeModal('modalComputadora')">&times;</button>
            </div>
            <div id="alertComputadora"></div>
            <form id="formComputadora" class="modal-form" onsubmit="submitForm(event, 'computadora')">
    <input type="hidden" name="tipo_reserva" value="computadora">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <div class="step-indicators" id="step-indicators-computadora">
                    <div class="step-indicator active" data-step="1">1. Identificación</div>
                    <div class="step-indicator" data-step="2">2. Datos Usuario</div>
                    <div class="step-indicator" data-step="3">3. Reserva</div>
                </div>

                <!-- Paso 1: Identificación -->
                <div id="step-1-computadora" class="step-content active">
                    <div class="form-section">
                        <div class="form-section-title">Identificación del Usuario</div>
                        <div class="form-group"><label for="cedula_computadora">Cédula:</label><input type="text" id="cedula_computadora" name="cedula" class="form-control" placeholder="Ej: 8-123-4567" autocomplete="off" required></div>
                        <button type="button" class="btn btn-primary" onclick="verificarUsuario('computadora')">Verificar Usuario</button>
                    </div>
                </div>

                <!-- Paso 2: Datos del Usuario -->
                <div id="step-2-computadora" class="step-content">
                    <div id="user-info-computadora"></div>
                    <div id="new-user-form-computadora">
                        <div class="form-section">
                            <div class="form-section-title">Datos del Nuevo Usuario</div>
                            <div class="form-row">
                                <div class="form-group"><label for="nombre_computadora">Nombre:</label><input type="text" id="nombre_computadora" name="nombre" class="form-control" autocomplete="off" required></div>
                                <div class="form-group"><label for="apellido_computadora">Apellido:</label><input type="text" id="apellido_computadora" name="apellido" class="form-control" autocomplete="off" required></div>
                            </div>
                        </div>
                        <hr class="form-separator">
                        <div class="form-section">
                            <div class="form-section-title">Afiliación</div>
                            <div class="form-group"><label for="afiliacion_computadora">Seleccione la afiliación:</label><select id="afiliacion_computadora" name="afiliacion" class="form-control" required onchange="actualizarFormularioPorAfiliacion('computadora')"><option value="">Seleccionar...</option><?php foreach ($afiliaciones as $afiliacion): ?><option value="<?php echo $afiliacion['id_afiliacion']; ?>"><?php echo htmlspecialchars($afiliacion['nombre_afiliacion']); ?></option><?php endforeach; ?></select></div>
                            <div id="campos_up_computadora" class="hidden-fields"><div class="form-row"><div class="form-group"><label for="tipo_usuario_computadora">Rol en la Universidad:</label><select id="tipo_usuario_computadora" name="tipo_usuario" class="form-control" onchange="actualizarCamposUP('computadora')"><option value="1">Estudiante</option><option value="2">Profesor</option><option value="3">Administrativo</option></select></div><div class="form-group" id="campo-facultad-computadora"><label for="facultad_computadora">Facultad:</label><select id="facultad_computadora" name="facultad" class="form-control" onchange="cargarCarreras('computadora')"><option value="">Seleccionar facultad</option><?php foreach ($facultades as $facultad): ?><option value="<?php echo $facultad['id_facultad']; ?>"><?php echo htmlspecialchars($facultad['nombre_facultad']); ?></option><?php endforeach; ?></select></div><div class="form-group" id="campo-carrera-computadora"><label for="carrera_computadora">Carrera:</label><select id="carrera_computadora" name="carrera" class="form-control"><option value="">Seleccione una facultad</option></select></div></div></div>
                            <div id="campos_otra_universidad_computadora" class="hidden-fields">
    <div class="form-row">
        <div class="form-group">
            <label for="tipo_usuario_externa_computadora">Tipo de Usuario:</label>
            <select id="tipo_usuario_externa_computadora" name="tipo_usuario" class="form-control">
                <option value="1">Estudiante</option>
                <option value="2">Profesor</option>
                <option value="3">Administrativo</option>
            </select>
        </div>
        <div class="form-group">
            <label for="universidad_externa_computadora">Nombre de la Universidad:</label>
            <input type="text" id="universidad_externa_computadora" name="universidad_externa" class="form-control" autocomplete="off">
        </div>
    </div>
</div>
                            <div id="campos_particular_computadora" class="hidden-fields"><div class="form-group"><label for="celular_computadora">Número de Celular:</label><input type="tel" id="celular_computadora" name="celular" class="form-control" autocomplete="off"></div></div>
                        </div>
                    </div>
                </div>

                <!-- Paso 3: Detalles de Reserva -->
                <div id="step-3-computadora" class="step-content">
                    <div class="form-section">
                        <div class="form-section-title">Detalles de la Reserva</div>
                        <div class="form-row">
                            <div class="form-group"><label for="computadora">Computadora:</label><select id="computadora" name="computadora" class="form-control" required><option value="">Seleccionar computadora</option><?php if (empty($computadoras)): ?><option value="" disabled>No hay computadoras disponibles</option><?php else: ?><?php foreach ($computadoras as $pc): ?><option value="<?php echo $pc['id_computadora']; ?>">Computadora <?php echo $pc['numero']; ?></option><?php endforeach; ?><?php endif; ?></select></div>
                            <div class="form-group"><label for="tipo_uso">Tipo de Uso:</label><select id="tipo_uso" name="tipo_uso" class="form-control" required><option value="">Seleccionar tipo de uso</option><?php foreach ($tiposUso as $tipo): ?><option value="<?php echo $tipo['id_tipo_uso']; ?>"><?php echo htmlspecialchars($tipo['nombre_tipo_uso']); ?></option><?php endforeach; ?></select></div>
                        </div>
                    </div>
                </div>

                <div class="step-navigation" id="nav-computadora">
                    <button type="button" class="btn btn-secondary" id="btn-prev-computadora" onclick="pasoAnterior('computadora')" style="display: none;">Anterior</button>
                    <button type="button" class="btn btn-primary" id="btn-next-computadora" onclick="siguientePaso('computadora')" style="display: none;">Siguiente</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitComputadora" style="display: none;"><span class="btn-text">Realizar Reserva</span></button>
                </div>
            </form>
        </div>
    </div>
    <!-- FIN DEL MODAL DE RESERVA DE COMPUTADORA -->

<script>
    const carrerasPorFacultad = <?php echo json_encode($carrerasPorFacultad); ?>;
    let requestInProgress = false;
    let currentStep = { computadora: 1 };
    window.usuarioEncontrado = { computadora: null };

    // --- Funciones para el Asistente del Modal (Wizard) ---
    
    function openModal(modalId) { 
        const modal = document.getElementById(modalId); 
        modal.classList.add('show'); 
        document.body.style.overflow = 'hidden'; 
        const tipo = modalId.replace('modal', '').toLowerCase();
        resetWizard(tipo);
    }

    function closeModal(modalId) { 
        const modal = document.getElementById(modalId); 
        modal.classList.remove('show'); 
        document.body.style.overflow = '';
        const tipo = modalId.replace('modal', '').toLowerCase();
        resetWizard(tipo);
    }
    
    function resetWizard(tipo) {
        const form = document.getElementById(`form${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`);
        form.reset();
        clearAlert(tipo);
        currentStep[tipo] = 1;
        mostrarPaso(1, tipo);
        requestInProgress = false;
        
        const cedulaInput = document.getElementById(`cedula_${tipo}`);
        cedulaInput.readOnly = false;
        cedulaInput.disabled = false;
        
        document.getElementById(`user-info-${tipo}`).innerHTML = '';
        document.getElementById(`new-user-form-${tipo}`).style.display = 'block';
        
        if (window.usuarioEncontrado) {
            window.usuarioEncontrado[tipo] = null;
        }
        
        document.getElementById(`campos_up_${tipo}`).style.display = 'none';
        document.getElementById(`campos_otra_universidad_${tipo}`).style.display = 'none';
        document.getElementById(`campos_particular_${tipo}`).style.display = 'none';
    }

    async function verificarUsuario(tipo) {
        const cedulaInput = document.getElementById(`cedula_${tipo}`);
        const cedula = cedulaInput.value;
        clearAlert(tipo);

        if (!/^(?:[1-9]|1[0-3]|PE|E|N|[1-9]AV|[1-9]PI)-\d{1,5}-\d{1,6}$/.test(cedula.trim())) {
            showAlert(tipo, 'Formato de cédula inválido.');
            return;
        }

        const formData = new FormData();
        formData.append('accion', 'verificar_usuario');
        formData.append('cedula', cedula);

        try {
            // La petición AJAX ahora apunta a este mismo archivo
            const response = await fetch('reservas_computadoras.php', { method: 'POST', body: formData });
            const result = await response.json();
            
            cedulaInput.readOnly = true;

            if (result.encontrado) {
                const user = result.usuario;
                window.usuarioEncontrado[tipo] = user;
                
                const userInfoDiv = document.getElementById(`user-info-${tipo}`);
                userInfoDiv.innerHTML = `
                    <div class="user-found-info">
                        <p><strong>Usuario Encontrado:</strong> ${user.nombre_completo}</p>
                        <p><strong>Cédula:</strong> ${user.cedula}</p>
                        <p><strong>Afiliación:</strong> ${user.nombre_afiliacion || 'No especificada'}</p>
                        ${user.nombre_facultad ? `<p><strong>Facultad:</strong> ${user.nombre_facultad}</p>` : ''}
                        ${user.nombre_carrera ? `<p><strong>Carrera:</strong> ${user.nombre_carrera}</p>` : ''}
                    </div>`;
                
                document.getElementById(`new-user-form-${tipo}`).style.display = 'none';
                
                // Pre-llenar campos ocultos para el submit
                document.getElementById(`nombre_${tipo}`).value = user.nombre || '';
                document.getElementById(`apellido_${tipo}`).value = user.apellido || '';
                document.getElementById(`afiliacion_${tipo}`).value = user.id_afiliacion || '';
                if (user.id_tipo_usuario) document.getElementById(`tipo_usuario_${tipo}`).value = user.id_tipo_usuario;
                if (user.id_facultad) {
                    document.getElementById(`facultad_${tipo}`).value = user.id_facultad;
                    cargarCarreras(tipo);
                }
                if (user.id_carrera) {
                    setTimeout(() => { document.getElementById(`carrera_${tipo}`).value = user.id_carrera; }, 100);
                }
                if (user.celular) document.getElementById(`celular_${tipo}`).value = user.celular;
                if (user.universidad_externa) document.getElementById(`universidad_externa_${tipo}`).value = user.universidad_externa;
                if (user.facultad_externa) document.getElementById(`facultad_externa_${tipo}`).value = user.facultad_externa;
                
                mostrarPaso(3, tipo);
            } else {
                showAlert(tipo, 'Usuario no encontrado. Por favor, complete el formulario de registro.', true);
                document.getElementById(`new-user-form-${tipo}`).style.display = 'block';
                document.getElementById(`user-info-${tipo}`).innerHTML = '';
                window.usuarioEncontrado[tipo] = null;
                mostrarPaso(2, tipo);
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert(tipo, 'Error al verificar el usuario.');
            cedulaInput.readOnly = false;
        }
    }

    function mostrarPaso(paso, tipo) {
        currentStep[tipo] = paso;
        document.querySelectorAll(`#modal${tipo.charAt(0).toUpperCase() + tipo.slice(1)} .step-content`).forEach(el => el.classList.remove('active'));
        document.getElementById(`step-${paso}-${tipo}`).classList.add('active');

        const indicators = document.querySelectorAll(`#step-indicators-${tipo} .step-indicator`);
        indicators.forEach((ind, index) => {
            ind.classList.remove('active', 'completed');
            if (index < paso - 1) ind.classList.add('completed');
            if (index === paso - 1) ind.classList.add('active');
        });

        const btnPrev = document.getElementById(`btn-prev-${tipo}`);
        const btnNext = document.getElementById(`btn-next-${tipo}`);
        const btnSubmit = document.getElementById(`btnSubmit${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`);
        const btnVerify = document.querySelector(`#step-1-${tipo} button`);

        btnVerify.style.display = (paso === 1) ? 'block' : 'none';
        btnPrev.style.display = (paso > 1) ? 'inline-block' : 'none';
        btnNext.style.display = (paso === 2) ? 'inline-block' : 'none';
        btnSubmit.style.display = (paso === 3) ? 'inline-block' : 'none';
    }

    function siguientePaso(tipo) {
        if (currentStep[tipo] < 3) {
            if (currentStep[tipo] === 2 && document.getElementById(`new-user-form-${tipo}`).style.display !== 'none') {
                const form = document.getElementById(`form${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`);
                let isValid = true;
                const fields = form.querySelectorAll(`#new-user-form-${tipo} [required]`);
                fields.forEach(field => {
                    if (field.offsetParent !== null && !field.checkValidity()) {
                        isValid = false;
                        field.reportValidity();
                    }
                });
                if (!isValid) return;
            }
            mostrarPaso(currentStep[tipo] + 1, tipo);
        }
    }

   function pasoAnterior(tipo) {
    if (currentStep[tipo] > 1) {
        // Si regresamos al paso 1, reseteamos para permitir nueva verificación
        if (currentStep[tipo] === 2) {
            const cedulaInput = document.getElementById(`cedula_${tipo}`);
            cedulaInput.readOnly = false;
            // CAMBIO INICIA: Asegurarse que el campo no esté deshabilitado
            cedulaInput.disabled = false;
            // CAMBIO TERMINA
            
            document.getElementById(`user-info-${tipo}`).innerHTML = '';
            document.getElementById(`new-user-form-${tipo}`).style.display = 'block';
        }
        
        // Si regresamos del paso 3 al 2 y hay un usuario encontrado
        if (currentStep[tipo] === 3 && window.usuarioEncontrado[tipo]) {
            const user = window.usuarioEncontrado[tipo];
            const userInfoDiv = document.getElementById(`user-info-${tipo}`);
            
            // Mostrar información del usuario nuevamente
            userInfoDiv.innerHTML = `
                <div class="user-found-info">
                    <p><strong>Usuario Encontrado:</strong> ${user.nombre_completo}</p>
                    <p><strong>Cédula:</strong> ${user.cedula}</p>
                    <p><strong>Afiliación:</strong> ${user.nombre_afiliacion || 'No especificada'}</p>
                    ${user.nombre_facultad ? `<p><strong>Facultad:</strong> ${user.nombre_facultad}</p>` : ''}
                    ${user.nombre_carrera ? `<p><strong>Carrera:</strong> ${user.nombre_carrera}</p>` : ''}
                </div>`;
            
            document.getElementById(`new-user-form-${tipo}`).style.display = 'none';
            mostrarPaso(2, tipo);
        } else {
            mostrarPaso(currentStep[tipo] - 1, tipo);
        }
    }
}
    function actualizarFormularioPorAfiliacion(tipo) {
    const afiSelect = document.getElementById(`afiliacion_${tipo}`);
    const afiId = afiSelect.value;
    document.getElementById(`campos_up_${tipo}`).style.display = 'none';
    document.getElementById(`campos_otra_universidad_${tipo}`).style.display = 'none';
    document.getElementById(`campos_particular_${tipo}`).style.display = 'none';
    document.querySelectorAll(`#campos_up_${tipo} select, #campos_up_${tipo} input`).forEach(el => el.required = false);
    document.querySelectorAll(`#campos_otra_universidad_${tipo} select, #campos_otra_universidad_${tipo} input`).forEach(el => el.required = false);
    document.querySelectorAll(`#campos_particular_${tipo} input`).forEach(el => el.required = false);

    if (afiId === '1') { // Universidad de Panamá
        const container = document.getElementById(`campos_up_${tipo}`);
        container.style.display = 'block';
        document.getElementById(`tipo_usuario_${tipo}`).required = true;
        actualizarCamposUP(tipo); 
    } else if (afiId === '2') { // Otra Universidad
        const container = document.getElementById(`campos_otra_universidad_${tipo}`);
        container.style.display = 'block';
        document.getElementById(`tipo_usuario_externa_${tipo}`).required = true;
        document.getElementById(`universidad_externa_${tipo}`).required = true;
    } else if (afiId === '3') { // Particular
        const container = document.getElementById(`campos_particular_${tipo}`);
        container.style.display = 'block';
        document.getElementById(`celular_${tipo}`).required = true;
    }
}

    // Buscar esta función en reservas_computadoras.php (alrededor de la línea 280)
// y reemplazarla con esta versión:

function actualizarCamposUP(tipo) {
    const rol = document.getElementById(`tipo_usuario_${tipo}`).value;
    const facultadField = document.getElementById(`campo-facultad-${tipo}`);
    const carreraField = document.getElementById(`campo-carrera-${tipo}`);
    const facultadSelect = document.getElementById(`facultad_${tipo}`);
    const carreraSelect = document.getElementById(`carrera_${tipo}`);

    facultadField.style.display = (rol === '1' || rol === '2') ? 'block' : 'none';
    facultadSelect.required = (rol === '1' || rol === '2');
    if (rol !== '1' && rol !== '2') {
        facultadSelect.value = '';
        facultadSelect.removeAttribute('required');
    }

    carreraField.style.display = (rol === '1') ? 'block' : 'none';
    carreraSelect.required = (rol === '1');
    if (rol !== '1') {
        carreraSelect.value = '';
        carreraSelect.removeAttribute('required');
    }
    
    cargarCarreras(tipo);
}
    function cargarCarreras(tipo) {
        const facultadId = document.getElementById(`facultad_${tipo}`).value; 
        const carreraSelect = document.getElementById(`carrera_${tipo}`);
        carreraSelect.innerHTML = '<option value="">Seleccione una carrera</option>';
        if (facultadId && carrerasPorFacultad[facultadId]) {
            carrerasPorFacultad[facultadId].forEach(carrera => {
                carreraSelect.add(new Option(carrera.nombre_carrera, carrera.id_carrera));
            });
        } else {
             carreraSelect.innerHTML = '<option value="">Seleccione una facultad primero</option>';
        }
    }

    async function submitForm(e, tipo) {
        e.preventDefault();
        if (requestInProgress) return;

        const form = e.target;
        let isValid = true;
        const fields = form.querySelectorAll(`#step-3-${tipo} [required]`);
        fields.forEach(field => {
            if (!field.checkValidity()) {
                isValid = false;
                field.reportValidity();
            }
        });
        if (!isValid) return;

        requestInProgress = true;
        const btn = document.getElementById(`btnSubmit${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`);
        setButtonLoading(btn, true);
        clearAlert(tipo);

        try {
            // La petición AJAX ahora apunta a este mismo archivo
            const response = await fetch('reservas_computadoras.php', { method: 'POST', body: new FormData(form) });
            const result = await response.json();
            if (result.success) {
                showAlert(tipo, result.message, true);
                fetchReservasPC(); // ¡Actualiza la tabla principal!
                setTimeout(() => { 
                    closeModal(`modal${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`);
                }, 1500);
            } else { 
                showAlert(tipo, result.message); 
            }
        } catch (error) { 
            console.error('Error:', error);
            showAlert(tipo, 'Error al procesar la reserva'); 
        } finally { 
            requestInProgress = false; 
            setButtonLoading(btn, false); 
        }
    }
    
    // --- Funciones de Ayuda ---
    function showAlert(tipo, message, isSuccess = false) { document.getElementById(`alert${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`).innerHTML = `<div class="alert ${isSuccess ? 'alert-success' : 'alert-danger'}">${message}</div>`; }
    function clearAlert(tipo) { document.getElementById(`alert${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`).innerHTML = ''; }
    function setButtonLoading(btn, isLoading) {
        const btnText = btn.querySelector('.btn-text');
        if (isLoading) {
            btn.disabled = true;
            if(btnText) btnText.innerHTML = '<span class="spinner"></span>Procesando...';
        } else {
            btn.disabled = false;
            if(btnText) btnText.innerHTML = 'Realizar Reserva';
        }
    }


    // --- Lógica de la Página Principal (Tabla y Filtros) ---
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const mainWrapper = document.getElementById('mainWrapper');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    menuToggle.addEventListener('click', function() { if (window.innerWidth <= 768) { sidebar.classList.toggle('mobile-open'); sidebarOverlay.classList.toggle('active'); } else { sidebar.classList.toggle('collapsed'); mainWrapper.classList.toggle('expanded'); } });
    sidebarOverlay.addEventListener('click', function() { sidebar.classList.remove('mobile-open'); sidebarOverlay.classList.remove('active'); });

    let searchTimeout, currentRequest = null;
    function filtrarReservas() {
        clearTimeout(searchTimeout); if (currentRequest) { currentRequest.abort(); }
        searchTimeout = setTimeout(() => { fetchReservasPC(); }, 300);
    }

    // En el script de reservas_computadoras.php, busca la función renderTablaPC
// y reemplázala con esta versión actualizada:

// Agregar esta función ANTES de renderTablaPC (alrededor de la línea 530)
function formatoAMPM(hora) {
    if (!hora) return '-';
    if (hora.includes('AM') || hora.includes('PM')) return hora;
    const partes = hora.split(':');
    let horas = parseInt(partes[0]);
    const minutos = partes[1];
    const periodo = horas >= 12 ? 'PM' : 'AM';
    horas = horas % 12 || 12;
    return `${horas}:${minutos} ${periodo}`;
}

// Reemplazar la función renderTablaPC completa:
function renderTablaPC(reservas) {
    const tbody = document.getElementById('reservas-pc-tbody');
    if (!reservas || reservas.length === 0) { 
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;">No hay reservas para mostrar.</td></tr>'; 
        return; 
    }
    const csrfToken = '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>';
    let html = '';
    reservas.forEach(reserva => {
        const fecha = new Date(reserva.fecha + 'T00:00:00').toLocaleDateString('es-ES', { 
            day: '2-digit', 
            month: '2-digit', 
            year: 'numeric'
        });
        const horaEntrada = formatoAMPM(reserva.hora_entrada);
        
        let horaSalidaHtml = `<form method="POST" style="display: inline;"><input type="hidden" name="csrf_token" value="${csrfToken}"><input type="hidden" name="reserva_id" value="${reserva.id_reserva_pc}"><button type="submit" name="registrar_salida" class="btn btn-success btn-sm">Registrar</button></form>`;
        if (reserva.hora_salida) { 
            horaSalidaHtml = formatoAMPM(reserva.hora_salida);
        }
        
        const estado = reserva.hora_salida ? 'Finalizado' : 'En uso';
        const estadoBadge = estado === 'Finalizado' ? 'badge-success' : 'badge-warning';
        html += `<tr><td>${reserva.usuario_nombre}</td><td>${reserva.cedula}</td><td>${reserva.computadora_numero}</td><td>${fecha}</td><td>${reserva.nombre_turno}</td><td>${reserva.nombre_tipo_uso}</td><td>${horaEntrada}</td><td>${horaSalidaHtml}</td><td><span class="badge ${estadoBadge}">${estado}</span></td></tr>`;
    });
    tbody.innerHTML = html;
}

    async function fetchReservasPC() {
        try {
            const params = new URLSearchParams({ 
                action: 'get_reservas_computadoras', 
                search: document.getElementById('filtroReserva').value, 
                fecha: document.getElementById('filtroFecha').value, 
                estado: document.getElementById('filtroEstado').value, 
                tipo_uso: document.getElementById('filtroTipoUso').value 
            });
            const hayFiltros = params.get('search') || params.get('fecha') || params.get('estado') || params.get('tipo_uso');
            params.append('limit', hayFiltros ? 0 : 10);
            const controller = new AbortController(); currentRequest = controller;
            const response = await fetch(`api.php?${params.toString()}`, { signal: controller.signal });
            if (!response.ok) throw new Error('Error en la API');
            const data = await response.json(); if (data.error) throw new Error(data.error);
            renderTablaPC(data);
            if (!hayFiltros && data.length === 10) { 
                document.getElementById('reservas-pc-tbody').innerHTML += '<tr><td colspan="9" style="text-align:center; background-color: #f8f9fa; font-style: italic;">Mostrando las últimas 10 reservas. Use los filtros para más resultados.</td></tr>'; 
            }
            currentRequest = null;
        } catch (error) { 
            if (error.name !== 'AbortError') { 
                console.error('Error:', error); 
                document.getElementById('reservas-pc-tbody').innerHTML = `<tr><td colspan="9" style="text-align:center; color: red;">Error al cargar datos.</td></tr>`; 
            } 
        }
    }

    document.addEventListener('DOMContentLoaded', () => { 
        fetchReservasPC(); 
        ['filtroReserva', 'filtroFecha', 'filtroEstado', 'filtroTipoUso'].forEach(id => { 
            const element = document.getElementById(id);
            if(element) {
                element.addEventListener(id === 'filtroReserva' ? 'input' : 'change', filtrarReservas);
            }
        }); 
        
        // Auto-ocultar alertas de la página principal
        const alerts = document.querySelectorAll('.alert-success, .alert-danger');
        alerts.forEach(alert => {
            if (alert.closest('.container')) { 
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }, 3000); // Aumentado a 5 segundos para mejor visibilidad
            }
        });
    });
	
</script>

<?php
// ---- AGREGAR ESTO ----
require_once 'templates/footer.php';
?>
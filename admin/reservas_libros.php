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

// Función para validar cédula
function validarCedulaPanama($cedula) {
    $cedula = trim($cedula);
    $patron = '/^(?:[1-9]|1[0-3]|PE|E|N|[1-9]AV|[1-9]PI)-\d{1,5}-\d{1,6}$/';
    return preg_match($patron, $cedula);
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
    
    exit; // Por si acaso hay otras acciones AJAX
}

// ============================================
// MANEJO DE RESERVA DE LIBRO (Modal Submit)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tipo_reserva']) && $_POST['tipo_reserva'] === 'libro') {
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
			
			// Determinar id_tipo_usuario según la afiliación
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
            $reservaData = [
                'libro'         => $_POST['libro'],
                'usuario'       => $usuario['id_usuario'],
                'fecha'         => date('Y-m-d'),
                'tipo_reserva'  => $_POST['tipo_reserva_libro'],
                'id_turno'      => determinarTurno(), // <-- AÑADIR ESTA LÍNEA
				'origen'        => 'admin'
            ];
            
           // ✅ NUEVO: Capturar resultado con código de error
    $resultado = reservarLibro($conn, $reservaData);
    
    if ($resultado['success']) {
        // Ya no necesitas el UPDATE aquí, la función lo hace
        $response['success'] = true;
        $response['message'] = "Reserva de libro realizada exitosamente";
    } else {
        // ✅ Mensajes específicos según el código de error
        $mensajes = [
            'LIBRO_NO_EXISTE' => 'El libro seleccionado no existe en el sistema.',
            'LIBRO_NO_DISPONIBLE' => 'Lo sentimos, este libro acaba de ser reservado por otro usuario. Por favor, seleccione otro libro.',
            'TIMEOUT' => 'El sistema está ocupado procesando otra reserva del mismo libro. Por favor, intente nuevamente en unos segundos.',
            'DEADLOCK' => 'Hubo un conflicto al procesar su reserva. Por favor, intente nuevamente.',
            'ERROR_SISTEMA' => 'Error técnico al procesar la reserva. Si el problema persiste, contacte al administrador.',
            'ERROR_DESCONOCIDO' => 'Error inesperado. Por favor, intente nuevamente.'
        ];
        
        $response['message'] = $mensajes[$resultado['code']] ?? 'Error al realizar la reserva de libro.';
    }
        }
    }
    
    echo json_encode($response);
    exit;
}

// ============================================
// MANEJO DE FORMULARIOS TRADICIONALES
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

// REGISTRAR ENTREGA
if (isset($_POST['registrar_entrega'], $_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $query = "UPDATE reservalibro SET hora_entrega = NOW() WHERE id_reserva_libro = :id";
    $stmt = $conn->prepare($query);
    if ($stmt->execute([':id' => $_POST['reserva_id']])) {
        $_SESSION['mensaje_flash'] = "Entrega registrada correctamente.";
    } else {
        $_SESSION['error_flash'] = "Error al registrar la entrega.";
    }
    regenerarToken();
    header('Location: reservas_libros.php');
    exit;
}

// REGISTRAR DEVOLUCIÓN
if (isset($_POST['registrar_devolucion'], $_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    try {
        $conn->beginTransaction();
        $queryReserva = "SELECT rl.id_libro, rl.fecha, rl.id_tipo_reserva, tr.nombre_tipo_reserva FROM reservalibro rl JOIN tiporeserva tr ON rl.id_tipo_reserva = tr.id_tipo_reserva WHERE rl.id_reserva_libro = :id";
        $stmtReserva = $conn->prepare($queryReserva);
        $stmtReserva->execute([':id' => $_POST['reserva_id']]);
        $reservaInfo = $stmtReserva->fetch(PDO::FETCH_ASSOC);
        if ($reservaInfo) {
            $queryUpdate = "UPDATE reservalibro SET fecha_hora_devolucion = NOW() WHERE id_reserva_libro = :id";
            $stmtUpdate = $conn->prepare($queryUpdate);
            $stmtUpdate->execute([':id' => $_POST['reserva_id']]);
            $queryLiberar = "UPDATE libro SET disponible = 1 WHERE id_libro = :id_libro";
            $stmtLiberar = $conn->prepare($queryLiberar);
            $stmtLiberar->execute([':id_libro' => $reservaInfo['id_libro']]);
            $conn->commit();
            $_SESSION['mensaje_flash'] = "Devolución registrada y libro liberado correctamente.";
        } else {
            $conn->rollBack();
            $_SESSION['error_flash'] = "No se encontró la reserva especificada.";
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error_flash'] = "Error al registrar la devolución: " . $e->getMessage();
    }
    regenerarToken();
    header('Location: reservas_libros.php');
    exit;
}

// ELIMINAR RESERVA
if (isset($_GET['eliminar'], $_GET['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
    $query = "DELETE FROM reservalibro WHERE id_reserva_libro = :id";
    $stmt = $conn->prepare($query);
    if ($stmt->execute([':id' => $_GET['eliminar']])) {
        $_SESSION['mensaje_flash'] = "Reserva eliminada correctamente.";
    } else {
        $_SESSION['error_flash'] = "Error al eliminar la reserva.";
    }
    regenerarToken();
    header('Location: reservas_libros.php');
    exit;
}

// REGISTRAR ENTREGA
if (isset($_POST['registrar_entrega'], $_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    $query = "UPDATE reservalibro SET hora_entrega = NOW() WHERE id_reserva_libro = :id";
    $stmt = $conn->prepare($query);
    if ($stmt->execute([':id' => $_POST['reserva_id']])) {
        $mensaje = "Entrega registrada correctamente.";
    } else {
        $error = "Error al registrar la entrega.";
    }
    regenerarToken();
    // Redireccionar para evitar reenvío de formulario
    header('Location: reservas_libros.php');
    exit;
}

// REGISTRAR DEVOLUCIÓN
if (isset($_POST['registrar_devolucion'], $_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    try {
        $conn->beginTransaction();
        $queryReserva = "SELECT rl.id_libro, rl.fecha, rl.id_tipo_reserva, tr.nombre_tipo_reserva FROM reservalibro rl JOIN tiporeserva tr ON rl.id_tipo_reserva = tr.id_tipo_reserva WHERE rl.id_reserva_libro = :id";
        $stmtReserva = $conn->prepare($queryReserva);
        $stmtReserva->execute([':id' => $_POST['reserva_id']]);
        $reservaInfo = $stmtReserva->fetch(PDO::FETCH_ASSOC);
        if ($reservaInfo) {
            $queryUpdate = "UPDATE reservalibro SET fecha_hora_devolucion = NOW() WHERE id_reserva_libro = :id";
            $stmtUpdate = $conn->prepare($queryUpdate);
            $stmtUpdate->execute([':id' => $_POST['reserva_id']]);
            $queryLiberar = "UPDATE libro SET disponible = 1 WHERE id_libro = :id_libro";
            $stmtLiberar = $conn->prepare($queryLiberar);
            $stmtLiberar->execute([':id_libro' => $reservaInfo['id_libro']]);
            $conn->commit();
            $mensaje = "Devolución registrada y libro liberado correctamente.";
        } else {
            $conn->rollBack();
            $error = "No se encontró la reserva especificada.";
        }
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Error al registrar la devolución: " . $e->getMessage();
    }
    regenerarToken();
    // Redireccionar para evitar reenvío de formulario
    header('Location: reservas_libros.php');
    exit;
}

// ELIMINAR RESERVA
if (isset($_GET['eliminar'], $_GET['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
    $query = "DELETE FROM reservalibro WHERE id_reserva_libro = :id";
    $stmt = $conn->prepare($query);
    if ($stmt->execute([':id' => $_GET['eliminar']])) {
        $mensaje = "Reserva eliminada correctamente.";
    } else {
        $error = "Error al eliminar la reserva.";
    }
    regenerarToken();
    header('Location: reservas_libros.php');
    exit;
}

// ============================================
// CARGA DE DATOS PARA EL MODAL
// ============================================
$libros = getAllLibros($conn, true);
$stmtFacultades = $conn->prepare("SELECT id_facultad, nombre_facultad FROM facultad WHERE activa = 1 ORDER BY nombre_facultad ASC");
$stmtFacultades->execute();
$facultades = $stmtFacultades->fetchAll(PDO::FETCH_ASSOC);
$tiposReserva = getTiposReserva($conn);

$stmtAfiliaciones = $conn->prepare("SELECT * FROM afiliacion ORDER BY id_afiliacion");
$stmtAfiliaciones->execute();
$afiliaciones = $stmtAfiliaciones->fetchAll(PDO::FETCH_ASSOC);

$stmtCategorias = $conn->prepare("SELECT id_categoria, nombre_categoria FROM categoria ORDER BY nombre_categoria ASC");
$stmtCategorias->execute();
$categorias = $stmtCategorias->fetchAll(PDO::FETCH_ASSOC);

$stmtCarreras = $conn->prepare("SELECT c.id_carrera, c.nombre_carrera, fc.id_facultad FROM carrera c INNER JOIN facultadcarrera fc ON c.id_carrera = fc.id_carrera WHERE c.activa = 1 ORDER BY fc.id_facultad, c.nombre_carrera");
$stmtCarreras->execute();
$todasCarreras = $stmtCarreras->fetchAll(PDO::FETCH_ASSOC);

$carrerasPorFacultad = [];
foreach ($todasCarreras as $carrera) {
    $carrerasPorFacultad[$carrera['id_facultad']][] = $carrera;
}

// ---- INICIO DE BLOQUE A AGREGAR ----

$pageTitle = 'Reservas de Libros';
$activePage = 'reservas_libros'; // Para el menú lateral
$pageStyles = '';

// 4. Incluir el HEADER (que solo tiene HTML)
require_once 'templates/header.php';
?>
        <div class="header">
            <div class="container" style="margin-top: 20px;">
        <?php display_flash_message(); ?>
                <h1>Gestión de Reservas de Libros</h1></div>
        </div>

        <div class="container">
            <?php if ($mensaje): ?><div class="alert alert-success"><?php echo $mensaje; ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

            <div class="card">
                <h2>Generar Reporte PDF</h2>
                <form action="../generar_pdf.php" method="GET" target="_blank">
                    <input type="hidden" name="tipo" value="libros">
                    <div class="form-row">
                        <div class="form-group"><label for="fecha_inicio_libros">Fecha de Inicio:</label><input type="date" id="fecha_inicio_libros" name="fecha_inicio" class="form-control"></div>
                        <div class="form-group"><label for="fecha_fin_libros">Fecha de Fin:</label><input type="date" id="fecha_fin_libros" name="fecha_fin" class="form-control"></div>
                    </div>
                    <div class="form-group"><button type="submit" class="btn btn-primary"><i class="fas fa-file-pdf"></i> Generar PDF</button><small style="display: block; margin-top: 5px;">Deje las fechas en blanco para generar un reporte completo.</small></div>
                </form>
            </div>

            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2>Reservas de Libros</h2>
                    <button type="button" class="btn btn-primary" onclick="openModal('modalLibro')">
                        <i class="fas fa-plus"></i> Nueva Reserva
                    </button>
                </div>
                
                <div class="form-row">
                    <div class="form-group"><input type="text" id="filtroReserva" placeholder="Buscar: nombre · cédula · título · autor · código" aria-label="Buscar: nombre · cédula · título · autor · código" class="form-control" onkeyup="filtrarReservas()"></div>
                    <div class="form-group"><input type="date" id="filtroFecha" class="form-control" onchange="filtrarReservas()"></div>
                    <div class="form-group">
                        <select id="filtroEstado" class="form-control" onchange="filtrarReservas()">
                            <option value="">Todos los estados</option><option value="Pendiente">Pendiente</option><option value="Devuelto">Devuelto</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <select id="filtroTipo" class="form-control" onchange="filtrarReservas()">
                            <option value="">Todos los tipos de uso</option><option value="Préstamo Externo">Préstamo Externo</option><option value="Consulta en Sala">Consulta en Sala</option>
                        </select>
                    </div>
                </div>

                <div style="overflow-x: auto;">
                    <table class="table" id="tablaReservas">
                        <thead>
                            <tr><th>Usuario</th><th>Cédula</th><th>Libro (Código)</th><th>Autor</th><th>Fecha</th><th>Tipo</th><th>Entrega</th><th>Devolución</th><th>Estado</th></tr>
                        </thead>
                       <tbody id="reservas-libros-tbody">
                            <tr><td colspan="10" style="text-align:center;">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- INICIO DEL MODAL DE RESERVA DE LIBRO (REPLICADO DE DASHBOARD.PHP) -->
    <div id="modalLibro" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Reservar Libro</h2>
                <button class="close-modal" onclick="closeModal('modalLibro')">&times;</button>
            </div>
            <div id="alertLibro"></div>
            <form id="formLibro" class="modal-form" onsubmit="submitForm(event, 'libro')">
    <input type="hidden" name="tipo_reserva" value="libro">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="step-indicators" id="step-indicators-libro">
                    <div class="step-indicator active" data-step="1">1. Identificación</div>
                    <div class="step-indicator" data-step="2">2. Datos Usuario</div>
                    <div class="step-indicator" data-step="3">3. Reserva</div>
                </div>

                <!-- Paso 1: Identificación -->
                <div id="step-1-libro" class="step-content active">
                    <div class="form-section">
                        <div class="form-section-title">Identificación del Usuario</div>
                        <div class="form-group"><label for="cedula_libro">Cédula:</label><input type="text" id="cedula_libro" name="cedula" class="form-control" placeholder="Ej: 8-123-4567" autocomplete="off" required></div>
                        <button type="button" class="btn btn-primary" onclick="verificarUsuario('libro')">Verificar Usuario</button>
                    </div>
                </div>

                <!-- Paso 2: Datos del Usuario -->
                <div id="step-2-libro" class="step-content">
                    <div id="user-info-libro"></div>
                    <div id="new-user-form-libro">
                        <div class="form-section">
                            <div class="form-section-title">Datos del Nuevo Usuario</div>
                            <div class="form-row">
                                <div class="form-group"><label for="nombre_libro">Nombre:</label><input type="text" id="nombre_libro" name="nombre" class="form-control" autocomplete="off" required></div>
                                <div class="form-group"><label for="apellido_libro">Apellido:</label><input type="text" id="apellido_libro" name="apellido" class="form-control" autocomplete="off" required></div>
                            </div>
                        </div>
                        <hr class="form-separator">
                        <div class="form-section">
                            <div class="form-section-title">Afiliación</div>
                            <div class="form-group"><label for="afiliacion_libro">Seleccione la afiliación:</label><select id="afiliacion_libro" name="afiliacion" class="form-control" required onchange="actualizarFormularioPorAfiliacion('libro')"><option value="">Seleccionar...</option><?php foreach ($afiliaciones as $afiliacion): ?><option value="<?php echo $afiliacion['id_afiliacion']; ?>"><?php echo htmlspecialchars($afiliacion['nombre_afiliacion']); ?></option><?php endforeach; ?></select></div>
                            <div id="campos_up_libro" class="hidden-fields"><div class="form-row"><div class="form-group"><label for="tipo_usuario_libro">Rol en la Universidad:</label><select id="tipo_usuario_libro" name="tipo_usuario" class="form-control" onchange="actualizarCamposUP('libro')"><option value="1">Estudiante</option><option value="2">Profesor</option><option value="3">Administrativo</option></select></div><div class="form-group" id="campo-facultad-libro"><label for="facultad_libro">Facultad:</label><select id="facultad_libro" name="facultad" class="form-control" onchange="cargarCarreras('libro')"><option value="">Seleccionar facultad</option><?php foreach ($facultades as $facultad): ?><option value="<?php echo $facultad['id_facultad']; ?>"><?php echo htmlspecialchars($facultad['nombre_facultad']); ?></option><?php endforeach; ?></select></div><div class="form-group" id="campo-carrera-libro"><label for="carrera_libro">Carrera:</label><select id="carrera_libro" name="carrera" class="form-control"><option value="">Seleccione una facultad</option></select></div></div></div>
                            <div id="campos_otra_universidad_libro" class="hidden-fields">
    <div class="form-row">
        <div class="form-group">
            <label for="tipo_usuario_externa_libro">Tipo de Usuario:</label>
            <select id="tipo_usuario_externa_libro" name="tipo_usuario" class="form-control">
                <option value="1">Estudiante</option>
                <option value="2">Profesor</option>
                <option value="3">Administrativo</option>
            </select>
        </div>
        <div class="form-group">
            <label for="universidad_externa_libro">Nombre de la Universidad:</label>
            <input type="text" id="universidad_externa_libro" name="universidad_externa" class="form-control" autocomplete="off">
        </div>
    </div>
</div>
                            <div id="campos_particular_libro" class="hidden-fields"><div class="form-group"><label for="celular_libro">Número de Celular:</label><input type="tel" id="celular_libro" name="celular" class="form-control"></div></div>
                        </div>
                    </div>
                </div>

                <!-- Paso 3: Detalles de Reserva -->
                <div id="step-3-libro" class="step-content">
                    <div class="form-section">
                        <div class="form-section-title">Detalles de la Reserva</div>
                        <div class="form-row"><div class="form-group"><label for="filtro-categoria-libro">Filtrar por Categoría:</label><select id="filtro-categoria-libro" class="form-control"><option value="">Todas las categorías</option><?php foreach ($categorias as $categoria): ?><option value="<?php echo $categoria['id_categoria']; ?>"><?php echo htmlspecialchars($categoria['nombre_categoria']); ?></option><?php endforeach; ?></select></div><div class="form-group"><label for="buscar-libro">Buscar por Título/Autor/Código:</label><input type="text" id="buscar-libro" class="form-control" placeholder="Escriba para buscar..." autocomplete="off"></div></div>
                        <div class="form-group"><label for="libro">Seleccionar Libro:</label><select id="libro" name="libro" class="form-control" required><option value="">Seleccione un libro de la lista</option><?php foreach ($libros as $libro): ?><option value="<?php echo $libro['id_libro']; ?>" data-searchtext="<?php echo strtolower(htmlspecialchars($libro['titulo'] . ' ' . $libro['autor'] . ' ' . $libro['codigo_unico'])); ?>" data-categoria-id="<?php echo $libro['id_categoria']; ?>"><?php echo htmlspecialchars($libro['titulo'] . ' - ' . $libro['autor'] . ' (' . $libro['codigo_unico'] . ')'); ?></option><?php endforeach; ?></select></div>
                        <div class="form-group"><label for="tipo_reserva_libro">Tipo de Reserva:</label><select id="tipo_reserva_libro" name="tipo_reserva_libro" class="form-control" required><option value="">Seleccionar tipo</option><?php foreach ($tiposReserva as $tipo): ?><option value="<?php echo $tipo['id_tipo_reserva']; ?>"><?php echo htmlspecialchars($tipo['nombre_tipo_reserva']); ?></option><?php endforeach; ?></select></div>
                    </div>
                </div>
                
                <div class="step-navigation" id="nav-libro">
                    <button type="button" class="btn btn-secondary" id="btn-prev-libro" onclick="pasoAnterior('libro')" style="display: none;">Anterior</button>
                    <button type="button" class="btn btn-primary" id="btn-next-libro" onclick="siguientePaso('libro')" style="display: none;">Siguiente</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitLibro" style="display: none;"><span class="btn-text">Realizar Reserva</span></button>
                </div>
            </form>
        </div>
    </div>
    <!-- FIN DEL MODAL DE RESERVA DE LIBRO -->

    <!-- SCRIPT JAVASCRIPT COMPLETAMENTE ACTUALIZADO -->
    <script>
    const carrerasPorFacultad = <?php echo json_encode($carrerasPorFacultad); ?>;
    let requestInProgress = false;
    let currentStep = { libro: 1 };
    window.usuarioEncontrado = { libro: null };

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

        if(tipo === 'libro') {
            filtrarLibros();
        }
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
            const response = await fetch('reservas_libros.php', { method: 'POST', body: formData });
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

    // Buscar esta función en reservas_libros.php (alrededor de la línea 280)
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
    
    const filtroCategoriaSelect = document.getElementById('filtro-categoria-libro');
    const buscarLibroInput = document.getElementById('buscar-libro');

    function filtrarLibros() {
        const categoriaId = filtroCategoriaSelect.value;
        const terminoBusqueda = buscarLibroInput.value.toLowerCase();
        const libroSelect = document.getElementById('libro');
        const opciones = libroSelect.options;
        for (let i = 1; i < opciones.length; i++) {
            const opcion = opciones[i];
            const categoriaCoincide = (categoriaId === '' || opcion.dataset.categoriaId === categoriaId);
            const textoCoincide = (terminoBusqueda.length < 3 || opcion.dataset.searchtext.includes(terminoBusqueda));
            opcion.style.display = (categoriaCoincide && textoCoincide) ? 'block' : 'none';
        }
    }

    filtroCategoriaSelect.addEventListener('change', filtrarLibros);
    buscarLibroInput.addEventListener('input', filtrarLibros);

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
            const response = await fetch('reservas_libros.php', { method: 'POST', body: new FormData(form) });
            const result = await response.json();
            if (result.success) {
                showAlert(tipo, result.message, true);
                fetchReservasLibros(); // ¡Actualiza la tabla principal!
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


    let searchTimeout, currentRequest = null;
    function filtrarReservas() {
        clearTimeout(searchTimeout); if (currentRequest) { currentRequest.abort(); }
        searchTimeout = setTimeout(() => { fetchReservasLibros(); }, 300);
    }

   // En el script de reservas_libros.php, busca la función renderTablaLibros
// y reemplázala con esta versión actualizada:

// Agregar estas funciones ANTES de renderTablaLibros (alrededor de la línea 555)
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

function formatoFechaHoraAMPM(fechaHora) {
    if (!fechaHora) return '-';
    const fecha = new Date(fechaHora);
    const fechaStr = fecha.toLocaleDateString('es-ES', { 
        day: '2-digit', 
        month: '2-digit', 
        year: 'numeric' 
    });
    let horas = fecha.getHours();
    const minutos = fecha.getMinutes().toString().padStart(2, '0');
    const periodo = horas >= 12 ? 'PM' : 'AM';
    horas = horas % 12 || 12;
    return `${fechaStr}<br>${horas}:${minutos} ${periodo}`;
}

// Reemplazar la función renderTablaLibros completa:
function renderTablaLibros(reservas) {
    const tbody = document.getElementById('reservas-libros-tbody');
    if (!reservas || reservas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;">No hay reservas para mostrar.</td></tr>';
        return;
    }
    const csrfToken = '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>';
    let html = '';
    reservas.forEach(reserva => {
        const estado = reserva.fecha_hora_devolucion ? 'Devuelto' : 'Pendiente';
        const fecha = new Date(reserva.fecha + 'T00:00:00').toLocaleDateString('es-ES', { 
            day: '2-digit', 
            month: '2-digit', 
            year: 'numeric' 
        });
        
        const horaEntregaHtml = reserva.hora_entrega ? 
            formatoAMPM(reserva.hora_entrega) : 
            `<form method="POST" style="display: inline;"><input type="hidden" name="reserva_id" value="${reserva.id_reserva_libro}"><input type="hidden" name="csrf_token" value="${csrfToken}"><button type="submit" name="registrar_entrega" class="btn btn-warning btn-sm">Registrar</button></form>`;

        let horaDevolucionHtml = '-';

        if (reserva.fecha_hora_devolucion) {
            horaDevolucionHtml = formatoFechaHoraAMPM(reserva.fecha_hora_devolucion);
        } else if (reserva.hora_entrega) {
            horaDevolucionHtml = `<form method="POST" style="display: inline;"><input type="hidden" name="reserva_id" value="${reserva.id_reserva_libro}"><input type="hidden" name="csrf_token" value="${csrfToken}"><button type="submit" name="registrar_devolucion" class="btn btn-success btn-sm">Registrar</button></form>`;
        }
        
        const estadoBadge = estado === 'Pendiente' ? 'badge-warning' : 'badge-success';
        html += `<tr><td>${reserva.usuario_nombre}</td><td>${reserva.cedula}</td><td>${reserva.titulo}<br><small>${reserva.codigo_unico}</small></td><td>${reserva.autor}</td><td>${fecha}</td><td>${reserva.nombre_tipo_reserva}</td><td>${horaEntregaHtml}</td><td>${horaDevolucionHtml}</td><td><span class="badge ${estadoBadge}">${estado}</span></td></tr>`;
    });
    tbody.innerHTML = html;
}

    async function fetchReservasLibros() {
        try {
            const params = new URLSearchParams({ action: 'get_reservas_libros', search: document.getElementById('filtroReserva').value, fecha: document.getElementById('filtroFecha').value, estado: document.getElementById('filtroEstado').value, tipo: document.getElementById('filtroTipo').value });
            const hayFiltros = params.get('search') || params.get('fecha') || params.get('estado') || params.get('tipo');
            params.append('limit', hayFiltros ? 0 : 10);
            const controller = new AbortController(); currentRequest = controller;
            const response = await fetch(`api.php?${params.toString()}`, { signal: controller.signal });
            if (!response.ok) throw new Error('Error en la API');
            const data = await response.json(); if (data.error) throw new Error(data.error);
            renderTablaLibros(data);
            if (!hayFiltros && data.length === 10) { document.getElementById('reservas-libros-tbody').innerHTML += '<tr><td colspan="9" style="text-align:center; background-color: #f8f9fa; font-style: italic;">Mostrando las últimas 10 reservas. Use los filtros para más resultados.</td></tr>'; }
            currentRequest = null;
        } catch (error) { if (error.name !== 'AbortError') { console.error('Error:', error); document.getElementById('reservas-libros-tbody').innerHTML = `<tr><td colspan="9" style="text-align:center; color: red;">Error al cargar datos.</td></tr>`; } }
    }
    document.addEventListener('DOMContentLoaded', () => { fetchReservasLibros(); ['filtroReserva', 'filtroFecha', 'filtroEstado', 'filtroTipo'].forEach(id => { document.getElementById(id).addEventListener(id === 'filtroReserva' ? 'input' : 'change', filtrarReservas); }); });
	
	// Auto-ocultar alertas después de 5 segundos
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert-success, .alert-danger');
        alerts.forEach(alert => {
            if (alert.closest('.container')) { // Solo para alertas de la página principal
                setTimeout(() => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 500);
                }, 3000);
            }
        });
    });
	
</script>

<?php
// ---- AGREGAR ESTO ----
require_once 'templates/footer.php';
?>
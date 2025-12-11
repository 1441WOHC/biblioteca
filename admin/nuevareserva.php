<?php
// ---- INICIO DE SECCIÓN CORREGIDA ----
    
// 1. Lógica que ANTES estaba en header.php
session_start();
if (!isset($_SESSION['bibliotecario'])) {
    header('Location: index.php');
    exit;
}
require_once '../config/database.php';
require_once '../includes/functions.php';
$esAdministrador = isset($_SESSION['bibliotecario']['es_administrador']) && $_SESSION['bibliotecario']['es_administrador'];

// 2. Define las variables de tu página (como ya hacías)
$pageTitle = 'Nueva Reserva';
$activePage = 'nuevareserva';
$pageStyles = '';

// 3. Ahora ejecuta la lógica de tu página
$database = new Database(); // <-- ¡Esto ahora SÍ FUNCIONARÁ!
$conn = $database->getConnection();


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

// 4. Tu manejo de POST (API)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     $response = ['success' => false, 'message' => ''];
    header('Content-Type: application/json');

    // ---- INICIO DE VALIDACIÓN CSRF ----
    // Validar token para CUALQUIER petición POST que no sea 'verificar_usuario'
    if (!isset($_POST['accion']) || $_POST['accion'] !== 'verificar_usuario') {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            $response['message'] = 'Error de validación de seguridad (CSRF). Recargue la página e intente de nuevo.';
            echo json_encode($response);
            exit;
        }
    }
    // ---- FIN DE VALIDACIÓN CSRF ----

    // Nueva acción para verificar usuario por cédula vía AJAX
    if (isset($_POST['accion']) && $_POST['accion'] === 'verificar_usuario') {
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
    
    // Lógica de reserva existente
    if (isset($_POST['tipo_reserva'])) {
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
                if ($_POST['tipo_reserva'] === 'libro') {
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
                } elseif ($_POST['tipo_reserva'] === 'computadora') {
        $reservaData = [
            'usuario' => $usuario['id_usuario'],
            'computadora' => $_POST['computadora'],
            'fecha' => date('Y-m-d'),
            'turno' => determinarTurno(),
            'tipo_uso' => $_POST['tipo_uso'],
            'hora_entrada' => date('H:i'),
            'origen' => 'admin'
        ];
        
        // ✅ NUEVO: Capturar resultado con código de error
        $resultado = reservarComputadora($conn, $reservaData);
        
        if ($resultado['success']) {
            $response['success'] = true;
            $response['message'] = "Reserva de computadora realizada exitosamente";
        } else {
            // ✅ Mensajes específicos según el código de error
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
    }
    
    echo json_encode($response);
    exit;
}

// 5. Tu lógica de GET (cargar datos para la página)
$libros = getAllLibros($conn, true);

// MODIFICACIÓN: Se filtra para obtener solo facultades activas.
$stmtFacultades = $conn->prepare("SELECT id_facultad, nombre_facultad FROM facultad WHERE activa = 1 ORDER BY nombre_facultad ASC");
$stmtFacultades->execute();
$facultades = $stmtFacultades->fetchAll(PDO::FETCH_ASSOC);

$tiposReserva = getTiposReserva($conn);
$tiposUso = getTiposUso($conn);

$stmtAfiliaciones = $conn->prepare("SELECT * FROM afiliacion ORDER BY id_afiliacion");
$stmtAfiliaciones->execute();
$afiliaciones = $stmtAfiliaciones->fetchAll(PDO::FETCH_ASSOC);

$stmtCategorias = $conn->prepare("SELECT id_categoria, nombre_categoria FROM categoria ORDER BY nombre_categoria ASC");
$stmtCategorias->execute();
$categorias = $stmtCategorias->fetchAll(PDO::FETCH_ASSOC);

$stmtComputadoras = $conn->prepare("SELECT id_computadora, numero FROM computadora WHERE disponible = 1 ORDER BY numero ASC");
$stmtComputadoras->execute();
$computadoras = $stmtComputadoras->fetchAll(PDO::FETCH_ASSOC);

$stmtCarreras = $conn->prepare("SELECT c.id_carrera, c.nombre_carrera, fc.id_facultad FROM carrera c INNER JOIN facultadcarrera fc ON c.id_carrera = fc.id_carrera WHERE c.activa = 1 ORDER BY fc.id_facultad, c.nombre_carrera");
$stmtCarreras->execute();
$todasCarreras = $stmtCarreras->fetchAll(PDO::FETCH_ASSOC);
$carrerasPorFacultad = [];

foreach ($todasCarreras as $carrera) {
    $carrerasPorFacultad[$carrera['id_facultad']][] = $carrera;
}

// ---- FIN DE SECCIÓN CORREGIDA ----

// 6. Ahora que TODA la lógica terminó, incluye el HEADER (que solo tiene HTML)
require_once 'templates/header.php';
?>

        <div class="header">
            <div class="container" style="margin-top: 20px;">
        <?php display_flash_message(); ?>
                <h1>Nueva Reserva</h1></div>
        </div>

        <div class="container">
            <div class="card">
                <h2>Acciones Rápidas</h2>
                <div class="choice-container">
                    <div class="choice-card" onclick="openModal('modalLibro')"><i class="fas fa-plus-circle"></i><h3>Nueva Reserva Libro</h3><p>Crear nueva reserva de libro</p></div>
                    <div class="choice-card" onclick="openModal('modalComputadora')"><i class="fas fa-plus-circle"></i><h3>Nueva Reserva PC</h3><p>Crear nueva reserva de computadora</p></div>
                </div>
            </div>
        </div>
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

                <div id="step-1-libro" class="step-content active">
                    <div class="form-section">
                        <div class="form-section-title">Identificación del Usuario</div>
                        <div class="form-group"><label for="cedula_libro">Cédula:</label><input type="text" id="cedula_libro" name="cedula" class="form-control" placeholder="Ej: 8-123-4567" autocomplete="off" required></div>
                        <button type="button" class="btn btn-primary" onclick="verificarUsuario('libro')">Verificar Usuario</button>
                    </div>
                </div>

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
                            <div id="campos_particular_libro" class="hidden-fields"><div class="form-group"><label for="celular_libro">Número de Celular:</label><input type="tel" id="celular_libro" name="celular" class="form-control" autocomplete="off"></div></div>
                        </div>
                    </div>
                </div>

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

                <div id="step-1-computadora" class="step-content active">
                    <div class="form-section">
                        <div class="form-section-title">Identificación del Usuario</div>
                        <div class="form-group"><label for="cedula_computadora">Cédula:</label><input type="text" id="cedula_computadora" name="cedula" class="form-control" placeholder="Ej: 8-123-4567" autocomplete="off" required></div>
                        <button type="button" class="btn btn-primary" onclick="verificarUsuario('computadora')">Verificar Usuario</button>
                    </div>
                </div>

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

    <script>
        const carrerasPorFacultad = <?php echo json_encode($carrerasPorFacultad); ?>;
        let requestInProgress = false;
        let currentStep = { libro: 1, computadora: 1 };
        window.usuarioEncontrado = { libro: null, computadora: null };

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
    
    // Desbloquear cédula
    const cedulaInput = document.getElementById(`cedula_${tipo}`);
    cedulaInput.readOnly = false;
    cedulaInput.disabled = false;
    
    // Limpiar UI
    document.getElementById(`user-info-${tipo}`).innerHTML = '';
    document.getElementById(`new-user-form-${tipo}`).style.display = 'block';
    
    // Limpiar usuario almacenado
    if (window.usuarioEncontrado) {
        window.usuarioEncontrado[tipo] = null;
    }
    
    // Ocultar campos condicionales y deshabilitarlos
    actualizarFormularioPorAfiliacion(tipo);

    if(tipo === 'libro') {
        filtrarLibros();
    }
}


async function verificarUsuario(tipo) {
    const cedulaInput = document.getElementById(`cedula_${tipo}`);
    const cedula = cedulaInput.value;
    clearAlert(tipo);

    if (!validarCedulaPanama(cedula)) {
        showAlert(tipo, 'Formato de cédula inválido.');
        return;
    }

    const formData = new FormData();
    formData.append('accion', 'verificar_usuario');
    formData.append('cedula', cedula);

    try {
        const response = await fetch('dashboard.php', { method: 'POST', body: formData });
        const result = await response.json();
        
        cedulaInput.readOnly = true;

        if (result.encontrado) {
            const user = result.usuario;
            
            // CRÍTICO: Guardar datos del usuario
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
            
            // CRÍTICO: Llenar TODOS los campos necesarios para el submit
            document.getElementById(`nombre_${tipo}`).value = user.nombre || '';
            document.getElementById(`apellido_${tipo}`).value = user.apellido || '';
            document.getElementById(`afiliacion_${tipo}`).value = user.id_afiliacion || '';
            
            if (user.id_tipo_usuario) {
                // Asegurarse que los selectores correctos tengan el valor
                document.getElementById(`tipo_usuario_${tipo}`).value = user.id_tipo_usuario;
                document.getElementById(`tipo_usuario_externa_${tipo}`).value = user.id_tipo_usuario;
            }
            if (user.id_facultad) {
                document.getElementById(`facultad_${tipo}`).value = user.id_facultad;
                cargarCarreras(tipo); // Cargar carreras antes de seleccionar
            }
            if (user.id_carrera) {
                // Esperar un momento para que se carguen las carreras
                setTimeout(() => {
                    document.getElementById(`carrera_${tipo}`).value = user.id_carrera;
                }, 100);
            }
            if (user.celular) {
                document.getElementById(`celular_${tipo}`).value = user.celular;
            }
            if (user.universidad_externa) {
                document.getElementById(`universidad_externa_${tipo}`).value = user.universidad_externa;
            }
            
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

        function showAlert(tipo, message, isSuccess = false) { const alertDiv = document.getElementById(`alert${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`); alertDiv.innerHTML = `<div class="alert ${isSuccess ? 'alert-success' : 'alert-danger'}">${message}</div>`; }
        function clearAlert(tipo) { const alertDiv = document.getElementById(`alert${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`); alertDiv.innerHTML = ''; }
        function setButtonLoading(btn, isLoading) {
            const btnText = btn.querySelector('.btn-text');
            if (isLoading) {
                btn.disabled = true;
                if(btnText) btnText.innerHTML = '<span class="spinner"></span>Procesando...';
                btn.style.pointerEvents = 'none';
            } else {
                btn.disabled = false;
                if(btnText) btnText.innerHTML = 'Realizar Reserva';
                btn.style.pointerEvents = 'auto';
            }
        }
        function validarCedulaPanama(cedula) { const patron = /^(?:[1-9]|1[0-3]|PE|E|N|[1-9]AV|[1-9]PI)-\d{1,5}-\d{1,6}$/; return patron.test(cedula.trim()); }

        function mostrarPaso(paso, tipo) {
            currentStep[tipo] = paso;
            
            // Ocultar todos los steps de ambos modales
            document.querySelectorAll('.step-content').forEach(el => el.classList.remove('active'));
            // Mostrar solo el step activo del modal activo
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
                // Validar campos de nuevo usuario si es el paso 2 y el formulario es visible
                if (currentStep[tipo] === 2 && document.getElementById(`new-user-form-${tipo}`).style.display !== 'none') {
                    const form = document.getElementById(`form${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`);
                    let isValid = true;
                    // Solo validar campos visibles y requeridos dentro del formulario de nuevo usuario
                    const fields = form.querySelectorAll(`#new-user-form-${tipo} [required]`);
                    fields.forEach(field => {
                        // Comprobar que el campo no esté deshabilitado antes de validar
                        if (!field.disabled && !field.checkValidity()) {
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
            cedulaInput.disabled = false;
            
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

            const secciones = {
                up: document.getElementById(`campos_up_${tipo}`),
                otra: document.getElementById(`campos_otra_universidad_${tipo}`),
                particular: document.getElementById(`campos_particular_${tipo}`)
            };

            // Guardar el estado original de 'required' si no se ha guardado
            if (!secciones.up.hasAttribute('data-required-saved')) {
                 secciones.up.querySelectorAll('input, select').forEach(el => { if (el.required) el.setAttribute('data-required', 'true'); });
                 secciones.otra.querySelectorAll('input, select').forEach(el => { if (el.required) el.setAttribute('data-required', 'true'); });
                 secciones.particular.querySelectorAll('input, select').forEach(el => { if (el.required) el.setAttribute('data-required', 'true'); });
                 secciones.up.setAttribute('data-required-saved', 'true');
            }

            const gestionarCampos = (seccion, habilitar) => {
                seccion.style.display = habilitar ? 'block' : 'none';
                seccion.querySelectorAll('input, select').forEach(el => {
                    el.disabled = !habilitar;
                    el.required = habilitar && el.hasAttribute('data-required');
                });
            };
            
            gestionarCampos(secciones.up, afiId === '1');
            gestionarCampos(secciones.otra, afiId === '2');
            gestionarCampos(secciones.particular, afiId === '3');

            if (afiId === '1') {
                actualizarCamposUP(tipo); 
            }
        }

        function actualizarCamposUP(tipo) {
            const rolSelect = document.getElementById(`tipo_usuario_${tipo}`);
            const facultadField = document.getElementById(`campo-facultad-${tipo}`);
            const carreraField = document.getElementById(`campo-carrera-${tipo}`);
            const facultadSelect = document.getElementById(`facultad_${tipo}`);
            const carreraSelect = document.getElementById(`carrera_${tipo}`);
            const rol = rolSelect.value;
            
            // No deshabilitar, solo mostrar/ocultar y gestionar 'required'
            const esEstudianteOProfesor = (rol === '1' || rol === '2');
            const esEstudiante = (rol === '1');

            facultadField.style.display = esEstudianteOProfesor ? 'block' : 'none';
            facultadSelect.required = esEstudianteOProfesor;
            if (!esEstudianteOProfesor) {
                facultadSelect.value = '';
                facultadSelect.removeAttribute('required');
            }

            carreraField.style.display = esEstudiante ? 'block' : 'none';
            carreraSelect.required = esEstudiante;
            if (!esEstudiante) {
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
                    const option = new Option(carrera.nombre_carrera, carrera.id_carrera);
                    carreraSelect.add(option);
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
        const response = await fetch('dashboard.php', { method: 'POST', body: new FormData(form) });
        const result = await response.json();
        if (result.success) {
            showAlert(tipo, result.message, true);
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
    
	
	</script>

<?php
// 10. Incluir el footer
require_once 'templates/footer.php';
?>
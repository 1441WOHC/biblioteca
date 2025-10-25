<?php
session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';

$error = '';
$success = '';
$mensaje = '';

$database = new Database();
$conn = $database->getConnection();

function validarCedulaPanama($cedula) {
    $cedula = trim($cedula);
    $patron = '/^(?:[1-9]|1[0-3]|PE|E|N|[1-9]AV|[1-9]PI)-\d{1,5}-\d{1,6}$/';
    return preg_match($patron, $cedula);
}

// ===== INICIO DE CÓDIGO A AGREGAR =====
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
// ===== FIN DE CÓDIGO A AGREGAR =====

// CSRF token simple
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    header('Content-Type: application/json');

    // ===== INICIO DE CÓDIGO A AGREGAR =====
    // Validar Token CSRF para todas las acciones POST
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        // Si la acción es AJAX (como verificar_usuario), responde con JSON
        if (isset($_POST['accion'])) {
            $response['message'] = 'Error de validación de seguridad (CSRF). Recargue la página.';
            echo json_encode($response);
            exit;
        }
        // Si es el envío del formulario principal
        $response['message'] = 'Error de validación de seguridad. Por favor, recargue la página e intente de nuevo.';
        echo json_encode($response);
        exit;
    }
    // ===== FIN DE CÓDIGO A AGREGAR =====

    // Verificar usuario por cédula vía AJAX
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
    
    // Lógica de reserva
    if (isset($_POST['tipo_reserva'])) {
        $cedula = trim($_POST['cedula']);
        
        if (!validarCedulaPanama($cedula)) {
            $response['message'] = "Formato de cédula inválido. Formatos válidos: 1-1234-12345, PE-1234-12345, E-1234-123456, N-1234-1234, 1AV-1234-12345, 1PI-1234-12345";
        } else {
            $usuario = verificarUsuario($conn, $cedula);
            
            if (!$usuario) {
                $idTipoUsuario = null;
                if (!empty($_POST['tipo_usuario'])) {
                    $idTipoUsuario = $_POST['tipo_usuario'];
                } elseif ($_POST['afiliacion'] == 3) {
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
                    'id_turno'      => determinarTurno() // <-- AÑADIR ESTA LÍNEA
                ];
                
                // ✅ NUEVO CÓDIGO CON MENSAJES ESPECÍFICOS
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
    }
    
    echo json_encode($response);
    exit;
}

// Obtener datos para los formularios
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservar Libro - Biblioteca</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: var(--spacing-sm); border-radius: var(--border-radius); margin-bottom: var(--spacing-md); }
        .alert-danger { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: var(--spacing-sm); border-radius: var(--border-radius); margin-bottom: var(--spacing-md); }
        .btn-primary { position: relative; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-primary:disabled { background-color: #6c757d; border-color: #6c757d; cursor: not-allowed; opacity: 0.7; }
        .spinner { width: 16px; height: 16px; border: 2px solid transparent; border-top: 2px solid #ffffff; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .hidden-fields, .step-content { display: none; }
        .step-content.active { display: block; animation: fadeIn 0.5s; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .step-navigation { display: flex; justify-content: space-between; margin-top: var(--spacing-lg); }
        .step-indicators { display: flex; justify-content: center; gap: 20px; margin-bottom: var(--spacing-lg); border-bottom: 1px solid var(--border-color); padding-bottom: var(--spacing-md); }
        .step-indicator { color: var(--text-secondary); padding: 5px 10px; border-bottom: 3px solid transparent; font-weight: 500; }
        .step-indicator.active { color: var(--primary-color); border-bottom-color: var(--primary-color); font-weight: 700; }
        .step-indicator.completed { color: #28a745; }
        .user-found-info { background-color: var(--light-gray); padding: var(--spacing-md); border-radius: var(--border-radius); border-left: 5px solid var(--primary-color); margin-bottom: var(--spacing-md); }
        .user-found-info p { margin: 0; }
        @media (max-width: 768px) { .step-navigation { flex-direction: column; gap: 10px; } .step-navigation .btn { width: 100%; } }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>Reservar Libro</h1>
            <p>Complete el formulario para reservar un libro</p>
        </div>
    </div>

    <div class="container">
        <a href="index.php" class="btn btn-secondary">← Volver al inicio</a>
        
        <div class="card">
            <h2>Formulario de Reserva de Libro</h2>
            <div id="alertLibro"></div>
            
            <form id="formLibro" class="modal-form" onsubmit="submitForm(event)">
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
                        <div class="form-group">
                            <label for="cedula_libro">Cédula:</label>
                            <input type="text" id="cedula_libro" name="cedula" class="form-control" placeholder="Ej: 8-123-4567" autocomplete="off" required>
                        </div>
                        <button type="button" class="btn btn-primary" onclick="verificarUsuario()">Verificar Usuario</button>
                    </div>
                </div>

                <!-- Paso 2: Datos del Usuario -->
                <div id="step-2-libro" class="step-content">
                    <div id="user-info-libro"></div>
                    <div id="new-user-form-libro">
                        <div class="form-section">
                            <div class="form-section-title">Datos del Nuevo Usuario</div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="nombre_libro">Nombre:</label>
                                    <input type="text" id="nombre_libro" name="nombre" class="form-control" autocomplete="off" required>
                                </div>
                                <div class="form-group">
                                    <label for="apellido_libro">Apellido:</label>
                                    <input type="text" id="apellido_libro" name="apellido" class="form-control" autocomplete="off" required>
                                </div>
                            </div>
                        </div>
                        <hr class="form-separator">
                        <div class="form-section">
                            <div class="form-section-title">Afiliación</div>
                            <div class="form-group">
                                <label for="afiliacion_libro">Seleccione la afiliación:</label>
                                <select id="afiliacion_libro" name="afiliacion" class="form-control" required onchange="actualizarFormularioPorAfiliacion()">
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($afiliaciones as $afiliacion): ?>
                                    <option value="<?php echo $afiliacion['id_afiliacion']; ?>"><?php echo htmlspecialchars($afiliacion['nombre_afiliacion']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div id="campos_up_libro" class="hidden-fields">
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="tipo_usuario_libro">Rol en la Universidad:</label>
                                        <select id="tipo_usuario_libro" name="tipo_usuario" class="form-control" onchange="actualizarCamposUP()">
                                            <option value="1">Estudiante</option>
                                            <option value="2">Profesor</option>
                                            <option value="3">Administrativo</option>
                                        </select>
                                    </div>
                                    <div class="form-group" id="campo-facultad-libro">
                                        <label for="facultad_libro">Facultad:</label>
                                        <select id="facultad_libro" name="facultad" class="form-control" onchange="cargarCarreras()">
                                            <option value="">Seleccionar facultad</option>
                                            <?php foreach ($facultades as $facultad): ?>
                                            <option value="<?php echo $facultad['id_facultad']; ?>"><?php echo htmlspecialchars($facultad['nombre_facultad']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group" id="campo-carrera-libro">
                                        <label for="carrera_libro">Carrera:</label>
                                        <select id="carrera_libro" name="carrera" class="form-control">
                                            <option value="">Seleccione una facultad</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
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
                            
                            <div id="campos_particular_libro" class="hidden-fields">
                                <div class="form-group">
                                    <label for="celular_libro">Número de Celular:</label>
                                    <input type="tel" id="celular_libro" name="celular" class="form-control" autocomplete="off">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Paso 3: Detalles de Reserva -->
                <div id="step-3-libro" class="step-content">
                    <div class="form-section">
                        <div class="form-section-title">Detalles de la Reserva</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="filtro-categoria-libro">Filtrar por Categoría:</label>
                                <select id="filtro-categoria-libro" class="form-control">
                                    <option value="">Todas las categorías</option>
                                    <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo $categoria['id_categoria']; ?>"><?php echo htmlspecialchars($categoria['nombre_categoria']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="buscar-libro">Buscar por Título/Autor/Código:</label>
                                <input type="text" id="buscar-libro" class="form-control" placeholder="Escriba para buscar..." autocomplete="off">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="libro">Seleccionar Libro:</label>
                            <select id="libro" name="libro" class="form-control" required>
                                <option value="">Seleccione un libro de la lista</option>
                                <?php foreach ($libros as $libro): ?>
                                <option value="<?php echo $libro['id_libro']; ?>" 
                                        data-searchtext="<?php echo strtolower(htmlspecialchars($libro['titulo'] . ' ' . $libro['autor'] . ' ' . $libro['codigo_unico'])); ?>" 
                                        data-categoria-id="<?php echo $libro['id_categoria']; ?>">
                                    <?php echo htmlspecialchars($libro['titulo'] . ' - ' . $libro['autor'] . ' (' . $libro['codigo_unico'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="tipo_reserva_libro">Tipo de Reserva:</label>
                            <select id="tipo_reserva_libro" name="tipo_reserva_libro" class="form-control" required>
                                <option value="">Seleccionar tipo</option>
                                <?php foreach ($tiposReserva as $tipo): ?>
                                <option value="<?php echo $tipo['id_tipo_reserva']; ?>"><?php echo htmlspecialchars($tipo['nombre_tipo_reserva']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="step-navigation" id="nav-libro">
                    <button type="button" class="btn btn-secondary" id="btn-prev-libro" onclick="pasoAnterior()" style="display: none;">Anterior</button>
                    <button type="button" class="btn btn-primary" id="btn-next-libro" onclick="siguientePaso()" style="display: none;">Siguiente</button>
                    <button type="submit" class="btn btn-primary" id="btnSubmitLibro" style="display: none;"><span class="btn-text">Realizar Reserva</span></button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const carrerasPorFacultad = <?php echo json_encode($carrerasPorFacultad); ?>;
        let requestInProgress = false;
        let currentStep = 1;
        window.usuarioEncontrado = null;

        function mostrarPaso(paso) {
            currentStep = paso;
            document.querySelectorAll('.step-content').forEach(el => el.classList.remove('active'));
            document.getElementById(`step-${paso}-libro`).classList.add('active');

            const indicators = document.querySelectorAll('#step-indicators-libro .step-indicator');
            indicators.forEach((ind, index) => {
                ind.classList.remove('active', 'completed');
                if (index < paso - 1) ind.classList.add('completed');
                if (index === paso - 1) ind.classList.add('active');
            });

            const btnPrev = document.getElementById('btn-prev-libro');
            const btnNext = document.getElementById('btn-next-libro');
            const btnSubmit = document.getElementById('btnSubmitLibro');
            const btnVerify = document.querySelector('#step-1-libro button');

            btnVerify.style.display = (paso === 1) ? 'block' : 'none';
            btnPrev.style.display = (paso > 1) ? 'inline-block' : 'none';
            btnNext.style.display = (paso === 2) ? 'inline-block' : 'none';
            btnSubmit.style.display = (paso === 3) ? 'inline-block' : 'none';
        }

        function siguientePaso() {
            if (currentStep < 3) {
                if (currentStep === 2 && document.getElementById('new-user-form-libro').style.display !== 'none') {
                    const form = document.getElementById('formLibro');
                    let isValid = true;
                    const fields = form.querySelectorAll('#new-user-form-libro [required]');
                    fields.forEach(field => {
                        if (!field.checkValidity()) {
                            isValid = false;
                            field.reportValidity();
                        }
                    });
                    if (!isValid) return;
                }
                mostrarPaso(currentStep + 1);
            }
        }

        function pasoAnterior() {
            if (currentStep > 1) {
                if (currentStep === 2) {
                    const cedulaInput = document.getElementById('cedula_libro');
                    cedulaInput.readOnly = false;
                    cedulaInput.disabled = false;
                    document.getElementById('user-info-libro').innerHTML = '';
                    document.getElementById('new-user-form-libro').style.display = 'block';
                }
                
                if (currentStep === 3 && window.usuarioEncontrado) {
                    const user = window.usuarioEncontrado;
                    const userInfoDiv = document.getElementById('user-info-libro');
                    userInfoDiv.innerHTML = `
                        <div class="user-found-info">
                            <p><strong>Usuario Encontrado:</strong> ${user.nombre_completo}</p>
                            <p><strong>Cédula:</strong> ${user.cedula}</p>
                            <p><strong>Afiliación:</strong> ${user.nombre_afiliacion || 'No especificada'}</p>
                            ${user.nombre_facultad ? `<p><strong>Facultad:</strong> ${user.nombre_facultad}</p>` : ''}
                            ${user.nombre_carrera ? `<p><strong>Carrera:</strong> ${user.nombre_carrera}</p>` : ''}
                        </div>`;
                    document.getElementById('new-user-form-libro').style.display = 'none';
                    mostrarPaso(2);
                } else {
                    mostrarPaso(currentStep - 1);
                }
            }
        }

       async function verificarUsuario() {
    const cedulaInput = document.getElementById('cedula_libro'); // o cedula_libro según el archivo
    const cedula = cedulaInput.value;
    clearAlert();

    if (!validarCedulaPanama(cedula)) {
        showAlert('Formato de cédula inválido.');
        return;
    }

    const formData = new FormData();
    formData.append('accion', 'verificar_usuario');
    formData.append('cedula', cedula);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

    try {
        const response = await fetch('reservar_libro.php', { method: 'POST', body: formData }); // o reservar_libro.php
        const result = await response.json();
        
        cedulaInput.readOnly = true;

        if (result.encontrado) {
            const user = result.usuario;
            window.usuarioEncontrado = user;
            
            const userInfoDiv = document.getElementById('user-info-libro'); // o user-info-libro
            userInfoDiv.innerHTML = `
                <div class="user-found-info">
                    <p><strong>Usuario Encontrado:</strong> ${user.nombre_completo}</p>
                    <p><strong>Cédula:</strong> ${user.cedula}</p>
                    <p><strong>Afiliación:</strong> ${user.nombre_afiliacion || 'No especificada'}</p>
                    ${user.nombre_facultad ? `<p><strong>Facultad:</strong> ${user.nombre_facultad}</p>` : ''}
                    ${user.nombre_carrera ? `<p><strong>Carrera:</strong> ${user.nombre_carrera}</p>` : ''}
                </div>`;
            
            document.getElementById('new-user-form-libro').style.display = 'none'; // o new-user-form-libro
            
            // ============ CRÍTICO: LLENAR TODOS LOS CAMPOS ============
            document.getElementById('nombre_libro').value = user.nombre || '';
            document.getElementById('apellido_libro').value = user.apellido || '';
            document.getElementById('afiliacion_libro').value = user.id_afiliacion || '';
            
            // Llenar campos según afiliación
            if (user.id_afiliacion == 1) { // Universidad de Panamá
                if (user.id_tipo_usuario) {
                    document.getElementById('tipo_usuario_libro').value = user.id_tipo_usuario;
                }
                if (user.id_facultad) {
                    document.getElementById('facultad_libro').value = user.id_facultad;
                    cargarCarreras();
                    if (user.id_carrera) {
                        setTimeout(() => {
                            document.getElementById('carrera_libro').value = user.id_carrera;
                        }, 100);
                    }
                }
            } else if (user.id_afiliacion == 2) { // Otra Universidad
                if (user.id_tipo_usuario) {
                    document.getElementById('tipo_usuario_externa_libro').value = user.id_tipo_usuario;
                }
                if (user.universidad_externa) {
                    document.getElementById('universidad_externa_libro').value = user.universidad_externa;
                }
            } else if (user.id_afiliacion == 3) { // Particular
                if (user.celular) {
                    document.getElementById('celular_libro').value = user.celular;
                }
            }
            // ========================================================
            
            mostrarPaso(3);
        } else {
            showAlert('Usuario no encontrado. Por favor, complete el formulario de registro.', true);
            document.getElementById('new-user-form-libro').style.display = 'block';
            document.getElementById('user-info-libro').innerHTML = '';
            window.usuarioEncontrado = null;
            mostrarPaso(2);
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('Error al verificar el usuario.');
        cedulaInput.readOnly = false;
    }
}

        function showAlert(message, isSuccess = false) {
            const alertDiv = document.getElementById('alertLibro');
            alertDiv.innerHTML = `<div class="alert ${isSuccess ? 'alert-success' : 'alert-danger'}">${message}</div>`;
        }

        function clearAlert() {
            document.getElementById('alertLibro').innerHTML = '';
        }

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

        function validarCedulaPanama(cedula) {
            const patron = /^(?:[1-9]|1[0-3]|PE|E|N|[1-9]AV|[1-9]PI)-\d{1,5}-\d{1,6}$/;
            return patron.test(cedula.trim());
        }

        function actualizarFormularioPorAfiliacion() {
            const afiSelect = document.getElementById('afiliacion_libro');
            const afiId = afiSelect.value;

            const secciones = {
                up: document.getElementById('campos_up_libro'),
                otra: document.getElementById('campos_otra_universidad_libro'),
                particular: document.getElementById('campos_particular_libro')
            };

            const gestionarCampos = (seccion, habilitar) => {
                seccion.style.display = habilitar ? 'block' : 'none';
                seccion.querySelectorAll('input, select').forEach(el => {
                    el.disabled = !habilitar;
                    if (el.hasAttribute('data-required')) {
                        el.required = habilitar;
                    }
                });
            };
            
            if (!secciones.up.querySelector('[data-required]')) {
                secciones.up.querySelectorAll('[required]').forEach(el => el.setAttribute('data-required', 'true'));
                secciones.otra.querySelectorAll('[required]').forEach(el => el.setAttribute('data-required', 'true'));
                secciones.particular.querySelectorAll('[required]').forEach(el => el.setAttribute('data-required', 'true'));
            }

            gestionarCampos(secciones.up, false);
            gestionarCampos(secciones.otra, false);
            gestionarCampos(secciones.particular, false);

            if (afiId === '1') {
                gestionarCampos(secciones.up, true);
                actualizarCamposUP();
            } else if (afiId === '2') {
                gestionarCampos(secciones.otra, true);
            } else if (afiId === '3') {
                gestionarCampos(secciones.particular, true);
            }
        }

       // Buscar esta función en reservar_libro.php (alrededor de la línea 280)
// y reemplazarla con esta versión:

function actualizarCamposUP() {
    const rolSelect = document.getElementById('tipo_usuario_libro');
    const facultadField = document.getElementById('campo-facultad-libro');
    const carreraField = document.getElementById('campo-carrera-libro');
    const facultadSelect = document.getElementById('facultad_libro');
    const carreraSelect = document.getElementById('carrera_libro');
    const rol = rolSelect.value;
    
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
    
    cargarCarreras();
}
        function cargarCarreras() {
            const facultadId = document.getElementById('facultad_libro').value;
            const carreraSelect = document.getElementById('carrera_libro');
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

        async function submitForm(e) {
            e.preventDefault();
            if (requestInProgress) return;

            const form = e.target;
            let isValid = true;
            const fields = form.querySelectorAll('#step-3-libro [required]');
            fields.forEach(field => {
                if (!field.checkValidity()) {
                    isValid = false;
                    field.reportValidity();
                }
            });
            if (!isValid) return;

            requestInProgress = true;
            const btn = document.getElementById('btnSubmitLibro');
            setButtonLoading(btn, true);
            clearAlert();

            try {
                const response = await fetch('reservar_libro.php', { method: 'POST', body: new FormData(form) });
                const result = await response.json();
                if (result.success) {
                    showAlert(result.message, true);
                    setTimeout(() => {
                        window.location.href = 'index.php?reserva_exitosa=1';
                    }, 1500);
                } else {
                    showAlert(result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Error al procesar la reserva');
            } finally {
                requestInProgress = false;
                setButtonLoading(btn, false);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            mostrarPaso(1);
        });
    </script>
</body>
</html>
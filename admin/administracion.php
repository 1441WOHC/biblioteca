<?php
session_start();
if (!isset($_SESSION['bibliotecario'])) {
    header('Location: index.php');
    exit;
}

require_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

$mensaje = '';
$error = '';

// Verificar si el bibliotecario actual es administrador
$esAdministrador = isset($_SESSION['bibliotecario']['es_administrador']) && $_SESSION['bibliotecario']['es_administrador'];

// Solo permitir acceso a administradores
if (!$esAdministrador) {
    header('Location: dashboard.php');
    exit;
}

// =====================================================
// OBTENER DATOS
// =====================================================

// Obtener lista de bibliotecarios
$query = "SELECT id_bibliotecario, nombre_completo, cedula, es_administrador 
          FROM bibliotecario 
          ORDER BY nombre_completo";
$stmt = $conn->prepare($query);
$stmt->execute();
$bibliotecarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de facultades
$queryFacultades = "SELECT f.*, COUNT(DISTINCT fc.id_carrera) as total_carreras 
                    FROM facultad f 
                    LEFT JOIN facultadcarrera fc ON f.id_facultad = fc.id_facultad 
                    GROUP BY f.id_facultad 
                    ORDER BY f.activa DESC, f.nombre_facultad";
$stmtFacultades = $conn->prepare($queryFacultades);
$stmtFacultades->execute();
$facultades = $stmtFacultades->fetchAll(PDO::FETCH_ASSOC);

// Obtener todas las facultades (para select)
$queryAllFacultades = "SELECT id_facultad, nombre_facultad FROM facultad WHERE activa = 1 ORDER BY nombre_facultad";
$stmtAllFacultades = $conn->prepare($queryAllFacultades);
$stmtAllFacultades->execute();
$todasFacultades = $stmtAllFacultades->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de carreras con sus facultades
// MODIFICACIÓN: Se añade MIN(f.activa) para determinar si alguna facultad asociada está inactiva.
$queryCarreras = "SELECT c.id_carrera, c.nombre_carrera, c.activa,
                  MIN(f.activa) as estado_facultad,
                  GROUP_CONCAT(f.nombre_facultad SEPARATOR ', ') as facultades,
                  GROUP_CONCAT(f.id_facultad) as facultades_ids
                  FROM carrera c
                  INNER JOIN facultadcarrera fc ON c.id_carrera = fc.id_carrera
                  INNER JOIN facultad f ON fc.id_facultad = f.id_facultad
                  GROUP BY c.id_carrera
                  ORDER BY c.activa DESC, c.nombre_carrera";
$stmtCarreras = $conn->prepare($queryCarreras);
$stmtCarreras->execute();
$carreras = $stmtCarreras->fetchAll(PDO::FETCH_ASSOC);

// ---- INICIO DE BLOQUE A AGREGAR ----

$pageTitle = 'Administración';
$activePage = 'administracion'; // Para el menú lateral
$pageStyles = '
<style>
    /* Estilos para las pestañas */
        .tabs-container {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            margin-bottom: var(--spacing-lg);
            box-shadow: var(--shadow-sm);
        }
        
        .tabs-header {
            display: flex;
            border-bottom: 2px solid var(--border-color);
            background: var(--light-gray);
        }
        
        .tab-button {
            flex: 1;
            padding: var(--spacing-md) var(--spacing-lg);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1em;
            font-weight: 500;
            color: var(--text-secondary);
            transition: all var(--transition);
            position: relative;
        }
        
        .tab-button:hover {
            background: rgba(79, 172, 254, 0.1);
            color: var(--primary-color);
        }
        
        .tab-button.active {
            color: var(--primary-color);
            background: white;
        }
        
        .tab-button.active::after {
             content: \'\';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary-color);
        }
        
        .tab-content {
            display: none;
            padding: var(--spacing-lg);
            animation: fadeIn 0.3s ease;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            color: white;
        }
        .badge-primary {
            background-color: #007bff;
        }
        .badge-secondary {
            background-color: #6c757d;
        }
        .badge-success {
            background-color: #28a745;
        }
        .badge-danger {
            background-color: #dc3545;
        }
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        .form-row .form-group {
            flex: 1;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
            margin-right: 5px;
        }
        /* Clases de botones añadidas para uniformidad */
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
            border: none;
        }
        .btn-warning:hover {
            background-color: #e0a800;
        }
        .btn-danger {
            background-color: #dc3545;
            color: white;
            border: none;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .btn-success {
            background-color: #28a745;
            color: white;
            border: none;
        }
        .btn-success:hover {
            background-color: #218838;
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
            border: none;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        /* Estilos mejorados para modales */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .modal-overlay.show {
            opacity: 1;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            z-index: 1001;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            transform: scale(0.9);
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .modal.show .modal-content {
            transform: scale(1);
            opacity: 1;
        }
        
        .modal-content h3 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #333;
            font-size: 1.5em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-content h3 i {
            color: #dc3545;
        }
        
        .modal-content p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .modal-actions button {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-group {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--spacing-sm);
        }
        
        .checkbox-item {
            padding: var(--spacing-xs);
        }
        
        .checkbox-item label {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
        .checkbox-item input[type="checkbox"] {
            margin-right: var(--spacing-sm);
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            .btn-sm {
                margin-bottom: 5px;
            }
            .tabs-header {
                flex-direction: column;
            }
            .tab-button {
                border-bottom: 1px solid var(--border-color);
            }
            .modal-actions {
                flex-direction: column;
            }
            .modal-actions button {
                width: 100%;
            }
        }

        /* Estilos de notificación */
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
            border-left: 5px solid #4facfe;
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
require_once 'templates/header.php';
?>

        <div class="header">
           <div class="container" style="margin-top: 20px;">
        <?php display_flash_message(); ?>
                <h1>Panel de Administración</h1>
                <p>Gestión completa del sistema</p>
            </div>
        </div>

        <div class="container">
            <?php if ($mensaje): ?>
                <div class="alert alert-success"><?php echo $mensaje; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="tabs-container">
                <div class="tabs-header">
                    <button class="tab-button active" onclick="switchTab('bibliotecarios')">
                        <i class="fas fa-user-shield"></i> Bibliotecarios
                    </button>
                    <button class="tab-button" onclick="switchTab('facultades')">
                        <i class="fas fa-building"></i> Facultades
                    </button>
                    <button class="tab-button" onclick="switchTab('carreras')">
                        <i class="fas fa-graduation-cap"></i> Carreras
                    </button>
                </div>

                <div id="tab-bibliotecarios" class="tab-content active">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2>Bibliotecarios Registrados</h2>
                        <button onclick="toggleFormularioBibliotecario()" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Nuevo Bibliotecario
                        </button>
                    </div>
					
					<div class="card" id="formularioBibliotecario" style="display: none; margin-top: 20px;">
                        <h2>Crear Nuevo Bibliotecario</h2>
                        <form method="POST" action="form_handler.php" autocomplete="off">
                            <input type="hidden" name="action" value="crear_bibliotecario">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="nombre_completo">Nombre Completo:</label>
                                    <input type="text" id="nombre_completo" name="nombre_completo" 
                                           class="form-control" required>
                                </div>
                                
                               <div class="form-group">
    <label for="cedula">Cédula:</label>
    <input type="text" id="cedula" name="cedula" 
           class="form-control" required
           pattern="[0-9\-]+" 
           minlength="8"
           maxlength="20"
           placeholder="Ej: 8-123-4567"
           title="Ingrese solo números y guiones">
</div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="contrasena">Contraseña:</label>
                                    <input type="password" id="contrasena" name="contrasena" 
                                           class="form-control" required minlength="6">
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirmar_contrasena">Confirmar Contraseña:</label>
                                    <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" 
                                           class="form-control" required minlength="6">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="es_administrador" value="1"> 
                                    Otorgar privilegios de administrador
                                </label>
                                <small style="display: block; color: #666; margin-top: 5px;">
                                    Los administradores pueden crear y gestionar otros bibliotecarios
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" name="crear_bibliotecario" class="btn btn-primary">
                                    <i class="fas fa-user-plus"></i> Crear Bibliotecario
                                </button>
                                <button type="button" onclick="toggleFormularioBibliotecario()" class="btn btn-secondary">
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="card" id="formularioEditarBibliotecario" style="display: none; margin-top: 20px;">
                        <h2>Editar Bibliotecario</h2>
                        <form method="POST" id="formEditar" autocomplete="off" action="form_handler.php">
                            <input type="hidden" name="action" value="editar_bibliotecario">
                            <input type="hidden" id="edit_id_bibliotecario" name="id_bibliotecario">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="edit_nombre_completo">Nombre Completo:</label>
                                    <input type="text" id="edit_nombre_completo" name="nombre_completo" autocomplete="off"
                                           class="form-control" required>
                                </div>
                                
                                <div class="form-group">
    <label for="edit_cedula">Cédula:</label>
    <input type="text" id="edit_cedula" name="cedula" autocomplete="off"
           class="form-control" required
           pattern="[0-9\-]+" 
           minlength="8"
           maxlength="20"
           placeholder="Ej: 8-123-4567"
           title="Ingrese solo números y guiones">
</div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="nueva_contrasena">Nueva Contraseña (opcional):</label>
                                    <input type="password" id="nueva_contrasena" name="nueva_contrasena" 
                                           class="form-control" minlength="6">
                                    <small style="color: #666;">Deja en blanco si no deseas cambiar la contraseña</small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_confirmar_contrasena">Confirmar Nueva Contraseña:</label>
                                    <input type="password" id="edit_confirmar_contrasena" name="confirmar_contrasena" 
                                           class="form-control" minlength="6">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" id="edit_es_administrador" name="es_administrador" value="1"> 
                                    Otorgar privilegios de administrador
                                </label>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" name="editar_bibliotecario" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Actualizar Bibliotecario
                                </button>
                                <button type="button" onclick="cancelarEdicion()" class="btn btn-secondary">
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nombre Completo</th>
                                    <th>Cédula</th>
                                    <th>Tipo</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bibliotecarios as $bib): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($bib['nombre_completo']); ?></td>
                                        <td><?php echo htmlspecialchars($bib['cedula']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $bib['es_administrador'] ? 'badge-primary' : 'badge-secondary'; ?>">
                                                <?php echo $bib['es_administrador'] ? 'Administrador' : 'Bibliotecario'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge badge-success">Activo</span>
                                        </td>
                                        <td>
                                            <button onclick="editarBibliotecario(<?php echo htmlspecialchars(json_encode($bib)); ?>)" 
                                                    class="btn btn-sm btn-primary" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($bib['id_bibliotecario'] != $_SESSION['bibliotecario']['id_bibliotecario']): ?>
                                            <button onclick="confirmarEliminarBibliotecario(<?php echo $bib['id_bibliotecario']; ?>, '<?php echo htmlspecialchars($bib['nombre_completo']); ?>')" 
                                                    class="btn btn-sm btn-danger" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="tab-facultades" class="tab-content">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2>Facultades Registradas</h2>
                        <button onclick="toggleFormularioFacultad()" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Nueva Facultad
                        </button>
                    </div>
					
					<div class="card" id="formularioFacultad" style="display: none; margin-top: 20px;">
                        <h2>Crear Nueva Facultad</h2>
                        <form method="POST" autocomplete="off" action="form_handler.php">
                            <input type="hidden" name="action" value="crear_facultad">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="form-group">
                                <label for="nombre_facultad">Nombre de la Facultad:</label>
                                <input type="text" id="nombre_facultad" name="nombre_facultad" autocomplete="off"
                                       class="form-control" required 
                                       placeholder="Ej: Facultad de Informática, Electrónica y Comunicación">
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" name="crear_facultad" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Crear Facultad
                                </button>
                                <button type="button" onclick="toggleFormularioFacultad()" class="btn btn-secondary">
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="card" id="formularioEditarFacultad" style="display: none; margin-top: 20px;">
                        <h2>Editar Facultad</h2>
                        <form method="POST" autocomplete="off" action="form_handler.php">
                            <input type="hidden" name="action" value="editar_facultad">
                            <input type="hidden" id="edit_id_facultad" name="id_facultad">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            
                            <div class="form-group">
                                <label for="edit_nombre_facultad">Nombre de la Facultad:</label>
                                <input type="text" id="edit_nombre_facultad" name="nombre_facultad" autocomplete="off"
                                       class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" name="editar_facultad" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Actualizar Facultad
                                </button>
                                <button type="button" onclick="cancelarEdicionFacultad()" class="btn btn-secondary">
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nombre de Facultad</th>
                                    <th>Total de Carreras</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($facultades as $facultad): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($facultad['nombre_facultad']); ?></td>
                                        <td>
                                            <span class="badge badge-primary">
                                                <?php echo $facultad['total_carreras']; ?> carrera(s)
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($facultad['activa']): ?>
                                                <span class="badge badge-success">
                                                    <i class="fas fa-check-circle"></i> Activa
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">
                                                    <i class="fas fa-power-off"></i> Desactivada
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button onclick="editarFacultad(<?php echo htmlspecialchars(json_encode($facultad)); ?>)" 
                                                    class="btn btn-sm btn-primary" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <?php if ($facultad['activa']): ?>
                                                <button onclick="confirmarCambiarEstadoFacultad(<?php echo $facultad['id_facultad']; ?>, '<?php echo htmlspecialchars($facultad['nombre_facultad']); ?>', 0)" 
                                                        class="btn btn-sm btn-warning" title="Desactivar">
                                                    <i class="fas fa-toggle-off"></i>
                                                </button>
                                            <?php else: ?>
                                                <button onclick="confirmarCambiarEstadoFacultad(<?php echo $facultad['id_facultad']; ?>, '<?php echo htmlspecialchars($facultad['nombre_facultad']); ?>', 1)" 
                                                        class="btn btn-sm btn-success" title="Activar">
                                                    <i class="fas fa-toggle-on"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="tab-carreras" class="tab-content">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2>Carreras Registradas</h2>
                        <button onclick="toggleFormularioCarrera()" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Nueva Carrera
                        </button>
                    </div>
					
					<div class="card" id="formularioCarrera" style="display: none; margin-top: 20px;">
                        <h2>Crear Nueva Carrera</h2>
                        <form method="POST" autocomplete="off" action="form_handler.php">
                            <input type="hidden" name="action" value="crear_carrera">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <div class="form-group">
                                <label for="nombre_carrera">Nombre de la Carrera:</label>
                                <input type="text" id="nombre_carrera" name="nombre_carrera" autocomplete="off"
                                       class="form-control" required 
                                       placeholder="Ej: Licenciatura en Desarrollo de Software">
                            </div>
                            
                            <div class="form-group">
                                <label for="facultad_carrera">Facultad:</label>
                                <select id="facultad_carrera" name="facultades[]" class="form-control" required>
                                    <option value="">Seleccionar facultad...</option>
                                    <?php foreach ($todasFacultades as $fac): ?>
                                        <option value="<?php echo $fac['id_facultad']; ?>">
                                            <?php echo htmlspecialchars($fac['nombre_facultad']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" name="crear_carrera" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Crear Carrera
                                </button>
                                <button type="button" onclick="toggleFormularioCarrera()" class="btn btn-secondary">
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="card" id="formularioEditarCarrera" style="display: none; margin-top: 20px;">
                        <h2>Editar Carrera</h2>
                        <form method="POST" autocomplete="off" action="form_handler.php">
                            <input type="hidden" name="action" value="editar_carrera">
                            <input type="hidden" id="edit_id_carrera" name="id_carrera">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            
                            <div class="form-group">
                                <label for="edit_nombre_carrera">Nombre de la Carrera:</label>
                                <input type="text" id="edit_nombre_carrera" name="nombre_carrera" autocomplete="off"
                                       class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="edit_facultad_carrera">Facultad:</label>
                                <select id="edit_facultad_carrera" name="facultades[]" class="form-control" required>
                                    <option value="">Seleccionar facultad...</option>
                                    <?php foreach ($todasFacultades as $fac): ?>
                                        <option value="<?php echo $fac['id_facultad']; ?>">
                                            <?php echo htmlspecialchars($fac['nombre_facultad']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" name="editar_carrera" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Actualizar Carrera
                                </button>
                                <button type="button" onclick="cancelarEdicionCarrera()" class="btn btn-secondary">
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    </div>

                    <div style="overflow-x: auto;">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nombre de Carrera</th>
                                    <th>Facultad</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($carreras as $carrera): ?>
                                    <?php
                                        $estado_efectivo = $carrera['activa'] && $carrera['estado_facultad'];
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($carrera['nombre_carrera']); ?></td>
                                        <td>
                                            <small><?php echo htmlspecialchars($carrera['facultades']); ?></small>
                                        </td>
                                        <td>
                                            <?php if ($estado_efectivo): ?>
                                                <span class="badge badge-success">
                                                    <i class="fas fa-check-circle"></i> Activa
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">
                                                    <i class="fas fa-power-off"></i> Desactivada
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button onclick="editarCarrera(<?php echo htmlspecialchars(json_encode($carrera)); ?>)" 
                                                    class="btn btn-sm btn-primary" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <?php if ($carrera['activa']): ?>
                                                <button onclick="confirmarCambiarEstadoCarrera(<?php echo $carrera['id_carrera']; ?>, '<?php echo htmlspecialchars($carrera['nombre_carrera']); ?>', 0)" 
                                                        class="btn btn-sm btn-warning" title="Desactivar">
                                                    <i class="fas fa-toggle-off"></i>
                                                </button>
                                            <?php else: ?>
                                                <button onclick="confirmarCambiarEstadoCarrera(<?php echo $carrera['id_carrera']; ?>, '<?php echo htmlspecialchars($carrera['nombre_carrera']); ?>', 1)" 
                                                        class="btn btn-sm btn-success" title="Activar">
                                                    <i class="fas fa-toggle-on"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- MODAL ELIMINAR BIBLIOTECARIO -->
            <div id="modalEliminarBibliotecario" class="modal">
                <div class="modal-overlay" onclick="cerrarModal('modalEliminarBibliotecario')"></div>
                <div class="modal-content">
                    <h3><i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación</h3>
                    <p id="mensajeEliminarBibliotecario"></p>
                    <div class="modal-actions">
                        <button type="button" onclick="cerrarModal('modalEliminarBibliotecario')" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <form method="POST" style="display: inline;" action="form_handler.php">
                        <input type="hidden" name="action" value="eliminar_bibliotecario">
                        <input type="hidden" id="idEliminarBibliotecario" name="id_bibliotecario">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <button type="submit" name="eliminar_bibliotecario" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Eliminar
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- MODAL CAMBIAR ESTADO FACULTAD -->
            <div id="modalCambiarEstadoFacultad" class="modal">
                <div class="modal-overlay" onclick="cerrarModal('modalCambiarEstadoFacultad')"></div>
                <div class="modal-content">
                    <h3><i class="fas fa-exclamation-circle"></i> Confirmar Acción</h3>
                    <p id="mensajeCambiarEstadoFacultad"></p>
                    <div class="modal-actions">
                        <button type="button" onclick="cerrarModal('modalCambiarEstadoFacultad')" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <form method="POST" style="display: inline;" action="form_handler.php">
                        <input type="hidden" name="action" value="cambiar_estado_facultad">
                        <input type="hidden" id="idCambiarEstadoFacultad" name="id_facultad">
                        <input type="hidden" id="nuevoEstadoFacultad" name="nuevo_estado">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <button type="submit" id="btnConfirmarEstadoFacultad" class="btn btn-warning">
                                <i class="fas fa-check"></i> Confirmar
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- MODAL CAMBIAR ESTADO CARRERA -->
            <div id="modalCambiarEstadoCarrera" class="modal">
                <div class="modal-overlay" onclick="cerrarModal('modalCambiarEstadoCarrera')"></div>
                <div class="modal-content">
                    <h3><i class="fas fa-exclamation-circle"></i> Confirmar Acción</h3>
                    <p id="mensajeCambiarEstadoCarrera"></p>
                    <div class="modal-actions">
                        <button type="button" onclick="cerrarModal('modalCambiarEstadoCarrera')" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </button>
                        <form method="POST" style="display: inline;" action="form_handler.php">
                        <input type="hidden" name="action" value="cambiar_estado_carrera">
                        <input type="hidden" id="idCambiarEstadoCarrera" name="id_carrera">
                        <input type="hidden" id="nuevoEstadoCarrera" name="nuevo_estado">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <button type="submit" id="btnConfirmarEstadoCarrera" class="btn btn-warning">
                                <i class="fas fa-check"></i> Confirmar
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle sidebar functionality
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const mainWrapper = document.getElementById('mainWrapper');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        menuToggle.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('mobile-open');
                sidebarOverlay.classList.toggle('active');
            } else {
                sidebar.classList.toggle('collapsed');
                mainWrapper.classList.toggle('expanded');
            }
        });

        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('mobile-open');
            sidebarOverlay.classList.remove('active');
        });

        // Sistema de pestañas
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            document.getElementById('tab-' + tabName).classList.add('active');
            event.target.closest('.tab-button').classList.add('active');
        }

        // ===== FUNCIONES PARA MODALES MEJORADAS =====
        function abrirModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function cerrarModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }

        // Cerrar modal con tecla ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.show').forEach(modal => {
                    cerrarModal(modal.id);
                });
            }
        });

        // ===== FUNCIONES BIBLIOTECARIOS =====
        function toggleFormularioBibliotecario() {
            const formulario = document.getElementById('formularioBibliotecario');
            const formularioEditar = document.getElementById('formularioEditarBibliotecario');
            
            if (formulario.style.display === 'none' || formulario.style.display === '') {
                formulario.style.display = 'block';
                formularioEditar.style.display = 'none';
            } else {
                formulario.style.display = 'none';
            }
        }

        function editarBibliotecario(bibliotecario) {
            const formulario = document.getElementById('formularioBibliotecario');
            const formularioEditar = document.getElementById('formularioEditarBibliotecario');
            
            formularioEditar.style.display = 'block';
            formulario.style.display = 'none';
            
            document.getElementById('edit_id_bibliotecario').value = bibliotecario.id_bibliotecario;
            document.getElementById('edit_nombre_completo').value = bibliotecario.nombre_completo;
            document.getElementById('edit_cedula').value = bibliotecario.cedula;
            document.getElementById('edit_es_administrador').checked = bibliotecario.es_administrador == 1;
            
            document.getElementById('nueva_contrasena').value = '';
            document.getElementById('edit_confirmar_contrasena').value = '';
            
            formularioEditar.scrollIntoView({ behavior: 'smooth' });
        }

        function cancelarEdicion() {
            document.getElementById('formularioEditarBibliotecario').style.display = 'none';
        }

        function confirmarEliminarBibliotecario(id, nombre) {
            document.getElementById('idEliminarBibliotecario').value = id;
            document.getElementById('mensajeEliminarBibliotecario').textContent = `¿Estás seguro de que deseas eliminar al bibliotecario "${nombre}"? Esta acción no se puede deshacer.`;
            abrirModal('modalEliminarBibliotecario');
        }

        // ===== FUNCIONES FACULTADES =====
        function toggleFormularioFacultad() {
            const formulario = document.getElementById('formularioFacultad');
            const formularioEditar = document.getElementById('formularioEditarFacultad');
            
            if (formulario.style.display === 'none' || formulario.style.display === '') {
                formulario.style.display = 'block';
                formularioEditar.style.display = 'none';
            } else {
                formulario.style.display = 'none';
            }
        }

        function editarFacultad(facultad) {
            const formulario = document.getElementById('formularioFacultad');
            const formularioEditar = document.getElementById('formularioEditarFacultad');
            
            formularioEditar.style.display = 'block';
            formulario.style.display = 'none';
            
            document.getElementById('edit_id_facultad').value = facultad.id_facultad;
            document.getElementById('edit_nombre_facultad').value = facultad.nombre_facultad;
            
            formularioEditar.scrollIntoView({ behavior: 'smooth' });
        }

        function cancelarEdicionFacultad() {
            document.getElementById('formularioEditarFacultad').style.display = 'none';
        }

        function confirmarCambiarEstadoFacultad(id, nombre, nuevoEstado) {
            document.getElementById('idCambiarEstadoFacultad').value = id;
            document.getElementById('nuevoEstadoFacultad').value = nuevoEstado;
            
            const accion = nuevoEstado === 0 ? 'desactivar' : 'activar';
            const btnConfirmar = document.getElementById('btnConfirmarEstadoFacultad');
            
            document.getElementById('mensajeCambiarEstadoFacultad').textContent = 
                `¿Estás seguro de que deseas ${accion} la facultad "${nombre}"?`;
            
            if (nuevoEstado === 0) {
                btnConfirmar.className = 'btn btn-warning';
                btnConfirmar.innerHTML = '<i class="fas fa-toggle-off"></i> Desactivar';
            } else {
                btnConfirmar.className = 'btn btn-success';
                btnConfirmar.innerHTML = '<i class="fas fa-toggle-on"></i> Activar';
            }
            
            abrirModal('modalCambiarEstadoFacultad');
        }

        // ===== FUNCIONES CARRERAS =====
        function toggleFormularioCarrera() {
            const formulario = document.getElementById('formularioCarrera');
            const formularioEditar = document.getElementById('formularioEditarCarrera');
            
            if (formulario.style.display === 'none' || formulario.style.display === '') {
                formulario.style.display = 'block';
                formularioEditar.style.display = 'none';
            } else {
                formulario.style.display = 'none';
            }
        }

        function editarCarrera(carrera) {
            const formulario = document.getElementById('formularioCarrera');
            const formularioEditar = document.getElementById('formularioEditarCarrera');
            
            formularioEditar.style.display = 'block';
            formulario.style.display = 'none';
            
            document.getElementById('edit_id_carrera').value = carrera.id_carrera;
            document.getElementById('edit_nombre_carrera').value = carrera.nombre_carrera;
            
            // Seleccionar la primera facultad (ya que ahora es solo una)
            const facultadId = carrera.facultades_ids ? carrera.facultades_ids.split(',')[0] : '';
            const selectFacultad = document.getElementById('edit_facultad_carrera');
            if (selectFacultad) {
                selectFacultad.value = facultadId;
            }
            
            formularioEditar.scrollIntoView({ behavior: 'smooth' });
        }

        function cancelarEdicionCarrera() {
            document.getElementById('formularioEditarCarrera').style.display = 'none';
        }

        function confirmarCambiarEstadoCarrera(id, nombre, nuevoEstado) {
            document.getElementById('idCambiarEstadoCarrera').value = id;
            document.getElementById('nuevoEstadoCarrera').value = nuevoEstado;
            
            const accion = nuevoEstado === 0 ? 'desactivar' : 'activar';
            const btnConfirmar = document.getElementById('btnConfirmarEstadoCarrera');
            
            document.getElementById('mensajeCambiarEstadoCarrera').textContent = 
                `¿Estás seguro de que deseas ${accion} la carrera "${nombre}"?`;
            
            if (nuevoEstado === 0) {
                btnConfirmar.className = 'btn btn-warning';
                btnConfirmar.innerHTML = '<i class="fas fa-toggle-off"></i> Desactivar';
            } else {
                btnConfirmar.className = 'btn btn-success';
                btnConfirmar.innerHTML = '<i class="fas fa-toggle-on"></i> Activar';
            }
            
            abrirModal('modalCambiarEstadoCarrera');
        }

        // Validación de contraseñas para crear
        document.getElementById('confirmar_contrasena').addEventListener('input', function() {
            const contrasena = document.getElementById('contrasena').value;
            const confirmar = this.value;
            
            if (contrasena !== confirmar) {
                this.setCustomValidity('Las contraseñas no coinciden');
            } else {
                this.setCustomValidity('');
            }
        });

        // Validación de contraseñas para editar
        document.getElementById('edit_confirmar_contrasena').addEventListener('input', function() {
            const contrasena = document.getElementById('nueva_contrasena').value;
            const confirmar = this.value;
            
            if (contrasena !== confirmar) {
                this.setCustomValidity('Las contraseñas no coinciden');
            } else {
                this.setCustomValidity('');
            }
        });

        // Validación adicional para que ambas contraseñas estén llenas o ambas vacías
        document.getElementById('nueva_contrasena').addEventListener('input', function() {
            const confirmar = document.getElementById('edit_confirmar_contrasena');
            if (this.value === '') {
                confirmar.value = '';
                confirmar.removeAttribute('required');
            } else {
                confirmar.setAttribute('required', 'required');
            }
        });

        // Validación de formato de cédula
function validarCedula(input) {
    // Remover caracteres no permitidos mientras escribe
    input.value = input.value.replace(/[^0-9\-]/g, '');
}

// Agregar validación en tiempo real
document.getElementById('cedula').addEventListener('input', function() {
    validarCedula(this);
});

document.getElementById('edit_cedula').addEventListener('input', function() {
    validarCedula(this);
});

        
    </script>
<?php
// ---- AGREGAR ESTO ----
require_once 'templates/footer.php';
?>
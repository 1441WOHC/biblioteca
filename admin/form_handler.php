<?php
session_start();

// 1. Incluir dependencias
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/security.php';

// 2. Conexión a la BD
$database = new Database();
$conn = $database->getConnection();

// 3. Validar que sea un POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash_message('Acceso no válido.', 'danger');
    header('Location: dashboard.php');
    exit;
}

// 4. Validar Token CSRF
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    set_flash_message('Error de validación de seguridad (CSRF). Intente de nuevo.', 'danger');
    header('Location: ' . $_SERVER['HTTP_REFERER']);
    exit;
}

// 5. Procesar la acción
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        
        // ===========================================
        // ACCIONES DE COMPUTADORAS
        // ===========================================
        case 'crear_computadora':
            $query = "INSERT INTO computadora (numero, disponible) VALUES (:numero, :disponible)";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':numero' => $_POST['numero'],
                ':disponible' => $_POST['disponible'] ?? 1
            ]);
            set_flash_message('Computadora agregada exitosamente', 'success');
            break;

        case 'editar_computadora':
            $query = "UPDATE computadora SET numero = :numero, disponible = :disponible WHERE id_computadora = :id";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':numero' => $_POST['numero'],
                ':disponible' => $_POST['disponible'],
                ':id' => $_POST['id_computadora']
            ]);
            set_flash_message('Computadora actualizada exitosamente', 'success');
            break;

        case 'eliminar_computadora':
            $checkQuery = "SELECT COUNT(*) as count FROM reservacomputadora WHERE id_computadora = :id AND fecha >= CURDATE()";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->execute([':id' => $_POST['id_computadora']]);
            $reservasActivas = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($reservasActivas > 0) {
                set_flash_message('No se puede eliminar la computadora porque tiene reservas activas', 'warning');
            } else {
                $query = "DELETE FROM computadora WHERE id_computadora = :id";
                $stmt = $conn->prepare($query);
                $stmt->execute([':id' => $_POST['id_computadora']]);
                set_flash_message('Computadora eliminada exitosamente', 'success');
            }
            break;

        case 'desactivar_computadora':
            $query = "UPDATE computadora SET disponible = 3 WHERE id_computadora = :id";
            $stmt = $conn->prepare($query);
            $stmt->execute([':id' => $_POST['id_computadora']]);
            set_flash_message('Computadora desactivada exitosamente', 'success');
            break;

        // ===========================================
        // ACCIONES DE LIBROS
        // ===========================================
        case 'crear_libro':
            $conn->beginTransaction();
            $idCategoriaParaLibro = $_POST['categoria'];
            if (!empty($_POST['nueva_categoria_nombre'])) {
                $nombreNuevaCategoria = trim($_POST['nueva_categoria_nombre']);
                $queryCat = "INSERT INTO categoria (nombre_categoria) VALUES (:nombre)";
                $stmtCat = $conn->prepare($queryCat);
                $stmtCat->execute([':nombre' => $nombreNuevaCategoria]);
                $idCategoriaParaLibro = $conn->lastInsertId();
            }
            $query = "INSERT INTO libro (titulo, autor, codigo_unico, id_categoria, disponible)
                      VALUES (:titulo, :autor, :codigo_unico, :categoria, :disponible)";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':titulo' => $_POST['titulo'],
                ':autor' => $_POST['autor'],
                ':codigo_unico' => $_POST['codigo_unico'],
                ':categoria' => $idCategoriaParaLibro,
                ':disponible' => $_POST['disponible'] ?? 1
            ]);
            $conn->commit();
            set_flash_message('Libro creado exitosamente', 'success');
            break;

        case 'editar_libro':
            $conn->beginTransaction();
            $idCategoriaParaLibro = $_POST['categoria'];
            if (!empty($_POST['nueva_categoria_nombre'])) {
                $nombreNuevaCategoria = trim($_POST['nueva_categoria_nombre']);
                $queryCat = "INSERT INTO categoria (nombre_categoria) VALUES (:nombre)";
                $stmtCat = $conn->prepare($queryCat);
                $stmtCat->execute([':nombre' => $nombreNuevaCategoria]);
                $idCategoriaParaLibro = $conn->lastInsertId();
            }
            $query = "UPDATE libro SET titulo = :titulo, autor = :autor, codigo_unico = :codigo_unico,
                      id_categoria = :categoria, disponible = :disponible WHERE id_libro = :id";
            $stmt = $conn->prepare($query);
            $stmt->execute([
                ':titulo' => $_POST['titulo'],
                ':autor' => $_POST['autor'],
                ':codigo_unico' => $_POST['codigo_unico'],
                ':categoria' => $idCategoriaParaLibro,
                ':disponible' => $_POST['disponible'],
                ':id' => $_POST['id_libro']
            ]);
            $conn->commit();
            set_flash_message('Libro actualizado exitosamente', 'success');
            break;

        case 'desactivar_libro':
            $query = "UPDATE libro SET disponible = 2 WHERE id_libro = :id";
            $stmt = $conn->prepare($query);
            $stmt->execute([':id' => $_POST['id_libro']]);
            set_flash_message('Libro desactivado exitosamente.', 'success');
            break;

        case 'eliminar_libro':
            $checkQuery = "SELECT COUNT(*) as count FROM reservalibro WHERE id_libro = :id AND fecha >= CURDATE()";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->execute([':id' => $_POST['id_libro']]);
            $reservasActivas = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
            if ($reservasActivas > 0) {
                set_flash_message('No se puede eliminar el libro porque tiene reservas activas.', 'warning');
            } else {
                $deleteQuery = "DELETE FROM libro WHERE id_libro = :id";
                $deleteStmt = $conn->prepare($deleteQuery);
                $deleteStmt->execute([':id' => $_POST['id_libro']]);
                set_flash_message('Libro eliminado permanentemente', 'success');
            }
            break;
            
        // ===========================================
        // ACCIONES DE BIBLIOTECARIOS
        // ===========================================
        case 'eliminar_bibliotecario':
            $idBibliotecario = (int)$_POST['id_bibliotecario'];
            
            if ($idBibliotecario == $_SESSION['bibliotecario']['id_bibliotecario']) {
                set_flash_message("No puedes eliminarte a ti mismo.", 'danger');
                break;
            }
            
            // Verificar si es el último administrador
            $queryAdmins = "SELECT COUNT(*) as total FROM bibliotecario WHERE es_administrador = 1 AND id_bibliotecario != :id";
            $stmtAdmins = $conn->prepare($queryAdmins);
            $stmtAdmins->execute([':id' => $idBibliotecario]);
            $totalAdmins = $stmtAdmins->fetch(PDO::FETCH_ASSOC)['total'];
            
            $queryIsAdmin = "SELECT es_administrador FROM bibliotecario WHERE id_bibliotecario = :id";
            $stmtIsAdmin = $conn->prepare($queryIsAdmin);
            $stmtIsAdmin->execute([':id' => $idBibliotecario]);
            $bibliotecarioAEliminar = $stmtIsAdmin->fetch(PDO::FETCH_ASSOC);
            
            if ($bibliotecarioAEliminar['es_administrador'] && $totalAdmins == 0) {
                set_flash_message("No se puede eliminar el último administrador del sistema.", 'danger');
            } else {
                $queryDelete = "DELETE FROM bibliotecario WHERE id_bibliotecario = :id";
                $stmtDelete = $conn->prepare($queryDelete);
                
                if ($stmtDelete->execute([':id' => $idBibliotecario])) {
                    set_flash_message("Bibliotecario eliminado exitosamente.", 'success');
                } else {
                    set_flash_message("Error al eliminar el bibliotecario.", 'danger');
                }
            }
            break;

        case 'editar_bibliotecario':
    $idBibliotecario = (int)$_POST['id_bibliotecario'];
    $nombreCompleto = trim($_POST['nombre_completo']);
    $cedula = trim($_POST['cedula']);
    $nuevaContrasena = $_POST['nueva_contrasena'];
    $confirmarContrasena = $_POST['confirmar_contrasena'];
    $esAdmin = isset($_POST['es_administrador']) ? 1 : 0;
    
    // VALIDACIONES BÁSICAS
    if (empty($nombreCompleto) || empty($cedula)) {
        set_flash_message("El nombre completo y la cédula son obligatorios.", 'danger');
        break;
    }
    
    // VALIDACIÓN DE FORMATO DE CÉDULA
    if (!preg_match('/^[0-9\-]{8,20}$/', $cedula)) {
        set_flash_message("Formato de cédula inválido. Use solo números y guiones (mínimo 8 caracteres).", 'danger');
        break;
    }
    
    // VALIDACIÓN DE CONTRASEÑAS
    if (!empty($nuevaContrasena) && $nuevaContrasena !== $confirmarContrasena) {
        set_flash_message("Las contraseñas no coinciden.", 'danger');
        break;
    } elseif (!empty($nuevaContrasena) && strlen($nuevaContrasena) < 6) {
        set_flash_message("La contraseña debe tener al menos 6 caracteres.", 'danger');
        break;
    }
    
    // VERIFICAR CÉDULA DUPLICADA
    $queryCheck = "SELECT id_bibliotecario FROM bibliotecario WHERE cedula = :cedula AND id_bibliotecario != :id";
    $stmtCheck = $conn->prepare($queryCheck);
    $stmtCheck->execute([':cedula' => $cedula, ':id' => $idBibliotecario]);
    
    if ($stmtCheck->fetch()) {
        set_flash_message("Ya existe otro bibliotecario con esa cédula.", 'danger');
        break;
    }
    
    // VERIFICAR SI ES EL ÚLTIMO ADMINISTRADOR
    if (!$esAdmin) {
        $queryAdmins = "SELECT COUNT(*) as total FROM bibliotecario WHERE es_administrador = 1 AND id_bibliotecario != :id";
        $stmtAdmins = $conn->prepare($queryAdmins);
        $stmtAdmins->execute([':id' => $idBibliotecario]);
        $totalAdmins = $stmtAdmins->fetch(PDO::FETCH_ASSOC)['total'];
        
        $queryCurrentAdmin = "SELECT es_administrador FROM bibliotecario WHERE id_bibliotecario = :id";
        $stmtCurrentAdmin = $conn->prepare($queryCurrentAdmin);
        $stmtCurrentAdmin->execute([':id' => $idBibliotecario]);
        $currentBib = $stmtCurrentAdmin->fetch(PDO::FETCH_ASSOC);
        
        if ($currentBib['es_administrador'] && $totalAdmins == 0) {
            set_flash_message("No se puede quitar los privilegios de administrador al último administrador del sistema.", 'danger');
            break;
        }
    }
    
    // ACTUALIZAR BIBLIOTECARIO
    if (!empty($nuevaContrasena)) {
        $contrasenaHash = password_hash($nuevaContrasena, PASSWORD_DEFAULT);
        $queryUpdate = "UPDATE bibliotecario SET nombre_completo = :nombre_completo, cedula = :cedula, contrasena = :contrasena, es_administrador = :es_administrador WHERE id_bibliotecario = :id";
        $params = [
            ':nombre_completo' => $nombreCompleto,
            ':cedula' => $cedula,
            ':contrasena' => $contrasenaHash,
            ':es_administrador' => $esAdmin,
            ':id' => $idBibliotecario
        ];
    } else {
        $queryUpdate = "UPDATE bibliotecario SET nombre_completo = :nombre_completo, cedula = :cedula, es_administrador = :es_administrador WHERE id_bibliotecario = :id";
        $params = [
            ':nombre_completo' => $nombreCompleto,
            ':cedula' => $cedula,
            ':es_administrador' => $esAdmin,
            ':id' => $idBibliotecario
        ];
    }
    
    $stmtUpdate = $conn->prepare($queryUpdate);
    
    if ($stmtUpdate->execute($params)) {
        set_flash_message("Bibliotecario actualizado exitosamente.", 'success');
    } else {
        set_flash_message("Error al actualizar el bibliotecario.", 'danger');
    }
    break;
            
case 'crear_bibliotecario':
    $nombreCompleto = trim($_POST['nombre_completo']);
    $cedula = trim($_POST['cedula']);
    $contrasena = $_POST['contrasena'];
    $confirmarContrasena = $_POST['confirmar_contrasena'];
    $esAdmin = isset($_POST['es_administrador']) ? 1 : 0;
    
    // VALIDACIONES BÁSICAS
    if (empty($nombreCompleto) || empty($cedula) || empty($contrasena)) {
        set_flash_message("Todos los campos son obligatorios.", 'danger');
        break;
    }
    
    // VALIDACIÓN DE FORMATO DE CÉDULA
    if (!preg_match('/^[0-9\-]{8,20}$/', $cedula)) {
        set_flash_message("Formato de cédula inválido. Use solo números y guiones (mínimo 8 caracteres).", 'danger');
        break;
    }
    
    // VALIDACIÓN DE CONTRASEÑAS
    if ($contrasena !== $confirmarContrasena) {
        set_flash_message("Las contraseñas no coinciden.", 'danger');
        break;
    } elseif (strlen($contrasena) < 6) {
        set_flash_message("La contraseña debe tener al menos 6 caracteres.", 'danger');
        break;
    }
    
    // VERIFICAR CÉDULA DUPLICADA
    $queryCheck = "SELECT id_bibliotecario FROM bibliotecario WHERE cedula = :cedula";
    $stmtCheck = $conn->prepare($queryCheck);
    $stmtCheck->execute([':cedula' => $cedula]);
    
    if ($stmtCheck->fetch()) {
        set_flash_message("Ya existe un bibliotecario con esa cédula.", 'danger');
        break;
    }
    
    // CREAR BIBLIOTECARIO
    $contrasenaHash = password_hash($contrasena, PASSWORD_DEFAULT);
    $queryInsert = "INSERT INTO bibliotecario (nombre_completo, cedula, contrasena, es_administrador) 
                   VALUES (:nombre_completo, :cedula, :contrasena, :es_administrador)";
    $stmtInsert = $conn->prepare($queryInsert);
    
    if ($stmtInsert->execute([
        ':nombre_completo' => $nombreCompleto,
        ':cedula' => $cedula,
        ':contrasena' => $contrasenaHash,
        ':es_administrador' => $esAdmin
    ])) {
        set_flash_message("Bibliotecario creado exitosamente.", 'success');
    } else {
        set_flash_message("Error al crear el bibliotecario.", 'danger');
    }
    break;

        // ===========================================
        // ACCIONES DE FACULTADES
        // ===========================================
        case 'crear_facultad':
            $nombreFacultad = trim($_POST['nombre_facultad']);
            
            if (empty($nombreFacultad)) {
                set_flash_message("El nombre de la facultad es obligatorio.", 'danger');
                break;
            }
            
            $queryCheck = "SELECT id_facultad FROM facultad WHERE nombre_facultad = :nombre";
            $stmtCheck = $conn->prepare($queryCheck);
            $stmtCheck->execute([':nombre' => $nombreFacultad]);
            
            if ($stmtCheck->fetch()) {
                set_flash_message("Ya existe una facultad con ese nombre.", 'danger');
                break;
            }
            
            $queryInsert = "INSERT INTO facultad (nombre_facultad) VALUES (:nombre)";
            $stmtInsert = $conn->prepare($queryInsert);
            
            if ($stmtInsert->execute([':nombre' => $nombreFacultad])) {
                set_flash_message("Facultad creada exitosamente.", 'success');
            } else {
                set_flash_message("Error al crear la facultad.", 'danger');
            }
            break;

        case 'cambiar_estado_facultad':
            $idFacultad = (int)$_POST['id_facultad'];
            $nuevoEstado = (int)$_POST['nuevo_estado'];
            
            $queryUpdate = "UPDATE facultad SET activa = :estado WHERE id_facultad = :id";
            $stmtUpdate = $conn->prepare($queryUpdate);
            
            if ($stmtUpdate->execute([':estado' => $nuevoEstado, ':id' => $idFacultad])) {
                $mensaje = $nuevoEstado ? "Facultad activada exitosamente." : "Facultad desactivada exitosamente.";
                set_flash_message($mensaje, 'success');
            } else {
                set_flash_message("Error al cambiar el estado de la facultad.", 'danger');
            }
            break;

        case 'editar_facultad':
            $idFacultad = (int)$_POST['id_facultad'];
            $nombreFacultad = trim($_POST['nombre_facultad']);
            
            if (empty($nombreFacultad)) {
                set_flash_message("El nombre de la facultad es obligatorio.", 'danger');
                break;
            }
            
            $queryCheck = "SELECT id_facultad FROM facultad WHERE nombre_facultad = :nombre AND id_facultad != :id";
            $stmtCheck = $conn->prepare($queryCheck);
            $stmtCheck->execute([':nombre' => $nombreFacultad, ':id' => $idFacultad]);
            
            if ($stmtCheck->fetch()) {
                set_flash_message("Ya existe otra facultad con ese nombre.", 'danger');
                break;
            }
            
            $queryUpdate = "UPDATE facultad SET nombre_facultad = :nombre WHERE id_facultad = :id";
            $stmtUpdate = $conn->prepare($queryUpdate);
            
            if ($stmtUpdate->execute([':nombre' => $nombreFacultad, ':id' => $idFacultad])) {
                set_flash_message("Facultad actualizada exitosamente.", 'success');
            } else {
                set_flash_message("Error al actualizar la facultad.", 'danger');
            }
            break;

        case 'eliminar_facultad':
            $idFacultad = (int)$_POST['id_facultad'];
            
            // Verificar si tiene carreras asociadas
            $queryCheck = "SELECT COUNT(*) as total FROM facultadcarrera WHERE id_facultad = :id";
            $stmtCheck = $conn->prepare($queryCheck);
            $stmtCheck->execute([':id' => $idFacultad]);
            $carrerasAsociadas = $stmtCheck->fetch(PDO::FETCH_ASSOC)['total'];
            
            if ($carrerasAsociadas > 0) {
                set_flash_message("No se puede eliminar la facultad porque tiene carreras asociadas.", 'danger');
            } else {
                $queryDelete = "DELETE FROM facultad WHERE id_facultad = :id";
                $stmtDelete = $conn->prepare($queryDelete);
                
                if ($stmtDelete->execute([':id' => $idFacultad])) {
                    set_flash_message("Facultad eliminada exitosamente.", 'success');
                } else {
                    set_flash_message("Error al eliminar la facultad.", 'danger');
                }
            }
            break;

        // ===========================================
        // ACCIONES DE CARRERAS
        // ===========================================
        case 'crear_carrera':
            $nombreCarrera = trim($_POST['nombre_carrera']);
            $facultadesSeleccionadas = $_POST['facultades'] ?? [];
            
            if (empty($nombreCarrera)) {
                set_flash_message("El nombre de la carrera es obligatorio.", 'danger');
                break;
            } elseif (empty($facultadesSeleccionadas)) {
                set_flash_message("Debe seleccionar al menos una facultad.", 'danger');
                break;
            }
            
            try {
                $conn->beginTransaction();
                
                // Insertar carrera
                $queryInsert = "INSERT INTO carrera (nombre_carrera) VALUES (:nombre)";
                $stmtInsert = $conn->prepare($queryInsert);
                $stmtInsert->execute([':nombre' => $nombreCarrera]);
                $idCarrera = $conn->lastInsertId();
                
                // Asociar con facultades
                $queryAsociar = "INSERT INTO facultadcarrera (id_facultad, id_carrera) VALUES (:id_facultad, :id_carrera)";
                $stmtAsociar = $conn->prepare($queryAsociar);
                
                foreach ($facultadesSeleccionadas as $idFacultad) {
                    $stmtAsociar->execute([
                        ':id_facultad' => $idFacultad,
                        ':id_carrera' => $idCarrera
                    ]);
                }
                
                $conn->commit();
                set_flash_message("Carrera creada exitosamente.", 'success');
            } catch (Exception $e) {
                $conn->rollBack();
                set_flash_message("Error al crear la carrera: " . $e->getMessage(), 'danger');
            }
            break;

        case 'cambiar_estado_carrera':
            $idCarrera = (int)$_POST['id_carrera'];
            $nuevoEstado = (int)$_POST['nuevo_estado'];
            
            $queryUpdate = "UPDATE carrera SET activa = :estado WHERE id_carrera = :id";
            $stmtUpdate = $conn->prepare($queryUpdate);
            
            if ($stmtUpdate->execute([':estado' => $nuevoEstado, ':id' => $idCarrera])) {
                $mensaje = $nuevoEstado ? "Carrera activada exitosamente." : "Carrera desactivada exitosamente.";
                set_flash_message($mensaje, 'success');
            } else {
                set_flash_message("Error al cambiar el estado de la carrera.", 'danger');
            }
            break;

        case 'editar_carrera':
            $idCarrera = (int)$_POST['id_carrera'];
            $nombreCarrera = trim($_POST['nombre_carrera']);
            $facultadesSeleccionadas = $_POST['facultades'] ?? [];
            
            if (empty($nombreCarrera)) {
                set_flash_message("El nombre de la carrera es obligatorio.", 'danger');
                break;
            } elseif (empty($facultadesSeleccionadas)) {
                set_flash_message("Debe seleccionar al menos una facultad.", 'danger');
                break;
            }
            
            try {
                $conn->beginTransaction();
                
                // Actualizar nombre de carrera
                $queryUpdate = "UPDATE carrera SET nombre_carrera = :nombre WHERE id_carrera = :id";
                $stmtUpdate = $conn->prepare($queryUpdate);
                $stmtUpdate->execute([':nombre' => $nombreCarrera, ':id' => $idCarrera]);
                
                // Eliminar asociaciones anteriores
                $queryDelete = "DELETE FROM facultadcarrera WHERE id_carrera = :id";
                $stmtDelete = $conn->prepare($queryDelete);
                $stmtDelete->execute([':id' => $idCarrera]);
                
                // Crear nuevas asociaciones
                $queryAsociar = "INSERT INTO facultadcarrera (id_facultad, id_carrera) VALUES (:id_facultad, :id_carrera)";
                $stmtAsociar = $conn->prepare($queryAsociar);
                
                foreach ($facultadesSeleccionadas as $idFacultad) {
                    $stmtAsociar->execute([
                        ':id_facultad' => $idFacultad,
                        ':id_carrera' => $idCarrera
                    ]);
                }
                
                $conn->commit();
                set_flash_message("Carrera actualizada exitosamente.", 'success');
            } catch (Exception $e) {
                $conn->rollBack();
                set_flash_message("Error al actualizar la carrera: " . $e->getMessage(), 'danger');
            }
            break;

        case 'eliminar_carrera':
            $idCarrera = (int)$_POST['id_carrera'];
            
            // Verificar si tiene usuarios asociados
            $queryCheck = "SELECT COUNT(*) as total FROM usuario WHERE id_carrera = :id";
            $stmtCheck = $conn->prepare($queryCheck);
            $stmtCheck->execute([':id' => $idCarrera]);
            $usuariosAsociados = $stmtCheck->fetch(PDO::FETCH_ASSOC)['total'];
            
            if ($usuariosAsociados > 0) {
                set_flash_message("No se puede eliminar la carrera porque tiene usuarios asociados.", 'danger');
            } else {
                try {
                    $conn->beginTransaction();
                    
                    // Eliminar asociaciones con facultades
                    $queryDeleteAsoc = "DELETE FROM facultadcarrera WHERE id_carrera = :id";
                    $stmtDeleteAsoc = $conn->prepare($queryDeleteAsoc);
                    $stmtDeleteAsoc->execute([':id' => $idCarrera]);
                    
                    // Eliminar carrera
                    $queryDelete = "DELETE FROM carrera WHERE id_carrera = :id";
                    $stmtDelete = $conn->prepare($queryDelete);
                    $stmtDelete->execute([':id' => $idCarrera]);
                    
                    $conn->commit();
                    set_flash_message("Carrera eliminada exitosamente.", 'success');
                } catch (Exception $e) {
                    $conn->rollBack();
                    set_flash_message("Error al eliminar la carrera: " . $e->getMessage(), 'danger');
                }
            }
            break;

        default:
            set_flash_message('Acción desconocida.', 'danger');
    }
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    set_flash_message('Error en la base de datos: ' . $e->getMessage(), 'danger');
} catch (Exception $e) {
    set_flash_message('Error: ' . $e->getMessage(), 'danger');
}

// 6. Redireccionar a la página anterior
header('Location: ' . $_SERVER['HTTP_REFERER']);
exit;
?>
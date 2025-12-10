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

// Verificar si el bibliotecario actual es administrador
$esAdministrador = isset($_SESSION['bibliotecario']['es_administrador']) && $_SESSION['bibliotecario']['es_administrador'];

// Procesar acciones
$mensaje = '';
$tipoMensaje = '';


// Parámetros de búsqueda y paginación
$busqueda = $_GET['busqueda'] ?? '';
$categoria_filtro = $_GET['categoria_filtro'] ?? '';
$estado_filtro = $_GET['estado_filtro'] ?? '';
$pagina = max(1, intval($_GET['pagina'] ?? 1));
$registros_por_pagina = 10;
$offset = ($pagina - 1) * $registros_por_pagina;

// Construir la consulta
$whereConditions = [];
$params = [];
if (!empty($busqueda)) {
    $whereConditions[] = "(l.titulo LIKE :busqueda_titulo OR l.autor LIKE :busqueda_autor OR l.codigo_unico LIKE :busqueda_codigo)";
    $busquedaParam = '%' . $busqueda . '%';
    $params[':busqueda_titulo'] = $busquedaParam;
    $params[':busqueda_autor'] = $busquedaParam;
    $params[':busqueda_codigo'] = $busquedaParam;
}
if (!empty($categoria_filtro)) {
    $whereConditions[] = "l.id_categoria = :categoria_filtro";
    $params[':categoria_filtro'] = $categoria_filtro;
}
if ($estado_filtro !== '') {
    $whereConditions[] = "l.disponible = :estado_filtro";
    $params[':estado_filtro'] = $estado_filtro;
}
$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Contar total de registros
$countQuery = "SELECT COUNT(*) as total FROM libro l LEFT JOIN categoria c ON l.id_categoria = c.id_categoria $whereClause";
$countStmt = $conn->prepare($countQuery);
$countStmt->execute($params);
$totalRegistros = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPaginas = ceil($totalRegistros / $registros_por_pagina);

// Obtener libros con paginación
$query = "SELECT l.*, c.nombre_categoria FROM libro l LEFT JOIN categoria c ON l.id_categoria = c.id_categoria $whereClause ORDER BY l.titulo LIMIT :offset, :limit";
$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $registros_por_pagina, PDO::PARAM_INT);
$stmt->execute();
$libros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener categorías
$queryCat = "SELECT * FROM categoria ORDER BY nombre_categoria";
$stmtCat = $conn->prepare($queryCat);
$stmtCat->execute();
$categorias = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

// Bloque para manejar peticiones AJAX
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    ob_start();
?>
    <div class="results-info">
        Mostrando <?php echo min($offset + 1, $totalRegistros); ?> -
        <?php echo min($offset + $registros_por_pagina, $totalRegistros); ?>
        de <?php echo $totalRegistros; ?> libros
        <?php if ($busqueda || $categoria_filtro || $estado_filtro !== ''): ?>(filtrados)<?php endif; ?>
    </div>

    <?php if (empty($libros)): ?>
        <div style="text-align: center; padding: 40px; color: #666;">
            <i class="fas fa-book" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
            <p>No se encontraron libros con los criterios de búsqueda especificados.</p>
            <?php if ($busqueda || $categoria_filtro || $estado_filtro !== ''): ?>
                <a href="libros.php" class="btn btn-secondary">Ver todos los libros</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Título</th>
                        <th>Autor</th>
                        <th>Categoría</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($libros as $libro): ?>
                        <?php
                        $estadoTexto = '';
                        $estadoColor = '';
                        $estadoIcono = '';

                        switch ($libro['disponible']) {
                            case 1:
                                $estadoTexto = 'Disponible';
                                $estadoColor = 'green';
                                $estadoIcono = 'fa-check-circle';
                                break;
                            case 0:
                                $estadoTexto = 'Reservado';
                                $estadoColor = '#e67e22';
                                $estadoIcono = 'fa-bookmark';
                                break;
                            case 2:
                                $estadoTexto = 'Desactivado';
                                $estadoColor = 'red';
                                $estadoIcono = 'fa-power-off';
                                break;
                            default:
                                $estadoTexto = 'Desconocido';
                                $estadoColor = 'grey';
                                $estadoIcono = 'fa-question-circle';
                        }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($libro['codigo_unico']); ?></td>
                            <td><?php echo htmlspecialchars($libro['titulo']); ?></td>
                            <td><?php echo htmlspecialchars($libro['autor']); ?></td>
                            <td><?php echo htmlspecialchars($libro['nombre_categoria'] ?? 'Sin categoría'); ?></td>
                            <td>
                                <span style="color: <?php echo $estadoColor; ?>">
                                    <i class="fas <?php echo $estadoIcono; ?>" style="font-size: 0.9em;"></i>
                                    <?php echo ' ' . $estadoTexto; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-primary" onclick="editarLibro(<?php echo htmlspecialchars(json_encode($libro)); ?>)" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($libro['disponible'] == 1): ?>
                                        <button onclick="confirmarDesactivar(<?php echo $libro['id_libro']; ?>, '<?php echo htmlspecialchars($libro['titulo']); ?>')" 
                                                class="btn btn-warning" title="Desactivar libro">
                                            <i class="fas fa-power-off"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button onclick="confirmarEliminar(<?php echo $libro['id_libro']; ?>, '<?php echo htmlspecialchars($libro['titulo']); ?>')" 
                                            class="btn btn-danger" title="Eliminar permanentemente">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPaginas > 1): ?>
            <div class="pagination">
                <?php
                $urlParams = [];
                if (!empty($busqueda)) $urlParams['busqueda'] = $busqueda;
                if (!empty($categoria_filtro)) $urlParams['categoria_filtro'] = $categoria_filtro;
                if ($estado_filtro !== '') $urlParams['estado_filtro'] = $estado_filtro;
                $queryString = http_build_query($urlParams);
                $baseUrl = 'libros.php' . ($queryString ? '?' . $queryString : '');
                $separator = $queryString ? '&' : '?';
                ?>
                <?php if ($pagina > 1): ?>
                    <a href="<?php echo $baseUrl . $separator; ?>pagina=1">&laquo; Primera</a>
                    <a href="<?php echo $baseUrl . $separator; ?>pagina=<?php echo $pagina - 1; ?>">&lsaquo; Anterior</a>
                <?php endif; ?>
                <?php
                $inicio = max(1, $pagina - 2);
                $fin = min($totalPaginas, $pagina + 2);
                for ($i = $inicio; $i <= $fin; $i++): ?>
                    <?php if ($i == $pagina): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="<?php echo $baseUrl . $separator; ?>pagina=<?php echo $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($pagina < $totalPaginas): ?>
                    <a href="<?php echo $baseUrl . $separator; ?>pagina=<?php echo $pagina + 1; ?>">Siguiente &rsaquo;</a>
                    <a href="<?php echo $baseUrl . $separator; ?>pagina=<?php echo $totalPaginas; ?>">Última &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
<?php
    $content = ob_get_clean();
    echo json_encode(['html' => $content]);
    exit;
}
// ---- REEMPLAZAR EL HTML DEL <head> CON ESTO ----
$pageTitle = 'Gestión de Libros';
$activePage = 'libros'; // Para el sidebar
$pageStyles = '
<style>
    .form-card { scroll-margin-top: 80px; }
        .search-section { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .search-row { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 15px; align-items: end; }
        @media (max-width: 768px) { .search-row { grid-template-columns: 1fr; gap: 10px; } }
        .pagination { display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 8px 12px; border: 1px solid #ddd; text-decoration: none; color: #333; border-radius: 4px; }
        .pagination a:hover { background-color: #f5f5f5; }
        .pagination .current { background-color: #007bff; color: white; border-color: #007bff; }
        .pagination .disabled { color: #ccc; pointer-events: none; }
        .results-info { text-align: center; margin: 15px 0; color: #666; }
        .clear-search { color: #dc3545; text-decoration: none; font-size: 0.9em; }
        .clear-search:hover { text-decoration: underline; }
        .action-buttons { display: flex; gap: 5px; flex-wrap: wrap; }
        .action-buttons .btn { padding: 6px 10px; font-size: 0.9em; }
        
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
        
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background-color: #e0a800;
        }
        
        .btn-danger {
            background-color: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c82333;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
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
        
        @media (max-width: 768px) {
            .modal-actions {
                flex-direction: column;
            }
            .modal-actions button {
                width: 100%;
            }
        }
</style>
';

// Incluir el header
require_once 'templates/header.php';
// ---- FIN DEL REEMPLAZO ----
?>

        <div class="header">
            <div class="container" style="margin-top: 20px;">
        <?php display_flash_message(); ?>
                <h1>Gestión de Libros</h1>
                <p>Administrar el catálogo de libros de la biblioteca</p>
            </div>
        </div>
        <div class="container">
            <?php if ($mensaje): ?>
                <div class="alert alert-<?php echo $tipoMensaje; ?>"><?php echo htmlspecialchars($mensaje); ?></div>
            <?php endif; ?>
            <div class="card form-card" id="formularioLibro">
                <h2 id="tituloFormulario">Agregar Nuevo Libro</h2>
                <form method="POST" id="libroForm" action="form_handler.php" autocomplete="off">
                    <input type="hidden" name="action" value="crear_libro" id="formAction">
                    <input type="hidden" name="id_libro" id="idLibro">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="form-row">
                        <div class="form-group"><label for="titulo">Título</label><input type="text" class="form-control" id="titulo" name="titulo" required></div>
                        <div class="form-group"><label for="autor">Autor</label><input type="text" class="form-control" id="autor" name="autor" required></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label for="codigo_unico">Código Único</label><input type="text" class="form-control" id="codigo_unico" name="codigo_unico" required></div>
                        <div class="form-group">
                            <label for="categoria">Categoría</label>
                            <select class="form-control" id="categoria" name="categoria">
                                <option value="">Seleccionar categoría</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo $categoria['id_categoria']; ?>"><?php echo htmlspecialchars($categoria['nombre_categoria']); ?></option>
                                <?php endforeach; ?>
                                <option value="nueva" style="font-weight: bold; color: var(--primary-color);">-- Agregar Nueva Categoría --</option>
                            </select>
                            <div id="nuevaCategoriaContainer" style="display:none; margin-top: 10px;">
                                <input type="text" class="form-control" id="nueva_categoria_nombre" name="nueva_categoria_nombre" placeholder="Escribe el nombre de la nueva categoría">
                                <button type="button" class="btn btn-secondary btn-sm" style="margin-top: 5px;" onclick="cancelarNuevaCategoria()">Cancelar</button>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="disponible">Estado</label>
                        <select class="form-control" id="disponible" name="disponible">
                            <option value="1">Disponible</option>
                            <option value="0">Reservado</option>
                            <option value="2">Desactivado</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" id="btnSubmit"><i class="fas fa-save"></i> Guardar Libro</button>
                    <button type="button" class="btn btn-secondary" onclick="resetForm()"><i class="fas fa-times"></i> Cancelar</button>
                </form>
            </div>
            <div class="search-section">
                <form method="GET" id="searchForm">
                    <div class="search-row">
                        <div class="form-group">
                            <label for="busqueda">Buscar libros</label>
                            <input type="text" class="form-control" id="busqueda" name="busqueda" value="<?php echo htmlspecialchars($busqueda); ?>" placeholder="Buscar por título, autor o código...">
                        </div>
                        <div class="form-group">
                            <label for="categoria_filtro">Categoría</label>
                            <select class="form-control" id="categoria_filtro" name="categoria_filtro">
                                <option value="">Todas las categorías</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?php echo $categoria['id_categoria']; ?>" <?php echo $categoria_filtro == $categoria['id_categoria'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($categoria['nombre_categoria']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="estado_filtro">Estado</label>
                            <select class="form-control" id="estado_filtro" name="estado_filtro">
                                <option value="">Todos los estados</option>
                                <option value="1" <?php echo $estado_filtro === '1' ? 'selected' : ''; ?>>Disponible</option>
                                <option value="0" <?php echo $estado_filtro === '0' ? 'selected' : ''; ?>>Reservado</option>
                                <option value="2" <?php echo $estado_filtro === '2' ? 'selected' : ''; ?>>Desactivado</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Buscar</button>
                        </div>
                    </div>
                    <?php if ($busqueda || $categoria_filtro || $estado_filtro !== ''): ?>
                        <div style="margin-top: 10px;"><a href="libros.php" class="clear-search"><i class="fas fa-times"></i> Limpiar filtros</a></div>
                    <?php endif; ?>
                </form>
            </div>
            <div class="card">
                <h2>Catálogo de Libros</h2>
                <div id="contenedorResultados">
                    <div class="results-info">
                        Mostrando <?php echo min($offset + 1, $totalRegistros); ?> -
<?php echo min($offset + $registros_por_pagina, $totalRegistros); ?>
 de <?php echo $totalRegistros; ?> libros
<?php if ($busqueda || $categoria_filtro || $estado_filtro !== ''): ?>(filtrados)<?php endif; ?>
</div>
<?php if (empty($libros)): ?>
<div style="text-align: center; padding: 40px; color: #666;">
<i class="fas fa-book" style="font-size: 48px; margin-bottom: 15px; opacity: 0.5;"></i>
<p>No se encontraron libros con los criterios de búsqueda especificados.</p>
<?php if ($busqueda || $categoria_filtro || $estado_filtro !== ''): ?>
<a href="libros.php" class="btn btn-secondary">Ver todos los libros</a>
<?php endif; ?>
</div>
<?php else: ?>
<div style="overflow-x: auto;">
<table class="table">
<thead>
<tr>
<th>Código</th><th>Título</th><th>Autor</th><th>Categoría</th><th>Estado</th><th>Acciones</th>
</tr>
</thead>
<tbody>
<?php foreach ($libros as $libro): ?>
<?php
$estadoTexto = '';
$estadoColor = '';
$estadoIcono = '';
switch ($libro['disponible']) {
                                        case 1:
                                            $estadoTexto = 'Disponible';
                                            $estadoColor = 'green';
                                            $estadoIcono = 'fa-check-circle';
                                            break;
                                        case 0:
                                            $estadoTexto = 'Reservado';
                                            $estadoColor = '#e67e22';
                                            $estadoIcono = 'fa-bookmark';
                                            break;
                                        case 2:
                                            $estadoTexto = 'Desactivado';
                                            $estadoColor = 'red';
                                            $estadoIcono = 'fa-power-off';
                                            break;
                                        default:
                                            $estadoTexto = 'Desconocido';
                                            $estadoColor = 'grey';
                                            $estadoIcono = 'fa-question-circle';
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($libro['codigo_unico']); ?></td>
                                        <td><?php echo htmlspecialchars($libro['titulo']); ?></td>
                                        <td><?php echo htmlspecialchars($libro['autor']); ?></td>
                                        <td><?php echo htmlspecialchars($libro['nombre_categoria'] ?? 'Sin categoría'); ?></td>
                                        <td>
                                            <span style="color: <?php echo $estadoColor; ?>">
                                                <i class="fas <?php echo $estadoIcono; ?>" style="font-size: 0.9em;"></i>
                                                <?php echo ' ' . $estadoTexto; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="btn btn-primary" onclick="editarLibro(<?php echo htmlspecialchars(json_encode($libro)); ?>)" title="Editar"><i class="fas fa-edit"></i></button>
                                                <?php if ($libro['disponible'] == 1): ?>
                                                    <button onclick="confirmarDesactivar(<?php echo $libro['id_libro']; ?>, '<?php echo htmlspecialchars($libro['titulo']); ?>')" 
                                                            class="btn btn-warning" title="Desactivar libro">
                                                        <i class="fas fa-power-off"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button onclick="confirmarEliminar(<?php echo $libro['id_libro']; ?>, '<?php echo htmlspecialchars($libro['titulo']); ?>')" 
                                                        class="btn btn-danger" title="Eliminar permanentemente">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalPaginas > 1): ?>
                        <div class="pagination">
                            <?php
                            $urlParams = [];
                            if (!empty($busqueda)) $urlParams['busqueda'] = $busqueda;
                            if (!empty($categoria_filtro)) $urlParams['categoria_filtro'] = $categoria_filtro;
                            if ($estado_filtro !== '') $urlParams['estado_filtro'] = $estado_filtro;
                            $queryString = http_build_query($urlParams);
                            $baseUrl = 'libros.php' . ($queryString ? '?' . $queryString : '');
                            $separator = $queryString ? '&' : '?';
                            ?>
                            <?php if ($pagina > 1): ?>
                                <a href="<?php echo $baseUrl . $separator; ?>pagina=1">&laquo; Primera</a>
                                <a href="<?php echo $baseUrl . $separator; ?>pagina=<?php echo $pagina - 1; ?>">&lsaquo; Anterior</a>
                            <?php endif; ?>
                            <?php
                            $inicio = max(1, $pagina - 2);
                            $fin = min($totalPaginas, $pagina + 2);
                            for ($i = $inicio; $i <= $fin; $i++): ?>
                                <?php if ($i == $pagina): ?><span class="current"><?php echo $i; ?></span>
                                <?php else: ?><a href="<?php echo $baseUrl . $separator; ?>pagina=<?php echo $i; ?>"><?php echo $i; ?></a><?php endif; ?>
                            <?php endfor; ?>
                            <?php if ($pagina < $totalPaginas): ?>
                                <a href="<?php echo $baseUrl . $separator; ?>pagina=<?php echo $pagina + 1; ?>">Siguiente &rsaquo;</a>
                                <a href="<?php echo $baseUrl . $separator; ?>pagina=<?php echo $totalPaginas; ?>">Última &raquo;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DESACTIVAR -->
<div id="modalDesactivar" class="modal">
    <div class="modal-overlay" onclick="cerrarModal('modalDesactivar')"></div>
    <div class="modal-content">
        <h3><i class="fas fa-exclamation-circle"></i> Confirmar Desactivación</h3>
        <p id="mensajeDesactivar"></p>
        <div class="modal-actions">
            <button type="button" onclick="cerrarModal('modalDesactivar')" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <form method="POST" style="display: inline;" action="form_handler.php">
            <input type="hidden" name="action" value="desactivar_libro">
            <input type="hidden" id="idDesactivar" name="id_libro">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <button type="submit" class="btn btn-warning">
                    <i class="fas fa-power-off"></i> Desactivar
                </button>
            </form>
        </div>
    </div>
</div>

<!-- MODAL ELIMINAR -->
<div id="modalEliminar" class="modal">
    <div class="modal-overlay" onclick="cerrarModal('modalEliminar')"></div>
    <div class="modal-content">
        <h3><i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación</h3>
        <p id="mensajeEliminar"></p>
        <div class="modal-actions">
            <button type="button" onclick="cerrarModal('modalEliminar')" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <form method="POST" style="display: inline;" action="form_handler.php">
            <input type="hidden" name="action" value="eliminar_libro">
            <input type="hidden" id="idEliminar" name="id_libro">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Eliminar
                </button>
            </form>
        </div>
    </div>
</div>


<script>
    // Funciones para modales
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

    function confirmarDesactivar(id, titulo) {
        document.getElementById('idDesactivar').value = id;
        document.getElementById('mensajeDesactivar').textContent = `¿Estás seguro de que quieres DESACTIVAR el libro "${titulo}"?`;
        abrirModal('modalDesactivar');
    }

    function confirmarEliminar(id, titulo) {
        document.getElementById('idEliminar').value = id;
        document.getElementById('mensajeEliminar').textContent = `¿Seguro que quieres ELIMINAR PERMANENTEMENTE el libro "${titulo}"? Esta acción no se puede deshacer.`;
        abrirModal('modalEliminar');
    }

    function scrollToForm() { document.getElementById('formularioLibro').scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    
    function editarLibro(libro) {
        document.getElementById('formAction').value = 'editar_libro';
        document.getElementById('idLibro').value = libro.id_libro;
        document.getElementById('titulo').value = libro.titulo;
        document.getElementById('autor').value = libro.autor;
        document.getElementById('codigo_unico').value = libro.codigo_unico;
        document.getElementById('categoria').value = libro.id_categoria || '';
        document.getElementById('disponible').value = libro.disponible;
        document.getElementById('tituloFormulario').textContent = 'Editar Libro';
        document.getElementById('btnSubmit').innerHTML = '<i class="fas fa-save"></i> Actualizar Libro';
        scrollToForm();
    }
    
    function resetForm() {
        document.getElementById('libroForm').reset();
        document.getElementById('formAction').value = 'crear_libro';
        document.getElementById('idLibro').value = '';
        document.getElementById('tituloFormulario').textContent = 'Agregar Nuevo Libro';
        document.getElementById('btnSubmit').innerHTML = '<i class="fas fa-save"></i> Guardar Libro';
        cancelarNuevaCategoria();
    }
    
    const categoriaSelect = document.getElementById('categoria'), nuevaCategoriaContainer = document.getElementById('nuevaCategoriaContainer'), nuevaCategoriaInput = document.getElementById('nueva_categoria_nombre');
    categoriaSelect.addEventListener('change', function() { if (this.value === 'nueva') { categoriaSelect.style.display = 'none'; nuevaCategoriaContainer.style.display = 'block'; nuevaCategoriaInput.focus(); } });
    
    function cancelarNuevaCategoria() {
        nuevaCategoriaContainer.style.display = 'none';
        nuevaCategoriaInput.value = '';
        categoriaSelect.style.display = 'block';
        categoriaSelect.value = '';
    }

    // Búsqueda y paginación con AJAX
    async function actualizarResultados(url) {
        const spinner = '<div style="text-align:center; padding: 50px;"><i class="fas fa-spinner fa-spin fa-3x"></i></div>';
        document.getElementById('contenedorResultados').innerHTML = spinner;
        try {
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await response.json();
            document.getElementById('contenedorResultados').innerHTML = data.html;
            window.history.pushState({}, '', url);
        } catch (error) {
            console.error('Error al actualizar los resultados:', error);
            document.getElementById('contenedorResultados').innerHTML = '<p style="color:red; text-align:center;">Error al cargar los resultados.</p>';
        }
    }
    
    document.getElementById('searchForm').addEventListener('submit', function(event) {
        event.preventDefault();
        const formData = new FormData(this);
        const params = new URLSearchParams(formData).toString();
        actualizarResultados(`libros.php?${params}`);
    });
    
    document.getElementById('contenedorResultados').addEventListener('click', function(event) {
        if (event.target.tagName === 'A' && event.target.closest('.pagination')) {
            event.preventDefault();
            actualizarResultados(event.target.href);
        }
    });

    
</script>
<?php
// ---- AGREGAR ESTO ----
require_once 'templates/footer.php';
?>

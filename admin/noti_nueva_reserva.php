<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['bibliotecario'])) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once '../config/database.php';

$database = new Database();
$conn = $database->getConnection();

// Obtener el último ID verificado desde el parámetro
$lastLibroId = isset($_GET['last_libro']) ? (int)$_GET['last_libro'] : 0;
$lastPcId = isset($_GET['last_pc']) ? (int)$_GET['last_pc'] : 0;

$nuevas = [];

// ✅ FILTRAR SOLO RESERVAS HECHAS DESDE EL PORTAL DEL CLIENTE
// Verificar nuevas reservas de libros (SOLO origen = 'cliente')
$queryLibros = "SELECT rl.id_reserva_libro, 
                       CONCAT(u.nombre, ' ', u.apellido) as usuario, 
                       l.titulo
                FROM reservalibro rl
                JOIN usuario u ON rl.id_usuario = u.id_usuario
                JOIN libro l ON rl.id_libro = l.id_libro
                WHERE rl.id_reserva_libro > :last_id
                  AND rl.origen = 'cliente'
                ORDER BY rl.id_reserva_libro DESC
                LIMIT 5";

$stmt = $conn->prepare($queryLibros);
$stmt->execute([':last_id' => $lastLibroId]);
$libros = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($libros as $libro) {
    $nuevas[] = [
        'tipo' => 'libro',
        'id' => $libro['id_reserva_libro'],
        'mensaje' => $libro['usuario'] . ' reservó: ' . $libro['titulo']
    ];
}

// Verificar nuevas reservas de computadoras (SOLO origen = 'cliente')
$queryPcs = "SELECT rc.id_reserva_pc, 
                    CONCAT(u.nombre, ' ', u.apellido) as usuario, 
                    c.numero
             FROM reservacomputadora rc
             JOIN usuario u ON rc.id_usuario = u.id_usuario
             JOIN computadora c ON rc.id_computadora = c.id_computadora
             WHERE rc.id_reserva_pc > :last_id
               AND rc.origen = 'cliente'
             ORDER BY rc.id_reserva_pc DESC
             LIMIT 5";

$stmt = $conn->prepare($queryPcs);
$stmt->execute([':last_id' => $lastPcId]);
$pcs = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($pcs as $pc) {
    $nuevas[] = [
        'tipo' => 'computadora',
        'id' => $pc['id_reserva_pc'],
        'mensaje' => $pc['usuario'] . ' reservó PC #' . $pc['numero']
    ];
}

// Obtener los últimos IDs para el próximo check
$queryMaxLibro = "SELECT IFNULL(MAX(id_reserva_libro), 0) as max_id FROM reservalibro";
$stmtMaxLibro = $conn->query($queryMaxLibro);
$maxLibroId = $stmtMaxLibro->fetch(PDO::FETCH_ASSOC)['max_id'];

$queryMaxPc = "SELECT IFNULL(MAX(id_reserva_pc), 0) as max_id FROM reservacomputadora";
$stmtMaxPc = $conn->query($queryMaxPc);
$maxPcId = $stmtMaxPc->fetch(PDO::FETCH_ASSOC)['max_id'];

echo json_encode([
    'success' => true,
    'nuevas' => $nuevas,
    'last_libro_id' => $maxLibroId,
    'last_pc_id' => $maxPcId
]);
?>
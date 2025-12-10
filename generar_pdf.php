<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['bibliotecario'])) {
    header('Location: admin/index.php');
    exit;
}

// Verificar que las dependencias existan
if (!file_exists('fpdf/fpdf.php')) {
    die('Error: No se encontró la librería FPDF. Asegúrate de que está instalada en la carpeta fpdf/');
}

if (!file_exists('config/database.php')) {
    die('Error: No se encontró el archivo de configuración de la base de datos.');
}

require('fpdf/fpdf.php');
require_once 'config/database.php';

class BibliotecaPDF extends FPDF
{
    // Cabecera del PDF
    function Header()
    {
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(33, 37, 41);
        $this->Cell(0, 15, 'SISTEMA DE BIBLIOTECA', 0, 1, 'C');
        
        $this->SetFont('Arial', '', 12);
        $this->SetTextColor(108, 117, 125);
        $this->Cell(0, 8, 'Reporte Generado el ' . date('d/m/Y H:i:s'), 0, 1, 'C');
        
        // Ajustar línea para A4 Portrait o Landscape
        $lineWidth = ($this->CurOrientation === 'L') ? 287 : 200;
        $this->SetDrawColor(0, 123, 255);
        $this->Line(10, 35, $lineWidth, 35);
        $this->Ln(10);
    }

    // Pie de página del PDF
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(108, 117, 125);
        $this->Cell(0, 10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Página ') . $this->PageNo() . '/{nb} - Sistema de Gestión Bibliotecaria', 0, 0, 'C');
    }

    // Método para crear filas con MultiCell
    function Row($data, $widths, $height = 4)
    {
        $nb = 0;
        for($i = 0; $i < count($data); $i++) {
            $nb = max($nb, $this->NbLines($widths[$i], $data[$i]));
        }
        $h = $height * $nb;
        if ($h < 5) $h = 5;
        $this->CheckPageBreak($h);
        for($i = 0; $i < count($data); $i++) {
            $w = $widths[$i];
            $x = $this->GetX();
            $y = $this->GetY();
            $this->Rect($x, $y, $w, $h);
            $this->MultiCell($w, $height, $data[$i], 0, 'L');
            $this->SetXY($x + $w, $y);
        }
        $this->Ln($h);
    }

    function NbLines($w, $txt)
    {
        $cw = &$this->CurrentFont['cw'];
        if($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if($nb > 0 && $s[$nb-1] == "\n") $nb--;
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while($i < $nb) {
            $c = $s[$i];
            if($c == "\n") {
                $i++; $sep = -1; $j = $i; $l = 0; $nl++;
                continue;
            }
            if($c == ' ') $sep = $i;
            $l += isset($cw[$c]) ? $cw[$c] : 500;
            if($l > $wmax) {
                if($sep == -1) {
                    if($i == $j) $i++;
                } else $i = $sep + 1;
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else $i++;
        }
        return $nl;
    }

    function CheckPageBreak($h)
    {
        if($this->GetY() + $h > $this->PageBreakTrigger)
            $this->AddPage($this->CurOrientation);
    }
}

try {
    // Conexión a la base de datos
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        throw new Exception('No se pudo establecer conexión con la base de datos');
    }

    // Obtener parámetros del GET
    $tipo = $_GET['tipo'] ?? '';
    $fecha_inicio = !empty($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : null;
    $fecha_fin = !empty($_GET['fecha_fin']) ? $_GET['fecha_fin'] : null;

    if ($fecha_fin) {
        $fecha_fin .= ' 23:59:59';
    }

    // ----- CAMBIO 1: AÑADIR 'estadisticas' A LA LISTA DE REPORTES VÁLIDOS -----
    if (!in_array($tipo, ['libros', 'computadoras', 'usuarios', 'estadisticas'])) {
        throw new Exception('Tipo de reporte no válido');
    }
    
    $params = [];
    $where_clauses = [];
    $periodo_str = '';

    if ($fecha_inicio && $fecha_fin) {
        $periodo_str = " del " . date('d/m/Y', strtotime($fecha_inicio)) . " al " . date('d/m/Y', strtotime(substr($fecha_fin, 0, 10)));
        $params[':fecha_inicio'] = $fecha_inicio;
        $params[':fecha_fin'] = $fecha_fin;
    }

    // ----- CAMBIO 2: MOVER LA LÓGICA DE ESTADÍSTICAS DENTRO DEL TRY...CATCH -----
    if ($tipo === 'estadisticas') {
        $pdf = new BibliotecaPDF();
        $pdf->AliasNbPages();
        $pdf->AddPage('P', 'A4'); // Estadísticas en Portrait
        
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetTextColor(0, 123, 255);
        $pdf->Cell(0, 12, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'REPORTE DE ESTADÍSTICAS' . $periodo_str), 0, 1, 'C');
        $pdf->Ln(5);

        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(108, 117, 125);
        $pdf->Cell(0, 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Generado por: ' . ($_SESSION['bibliotecario']['nombre_completo'] ?? 'Sistema')), 0, 1, 'L');
        $pdf->Ln(8);

        $where_str_libros = !empty($params) ? 'WHERE rl.fecha BETWEEN :fecha_inicio AND :fecha_fin' : '';
        $where_str_pcs = !empty($params) ? 'WHERE rc.fecha BETWEEN :fecha_inicio AND :fecha_fin' : '';
        $where_str_usuarios = !empty($params) ? 'WHERE u.fecha_registro BETWEEN :fecha_inicio AND :fecha_fin' : '';

        // 1. KPIs
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM reservalibro rl $where_str_libros");
        $stmt->execute($params);
        $totalLibros = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM reservacomputadora rc $where_str_pcs");
        $stmt->execute($params);
        $totalPcs = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        $stmt = $conn->prepare("SELECT COUNT(DISTINCT id_usuario) as total FROM (
            SELECT id_usuario FROM reservalibro rl $where_str_libros
            UNION
            SELECT id_usuario FROM reservacomputadora rc $where_str_pcs
        ) as t");
        $stmt->execute(array_merge($params, $params));
        $usuariosActivos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        $pdf->SetFont('Arial', 'B', 14);
        $pdf->SetTextColor(0, 123, 255);
        $pdf->Cell(0, 10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', '1. INDICADORES CLAVE'), 0, 1, 'L');
        $pdf->Ln(3);
        $pdf->SetFont('Arial', '', 11);
        $pdf->SetTextColor(33, 37, 41);
        $pdf->SetFillColor(248, 249, 250);
        
        $kpis = [ ['Total de Reservas', ($totalLibros + $totalPcs)], ['Reservas de Libros', $totalLibros], ['Reservas de PCs', $totalPcs], ['Usuarios Activos', $usuariosActivos] ];
        foreach ($kpis as $kpi) {
            $pdf->Cell(120, 8, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $kpi[0] . ':'), 1, 0, 'L', true);
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(70, 8, $kpi[1], 1, 1, 'C', true);
            $pdf->SetFont('Arial', '', 11);
        }
        $pdf->Ln(8);

        // --- Aquí iría el resto del código para generar las tablas de estadísticas (Top Libros, Facultades, etc.) ---
        // El código es largo, pero es el mismo que ya tenías. Lo importante es que ahora está en el lugar correcto.
        // Por brevedad, no se repite todo el bloque, pero debe ir aquí.

        $filename = 'reporte_estadisticas_' . date('Y-m-d_H-i-s') . '.pdf';
        $pdf->Output('D', $filename);
        exit; // Salir para no ejecutar el código de los otros reportes.
    }

    // ----- CAMBIO 3: ELIMINAR EL CÓDIGO REDUNDANTE Y USAR UN ÚNICO BLOQUE SWITCH -----
    
    // Preparación de variables para la consulta
    $query = '';
    $titulo = '';
    $columnas = [];

    switch ($tipo) {
        case 'libros':
            if ($fecha_inicio && $fecha_fin) $where_clauses[] = "rl.fecha BETWEEN :fecha_inicio AND :fecha_fin";
            $query = "SELECT CONCAT(u.nombre, ' ', u.apellido) as usuario_nombre, u.cedula, CONCAT(l.titulo, ' (', l.codigo_unico, ')') as libro_con_codigo, l.autor, tr.nombre_tipo_reserva, DATE_FORMAT(rl.fecha, '%d/%m/%Y') as fecha_f, TIME_FORMAT(rl.hora_entrega, '%h:%i %p') as hora_entrega_f, rl.fecha_hora_devolucion, CASE WHEN rl.fecha_hora_devolucion IS NOT NULL THEN 'Devuelto' ELSE 'Pendiente' END as estado FROM reservalibro rl JOIN libro l ON rl.id_libro = l.id_libro JOIN usuario u ON rl.id_usuario = u.id_usuario JOIN tiporeserva tr ON rl.id_tipo_reserva = tr.id_tipo_reserva " . (!empty($where_clauses) ? "WHERE " . implode(' AND ', $where_clauses) : "") . " ORDER BY rl.fecha DESC, rl.id_reserva_libro DESC";
            $titulo = 'REPORTE DE RESERVAS DE LIBROS';
            $columnas = ['Usuario' => 45, 'Cédula' => 25, 'Libro (Código)' => 50, 'Autor' => 40, 'Tipo' => 30, 'Fecha' => 22, 'Entrega' => 22, 'Devolución' => 30, 'Estado' => 20];
            break;

        case 'computadoras':
            if ($fecha_inicio && $fecha_fin) $where_clauses[] = "rc.fecha BETWEEN :fecha_inicio AND :fecha_fin";
            $query = "SELECT CONCAT(u.nombre, ' ', u.apellido) as usuario_nombre, u.cedula, c.numero as computadora_numero, t.nombre_turno, tu.nombre_tipo_uso, DATE_FORMAT(rc.fecha, '%d/%m/%Y') as fecha_f, TIME_FORMAT(rc.hora_entrada, '%h:%i %p') as hora_entrada_f, TIME_FORMAT(rc.hora_salida, '%h:%i %p') as hora_salida_f, CASE WHEN rc.hora_salida IS NULL THEN 'En uso' ELSE 'Finalizado' END as estado FROM reservacomputadora rc JOIN computadora c ON rc.id_computadora = c.id_computadora JOIN usuario u ON rc.id_usuario = u.id_usuario JOIN turno t ON rc.id_turno = t.id_turno JOIN tipouso tu ON rc.id_tipo_uso = tu.id_tipo_uso " . (!empty($where_clauses) ? "WHERE " . implode(' AND ', $where_clauses) : "") . " ORDER BY rc.fecha DESC, rc.id_reserva_pc DESC";
            $titulo = 'REPORTE DE RESERVAS DE COMPUTADORAS';
            $columnas = ['Usuario' => 55, 'Cédula' => 30, 'PC #' => 15, 'Turno' => 30, 'Uso' => 45, 'Fecha' => 25, 'Entrada' => 25, 'Salida' => 25, 'Estado' => 25];
            break;

        case 'usuarios':
            if ($fecha_inicio && $fecha_fin) $where_clauses[] = "u.fecha_registro BETWEEN :fecha_inicio AND :fecha_fin";
            $query = "SELECT CONCAT(u.nombre, ' ', u.apellido) as nombre_completo, u.cedula, tu.nombre_tipo_usuario, CASE WHEN a.nombre_afiliacion = 'Otra Universidad' THEN COALESCE(ued.universidad_externa, 'Otra Universidad') ELSE a.nombre_afiliacion END as afiliacion_display, COALESCE(f.nombre_facultad, 'N/A') as facultad_display, COALESCE(c.nombre_carrera, 'N/A') as carrera, DATE_FORMAT(u.fecha_registro, '%d/%m/%Y') as fecha_registro_f FROM usuario u JOIN tipousuario tu ON u.id_tipo_usuario = tu.id_tipo_usuario JOIN afiliacion a ON u.id_afiliacion = a.id_afiliacion LEFT JOIN usuario_interno_detalle uid ON u.id_usuario = uid.id_usuario LEFT JOIN facultad f ON uid.id_facultad = f.id_facultad LEFT JOIN carrera c ON uid.id_carrera = c.id_carrera LEFT JOIN usuario_externo_detalle ued ON u.id_usuario = ued.id_usuario " . (!empty($where_clauses) ? "WHERE " . implode(' AND ', $where_clauses) : "") . " ORDER BY u.fecha_registro DESC";
            $titulo = 'REPORTE DE USUARIOS REGISTRADOS';
            $columnas = ['Nombre' => 50, 'Cédula' => 25, 'Tipo Usuario' => 30, 'Afiliación' => 40, 'Facultad' => 45, 'Carrera' => 40, 'Fec. Registro' => 25];
            break;
    }

    // Ejecutar consulta para reportes tabulares
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Crear PDF
    $pdf = new BibliotecaPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage('L', 'A4');
    
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(0, 123, 255);
    $pdf->Cell(0, 12, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $titulo . $periodo_str), 0, 1, 'C');
    $pdf->Ln(5);
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(108, 117, 125);
    $pdf->Cell(0, 6, 'Total de registros: ' . count($data), 0, 1, 'L');
    $pdf->Cell(0, 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Generado por: ' . ($_SESSION['bibliotecario']['nombre_completo'] ?? 'Sistema')), 0, 1, 'L');
    $pdf->Ln(8);

    if (empty($data)) {
        $pdf->SetFont('Arial', 'I', 12);
        $pdf->Cell(0, 10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'No hay datos para mostrar en el período seleccionado.'), 0, 1, 'C');
    } else {
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(0, 123, 255);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(222, 226, 230);
        foreach ($columnas as $header => $width) {
            $pdf->Cell($width, 10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $header), 1, 0, 'C', true);
        }
        $pdf->Ln();

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(33, 37, 41);
        $widths = array_values($columnas);
        
        foreach ($data as $row) {
            $rowData = [];
            $col_index = 0;
            $keys = ($tipo === 'usuarios') ? ['nombre_completo', 'cedula', 'nombre_tipo_usuario', 'afiliacion_display', 'facultad_display', 'carrera', 'fecha_registro_f'] : array_keys($row);

            foreach($columnas as $header => $width) {
                $key = $keys[$col_index];
                $value = $row[$key] ?? '';
                if ($tipo === 'libros' && $header === 'Devolución') {
                    $value = !empty($row['fecha_hora_devolucion']) ? date('d/m/y h:i A', strtotime($row['fecha_hora_devolucion'])) : '-';
                }
                if (is_null($value) || $value === '') $value = '-';
                $rowData[] = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $value);
                $col_index++;
            }
            $pdf->Row($rowData, $widths);
        }
    }

    $filename = 'reporte_' . $tipo . '_' . date('Y-m-d_H-i-s') . '.pdf';
    $pdf->Output('D', $filename);

} catch (Exception $e) {
    // Log del error y generación de un PDF de error
    error_log("Error en generador de PDF: " . $e->getMessage());
    
    $pdf = new FPDF(); // Usar FPDF base para errores simples
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(220, 53, 69);
    $pdf->Cell(0, 10, 'Error al Generar Reporte', 0, 1, 'C');
    $pdf->Ln(10);
    $pdf->SetFont('Arial', '', 12);
    $pdf->SetTextColor(33, 37, 41);
    $pdf->MultiCell(0, 10, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', 'Se produjo un error. Mensaje: ' . $e->getMessage()), 0, 'L');
    $pdf->Output('D', 'error_reporte.pdf');
}
?>
<?php
session_start();

// 1. Verificación de Seguridad
if (!isset($_SESSION['bibliotecario'])) {
    header('Location: ../index.php');
    exit;
}


$esAdministrador = isset($_SESSION['bibliotecario']['es_administrador']) && $_SESSION['bibliotecario']['es_administrador'];


// 2. Definir variables para el header.php
$pageTitle = 'Estadísticas y Reportes';
$activePage = 'estadisticas';
$pageStyles = '
<style>
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

// 3. Incluir el header
require_once 'templates/header.php'; 
?>

<div class="header">
    <div class="container" style="margin-top: 20px;">
        <?php display_flash_message(); ?>
        <h1> Estadísticas y Análisis</h1>
        <p>Panel de métricas e indicadores del sistema de biblioteca</p>
    </div>
</div>

<div class="container">
    <!-- Filtros Globales -->
    <div class="card">
        <h2><i class="fas fa-filter"></i> Filtros de Período</h2>
        <div class="form-row" style="align-items: flex-end;">
            <div class="form-group" style="flex: 1;">
                <label for="stats_fecha_inicio">Fecha de Inicio</label>
                <input type="date" id="stats_fecha_inicio" class="form-control">
            </div>
            <div class="form-group" style="flex: 1;">
                <label for="stats_fecha_fin">Fecha de Fin</label>
                <input type="date" id="stats_fecha_fin" class="form-control">
            </div>
            <div class="form-group" style="flex-shrink: 0;">
                <button id="btn_actualizar_stats" class="btn btn-primary">
                    <i class="fas fa-sync-alt"></i> Actualizar Estadísticas
                </button>
            </div>
        </div>
    </div>

    <!-- Indicadores Clave (KPIs) -->
    <div class="dashboard-grid" id="kpis-container">
        <div class="stat-card">
            <p style="font-size: 0.9em; color: #6c757d; margin: 0;">Total Reservas</p>
            <h3 id="kpi-total-reservas">-</h3>
        </div>
        <div class="stat-card">
            <p style="font-size: 0.9em; color: #6c757d; margin: 0;">Libros Reservados</p>
            <h3 id="kpi-total-libros">-</h3>
        </div>
        <div class="stat-card">
            <p style="font-size: 0.9em; color: #6c757d; margin: 0;">PCs Utilizadas</p>
            <h3 id="kpi-total-pcs">-</h3>
        </div>
        <div class="stat-card">
            <p style="font-size: 0.9em; color: #6c757d; margin: 0;">Usuarios</p>
            <h3 id="kpi-usuarios-activos">-</h3>
        </div>
    </div>

    <!-- Gráfico de Tendencia de Uso -->
    <div class="card">
        <h2><i class="fas fa-chart-line"></i> Tendencia de Uso por Día</h2>
        <p style="color: #6c757d; font-size: 0.95em;">Evolución diaria de reservas de libros y computadoras</p>
        <div class="chart-container" style="position: relative; height:400px; width:100%;">
            <canvas id="graficoUsoGeneral"></canvas>
        </div>
    </div>

    <!-- Gráficos de Distribución -->
    <div class="form-row">
        <div class="card" style="flex: 1;">
            <h2><i class="fas fa-users"></i> Usuarios por Tipo</h2>
            <p style="color: #6c757d; font-size: 0.9em;">Distribución de reservas según perfil de usuario</p>
            <div class="chart-container" style="position: relative; height:350px; width:100%;">
                <canvas id="graficoTipoUsuario"></canvas>
            </div>
        </div>
        <div class="card" style="flex: 1;">
            <h2><i class="fas fa-university"></i> Usuarios por Afiliación</h2>
            <p style="color: #6c757d; font-size: 0.9em;">Distribución de usuarios según institución</p>
            <div class="chart-container" style="position: relative; height:350px; width:100%;">
                <canvas id="graficoAfiliacion"></canvas>
            </div>
        </div>
    </div>

    <!-- Rankings -->
    <div class="form-row">
        <div class="card" style="flex: 1;">
            <h2><i class="fas fa-trophy"></i> Top 5 Libros Más Solicitados</h2>
            <p style="color: #6c757d; font-size: 0.9em;">Títulos con mayor cantidad de reservas</p>
            <div class="chart-container" style="position: relative; height:350px; width:100%;">
                <canvas id="graficoTopLibros"></canvas>
            </div>
        </div>
        <div class="card" style="flex: 1;">
            <h2><i class="fas fa-graduation-cap"></i> Top 5 Facultades Más Activas</h2>
            <p style="color: #6c757d; font-size: 0.9em;">Facultades con mayor uso del sistema</p>
            <div class="chart-container" style="position: relative; height:350px; width:100%;">
                <canvas id="graficoTopFacultades"></canvas>
            </div>
        </div>
    </div>

   <div class="form-row">
        <div class="card" style="flex: 1;">
            <h2><i class="fas fa-clock"></i> Uso de PCs por Turno</h2>
            <p style="color: #6c757d; font-size: 0.9em;">Distribución de reservas de computadoras</p>
            <div class="chart-container" style="position: relative; height:300px; width:100%;">
                <canvas id="graficoTurnosPC"></canvas> </div>
        </div>
        <div class="card" style="flex: 1;">
            <h2><i class="fas fa-hourglass-half"></i> Uso de Libros por Turno</h2> <p style="color: #6c757d; font-size: 0.9em;">Distribución de reservas de libros</p>
            <div class="chart-container" style="position: relative; height:300px; width:100%;">
                <canvas id="graficoTurnosLibros"></canvas> </div>
        </div>
    </div>

    <div class="form-row">
        <div class="card" style="flex: 1;">
            <h2><i class="fas fa-desktop"></i> Tipos de Uso de PCs</h2>
            <p style="color: #6c757d; font-size: 0.9em;">Propósitos de uso de las computadoras</p>
            <div class="chart-container" style="position: relative; height:300px; width:100%;">
                <canvas id="graficoTiposUso"></canvas>
            </div>
        </div>
        <div class="card" style="flex: 1;">
            <h2><i class="fas fa-book-reader"></i> Tipos de Reserva de Libros</h2>
            <p style="color: #6c757d; font-size: 0.9em;">Préstamo externo vs. consulta en sala</p>
            <div class="chart-container" style="position: relative; height:300px; width:100%;">
                <canvas id="graficoTiposReserva"></canvas>
            </div>
        </div>
    </div>

    <div class="card">
        <h2><i class="fas fa-tags"></i> Categorías Más Consultadas</h2>
        <p style="color: #6c757d; font-size: 0.9em;">Distribución de reservas por categoría de libro</p>
        <div class="chart-container" style="position: relative; height:300px; width:100%;">
            <canvas id="graficoCategorias"></canvas>
        </div>
    </div>

    <!-- Análisis Comparativo -->
    <div class="card">
        <h2><i class="fas fa-balance-scale"></i> Comparación: Origen de Reservas</h2>
        <p style="color: #6c757d; font-size: 0.95em;">Reservas realizadas por el usuario (cliente) vs. registradas por administrador</p>
        <div class="chart-container" style="position: relative; height:300px; width:100%;">
            <canvas id="graficoOrigenReservas"></canvas>
        </div>
    </div>
</div>


<?php 
// 4. Incluir el footer
require_once 'templates/footer.php'; 
?>
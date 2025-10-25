<?php

require_once '../includes/security.php'; 

// Generamos el token para que esté disponible en toda la página
generate_csrf_token();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - Biblioteca' : 'Administración - Biblioteca'; ?></title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php
    // Imprimir estilos específicos de la página si están definidos
    if (isset($pageStyles)) {
        echo $pageStyles;
    }
    ?>
</head>
<body>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2 style="display: flex; align-items: center; gap: 10px;;"><img style="height: 40px;" src="../img/up.png" alt="Logo UP"> Biblioteca</h2>
            <p>Sistema de Gestión</p>
        </div>
        <ul class="sidebar-menu">
            <li><a style=" padding: 10px;" href="dashboard.php" class="<?php echo ($activePage === 'dashboard') ? 'active' : ''; ?>"><i class="fas fa-tachometer-alt" style=" margin-right: 8px;"></i> Dashboard</a></li>
            <li><a style=" padding: 10px;" href="usuarios.php" class="<?php echo ($activePage === 'usuarios') ? 'active' : ''; ?>"><i class="fas fa-users" style=" margin-right: 8px;"></i> Usuarios</a></li>
            <li><a style=" padding: 10px;" href="libros.php" class="<?php echo ($activePage === 'libros') ? 'active' : ''; ?>"><i class="fas fa-book" style=" margin-right: 8px;"></i> Gestión Libros</a></li>
            <li><a style=" padding: 10px;" href="computadoras.php" class="<?php echo ($activePage === 'computadoras') ? 'active' : ''; ?>"><i class="fas fa-laptop" style=" margin-right: 8px;"></i> Gestión PC</a></li>
            <li><a style=" padding: 10px;" href="reservas_libros.php" class="<?php echo ($activePage === 'reservas_libros') ? 'active' : ''; ?>"><i class="fas fa-book" style=" margin-right: 8px;"></i> Reservas Libros</a></li>
            <li><a style=" padding: 10px;" href="reservas_computadoras.php" class="<?php echo ($activePage === 'reservas_computadoras') ? 'active' : ''; ?>"><i class="fas fa-desktop" style=" margin-right: 8px;"></i> Reservas PC</a></li>
            <li><a style=" padding: 10px;" href="estadisticas.php" class="<?php echo ($activePage === 'estadisticas') ? 'active' : ''; ?>"><i class="fas fa-chart-pie" style=" margin-right: 8px;"></i> Estadísticas</a></li>
            <?php if (isset($esAdministrador) && $esAdministrador): ?><li><a style=" padding: 10px;" href="administracion.php" class="<?php echo ($activePage === 'administracion') ? 'active' : ''; ?>"><i class="fas fa-user-shield" style=" margin-right: 8px;"></i style=" margin-right: 8px;"> Administración</a></li><?php endif; ?>
            <li><a style=" padding: 10px; color: #DC3545;" href="logout.php"><i class="fas fa-sign-out-alt" style=" margin-right: 8px"></i> Cerrar Sesión</a></li>
        </ul>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="main-wrapper" id="mainWrapper">
        <div class="top-header">
            <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i><span>Menu</span></button>
            <div class="user-info">
                <div class="user-avatar"><?php if (isset($esAdministrador) && $esAdministrador): ?><i class="fas fa-user-shield"></i><?php else: ?><i class="fas fa-user"></i><?php endif; ?></div>
                <div><strong><?php echo htmlspecialchars($_SESSION['bibliotecario']['nombre_completo']); ?></strong><div style="font-size: 0.9em; color: #666;">Bibliotecario</div></div>
            </div>
        </div>

        <div class="container" style="margin-top: 20px;">
            <?php if (isset($_SESSION['mensaje_flash'])): ?>
                <div class="alert alert-success"><?php echo $_SESSION['mensaje_flash']; ?></div>
                <?php unset($_SESSION['mensaje_flash']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_flash'])): ?>
                <div class="alert alert-danger"><?php echo $_SESSION['error_flash']; ?></div>
                <?php unset($_SESSION['error_flash']); ?>
            <?php endif; ?>
        </div>
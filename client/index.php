<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Reservas - Biblioteca Universitaria</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div style="display: flex; padding: 10px;" class="header">
        <div class="container">
            <h1>Sistema de Reservas</h1>
            <p>Biblioteca Universitaria</p>
        </div>
        <div class="container">
<img style="height: 100px;" src="../img/sb.jpg" alt="Logo UP">
        </div>
    </div>

    <div class="container">
        <div class="card">
            <h2>Bienvenido al Sistema de Reservas</h2>
            <p>Selecciona el tipo de reserva que deseas realizar:</p>
            
            <div class="choice-container">
                <div class="choice-card" onclick="location.href='reservar_libro.php'">
                    <i class="fas fa-book"></i>
                    <h3>Reservar Libro</h3>
                    <p>Reserva libros del catálogo de la biblioteca</p>
                </div>
                
                <div class="choice-card" onclick="location.href='reservar_computadora.php'">
                    <i class="fas fa-desktop"></i>
                    <h3>Reservar Computadora</h3>
                    <p>Reserva una computadora para uso académico</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
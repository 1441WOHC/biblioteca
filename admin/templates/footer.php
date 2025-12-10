<script>
    (function() {
        
        // Obtener IDs actuales al cargar la página
        let lastLibroId = 0;
        let lastPcId = 0;
        let checkInterval;
    
        function mostrarNotificacion(reserva) {
            const toast = document.createElement('div');
            toast.className = 'simple-toast ' + reserva.tipo;
            
            const icono = reserva.tipo === 'libro' ? '📚' : '💻';
            const titulo = reserva.tipo === 'libro' ? 'Nueva reserva de libro' : 'Nueva reserva de PC';
            
            toast.innerHTML = `
                <strong>${icono} ${titulo}</strong>
                <small>${reserva.mensaje}</small>
            `;
            
            document.body.appendChild(toast);
            
            // Ocultar después de 3 segundos
            setTimeout(() => {
                toast.classList.add('hide');
                setTimeout(() => toast.remove(), 400);
            }, 3000);
        }
    
        async function verificarNuevasReservas() {
            try {
                const response = await fetch(`noti_nueva_reserva.php?last_libro=${lastLibroId}&last_pc=${lastPcId}`);
                const data = await response.json();
                
                if (data.success && data.nuevas.length > 0) {
                    // Mostrar cada notificación con un pequeño delay
                    data.nuevas.forEach((reserva, index) => {
                        setTimeout(() => {
                            mostrarNotificacion(reserva);
                        }, index * 500);
                    });
                }
                
                // Actualizar los últimos IDs verificados
                lastLibroId = data.last_libro_id;
                lastPcId = data.last_pc_id;
                
            } catch (error) {
                console.error('Error verificando reservas:', error);
            }
        }
    
        // Inicializar: obtener IDs actuales sin mostrar notificaciones
        async function inicializar() {
            try {
                const response = await fetch(`noti_nueva_reserva.php?last_libro=999999&last_pc=999999`);
                const data = await response.json();
                
                if (data.success) {
                    lastLibroId = data.last_libro_id;
                    lastPcId = data.last_pc_id;
                    console.log('✅ Notificaciones activadas');
                    
                    // Empezar a verificar cada 3 segundos
                    checkInterval = setInterval(verificarNuevasReservas, 3000);
                }
            } catch (error) {
                console.error('Error inicializando notificaciones:', error);
            }
        }

        // Limpiar al salir
        window.addEventListener('beforeunload', () => {
            if (checkInterval) clearInterval(checkInterval);
        });
    
        // Iniciar inmediatamente
        // Se elimina la espera a DOMContentLoaded para que esta
        // petición se ejecute ANTES que cualquier script diferido.
        inicializar();

    })();

    
</script>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="js/admin_main.js"></script>

<?php if (isset($activePage) && $activePage === 'estadisticas'): ?>
    <script src="js/estadisticas.js"></script>
<?php endif; ?>

</div> </body>
</html>
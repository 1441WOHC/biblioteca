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

            // --- INICIO DE LA SOLUCIÓN MEJORADA ---
            // No esperamos a 'transitionend', que es demasiado lento y causa scroll.
            // Disparamos un resize un instante (50ms) después de cambiar la clase.
            // Esto es casi imperceptible y soluciona el scroll horizontal.
            setTimeout(() => {
                window.dispatchEvent(new Event('resize'));
            }, 50);
            // --- FIN DE LA SOLUCIÓN MEJORADA ---
        }
    });

    sidebarOverlay.addEventListener('click', function() {
        sidebar.classList.remove('mobile-open');
        sidebarOverlay.classList.remove('active');
    });

    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('mobile-open');
            sidebarOverlay.classList.remove('active');
        }
        // Nota: Este evento 'resize' nativo ya maneja el redimensionamiento
        // si el usuario cambia el tamaño de la ventana del navegador.
        // No necesitamos tocar nada aquí.
    });

    document.querySelectorAll('.sidebar-menu a').forEach(link => {
        link.addEventListener('click', function(e) {
            document.querySelectorAll('.sidebar-menu a').forEach(l => l.classList.remove('active'));
            this.classList.add('active');
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('mobile-open');
                sidebarOverlay.classList.remove('active');
            }
        });
    });



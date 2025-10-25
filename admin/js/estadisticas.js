document.addEventListener('DOMContentLoaded', () => {

    // Referencias a los canvas
    const ctxUso = document.getElementById('graficoUsoGeneral').getContext('2d');
    const ctxLibros = document.getElementById('graficoTopLibros').getContext('2d');
    const ctxUsuarios = document.getElementById('graficoTipoUsuario').getContext('2d');
    const ctxAfiliacion = document.getElementById('graficoAfiliacion').getContext('2d');
    const ctxFacultades = document.getElementById('graficoTopFacultades').getContext('2d');
    const ctxTurnosPC = document.getElementById('graficoTurnosPC').getContext('2d'); // ID Actualizado
    const ctxTurnosLibros = document.getElementById('graficoTurnosLibros').getContext('2d'); // ID Nuevo
    const ctxTiposUso = document.getElementById('graficoTiposUso').getContext('2d');
    const ctxTiposReserva = document.getElementById('graficoTiposReserva').getContext('2d');
    const ctxCategorias = document.getElementById('graficoCategorias').getContext('2d');
    const ctxOrigen = document.getElementById('graficoOrigenReservas').getContext('2d');

    // Paleta de colores profesional
    const colores = {
        primario: 'rgba(0, 86, 179, 0.8)',
        secundario: 'rgba(255, 159, 64, 0.8)',
        exito: 'rgba(40, 167, 69, 0.8)',
        peligro: 'rgba(220, 53, 69, 0.8)',
        advertencia: 'rgba(255, 193, 7, 0.8)',
        info: 'rgba(0, 35, 87, 0.8)',
        paleta: [
            'rgba(0, 86, 179, 0.8)',
            'rgba(255, 159, 64, 0.8)',
            'rgba(40, 167, 69, 0.8)',
            'rgba(220, 53, 69, 0.8)',
            'rgba(153, 102, 255, 0.8)',
            'rgba(255, 193, 7, 0.8)',
            'rgba(0, 188, 212, 0.8)',
            'rgba(233, 30, 99, 0.8)'
        ]
    };

    

    // Configuración global de Chart.js
    Chart.defaults.font.family = "'Inter', 'Segoe UI', sans-serif";
    Chart.defaults.responsive = true;
    Chart.defaults.maintainAspectRatio = false;

    // Función para formatear fechas a DD/MM/YYYY
    function formatearFecha(fechaStr) {
        const fecha = new Date(fechaStr + 'T00:00:00');
        const dia = String(fecha.getDate()).padStart(2, '0');
        const mes = String(fecha.getMonth() + 1).padStart(2, '0');
        const anio = fecha.getFullYear();
        return `${dia}/${mes}/${anio}`;
    }

    // 1. Gráfico de Uso General (Línea)
    const chartUso = new Chart(ctxUso, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'Reservas de Libros',
                    data: [],
                    borderColor: colores.primario,
                    backgroundColor: 'rgba(0, 86, 179, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                },
                {
                    label: 'Reservas de PC',
                    data: [],
                    borderColor: colores.secundario,
                    backgroundColor: 'rgba(255, 159, 64, 0.1)',
                    fill: true,
                    tension: 0.4,
                    borderWidth: 2,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }
            ]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                tooltip: {
                    mode: 'index',
                    intersect: false
                }
            }
        }
    });

    const chartLibros = new Chart(ctxLibros, {
    type: 'bar',
    data: {
        labels: [],
        datasets: [{
            label: 'Nº de Reservas',
            data: [],
            backgroundColor: colores.paleta,
            borderWidth: 0
        }]
    },
    options: {
        indexAxis: 'y',
        scales: {
            x: {
                beginAtZero: true,
                ticks: { stepSize: 1 }
            },
            // --- BLOQUE AÑADIDO PARA TRUNCAR TEXTO ---
            y: {
                ticks: {
                    callback: function(value, index) {
                        const label = this.getLabelForValue(value);
                        const maxLength = 35; // Máximo de caracteres
                        if (label.length > maxLength) {
                            return label.substring(0, maxLength) + '...';
                        }
                        return label;
                    },
                    autoSkip: false,
                    font: {
                        size: 11
                    }
                }
            }
            // --- FIN DEL BLOQUE ---
        },
        plugins: {
            legend: { display: false },
            // --- BLOQUE AÑADIDO PARA TOOLTIP COMPLETO ---
            tooltip: {
                callbacks: {
                    title: function(context) {
                        // Muestra el nombre completo en el tooltip
                        return context[0].label;
                    }
                }
            }
            // --- FIN DEL BLOQUE ---
        }
    }
});

    // 3. Tipo Usuario (Dona)
    const chartUsuarios = new Chart(ctxUsuarios, {
        type: 'doughnut',
        data: {
            labels: [],
            datasets: [{
                data: [],
                backgroundColor: colores.paleta,
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // 4. Afiliación (Dona)
    const chartAfiliacion = new Chart(ctxAfiliacion, {
        type: 'doughnut',
        data: {
            labels: [],
            datasets: [{
                data: [],
                backgroundColor: colores.paleta,
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

   // 5. Top Facultades (Barras Horizontales)
const chartFacultades = new Chart(ctxFacultades, {
    type: 'bar',
    data: {
        labels: [],
        datasets: [{
            label: 'Reservas',
            data: [],
            backgroundColor: colores.paleta,
            borderWidth: 0
        }]
    },
    options: {
        indexAxis: 'y',
        scales: {
            x: {
                beginAtZero: true,
                ticks: { stepSize: 1 }
            },
            y: {
                ticks: {
                    callback: function(value, index) {
                        const label = this.getLabelForValue(value);
                        const maxLength = 35; // Ajusta según necesites
                        if (label.length > maxLength) {
                            return label.substring(0, maxLength) + '...';
                        }
                        return label;
                    },
                    autoSkip: false,
                    font: {
                        size: 11
                    }
                }
            }
        },
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    title: function(context) {
                        // Muestra el nombre completo en el tooltip
                        return context[0].label;
                    }
                }
            }
        }
    }
});

    // 6. Turnos (Barras)
    const chartTurnosPC = new Chart(ctxTurnosPC, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                label: 'Reservas',
                data: [],
                backgroundColor: colores.primario,
                borderWidth: 0
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });

    // 6b. Turnos (Libros)
    const chartTurnosLibros = new Chart(ctxTurnosLibros, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                label: 'Reservas de Libros',
                data: [],
                backgroundColor: colores.exito, // Color diferente
                borderWidth: 0
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            },
            plugins: {
                legend: { display: false }
            }
        }
    });

    // 7. Tipos de Uso (Pastel)
    const chartTiposUso = new Chart(ctxTiposUso, {
        type: 'pie',
        data: {
            labels: [],
            datasets: [{
                data: [],
                backgroundColor: colores.paleta,
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // 8. Tipos de Reserva de Libros (Pastel)
    const chartTiposReserva = new Chart(ctxTiposReserva, {
        type: 'pie',
        data: {
            labels: [],
            datasets: [{
                data: [],
                backgroundColor: [colores.primario, colores.secundario],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });

    // 9. Categorías (Barras)
    const chartCategorias = new Chart(ctxCategorias, {
        type: 'bar',
        data: {
            labels: [],
            datasets: [{
                label: 'Reservas',
                data: [],
                backgroundColor: colores.exito,
                borderWidth: 0
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                },
                x: { // <-- ✅ CORREGIDO: Este bloque ahora está DENTRO de "scales"
                    ticks: {
                        callback: function(value, index) {
                            const label = this.getLabelForValue(value);
                            const maxLength = 15; // Longitud más corta para eje X
                            if (label.length > maxLength) {
                                return label.substring(0, maxLength) + '...';
                            }
                            return label;
                        },
                        autoSkip: false,
                        font: {
                            size: 11
                        }
                    }
                }
            },
            plugins: {
                legend: { display: false },
                // Añado el tooltip que faltaba para ver el nombre completo
                tooltip: {
                    callbacks: {
                        title: function(context) {
                            return context[0].label;
                        }
                    }
                }
            }
        }
    });

    // 10. Origen de Reservas (Barras Agrupadas)
    const chartOrigen = new Chart(ctxOrigen, {
        type: 'bar',
        data: {
            labels: ['Libros', 'Computadoras'],
            datasets: [
                {
                    label: 'Por Cliente',
                    data: [],
                    backgroundColor: colores.primario,
                    borderWidth: 0
                },
                {
                    label: 'Por Administrador',
                    data: [],
                    backgroundColor: colores.secundario,
                    borderWidth: 0
                }
            ]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { stepSize: 1 }
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            }
        }
    });

    // Función Principal para Cargar Datos (MODIFICADA)
    async function cargarEstadisticas() {
        const fechaInicio = document.getElementById('stats_fecha_inicio').value;
        const fechaFin = document.getElementById('stats_fecha_fin').value;

        // Mostrar indicador de carga
        const btn = document.getElementById('btn_actualizar_stats');
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cargando...';
        btn.disabled = true;

        // Construir parámetros de URL
        const params = new URLSearchParams();
        if (fechaInicio) params.append('fecha_inicio', fechaInicio);
        if (fechaFin) params.append('fecha_fin', fechaFin);
        const queryString = params.toString();

        try {
            // Realizar UNA SOLA petición para obtener todos los datos
            const res = await fetch(`api.php?action=get_all_stats&${queryString}`);
            
            if (!res.ok) {
                throw new Error('Error en la respuesta de la API');
            }
            
            const allData = await res.json();

            // Actualizar KPIs
            document.getElementById('kpi-total-reservas').textContent = allData.kpis.total_reservas || '0';
            document.getElementById('kpi-total-libros').textContent = allData.kpis.total_libros || '0';
            document.getElementById('kpi-total-pcs').textContent = allData.kpis.total_pcs || '0';
            document.getElementById('kpi-usuarios-activos').textContent = allData.kpis.usuarios_activos || '0';

            // Actualizar Gráfico 1: Uso General (con fechas formateadas)
            chartUso.data.labels = allData.general.labels.map(fecha => formatearFecha(fecha));
            chartUso.data.datasets[0].data = allData.general.libros;
            chartUso.data.datasets[1].data = allData.general.computadoras;
            chartUso.update();

            // Actualizar Gráfico 2: Top Libros
            chartLibros.data.labels = allData.top_libros.map(item => item.titulo);
            chartLibros.data.datasets[0].data = allData.top_libros.map(item => item.total);
            chartLibros.update();

            // Actualizar Gráfico 3: Tipo Usuario
            chartUsuarios.data.labels = allData.tipo_usuario.map(item => item.nombre_tipo_usuario);
            chartUsuarios.data.datasets[0].data = allData.tipo_usuario.map(item => item.total);
            chartUsuarios.update();

            // Actualizar Gráfico 4: Afiliación
            chartAfiliacion.data.labels = allData.afiliacion.map(item => item.nombre_afiliacion);
            chartAfiliacion.data.datasets[0].data = allData.afiliacion.map(item => item.total);
            chartAfiliacion.update();

            // Actualizar Gráfico 5: Top Facultades
            chartFacultades.data.labels = allData.top_facultades.map(item => item.nombre_facultad || 'Sin especificar');
            chartFacultades.data.datasets[0].data = allData.top_facultades.map(item => item.total);
            chartFacultades.update();

            // Actualizar Gráfico 6: Turnos (PC)
        chartTurnosPC.data.labels = allData.turnos.map(item => item.nombre_turno);
        chartTurnosPC.data.datasets[0].data = allData.turnos.map(item => item.total);
        chartTurnosPC.update();

        // Actualizar Gráfico 6b: Turnos (Libros)
        chartTurnosLibros.data.labels = allData.turnos_libros.map(item => item.nombre_turno);
        chartTurnosLibros.data.datasets[0].data = allData.turnos_libros.map(item => item.total);
        chartTurnosLibros.update();

            // Actualizar Gráfico 7: Tipos de Uso
            chartTiposUso.data.labels = allData.tipos_uso.map(item => item.nombre_tipo_uso);
            chartTiposUso.data.datasets[0].data = allData.tipos_uso.map(item => item.total);
            chartTiposUso.update();

            // Actualizar Gráfico 8: Tipos de Reserva
            chartTiposReserva.data.labels = allData.tipos_reserva.map(item => item.nombre_tipo_reserva);
            chartTiposReserva.data.datasets[0].data = allData.tipos_reserva.map(item => item.total);
            chartTiposReserva.update();

            // Actualizar Gráfico 9: Categorías
            chartCategorias.data.labels = allData.categorias.map(item => item.nombre_categoria || 'Sin categoría');
            chartCategorias.data.datasets[0].data = allData.categorias.map(item => item.total);
            chartCategorias.update();

            // Actualizar Gráfico 10: Origen
            chartOrigen.data.datasets[0].data = [allData.origen.libros_cliente || 0, allData.origen.pcs_cliente || 0];
            chartOrigen.data.datasets[1].data = [allData.origen.libros_admin || 0, allData.origen.pcs_admin || 0];
            chartOrigen.update();

        } catch (error) {
            console.error('Error al cargar estadísticas:', error);
            alert('No se pudieron cargar las estadísticas. Revise la consola para más detalles.');
        } finally {
            // Restaurar el botón
            btn.innerHTML = '<i class="fas fa-sync-alt"></i> Actualizar Estadísticas';
            btn.disabled = false;
        }
    }

    cargarEstadisticas();

    // 2. Asignar la función cargarEstadisticas al botón de actualizar
    const btnActualizar = document.getElementById('btn_actualizar_stats');
    if (btnActualizar) {
        btnActualizar.addEventListener('click', cargarEstadisticas);
    }
});
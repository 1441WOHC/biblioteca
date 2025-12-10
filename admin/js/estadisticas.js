document.addEventListener('DOMContentLoaded', () => {

     // Función para mostrar notificaciones
    function mostrarNotificacion(mensaje, tipo = 'info') {
        // Crear elemento de notificación
        const notif = document.createElement('div');
        notif.className = 'simple-toast';
        
        // Definir colores según el tipo
        const colores = {
            'info': '#4facfe',
            'success': '#28A745',
            'danger': '#DC3545',
            'warning': '#FFC107'
        };
		
		// --- NUEVO: Mapeo de tipos a etiquetas en español ---
        const etiquetas = {
            'info': 'INFORMACIÓN',
            'success': 'ÉXITO',
            'danger': 'ERROR',
            'warning': 'ADVERTENCIA'
        };
        
        notif.style.borderLeftColor = colores[tipo] || colores['info'];
		
		// --- MODIFICADO: Usar la etiqueta en español ---
        const etiquetaMostrar = etiquetas[tipo] || tipo.toUpperCase();
        
        notif.innerHTML = `
    <strong>${etiquetaMostrar}</strong>
    <small>${mensaje}</small>
`;
        
        // Agregar al body
        document.body.appendChild(notif);
        
        // Remover después de 3 segundos
        setTimeout(() => {
            notif.classList.add('hide');
            setTimeout(() => notif.remove(), 400);
        }, 3000);
    }

    // --- 1. Variables de instancia de Gráficos ---
    let chartUso, chartLibros, chartUsuarios, chartAfiliacion, 
        chartFacultades, chartTurnosPC, chartTurnosLibros, 
        chartTiposUso, chartTiposReserva, chartCategorias, chartOrigen;

    // --- 2. Paleta de Colores ---
    const colores = {
        primario: '#0056B3', // Azul primario
        secundario: '#FF9F40', // Naranja secundario
        exito: '#28A745', // Verde
        peligro: '#DC3545', // Rojo
        advertencia: '#FFC107', // Amarillo
        info: '#002357', // Azul oscuro
        paleta: [
            '#0056B3', '#FF9F40', '#28A745', '#DC3545', 
            '#9966FF', '#FFC107', '#00BCD4', '#E91E63'
        ]
    };

    // --- 3. Función Utilidad para Formatear Fechas ---
    function formatearFecha(fechaStr) {
        if (!fechaStr) return '';
        const fecha = new Date(fechaStr + 'T00:00:00'); // Asumir zona horaria local
        const dia = String(fecha.getDate()).padStart(2, '0');
        const mes = String(fecha.getMonth() + 1).padStart(2, '0');
        const anio = fecha.getFullYear();
        return `${dia}/${mes}/${anio}`;
    }

    // --- 4. Función para Forzar Ticks Enteros ---
    function formatAsInteger(val) {
        if (val === null || val === undefined) return 0;
        return val.toFixed(0);
    }

    // --- 5. Inicialización de Gráficos (Estructura Vacía) ---
    function inicializarGraficos() {
        
        // 1. Gráfico de Uso General (Línea/Área)
        const optionsUso = {
            series: [
                { name: 'Reservas de Libros', data: [] },
                { name: 'Reservas de PC', data: [] }
            ],
            chart: { 
                type: 'area', 
                height: 400, 
                fontFamily: 'Inter, sans-serif', 
                zoom: { enabled: false }, 
                toolbar: { 
                    show: true, 
                    tools: { 
                        download: true, 
                        selection: false, 
                        zoom: false, 
                        zoomin: false, 
                        zoomout: false, 
                        pan: false, 
                        reset: false
                    } 
                } 
            },
            colors: [colores.primario, colores.secundario],
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { opacityFrom: 0.6, opacityTo: 0.1 } },
            labels: [],
            xaxis: {
                type: 'category',
                tickPlacement: 'between',
                labels: {
                    formatter: (value, timestamp, opts) => {
                         if (typeof value === 'string' && value.includes('/')) return value;
                         if (timestamp) {
                             const date = new Date(timestamp);
                             return `${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}`;
                         }
                         return value;
                    },
                    offsetX: 0,
                    offsetY: 0
                }
            },
            yaxis: { min: 0, labels: { formatter: formatAsInteger } },
            tooltip: { 
                shared: true, 
                intersect: false, 
                x: { format: 'dd/MM/yyyy' },
                y: {
                    formatter: (val) => `${val} ${val === 1 ? 'reserva' : 'reservas'}`
                }
            },
            legend: { position: 'top' }
        };
        chartUso = new ApexCharts(document.querySelector("#graficoUsoGeneral"), optionsUso);
        chartUso.render();

        // 2. Gráfico Top Libros (Barra Horizontal)
        const optionsLibros = {
            series: [{ name: 'Nº de Reservas', data: [] }],
            chart: { 
                type: 'bar', 
                height: 350, 
                fontFamily: 'Inter, sans-serif', 
                toolbar: { 
                    show: true,
                    tools: {
                        download: true,
                        selection: false,
                        zoom: false,
                        zoomin: false,
                        zoomout: false,
                        pan: false,
                        reset: false
                    }
                } 
            },
            plotOptions: { bar: { horizontal: true, barHeight: '70%', borderRadius: 4 } },
            colors: [colores.primario],
            xaxis: { categories: [], labels: { formatter: formatAsInteger } },
            yaxis: {
                labels: {
                    formatter: (val) => {
                        if (typeof val !== 'string') return val;
                        const maxLength = 35;
                        return val.length > maxLength ? val.substring(0, maxLength) + '...' : val;
                    }
                }
            },
            tooltip: {
                x: {
                    formatter: (val) => `${val} ${val === 1 ? 'reserva' : 'reservas'}`
                },
                y: {
                    title: {
                        formatter: (seriesName) => ''
                    }
                }
            },
            dataLabels: { enabled: false }
        };
        chartLibros = new ApexCharts(document.querySelector("#graficoTopLibros"), optionsLibros);
        chartLibros.render();

        // 3. Gráfico Tipo Usuario (Dona)
        const optionsUsuarios = {
            series: [],
            labels: [],
            chart: { 
                type: 'donut', 
                height: 350, 
                fontFamily: 'Inter, sans-serif',
                toolbar: {
                    show: true,
                    tools: {
                        download: true,
                        selection: false,
                        zoom: false,
                        zoomin: false,
                        zoomout: false,
                        pan: false,
                        reset: false
                    }
                }
            },
            colors: colores.paleta,
            legend: { position: 'bottom' },
            tooltip: {
                y: {
                    formatter: (val) => `${val} ${val === 1 ? 'reserva' : 'reservas'}`
                }
            },
            responsive: [{
                breakpoint: 480,
                options: { chart: { height: 300 }, legend: { position: 'bottom' } }
            }]
        };
        chartUsuarios = new ApexCharts(document.querySelector("#graficoTipoUsuario"), optionsUsuarios);
        chartUsuarios.render();

        // 4. Gráfico Afiliación (Dona)
        const optionsAfiliacion = {
            series: [],
            labels: [],
            chart: { 
                type: 'donut', 
                height: 350, 
                fontFamily: 'Inter, sans-serif',
                toolbar: {
                    show: true,
                    tools: {
                        download: true,
                        selection: false,
                        zoom: false,
                        zoomin: false,
                        zoomout: false,
                        pan: false,
                        reset: false
                    }
                }
            },
            colors: colores.paleta,
            legend: { position: 'bottom' },
            tooltip: {
                y: {
                    formatter: (val) => `${val} ${val === 1 ? 'reserva' : 'reservas'}`
                }
            },
            responsive: [{
                breakpoint: 480,
                options: { chart: { height: 300 }, legend: { position: 'bottom' } }
            }]
        };
        chartAfiliacion = new ApexCharts(document.querySelector("#graficoAfiliacion"), optionsAfiliacion);
        chartAfiliacion.render();

        // 5. Gráfico Top Facultades (Barra Horizontal)
        const optionsFacultades = {
            series: [{ name: 'Reservas', data: [] }],
            chart: { 
                type: 'bar', 
                height: 350, 
                fontFamily: 'Inter, sans-serif', 
                toolbar: { 
                    show: true,
                    tools: {
                        download: true,
                        selection: false,
                        zoom: false,
                        zoomin: false,
                        zoomout: false,
                        pan: false,
                        reset: false
                    }
                } 
            },
            plotOptions: { bar: { horizontal: true, barHeight: '70%', borderRadius: 4 } },
            colors: [colores.exito],
            xaxis: { categories: [], labels: { formatter: formatAsInteger } },
            yaxis: {
                labels: {
                    formatter: (val) => {
                        if (typeof val !== 'string') return val;
                        const maxLength = 35;
                        return val.length > maxLength ? val.substring(0, maxLength) + '...' : val;
                    }
                }
            },
            tooltip: {
                x: {
                    formatter: (val) => `${val} ${val === 1 ? 'reserva' : 'reservas'}`
                },
                y: {
                    title: {
                        formatter: (seriesName) => ''
                    }
                }
            },
            dataLabels: { enabled: false }
        };
        chartFacultades = new ApexCharts(document.querySelector("#graficoTopFacultades"), optionsFacultades);
        chartFacultades.render();

        // 6. Gráfico Turnos PC (Barra)
        const optionsTurnosPC = {
            series: [{ name: 'Reservas', data: [] }],
            chart: { 
                type: 'bar', 
                height: 300, 
                fontFamily: 'Inter, sans-serif', 
                toolbar: { 
                    show: true,
                    tools: {
                        download: true,
                        selection: false,
                        zoom: false,
                        zoomin: false,
                        zoomout: false,
                        pan: false,
                        reset: false
                    }
                } 
            },
            plotOptions: { bar: { columnWidth: '60%', borderRadius: 4 } },
            colors: [colores.primario],
            xaxis: { categories: [] },
            yaxis: { min: 0, labels: { formatter: formatAsInteger } },
            tooltip: {
                y: {
                    formatter: (val) => `${val} ${val === 1 ? 'reserva' : 'reservas'}`,
                    title: {
                        formatter: (seriesName) => ''
                    }
                }
            },
            dataLabels: { enabled: false },
            legend: { show: false }
        };
        chartTurnosPC = new ApexCharts(document.querySelector("#graficoTurnosPC"), optionsTurnosPC);
        chartTurnosPC.render();

        // 6b. Gráfico Turnos Libros (Barra)
        const optionsTurnosLibros = {
            series: [{ name: 'Reservas de Libros', data: [] }],
            chart: { 
                type: 'bar', 
                height: 300, 
                fontFamily: 'Inter, sans-serif', 
                toolbar: { 
                    show: true,
                    tools: {
                        download: true,
                        selection: false,
                        zoom: false,
                        zoomin: false,
                        zoomout: false,
                        pan: false,
                        reset: false
                    }
                } 
            },
            plotOptions: { bar: { columnWidth: '60%', borderRadius: 4 } },
            colors: [colores.exito],
            xaxis: { categories: [] },
            yaxis: { min: 0, labels: { formatter: formatAsInteger } },
            tooltip: {
                y: {
                    formatter: (val) => `${val} ${val === 1 ? 'reserva' : 'reservas'}`,
                    title: {
                        formatter: (seriesName) => ''
                    }
                }
            },
            dataLabels: { enabled: false },
            legend: { show: false }
        };
        chartTurnosLibros = new ApexCharts(document.querySelector("#graficoTurnosLibros"), optionsTurnosLibros);
        chartTurnosLibros.render();

        // 7. Gráfico Tipos de Uso (Pie)
        const optionsTiposUso = {
            series: [],
            labels: [],
            chart: { 
                type: 'pie', 
                height: 300, 
                fontFamily: 'Inter, sans-serif',
                toolbar: {
                    show: true,
                    tools: {
                        download: true,
                        selection: false,
                        zoom: false,
                        zoomin: false,
                        zoomout: false,
                        pan: false,
                        reset: false
                    }
                }
            },
            colors: colores.paleta,
            legend: { position: 'bottom' },
            tooltip: {
                y: {
                    formatter: (val) => `${val} ${val === 1 ? 'reserva' : 'reservas'}`
                }
            },
            responsive: [{
                breakpoint: 480,
                options: { chart: { height: 250 }, legend: { position: 'bottom' } }
            }]
        };
        chartTiposUso = new ApexCharts(document.querySelector("#graficoTiposUso"), optionsTiposUso);
        chartTiposUso.render();

        // 8. Gráfico Tipos de Reserva (Pie)
        const optionsTiposReserva = {
            series: [],
            labels: [],
            chart: { 
                type: 'pie', 
                height: 300, 
                fontFamily: 'Inter, sans-serif',
                toolbar: {
                    show: true,
                    tools: {
                        download: true,
                        selection: false,
                        zoom: false,
                        zoomin: false,
                        zoomout: false,
                        pan: false,
                        reset: false
                    }
                }
            },
            colors: [colores.primario, colores.secundario],
            legend: { position: 'bottom' },
            tooltip: {
                y: {
                    formatter: (val) => `${val} ${val === 1 ? 'reserva' : 'reservas'}`
                }
            },
            responsive: [{
                breakpoint: 480,
                options: { chart: { height: 250 }, legend: { position: 'bottom' } }
            }]
        };
        chartTiposReserva = new ApexCharts(document.querySelector("#graficoTiposReserva"), optionsTiposReserva);
        chartTiposReserva.render();

        // 9. Gráfico Categorías (Barra)
        const optionsCategorias = {
            series: [{ name: 'Reservas', data: [] }],
            chart: { 
                type: 'bar', 
                height: 300, 
                fontFamily: 'Inter, sans-serif', 
                toolbar: { 
                    show: true,
                    tools: {
                        download: true,
                        selection: false,
                        zoom: false,
                        zoomin: false,
                        zoomout: false,
                        pan: false,
                        reset: false
                    }
                } 
            },
            plotOptions: { bar: { columnWidth: '70%', borderRadius: 4 } },
            colors: [colores.info],
            xaxis: {
                categories: [],
                labels: {
                    formatter: (val) => {
                        if (typeof val !== 'string') return val;
                        const maxLength = 15;
                        return val.length > maxLength ? val.substring(0, maxLength) + '...' : val;
                    },
                    trim: false
                }
            },
            yaxis: { min: 0, labels: { formatter: formatAsInteger } },
            tooltip: {
                y: {
                    formatter: (val) => `${val} ${val === 1 ? 'reserva' : 'reservas'}`,
                    title: {
                        formatter: (seriesName) => ''
                    }
                }
            },
            dataLabels: { enabled: false },
            legend: { show: false }
        };
        chartCategorias = new ApexCharts(document.querySelector("#graficoCategorias"), optionsCategorias);
        chartCategorias.render();

        // 10. Gráfico Origen de Reservas (Barra Agrupada)
        const optionsOrigen = {
            series: [
                { name: 'Por Cliente', data: [] },
                { name: 'Por Administrador', data: [] }
            ],
            chart: { 
                type: 'bar', 
                height: 300, 
                fontFamily: 'Inter, sans-serif', 
                toolbar: { 
                    show: true,
                    tools: {
                        download: true,
                        selection: false,
                        zoom: false,
                        zoomin: false,
                        zoomout: false,
                        pan: false,
                        reset: false
                    }
                } 
            },
            plotOptions: { bar: { horizontal: false, columnWidth: '80%', borderRadius: 4 } },
            colors: [colores.primario, colores.secundario],
            xaxis: { categories: ['Libros', 'Computadoras'] },
            yaxis: { min: 0, labels: { formatter: formatAsInteger } },
            dataLabels: { enabled: false },
            legend: { position: 'top' }
        };
        chartOrigen = new ApexCharts(document.querySelector("#graficoOrigenReservas"), optionsOrigen);
        chartOrigen.render();
    }

    async function cargarEstadisticas() {
    const fechaInicio = document.getElementById('stats_fecha_inicio').value;
    const fechaFin = document.getElementById('stats_fecha_fin').value;

    const btn = document.getElementById('btn_actualizar_stats');
    const btnOriginalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cargando datos...';
    btn.disabled = true;

    // Mostrar mensaje si hay filtros activos
    let mensajeFiltro = '';
    if (fechaInicio || fechaFin) {
        mensajeFiltro = 'Aplicando filtros de fecha...';
        mostrarNotificacion(mensajeFiltro, 'info');
    }

    // Construir URL de forma más segura
    let url = 'api.php?action=get_all_stats';
    if (fechaInicio) {
        url += `&fecha_inicio=${encodeURIComponent(fechaInicio)}`;
        console.log('Fecha inicio:', fechaInicio);
    }
    if (fechaFin) {
        url += `&fecha_fin=${encodeURIComponent(fechaFin)}`;
        console.log('Fecha fin:', fechaFin);
    }
    
    console.log('URL de petición completa:', url);

    try {
        const res = await fetch(url);
        
        console.log('Estado de respuesta:', res.status);
        
        if (!res.ok) {
            const errorText = await res.text();
            console.error('Texto de error:', errorText);
            throw new Error(`Error en la respuesta de la API: ${res.status} - ${res.statusText}`);
        }
        
        const responseText = await res.text();
        console.log('Respuesta raw (primeros 500 caracteres):', responseText.substring(0, 500));
        
        let allData;
        try {
            allData = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Error al parsear JSON:', parseError);
            console.error('Respuesta completa que causó el error:', responseText);
            throw new Error('Error al procesar la respuesta del servidor. Verifica la consola para más detalles.');
        }
        
        console.log('Datos recibidos:', allData);

        // Verificar si hay datos
        if (!allData || !allData.kpis) {
            throw new Error('No se recibieron datos válidos del servidor');
        }

        // Actualizar KPIs
        document.getElementById('kpi-total-reservas').textContent = allData.kpis.total_reservas || '0';
        document.getElementById('kpi-total-libros').textContent = allData.kpis.total_libros || '0';
        document.getElementById('kpi-total-pcs').textContent = allData.kpis.total_pcs || '0';
        document.getElementById('kpi-usuarios-activos').textContent = allData.kpis.usuarios_activos || '0';

        // --- Actualizar Gráficos ---

        // 1. Uso General
        const labelsFormateadas = allData.general.labels.map(fecha => formatearFecha(fecha));
        chartUso.updateOptions({
            labels: labelsFormateadas,
            xaxis: {
                categories: labelsFormateadas,
                type: 'category',
                tickPlacement: 'between'
            }
        });
        chartUso.updateSeries([
            { name: 'Reservas de Libros', data: allData.general.libros },
            { name: 'Reservas de PC', data: allData.general.computadoras }
        ]);

        // 2. Top Libros
        chartLibros.updateOptions({
            xaxis: { categories: allData.top_libros.map(item => item.titulo) }
        });
        chartLibros.updateSeries([{ data: allData.top_libros.map(item => item.total) }]);

        // 3. Tipo Usuario
        chartUsuarios.updateOptions({
            labels: allData.tipo_usuario.map(item => item.nombre_tipo_usuario)
        });
        chartUsuarios.updateSeries(allData.tipo_usuario.map(item => item.total));

        // 4. Afiliación
        chartAfiliacion.updateOptions({
            labels: allData.afiliacion.map(item => item.nombre_afiliacion)
        });
        chartAfiliacion.updateSeries(allData.afiliacion.map(item => item.total));

        // 5. Top Facultades
        chartFacultades.updateOptions({
            xaxis: { categories: allData.top_facultades.map(item => item.nombre_facultad || 'Sin especificar') }
        });
        chartFacultades.updateSeries([{ data: allData.top_facultades.map(item => item.total) }]);

        // 6. Turnos (PC)
        chartTurnosPC.updateOptions({
            xaxis: { categories: allData.turnos.map(item => item.nombre_turno) }
        });
        chartTurnosPC.updateSeries([{ data: allData.turnos.map(item => item.total) }]);

        // 6b. Turnos (Libros)
        chartTurnosLibros.updateOptions({
            xaxis: { categories: allData.turnos_libros.map(item => item.nombre_turno) }
        });
        chartTurnosLibros.updateSeries([{ data: allData.turnos_libros.map(item => item.total) }]);

        // 7. Tipos de Uso
        chartTiposUso.updateOptions({
            labels: allData.tipos_uso.map(item => item.nombre_tipo_uso)
        });
        chartTiposUso.updateSeries(allData.tipos_uso.map(item => item.total));

        // 8. Tipos de Reserva
        chartTiposReserva.updateOptions({
            labels: allData.tipos_reserva.map(item => item.nombre_tipo_reserva)
        });
        chartTiposReserva.updateSeries(allData.tipos_reserva.map(item => item.total));

        // 9. Categorías
        chartCategorias.updateOptions({
            xaxis: { categories: allData.categorias.map(item => item.nombre_categoria || 'Sin categoría') }
        });
        chartCategorias.updateSeries([{ data: allData.categorias.map(item => item.total) }]);

        // 10. Origen
        chartOrigen.updateSeries([
            { name: 'Por Cliente', data: [allData.origen.libros_cliente || 0, allData.origen.pcs_cliente || 0] },
            { name: 'Por Administrador', data: [allData.origen.libros_admin || 0, allData.origen.pcs_admin || 0] }
        ]);

        // Mostrar notificación de éxito
        if (fechaInicio || fechaFin) {
            mostrarNotificacion('Filtros aplicados correctamente', 'success');
        }

    } catch (error) {
        console.error('Error detallado:', error);
        mostrarNotificacion('Error al cargar estadísticas: ' + error.message, 'danger');
    } finally {
        btn.innerHTML = btnOriginalHTML;
        btn.disabled = false;
    }

}
    // --- 7. Ejecución Inicial ---
    inicializarGraficos();
    cargarEstadisticas();

    const btnActualizar = document.getElementById('btn_actualizar_stats');
    if (btnActualizar) {
        btnActualizar.addEventListener('click', cargarEstadisticas);
    }
});
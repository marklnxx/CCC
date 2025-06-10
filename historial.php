<?php
// Establecer cabeceras para evitar el almacenamiento en caché
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Habilitar registro de errores para diagnóstico
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Función para registrar errores en un archivo de log
function log_error($message) {
    $log_file = 'logs/historial_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] $message\n";
    
    // Asegurar que el directorio de logs existe
    if (!file_exists(dirname($log_file))) {
        mkdir(dirname($log_file), 0755, true);
    }
    
    // Registrar el error
    file_put_contents($log_file, $log_message, FILE_APPEND);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Historial de Cubierta</title>
    <!-- Enlace a Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Enlace a Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- jsPDF para exportar a PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <!-- Enlace a archivo CSS externo -->
    <link rel="stylesheet" href="styles-dark.css">
    <link rel="stylesheet" href="header-fix.css">
    <link rel="stylesheet" href="nuevo-header.css">
</head>
<body>
    <div class="main-container">
        <!-- Header con logo -->
        <header>
            <div class="logo-container">
                <img src="LOGO.PNG" alt="Logo de la empresa" class="fade-in">
                <h1 class="fade-in delay-1">HISTORIAL DE CUBIERTAS</h1>
            </div>
        </header>

        <div class="content">
            <!-- Sección de navegación -->
            <div class="nav-buttons">
                <button class="boton slide-in" onclick="window.location.href='index.php'">
                    <i class="fas fa-home"></i> VOLVER A INICIO
                </button>
            </div>

            <div class="selector-container fade-in delay-2">
                <h2><i class="fas fa-history"></i> Consulta de Historial</h2>
                <form method="GET" class="selector-form">
                    <div class="form-group">
                        <label for="cubierta_seleccionada"><i class="fas fa-search"></i> Seleccionar Cubierta:</label>
                        <select name="cubierta_id" id="cubierta_seleccionada">
                            <option value="">-- Seleccionar Cubierta --</option>
                            <?php
                                // **¡IMPORTANTE! Reemplaza con tus credenciales de base de datos**
                                $servername = "localhost";
                                $username = "root";
                                $password = "";
                                $dbname = "prueba4";

                                $conn = new mysqli($servername, $username, $password, $dbname);

                                // Verificar la conexión
                                if ($conn->connect_error) {
                                    die("Conexión fallida: " . $conn->connect_error);
                                }

                                // Consulta para obtener todas las cubiertas
                                $sql_cubiertas = "SELECT id, nombre, 
                                    CASE WHEN estado = 'baja' THEN CONCAT(nombre, ' (BAJA)') ELSE nombre END AS nombre_display  
                                    FROM cubiertas 
                                    ORDER BY nombre";
                                $result_cubiertas = $conn->query($sql_cubiertas);

                                if ($result_cubiertas->num_rows > 0) {
                                    while ($row_cubierta = $result_cubiertas->fetch_assoc()) {
                                       echo "<option value='" . $row_cubierta["id"] . "'>" . $row_cubierta["nombre_display"] . "</option>";
                                    }
                                }
                                ?>
                        </select>
                    </div>
                    <input type="submit" value="Mostrar Historial">
                </form>
            </div>

            <?php
                if (isset($_GET["cubierta_id"]) && $_GET["cubierta_id"] != "") {
                    $cubierta_seleccionada_id = $_GET["cubierta_id"];

                    // Obtener el historial de la cubierta
                    $sql_historial_base = "
                        SELECT
                            hc.id AS historial_id,
                            hc.fecha_colocacion,
                            hc.fecha_retiro,
                            hc.kilometraje_colocacion,
                            hc.kilometraje_retiro,
                            co.id AS coche_id
                        FROM historial_cubiertas hc
                        LEFT JOIN coches co ON hc.coche_id = co.id
                        WHERE hc.cubierta_id = $cubierta_seleccionada_id
                        ORDER BY hc.fecha_colocacion DESC
                    ";
                    $result_historial_base = $conn->query($sql_historial_base);
                    
                    // Inicializar el array para evitar errores
                    $registros_historial = array();
                    
                    // Cargar los resultados en el array
                    if ($result_historial_base && $result_historial_base->num_rows > 0) {
                        while ($row = $result_historial_base->fetch_assoc()) {
                            $registros_historial[] = $row;
                        }
                    }

                    // Obtener TODAS las reconstrucciones de la cubierta
                    $sql_reconstrucciones = "
                        SELECT id, fecha_reconstruccion
                        FROM reconstrucciones
                        WHERE cubierta_id = $cubierta_seleccionada_id
                        ORDER BY fecha_reconstruccion DESC
                    ";
                    $result_reconstrucciones = $conn->query($sql_reconstrucciones);
                    
                    // Verificar si hay reconstrucciones
                    if (!$result_reconstrucciones) {
                        log_error("Error en la consulta de reconstrucciones: " . $conn->error);
                    }
                    
                    // Guardar las reconstrucciones en un array simple
                    $reconstrucciones = array();
                    if ($result_reconstrucciones && $result_reconstrucciones->num_rows > 0) {
                        while ($row_r = $result_reconstrucciones->fetch_assoc()) {
                            $reconstrucciones[] = $row_r['fecha_reconstruccion'];
                        }
                    }

                    // Obtener el nombre de la cubierta
                    $sql_nombre_cubierta = "SELECT nombre FROM cubiertas WHERE id = $cubierta_seleccionada_id";
                    $result_nombre_cubierta = $conn->query($sql_nombre_cubierta);

                    if ($result_nombre_cubierta->num_rows > 0) {
                        $row_nombre = $result_nombre_cubierta->fetch_assoc();
                        echo "<div class='diagrama-cubiertas fade-in delay-2'>";
                        echo "<div class='diagrama-titulo'><i class='fas fa-history'></i> Historial de la Cubierta: " . $row_nombre["nombre"] . "</div>";
                        
                        // NUEVA SECCIÓN: Mostrar todas las reconstrucciones
                        if (!empty($reconstrucciones)) {
                            echo "<div class='reconstrucciones-container fade-in delay-3'>";
                            echo "<h3><i class='fas fa-tools'></i> Historial de reconstrucciones</h3>";
                            
                            echo "<div class='reconstrucciones-lista'>";
                            foreach ($reconstrucciones as $index => $fecha) {
                                $num = $index + 1;
                                echo "<div class='reconstruccion-item'>";
                                echo "<span class='reconstruccion-num'>#$num</span>";
                                echo "<span class='reconstruccion-fecha'>$fecha</span>";
                                echo "</div>";
                            }
                            echo "</div>";
                            
                            echo "</div>";
                        }
                    
                        // COMPROBACIÓN PARA CASO ESPECIAL: Reconstrucciones sin historial
                        if (empty($registros_historial) && !empty($reconstrucciones)) {
                            echo "<div class='tabla-container'>";
                            echo "<table class='tabla-historial' id='tabla-historial-cubierta'>";
                            echo "<thead>";
                            echo "<tr>";
                            echo "<th colspan='6'><i class='fas fa-info-circle'></i> Esta cubierta no tiene registros de uso en coches</th>";
                            echo "</tr>";
                            echo "</thead>";
                            echo "<tbody>";
                            
                            echo "<tr class='hover-glow'>";
                            echo "<td colspan='6' class='mensaje-info' style='text-align:center;'>Sin datos de uso previo</td>";
                            echo "</tr>";
                            
                            echo "</tbody>";
                            echo "</table>";
                            echo "</div>";
                            
                            // Mensaje informativo adicional
                            echo "<div class='mensaje-info' style='margin-top:15px;'>";
                            echo "<i class='fas fa-info-circle'></i> Esta cubierta tiene reconstrucciones registradas pero no hay historial de uso en coches. ";
                            echo "Es posible que haya sido reconstruida antes de ser asignada a un coche o que los datos de uso no se hayan registrado correctamente.";
                            echo "</div>";
                            
                        } elseif (!empty($registros_historial)) {
							// Verificar el estado actual de la cubierta
$sql_estado_actual = "
    SELECT c.id, c.nombre, c.estado, 
    (SELECT coche_id FROM historial_cubiertas 
     WHERE cubierta_id = $cubierta_seleccionada_id 
     AND fecha_retiro IS NULL 
     LIMIT 1) AS coche_actual
    FROM cubiertas c
    WHERE c.id = $cubierta_seleccionada_id
";
$result_estado = $conn->query($sql_estado_actual);
$cubierta_actual = $result_estado->fetch_assoc();

$realmente_en_uso = false;
if ($cubierta_actual && !empty($cubierta_actual['coche_actual'])) {
    $realmente_en_uso = true;
}
                            // CASO NORMAL: Hay registros de historial
                            echo "<div class='tabla-container'>";
                            echo "<table class='tabla-historial' id='tabla-historial-cubierta'>";
                            echo "<thead>";
                            echo "<tr>";
                            echo "<th><i class='fas fa-calendar-plus'></i> Fecha Colocación</th>";
                            echo "<th><i class='fas fa-calendar-minus'></i> Fecha Retiro</th>";
                            echo "<th><i class='fas fa-tachometer-alt'></i> KM Colocación</th>";
                            echo "<th><i class='fas fa-tachometer-alt'></i> KM Retiro</th>";
                            echo "<th><i class='fas fa-car'></i> Coche ID</th>";
                            echo "<th><i class='fas fa-road'></i> KM Recorridos</th>";
                            echo "</tr>";
                            echo "</thead>";
                            echo "<tbody>";

                            // Mostrar cada registro de historial
                            // Mostrar cada registro de historial
foreach ($registros_historial as $index => $registro) {
    // Verificar si este es el último registro (más reciente) y no hay fecha de retiro
    $es_ultimo_registro = ($index === 0); // Asumiendo que están ordenados por fecha DESC
    
    // Determinar qué mostrar en el campo fecha_retiro
    $estado_fecha = $registro["fecha_retiro"];
    if (empty($estado_fecha)) {
        if ($es_ultimo_registro && !$realmente_en_uso) {
            $estado_fecha = 'Fuera de uso';
        } else {
            $estado_fecha = 'En uso';
        }
    }
    
    // Determinar qué mostrar en el campo kilometraje_retiro
    $estado_km = '';
    if (!empty($registro["kilometraje_retiro"])) {
        $estado_km = number_format($registro["kilometraje_retiro"], 0, ',', '.') . " km";
    } else {
        if ($es_ultimo_registro && !$realmente_en_uso) {
            $estado_km = 'Fuera de uso';
        } else {
            $estado_km = 'En uso';
        }
    }
    
    // Calcular kilómetros recorridos
    $km_recorridos = '';
    if (!empty($registro["kilometraje_colocacion"]) && !empty($registro["kilometraje_retiro"])) {
        $km_recorridos = $registro["kilometraje_retiro"] - $registro["kilometraje_colocacion"];
        $km_recorridos = number_format($km_recorridos, 0, ',', '.') . " km";
    } else if (!empty($registro["kilometraje_colocacion"])) {
        if ($es_ultimo_registro && !$realmente_en_uso) {
            $km_recorridos = 'Fuera de uso';
        } else {
            $km_recorridos = 'En uso';
        }
    }
    
    echo "<tr class='hover-glow'>";
    echo "<td>" . $registro["fecha_colocacion"] . "</td>";
    echo "<td>" . $estado_fecha . "</td>";
    echo "<td>" . number_format($registro["kilometraje_colocacion"], 0, ',', '.') . " km</td>";
    echo "<td>" . $estado_km . "</td>";
    echo "<td>" . $registro["coche_id"] . "</td>";
    echo "<td>" . $km_recorridos . "</td>";
    echo "</tr>";
}
                            
                            echo "</tbody>";
                            echo "</table>";
                            echo "</div>";
                            
                        } else {
                            // No hay historial ni reconstrucciones
                            echo "<p class='mensaje-info'><i class='fas fa-info-circle'></i> No hay historial disponible para esta cubierta.</p>";
                        }
                        
                        // Botón para exportar a PDF
                        if (!empty($registros_historial) || !empty($reconstrucciones)) {
                            echo "<div class='botones-exportar'>";
                            echo "<button class='boton exportar-pdf'>";
                            echo "<i class='fas fa-file-pdf'></i> Exportar a PDF";
                            echo "</button>";
                            echo "</div>";
                        }
                        
                        echo "</div>";
                    }
                }

                $conn->close();
            ?>
        </div>

        <footer class="fade-in delay-5">
            <p>&copy; <?php echo date('Y'); ?> Sistema de Gestión de Cubiertas | Empresa Casanova S.A todos los derechos reservados</p>
        </footer>
    </div>

    <script>
        // Script para manejar animaciones
        document.addEventListener('DOMContentLoaded', function() {
            // Añadir clase de animación a elementos
            const fadeElements = document.querySelectorAll('.fade-in');
            fadeElements.forEach((element, index) => {
                setTimeout(() => {
                    element.style.opacity = '1';
                }, 100 * index);
            });
            
            const slideElements = document.querySelectorAll('.slide-in');
            slideElements.forEach((element, index) => {
                setTimeout(() => {
                    element.style.opacity = '1';
                    element.style.transform = 'translateX(0)';
                }, 100 * index);
            });
            
            // Conectar la función de exportar PDF al botón (nuevo)
            const exportarBtn = document.querySelector('.exportar-pdf');
            if (exportarBtn) {
                exportarBtn.addEventListener('click', function() {
                    exportarPDF();
                });
            }
        });
        
        // Función para exportar a PDF (nuevo)
        function exportarPDF() {
            try {
                // Verificar si la biblioteca jsPDF está disponible
                if (typeof window.jspdf === 'undefined') {
                    throw new Error('La biblioteca jsPDF no está disponible. Verifique su conexión a internet.');
                }
                
                // Inicializar jsPDF
                const { jsPDF } = window.jspdf;
                const doc = new jsPDF();
                
                // Obtener el nombre de la cubierta
                const tituloCubierta = document.querySelector('.diagrama-titulo');
                const nombreCubierta = tituloCubierta ? tituloCubierta.textContent.replace('Historial de la Cubierta: ', '') : 'Cubierta';
                
                // Añadir título
                doc.setFontSize(18);
                doc.text(`Historial de ${nombreCubierta}`, 14, 20);
                
                // Añadir fecha de generación
                const fechaActual = new Date().toLocaleDateString();
                doc.setFontSize(10);
                doc.text(`Informe generado el ${fechaActual}`, 14, 30);
                
                // Sección de reconstrucciones
                const reconstruccionesContainer = document.querySelector('.reconstrucciones-container');
                if (reconstruccionesContainer) {
                    doc.setFontSize(14);
                    doc.text('Historial de reconstrucciones', 14, 40);
                    
                    const reconstruccionesItems = document.querySelectorAll('.reconstruccion-item');
                    if (reconstruccionesItems.length > 0) {
                        let y = 50;
                        reconstruccionesItems.forEach((item, index) => {
                            const numero = item.querySelector('.reconstruccion-num').textContent;
                            const fecha = item.querySelector('.reconstruccion-fecha').textContent;
                            doc.setFontSize(10);
                            doc.text(`${numero} ${fecha}`, 20, y);
                            y += 8; // Incrementar posición vertical
                        });
                    }
                }
                
                // Verificar si la tabla existe
                const tabla = document.getElementById('tabla-historial-cubierta');
                if (tabla) {
                    // Añadir la tabla al PDF
                    const startY = reconstruccionesContainer ? 70 : 40;
                    doc.autoTable({
                        html: '#tabla-historial-cubierta',
                        startY: startY,
                        styles: { fontSize: 8 },
                        headStyles: { fillColor: [52, 152, 219] },
                        alternateRowStyles: { fillColor: [240, 240, 240] },
                        margin: { top: startY }
                    });
                }
                
                // Guardar el PDF
                doc.save(`Historial_${nombreCubierta.trim()}_${fechaActual.replace(/\//g, '-')}.pdf`);
                alert('PDF exportado con éxito');
                
            } catch(error) {
                console.error('Error al exportar a PDF:', error);
                alert('Error al exportar a PDF: ' + error.message);
            }
        }
    </script>

    <style>
        /* Estilos específicos para la tabla de historial */
        .tabla-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        
        .tabla-historial {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            background-color: #2d2d2d;
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        
        .tabla-historial th, 
        .tabla-historial td {
            border: 1px solid #444;
            padding: 12px;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .tabla-historial th {
            background-color: var(--color-secondary);
            color: var(--color-text);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 14px;
            letter-spacing: 1px;
        }
        
        .tabla-historial th i {
            color: var(--color-primary);
            margin-right: 8px;
        }
        
        .tabla-historial tbody tr {
            transition: all 0.3s ease;
        }
        
        .tabla-historial tbody tr:hover {
            background-color: #383838;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }
        
        .mensaje-info {
            padding: 15px;
            background-color: rgba(52, 152, 219, 0.2);
            border-left: 5px solid var(--color-primary);
            color: var(--color-text);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .mensaje-info i {
            color: var(--color-primary);
            font-size: 20px;
        }
        
        /* Estilos para el botón de exportar (nuevo) */
        .botones-exportar {
            display: flex;
            justify-content: flex-end;
            margin-top: 15px;
        }
        
        .exportar-pdf {
            background-color: var(--color-secondary);
            color: var(--color-text);
            border: none;
            padding: 10px 15px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .exportar-pdf:hover {
            background-color: var(--color-primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .exportar-pdf i {
            font-size: 16px;
        }
        
        /* Estilos para la sección de reconstrucciones */
        .reconstrucciones-container {
            background-color: var(--color-card-bg);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
        }
        
        .reconstrucciones-container h3 {
            color: var(--color-primary);
            font-size: 18px;
            margin-top: 0;
            margin-bottom: 15px;
            font-weight: 600;
            border-bottom: 1px solid #444;
            padding-bottom: 8px;
        }
        
        .reconstrucciones-lista {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .reconstruccion-item {
            background-color: #333;
            padding: 10px 15px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }
        
        .reconstruccion-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
        }
        
        .reconstruccion-num {
            background-color: var(--color-primary);
            color: white;
            font-weight: bold;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .reconstruccion-fecha {
            color: white;
            font-size: 14px;
        }
    </style>
</body>
</html>
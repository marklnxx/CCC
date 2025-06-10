<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alertas de Cubiertas</title>
    <!-- Enlace a Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Enlace a Font Awesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Enlace a tu archivo CSS externo -->
    <link rel="stylesheet" href="styles-dark.css">
    <link rel="stylesheet" href="header-fix.css">
	
    <style>
	

    footer {
        background-color: #2c3e50 !important;
        color: #b3b3b3 !important;
        text-align: center !important;
        padding: 20px !important;
        margin-top: auto !important;
        border-radius: 10 !important;
        width: 100% !important;
        box-sizing: border-box !important;
        font-size: 14px !important;
    }

    footer p {
        margin: 0 !important;
    }

    .main-container {
        display: flex !important;
        flex-direction: column !important;
        min-height: 100vh !important;
    }

    .content {
        flex: 1 !important;
    }
	
	
        .alerta-card {
            background-color: #2c3e50;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            border-left: 5px solid #e74c3c;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .alerta-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
        }
        
        .alerta-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            border-bottom: 1px solid #34495e;
            padding-bottom: 10px;
        }
        
        .alerta-titulo {
            font-size: 1.2rem;
            font-weight: 600;
            color: #e74c3c;
            display: flex;
            align-items: center;
        }
        
        .alerta-titulo i {
            margin-right: 10px;
        }
        
        .alerta-km {
            background-color: #e74c3c;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .alerta-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .alerta-dato {
            margin-bottom: 5px;
        }
        
        .alerta-label {
            font-weight: 500;
            color: #bdc3c7;
        }
        
        .alerta-value {
            font-weight: 400;
            color: white;
        }
        
        .alerta-acciones {
            display: flex;
            justify-content: flex-end;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #34495e;
        }
        
        .boton-accion {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-left: 10px;
            font-size: 0.9rem;
            transition: background-color 0.3s ease;
            display: flex;
            align-items: center;
        }
        
        .boton-accion i {
            margin-right: 5px;
        }
        
        .boton-accion:hover {
            background-color: #2980b9;
        }
        
        .boton-accion.rojo {
            background-color: #e74c3c;
        }
        
        .boton-accion.rojo:hover {
            background-color: #c0392b;
        }
        
        .sin-alertas {
            text-align: center;
            padding: 30px;
            background-color: #2c3e50;
            border-radius: 8px;
        }
        
        .sin-alertas i {
            font-size: 3rem;
            color: #3498db;
            margin-bottom: 15px;
        }
        
        .filtros {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            background-color: #2c3e50;
            padding: 15px;
            border-radius: 8px;
            align-items: center;
        }
        
        .filtro-grupo {
            display: flex;
            align-items: center;
        }
        
        .filtro-grupo label {
            margin-right: 10px;
            font-weight: 500;
        }
        
        .filtro-grupo select {
            padding: 8px;
            border-radius: 5px;
            border: 1px solid #34495e;
            background-color: #34495e;
            color: white;
        }
        
        .contador-alertas {
            background-color: #e74c3c;
            color: white;
			height: 30px; /* Altura fija */
            line-height: 50px; /* Centra el texto verticalmente */
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
			margin-top: -10px; /* Ajusta este valor según cuánto quieras subirlo */
			position: relative; /* Asegura que el posicionamiento funcione correctamente */
			z-index: 10; /* Evita que otros elementos lo cubran */
        }
        
        .contador-alertas i {
            margin-right: 10px;
        }
        
        /* Animaciones */
        .fade-in {
            animation: fadeIn 0.5s ease forwards;
            opacity: 0;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .delay-1 {
            animation-delay: 0.1s;
        }
        
        .delay-2 {
            animation-delay: 0.2s;
        }
        
        .delay-3 {
            animation-delay: 0.3s;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header con logo -->
        <header>
            <div class="logo-container">
                <img src="LOGO.PNG" alt="Logo de la empresa" class="fade-in">
                <h1 class="fade-in delay-1">ALERTAS DE CUBIERTAS</h1>
            </div>
        </header>

        <div class="content">
            <!-- Botón de volver al inicio -->
            <div class="nav-buttons">
                <button class="boton slide-in" onclick="window.location.href='index.php'">
                    <i class="fas fa-home"></i> VOLVER AL INICIO
                </button>
            </div>

            <!-- Panel de filtros -->
            <div class="filtros fade-in delay-2">
                <div class="filtro-grupo">
                    <label for="filtro_alerta"><i class="fas fa-filter"></i> Filtrar por:</label>
                    <select name="filtro_alerta" id="filtro_alerta">
                        <option value="todos">Todas las alertas</option>
                        <option value="45000">Cubiertas cerca del límite (45,000+ km)</option>
                        <option value="50000">Cubiertas sobre el límite (50,000+ km)</option>
                    </select>
                </div>
                
                <?php
                // **¡IMPORTANTE! Reemplaza con tus credenciales de base de datos**
                $servername = "localhost";
                $username = "root";
                $password = "";
                $dbname = "prueba4";

                $conn = new mysqli($servername, $username, $password, $dbname);

                if ($conn->connect_error) {
                    die("Conexión fallida: " . $conn->connect_error);
                }
                
                // Conteo de alertas
                $sql_count = "SELECT 
                    SUM(CASE WHEN (km_actual - km_inicial >= 45000 AND km_actual - km_inicial < 50000) THEN 1 ELSE 0 END) as alertas_45k,
                    SUM(CASE WHEN (km_actual - km_inicial >= 50000) THEN 1 ELSE 0 END) as alertas_50k
                FROM (
                    SELECT 
                        c.id, 
                        c.nombre, 
                        c.coche_id,
                        (SELECT MAX(h.kilometraje_colocacion) FROM historial_cubiertas h 
                         WHERE h.cubierta_id = c.id AND h.fecha_retiro IS NULL) AS km_inicial,
                        (SELECT MAX(h2.kilometraje_retiro) FROM historial_cubiertas h2 
                         WHERE h2.cubierta_id = c.id) AS km_actual
                    FROM cubiertas c
                    WHERE c.coche_id IS NOT NULL
                ) AS subconsulta";
                
                $result_count = $conn->query($sql_count);
                $row_count = $result_count->fetch_assoc();
                $alertas_45k = $row_count['alertas_45k'] ?: 0;
                $alertas_50k = $row_count['alertas_50k'] ?: 0;
                $total_alertas = $alertas_45k + $alertas_50k;
                ?>
                
                <div class="contador-alertas">
                    <i class="fas fa-exclamation-triangle"></i> 
                    Total de alertas: <?php echo $total_alertas; ?>
                    (<?php echo $alertas_45k; ?> preventivas, <?php echo $alertas_50k; ?> críticas)
                </div>
            </div>

            <!-- Lista de alertas -->
            <div class="alertas-lista">
                <?php
                // Consulta para obtener las cubiertas que necesitan atención
                $sql = "SELECT 
                            c.id as cubierta_id, 
                            c.nombre as cubierta_nombre, 
                            c.coche_id,
                            h.fecha_colocacion,
                            h.kilometraje_colocacion as km_inicial,
                            COALESCE(h.kilometraje_retiro, 
                                (SELECT MAX(kilometraje_retiro) FROM historial_cubiertas 
                                 WHERE cubierta_id = c.id)) as km_actual,
                            (COALESCE(h.kilometraje_retiro, 
                                (SELECT MAX(kilometraje_retiro) FROM historial_cubiertas 
                                 WHERE cubierta_id = c.id)) - h.kilometraje_colocacion) as km_recorridos
                        FROM cubiertas c
                        JOIN historial_cubiertas h ON c.id = h.cubierta_id AND h.fecha_retiro IS NULL
                        WHERE c.coche_id IS NOT NULL
                        HAVING km_recorridos >= 45000
                        ORDER BY km_recorridos DESC";
                
                $result = $conn->query($sql);
                
                if ($result->num_rows > 0) {
                    $delay = 2;
                    while ($row = $result->fetch_assoc()) {
                        $delay++;
                        $km_recorridos = $row['km_recorridos'];
                        $es_critica = $km_recorridos >= 50000;
                        $fecha_colocacion = date('d/m/Y', strtotime($row['fecha_colocacion']));
                        
                        // Determinar ícono y mensaje según el kilometraje
                        $icono = $es_critica ? "fas fa-exclamation-circle" : "fas fa-exclamation-triangle";
                        $mensaje = $es_critica ? "¡LÍMITE ALCANZADO!" : "Cerca del límite";
                        $clase_filtro = $es_critica ? "alerta-50000" : "alerta-45000";
                        
                        echo "<div class='alerta-card fade-in delay-{$delay} {$clase_filtro}'>";
                        echo "<div class='alerta-header'>";
                        echo "<div class='alerta-titulo'>";
                        echo "<i class='{$icono}'></i> Cubierta {$row['cubierta_nombre']} - {$mensaje}";
                        echo "</div>";
                        echo "<div class='alerta-km'>";
                        echo number_format($km_recorridos, 0, ',', '.') . " km recorridos";
                        echo "</div>";
                        echo "</div>";
                        
                        echo "<div class='alerta-info'>";
                        echo "<div class='alerta-dato'>";
                        echo "<span class='alerta-label'><i class='fas fa-hashtag'></i> ID Cubierta:</span> ";
                        echo "<span class='alerta-value'>{$row['cubierta_id']}</span>";
                        echo "</div>";
                        
                        echo "<div class='alerta-dato'>";
                        echo "<span class='alerta-label'><i class='fas fa-car'></i> ID Coche:</span> ";
                        echo "<span class='alerta-value'>{$row['coche_id']}</span>";
                        echo "</div>";
                        
                        echo "<div class='alerta-dato'>";
                        echo "<span class='alerta-label'><i class='fas fa-calendar-alt'></i> Fecha colocación:</span> ";
                        echo "<span class='alerta-value'>{$fecha_colocacion}</span>";
                        echo "</div>";
                        
                        echo "<div class='alerta-dato'>";
                        echo "<span class='alerta-label'><i class='fas fa-tachometer-alt'></i> Kilometraje inicial:</span> ";
                        echo "<span class='alerta-value'>" . number_format($row['km_inicial'], 0, ',', '.') . " km</span>";
                        echo "</div>";
                        echo "</div>";
                        
                        echo "<div class='alerta-acciones'>";
                        echo "<button class='boton-accion' onclick=\"window.location.href='index.php?coche_id={$row['coche_id']}'\">";
                        echo "<i class='fas fa-eye'></i> Ver detalles";
                        echo "</button>";
                        
                        if ($es_critica) {
                            echo "<button class='boton-accion rojo' onclick=\"window.location.href='index.php?coche_id={$row['coche_id']}'\">";
                            echo "<i class='fas fa-exclamation-triangle'></i> Cambiar urgente";
                            echo "</button>";
                        }
                        
                        echo "</div>";
                        echo "</div>";
                    }
                } else {
                    echo "<div class='sin-alertas fade-in delay-3'>";
                    echo "<i class='fas fa-check-circle'></i>";
                    echo "<h2>No hay alertas de cubiertas</h2>";
                    echo "<p>Todas las cubiertas están dentro del rango de kilometraje seguro</p>";
                    echo "</div>";
                }
                
                $conn->close();
                ?>
				
				        </div>
						<br>
        
        <footer class="fade-in delay-5">
            <p>&copy; <?php echo date('Y'); ?> Sistema de Gestión de Cubiertas | Empresa Casanova S.A todos los derechos reservados</p>
        </footer>
    </div>
            </div>


    <script>
        // Script para filtrar alertas
        document.addEventListener('DOMContentLoaded', function() {
            const filtroSelect = document.getElementById('filtro_alerta');
            const alertas45k = document.querySelectorAll('.alerta-45000');
            const alertas50k = document.querySelectorAll('.alerta-50000');
            
            filtroSelect.addEventListener('change', function() {
                const filtroValor = this.value;
                
                if (filtroValor === 'todos') {
                    alertas45k.forEach(alerta => alerta.style.display = 'block');
                    alertas50k.forEach(alerta => alerta.style.display = 'block');
                } else if (filtroValor === '45000') {
                    alertas45k.forEach(alerta => alerta.style.display = 'block');
                    alertas50k.forEach(alerta => alerta.style.display = 'none');
                } else if (filtroValor === '50000') {
                    alertas45k.forEach(alerta => alerta.style.display = 'none');
                    alertas50k.forEach(alerta => alerta.style.display = 'block');
                }
            });
            
            // Efecto hover para tarjetas de alertas
            const cards = document.querySelectorAll('.alerta-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.boxShadow = '0 0 15px rgba(231, 76, 60, 0.5)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '0 4px 8px rgba(0, 0, 0, 0.2)';
                });
            });
        });
    </script>
</body>
</html>

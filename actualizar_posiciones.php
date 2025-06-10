<?php
// Script para actualizar la tabla de cubiertas y corregir el problema de posiciones
// Guardar este archivo como 'actualizar_posiciones.php' y ejecutarlo una vez

// Conexión a la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "prueba4";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Verificar si la tabla ya tiene el campo posición
$verificar_campo = $conn->query("SHOW COLUMNS FROM cubiertas LIKE 'posicion'");
if ($verificar_campo->num_rows == 0) {
    echo "<p>Añadiendo campo 'posicion' a la tabla cubiertas...</p>";
    
    // Añadir el campo posición a la tabla de cubiertas
    if ($conn->query("ALTER TABLE cubiertas ADD COLUMN posicion VARCHAR(50) NULL")) {
        echo "<p>Campo añadido correctamente.</p>";
    } else {
        echo "<p>Error al añadir el campo: " . $conn->error . "</p>";
        exit;
    }
    
    // Posiciones predefinidas en orden
    $posiciones = [
        "DELANTERA CHOFER",
        "DELANTERA PUERTA",
        "TRASERA CHOFER AFUERA",
        "TRASERA PUERTA AFUERA",
        "TRASERA CHOFER ADENTRO",
        "TRASERA PUERTA ADENTRO"
    ];
    
    echo "<p>Asignando posiciones a cubiertas existentes...</p>";
    
    // Obtener todos los coches
    $result_coches = $conn->query("SELECT id FROM coches");
    $total_coches = 0;
    $coches_actualizados = 0;
    
    while ($coche = $result_coches->fetch_assoc()) {
        $coche_id = $coche['id'];
        $total_coches++;
        
        // Obtener cubiertas de este coche
        $result_cubiertas = $conn->query("SELECT id FROM cubiertas WHERE coche_id = $coche_id ORDER BY id ASC");
        
        if ($result_cubiertas->num_rows > 0) {
            $index = 0;
            $cubiertas_actualizadas = 0;
            
            while ($cubierta = $result_cubiertas->fetch_assoc()) {
                if ($index < count($posiciones)) {
                    $cubierta_id = $cubierta['id'];
                    $posicion = $posiciones[$index];
                    
                    // Actualizar la posición de la cubierta
                    if ($conn->query("UPDATE cubiertas SET posicion = '$posicion' WHERE id = $cubierta_id")) {
                        $cubiertas_actualizadas++;
                    }
                    
                    $index++;
                }
            }
            
            if ($cubiertas_actualizadas > 0) {
                $coches_actualizados++;
                echo "<p>Actualizadas $cubiertas_actualizadas cubiertas para el coche ID: $coche_id</p>";
            }
        }
    }
    
    echo "<p>Proceso completado. Se actualizaron cubiertas en $coches_actualizados de $total_coches coches.</p>";
} else {
    echo "<p>El campo 'posicion' ya existe en la tabla cubiertas. No es necesario actualizar.</p>";
}

$conn->close();
echo "<p>Puede volver a la <a href='index.php'>página principal</a>.</p>";
?>
<?php
if(isset($_POST['agregar_coche']) && isset($_POST['nuevo_coche_id'])) {
    $nuevo_coche_id = $_POST['nuevo_coche_id'];
    
    // Conexión a la base de datos
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "prueba4";

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Conexión fallida: " . $conn->connect_error);
    }
    
    // Verificar si el coche ya existe
    $check_sql = "SELECT id FROM coches WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $nuevo_coche_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if($check_result->num_rows > 0) {
        echo "<script>alert('Error: El coche con ID " . $nuevo_coche_id . " ya existe.'); window.location.href = 'index.php';</script>";
    } else {
        // Insertar el nuevo coche
        $insert_sql = "INSERT INTO coches (id) VALUES (?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("i", $nuevo_coche_id);
        
        if($insert_stmt->execute()) {
            echo "<script>alert('Coche con ID " . $nuevo_coche_id . " agregado con éxito.'); window.location.href = 'index.php';</script>";
        } else {
            echo "<script>alert('Error al agregar el coche: " . $conn->error . "'); window.location.href = 'index.php';</script>";
        }
        
        $insert_stmt->close();
    }
    
    $check_stmt->close();
    $conn->close();
}
?>
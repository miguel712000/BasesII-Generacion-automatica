<?php

	//Variables obtenidas por POST de index.html
	$host = $_POST['host'];
	$port = $_POST['portNumber'];
	$username = $_POST['username'];
	$password = $_POST['password'];
	$dbname = $_POST['dbname'];
	
	//plantilla conexión
	$connTemplate = "host=#host port=#port dbname=#dbname user=#user password=#password";

	//Conexión a base de datos
	$connStr = str_replace('#host', $host, $connTemplate);
	$connStr = str_replace('#port', $port, $connStr);
	$connStr = str_replace('#user', $username, $connStr);
	$connStr = str_replace('#password', $password, $connStr);
	$connStr = str_replace('#dbname', $dbname, $connStr);
    
	$conn = pg_connect($connStr) or die('Error de conexion');
    
	//Consulta de tablas con su esquema
    $result = pg_query($conn, "select table_schema,table_name from information_schema.tables");
    if (!$result) {
      echo "Ocurrió un error en la consulta.\n";
      exit;
    }
	
	//Generación de tabla con información de consulta
    echo "<table id='databaseTables'>";
	echo "<tr> <th>Esquema</th> <th>Tabla</th> <th>Selección</th> </tr>";
	while ($row = pg_fetch_row($result)) {
      echo "<tr> <td>$row[0]</td>  <td>$row[1]</td> <td> <input type='checkbox' name=$row[1] id=$row[1]></td> </tr>";
    }
	
	//
    echo ("<label style='color: #18ad22;'>conectado </label> <br>");
	echo ("Seleccione las tablas a las que desea generarles CRUDs: &nbsp;");
	echo ("<button onclick='submitTables(\"databaseTables\")'> Elegir tablas </button>");
	
	
?>    

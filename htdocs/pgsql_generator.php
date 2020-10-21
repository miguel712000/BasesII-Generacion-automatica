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
    
	//Si el post incluye una lista de esquemas y tablas, se generan los cruds
	if(isset($_POST['schema']) and isset($_POST['table'])) {
		$schemaInfo = $_POST['schema'];
		$tableInfo = $_POST['table'];
		//si incluye prefijo
		if(isset($_POST['prefix'])){
			$prefix = $_POST['prefix'];
		}
		//si incluye esquema para generar los procedimientos
		if(isset($_POST['targetSchema'])){
			$targetSchema = $_POST['targetSchema'];
		}
		//asignacion de esquemas y tablas del post a arrays php
		$schemaArray = array();
		$tableArray = array();
		foreach ($schemaInfo as $schema){
			$schemaArray[] = $schema;
		}
		foreach ($tableInfo as $table){
			$tableArray[] = $table;
		}
		//ciclo de generacion de procedimientos
		for($i = 0; $i<sizeof($tableArray); $i++){
			//consulta de informacion de atributos de la tabla
			$query_str = "select column_name, data_type, character_maximum_length from information_schema.columns
						  where table_name='#table_name' and table_schema= '#table_schema'
						  order by ordinal_position;";
			$query_str = str_replace("#table_name", $tableArray[$i], $query_str);
			$query_str = str_replace("#table_schema", $schemaArray[$i], $query_str);
			$result = pg_query($conn, $query_str);
			//asignacion de informacion de atributos a arrays php
			$columns = array();
			$dataTypes = array();
			$dataSizes = array();
			while ($row = pg_fetch_row($result)) {
				$columns[] = $row[0];
				$dataTypes[] = $row[1];
				$dataSizes[] = $row[2];
			}
			
			//generacion de insert
			$insertProcedureStr = "CREATE OR REPLACE PROCEDURE ";
			if(isset($targetSchema)){
				$insertProcedureStr .= "{$targetSchema}.";
			}
			if(isset($prefix)){
				$insertProcedureStr .= "{$prefix}_";
			}
			
			$insertProcedureStr .= "insert_{$tableArray[$i]}(";
			//parametros del procedimiento
			for($columnIndex = 0; $columnIndex < sizeof($columns); $columnIndex++){
				$insertProcedureStr .= "IN p_{$columns[$columnIndex]} {$dataTypes[$columnIndex]}";
				if(!is_null($dataSizes[$columnIndex])){
					$insertProcedureStr .= "({$dataSizes[$columnIndex]})";
				}
				if(($columnIndex+1) != sizeof($columns)){
					$insertProcedureStr .= ", ";
				}
			}
			$insertProcedureStr .= ")";
			//correccion de tipos de dato timestamp y timestamptz
			$insertProcedureStr = str_replace("timestamp without time zone", 'timestamp', $insertProcedureStr);
			$insertProcedureStr = str_replace("timestamp with time zone", 'timestamptz', $insertProcedureStr);
			
			$insertProcedureStr .= "<br>";
			$insertProcedureStr .= "LANGUAGE plpgsql <br>";
			$insertProcedureStr .= "AS $$ <br>";
			$insertProcedureStr .= "BEGIN <br>";
			//cuerpo del procedimiento
			$insertProcedureStr .= "INSERT INTO {$schemaArray[$i]}.{$tableArray[$i]}" . "<br>";
			$insertProcedureStr .= "VALUES (";
			//values de la insercion según los atributos
			for($columnIndex = 0; $columnIndex < sizeof($columns); $columnIndex++){
				$insertProcedureStr .= "p_{$columns[$columnIndex]}";
				if(($columnIndex+1) != sizeof($columns)){
					$insertProcedureStr .= ", ";
				}
			}
			$insertProcedureStr .= "); <br>";
			$insertProcedureStr .= "END; $$ <br>";
			echo($insertProcedureStr);
			echo("<br>");
		}
	}
	//si no hay tablas ni esquemas en el post, muestra el menú para seleccionar tablas para generar cruds
	else {
	//Consulta de tablas con su esquema
		$result = pg_query($conn, "select table_schema,table_name from information_schema.tables");
		if (!$result) {
			echo "Ocurrió un error en la consulta.\n";
			exit;
		}
	
	//Etiquetas de tabla
		echo ("<label style='color: #18ad22;'>conectado </label> <br>");
		echo ("Seleccione las tablas a las que desea generarles CRUDs: &nbsp;");
	//Generación de tabla con información de consulta
		echo "<table id='databaseTables'>";
		echo "<tr> <th>Esquema</th> <th>Tabla</th> <th>Selección</th> </tr>";
		while ($row = pg_fetch_row($result)) {
			echo "<tr> <td>$row[0]</td>  <td>$row[1]</td> <td> <input type='checkbox' name=$row[1] id=$row[1]></td> </tr>";
		}
		echo "</table>";
	
	//Información de generacion
		echo ("<br>");
		echo ("Elija el esquema y prefijo de los procedimientos a generar:");
		echo ("<br>");
		echo ("-Si el esquema ingresado no coincide con ningún esquema dentro de la base, será creado al ejecutar");
		echo ("<br>");
		echo ("-Si no ingresa ningún esquema, se utilizará el esquema predeterminado.");
		echo ("<br>");
	//inputs para opciones de generacion
		echo ("<label for='schema'> Esquema: </label>
			   <input type='text' id='schema' name='schema'>");
		echo ("<br>");
		echo ("<label for='prefix'> Prefijo: </label>&nbsp;&nbsp;
			   <input type='text' id='prefix' name='prefix'>");
		echo ("<br>");
		echo ("<button onclick='generateSQLCode(\"databaseTables\")'> Generar código </button>&nbsp;&nbsp;");
		echo ("<button onclick='submitTables(\"databaseTables\")'> Generar código y Ejecutar </button>");
	
	}
?>    

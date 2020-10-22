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
    
	////////GENERACION DE CRUDs\\\\\\\\\\\
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
			if(isset($_POST['execute'])){ //crear esquema (solo si se ejecuta el codigo)
				$result = pg_query($conn, "CREATE SCHEMA IF NOT EXISTS {$targetSchema};");
				if($result){
					echo("<br>Esquema {$targetSchema} creado o existente.<br>");
				}
			}
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
		//en este ciclo se generan los cruds de cada tabla seleccionada, cada iteracion es una tabla
		for($i = 0; $i<sizeof($tableArray); $i++){
			echo ("<h1>{$schemaArray[$i]}.{$tableArray[$i]}</h1>");
			
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
			
			////////GENERACION INSERT\\\\\\\\\\\
			
			echo("<h2> Insert </h2>");
			//inicio del procedimiento
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
			echo($insertProcedureStr); //imprimir el procedimiento en html
			
			//si se solicitó ejecutar el código
			if(isset($_POST['execute'])){
				$insertProcedureStr = str_replace("<br>", " ", $insertProcedureStr);
				$result = pg_query($conn, $insertProcedureStr);
				if($result){
					echo("<label style='color: #18ad22;'>Procedimiento ejecutado </label><br>");
				} else {
					echo("<label style='color: #ed2618;'>Error al ejecutar </label><br>");
				}
				echo("<br>");
			}
			
			////////GENERACION SELECT\\\\\\\\\\\
			
			echo ("<h2>Select</h2>");
			//inicio del procedimiento
			$selectProcedureStr = "CREATE OR REPLACE FUNCTION ";
			if(isset($targetSchema)){
				$selectProcedureStr .= "{$targetSchema}.";
			}
			if(isset($prefix)){
				$selectProcedureStr .= "{$prefix}_";
			}
			$selectProcedureStr .= "select_{$tableArray[$i]}(";
			
			//parametros del procedimiento
			for($columnIndex = 0; $columnIndex < sizeof($columns); $columnIndex++){
				$selectProcedureStr .= "IN p_{$columns[$columnIndex]} {$dataTypes[$columnIndex]}";
				if(!is_null($dataSizes[$columnIndex])){
					$selectProcedureStr .= "({$dataSizes[$columnIndex]})";
				}
				if(($columnIndex+1) != sizeof($columns)){
					$selectProcedureStr .= ", ";
				}
			}
			$selectProcedureStr .= ") ";
			//correccion de tipos de dato timestamp y timestamptz
			$selectProcedureStr = str_replace("timestamp without time zone", 'timestamp', $selectProcedureStr);
			$selectProcedureStr = str_replace("timestamp with time zone", 'timestamptz', $selectProcedureStr);
			
			//tipos de dato de la tabla retornada
			$selectProcedureStr .= "RETURNS TABLE (";
			for($columnIndex = 0; $columnIndex < sizeof($columns); $columnIndex++){
				$selectProcedureStr .= "{$columns[$columnIndex]} {$dataTypes[$columnIndex]}";
				if(!is_null($dataSizes[$columnIndex])){
					$selectProcedureStr .= "({$dataSizes[$columnIndex]})";
				}
				if(($columnIndex+1) != sizeof($columns)){
					$selectProcedureStr .= ", ";
				}
			}
			$selectProcedureStr .= ")<br>";
			
			$selectProcedureStr .= "LANGUAGE plpgsql <br>";
			$selectProcedureStr .= "AS $$ <br>";
			$selectProcedureStr .= "BEGIN <br>";
			$selectProcedureStr .= "RETURN QUERY<br>";
			$selectProcedureStr .= "SELECT ";
			
			//atributos del select
			for($columnIndex = 0; $columnIndex < sizeof($columns); $columnIndex++){
				$selectProcedureStr .= "{$schemaArray[$i]}.{$tableArray[$i]}.{$columns[$columnIndex]}";
				if(($columnIndex+1) != sizeof($columns)){
					$selectProcedureStr .= ", ";
				}
			}
			$selectProcedureStr .= " FROM {$schemaArray[$i]}.{$tableArray[$i]}<br>";
			$selectProcedureStr .= "WHERE (";
			
			//filtros para los atributos
			for($columnIndex = 0; $columnIndex < sizeof($columns); $columnIndex++){
				$selectProcedureStr .= "(";
				$selectProcedureStr .= "{$schemaArray[$i]}.{$tableArray[$i]}.{$columns[$columnIndex]} = p_{$columns[$columnIndex]}";
				$selectProcedureStr .= " OR p_{$columns[$columnIndex]} IS NULL";
				$selectProcedureStr .= ")";
				if(($columnIndex+1) != sizeof($columns)){
					$selectProcedureStr .= "<br> AND ";
				}
			}
			$selectProcedureStr .=");<br>";
			$selectProcedureStr .="END; $$<br>";
			echo($selectProcedureStr); //imprimir el procedimiento
			
			//si se solicita ejecutar
			if(isset($_POST['execute'])){
				$selectProcedureStr = str_replace("<br>", " ", $selectProcedureStr);
				$result = pg_query($conn, $selectProcedureStr);
				if($result){
					echo("<label style='color: #18ad22;'>Procedimiento ejecutado </label><br>");
				} else {
					echo("<label style='color: #ed2618;'>Error al ejecutar </label><br>");
				}
				echo("<br>");
			}
			
			////////GENERACION UPDATE\\\\\\\\\\\
			echo("<h2>Update</h2>");
			
			//consulta de atributo llave primaria
			$result = pg_query($conn, "SELECT c.column_name, c.data_type, c.character_maximum_length
									   FROM information_schema.table_constraints tc 
									   JOIN information_schema.constraint_column_usage AS ccu USING (constraint_schema, constraint_name) 
									   JOIN information_schema.columns AS c ON c.table_schema = tc.constraint_schema
										AND tc.table_name = c.table_name AND ccu.column_name = c.column_name
									   WHERE constraint_type = 'PRIMARY KEY' and tc.table_name = '{$tableArray[$i]}' and tc.table_schema = '{$schemaArray[$i]}';");
			if($result){
				$row = pg_fetch_row($result);
				$pkColumn = $row[0];
				$pkDataType = $row[1];
				$pkDataSize = $row[2];
			}
			//inicio del procedimiento
			$updateProcedureStr = "CREATE OR REPLACE PROCEDURE ";
			if(isset($targetSchema)){
				$updateProcedureStr .= "{$targetSchema}.";
			}
			if(isset($prefix)){
				$updateProcedureStr .= "{$prefix}_";
			}
			
			$updateProcedureStr .= "update_{$tableArray[$i]}(";
			
			//parametros del procedimiento
			$updateProcedureStr .= "IN pk_{$pkColumn} {$pkDataType}";
			if(!is_null($pkDataSize)){
				$updateProcedureStr .= "({$pkDataSize})";
			}
			$updateProcedureStr .= ", ";
			
			for($columnIndex = 0; $columnIndex < sizeof($columns); $columnIndex++){
				$updateProcedureStr .= "IN p_{$columns[$columnIndex]} {$dataTypes[$columnIndex]}";
				if(!is_null($dataSizes[$columnIndex])){
					$updateProcedureStr .= "({$dataSizes[$columnIndex]})";
				}
				if(($columnIndex+1) != sizeof($columns)){
					$updateProcedureStr .= ", ";
				}
			}
			$updateProcedureStr .= ") <br>";
			
			//correccion de tipos de dato timestamp y timestamptz
			$updateProcedureStr = str_replace("timestamp without time zone", 'timestamp', $updateProcedureStr);
			$updateProcedureStr = str_replace("timestamp with time zone", 'timestamptz', $updateProcedureStr);
			
			$updateProcedureStr .= "LANGUAGE plpgsql <br>";
			$updateProcedureStr .= "AS $$ <br>";
			$updateProcedureStr .= "BEGIN <br>";
			$updateProcedureStr .= "UPDATE {$schemaArray[$i]}.{$tableArray[$i]} SET <br>";
			//atributos a actualizar (atributos del SET)
			for($columnIndex = 0; $columnIndex < sizeof($columns); $columnIndex++){
				$updateProcedureStr .= "{$columns[$columnIndex]} = p_{$columns[$columnIndex]}";
				if(($columnIndex+1) != sizeof($columns)){
					$updateProcedureStr .= ", <br>";
				}
			}
			//llave primaria de la fila a cambiar (WHERE)
			$updateProcedureStr .= "<br> WHERE {$pkColumn} = pk_{$pkColumn};";
			$updateProcedureStr .="<br>END; $$<br>";
			
			echo($updateProcedureStr); //imprimir procedimiento
			
			//si se solicita ejecutar el código
			if(isset($_POST['execute'])){
				$updateProcedureStr = str_replace("<br>", " ", $updateProcedureStr);
				$result = pg_query($conn, $updateProcedureStr);
				if($result){
					echo("<label style='color: #18ad22;'>Procedimiento ejecutado </label><br>");
				} else {
					echo("<label style='color: #ed2618;'>Error al ejecutar </label><br>");
				}
				echo("<br>");
			}
			
			////////GENERACION DELETE\\\\\\\\\\\
			echo("<h2>Delete</h2>");
			
			//inicio del procedimiento
			$deleteProcedureStr = "CREATE OR REPLACE PROCEDURE ";
			if(isset($targetSchema)){
				$deleteProcedureStr .= "{$targetSchema}.";
			}
			if(isset($prefix)){
				$deleteProcedureStr .= "{$prefix}_";
			}
			
			$deleteProcedureStr .= "delete_{$tableArray[$i]}(";
			
			//parametro de llave primaria de la fila a borrar
			$deleteProcedureStr .= "IN pk_{$pkColumn} {$pkDataType}";
			if(!is_null($pkDataSize)){
				$deleteProcedureStr .= "({$pkDataSize})";
			}
			
			$deleteProcedureStr .=") <br>";
			
			//correccion de tipos de dato timestamp y timestamptz
			$deleteProcedureStr = str_replace("timestamp without time zone", 'timestamp', $deleteProcedureStr);
			$deleteProcedureStr = str_replace("timestamp with time zone", 'timestamptz', $deleteProcedureStr);
			
			$deleteProcedureStr .= "LANGUAGE plpgsql <br>";
			$deleteProcedureStr .= "AS $$ <br>";
			$deleteProcedureStr .= "BEGIN <br>";
			//cuerpo del delete
			$deleteProcedureStr .= "DELETE FROM {$schemaArray[$i]}.{$tableArray[$i]} <br>";
			$deleteProcedureStr .= "WHERE {$pkColumn} = pk_{$pkColumn}; <br>";
			
			$deleteProcedureStr .="END; $$<br>";
			
			echo ($deleteProcedureStr); //imprimir procedimiento
			
			if(isset($_POST['execute'])){
				$deleteProcedureStr = str_replace("<br>", " ", $deleteProcedureStr);
				$result = pg_query($conn, $deleteProcedureStr);
				if($result){
					echo("<label style='color: #18ad22;'>Procedimiento ejecutado </label><br>");
				} else {
					echo("<label style='color: #ed2618;'>Error al ejecutar </label><br>");
				}
				echo("<br>");
			}
			echo ("<hr>");
		}
		//fin de generacion de cruds
	}
	////////GENERACION DE TABLA CON TABLAS DE LA BD\\\\\\\\\\\
	//si no hay tablas ni esquemas en el post, muestra el menú para seleccionar tablas para generar cruds
	else {
	//Consulta de tablas con su esquema respectivo
		$result = pg_query($conn, "select table_schema, table_name
								   from information_schema.tables
								   where table_schema not in ('pg_catalog', 'information_schema');");
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
	
	//Información para generacion
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
		echo ("<label for='executeCheckbox'> ¿Ejecutar código? </label>");
		echo ("<input type='checkbox' id='executeCheckbox'>");
	}
?>    

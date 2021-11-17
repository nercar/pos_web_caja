<?php
	/**
	* Permite obtener los datos de la base de datos y retornarlos
	* en modo json o array
	*/

	try {
		date_default_timezone_set('America/Bogota');
		// Se capturan las opciones por Post
		$opcion = (isset($_POST["opcion"])) ? $_POST["opcion"] : "";
		$fecha  = (isset($_POST["fecha"]) ) ? $_POST["fecha"]  : date("Y-m-d");
		$hora   = (isset($_POST["hora"])  ) ? $_POST["hora"]   : date("H:i:s");

		// id para los filtros en las consultas
		$idpara = (isset($_POST["idpara"])) ? $_POST["idpara"] : '';

		// Se establece la conexion con la BBDD
		$params = parse_ini_file('../dist/config.ini');

		if ($params === false) {
			// exeption leyen archivo config
			throw new \Exception("Error reading database configuration file");
		}

		// connect to the sql server database
		if($params['instance']!='') {
			$conStr = sprintf("Driver={SQL Server};Server=%s\%s;",$params['host_sql'],$params['instance']);
		} else {
			$conStr = sprintf("Driver={SQL Server};Server=%s;",$params['host_sql']);
		}

		$connec   = odbc_connect( $conStr, $params['user_sql'], $params['password_sql'] );

		$moneda   = $params['moneda'];
		$simbolo  = $params['simbolo'];
		$host_ppl = $params['host_ppl'];
		$bdes     = $params['bdes'];

		switch ($opcion) {
			case 'hora_srv':
				echo json_encode('1¬' . $hora);
				break;

			case 'iniciar_sesion':
				extract($_POST);
				if(empty($tusuario) || empty($tclave)){
					header("Location: " . $idpara);
					break;
				}

				// $connec = odbc_connect( "Driver={SQL Server};Server=localhost,1433;", $params['user_sql'], '' );
				$connec = odbc_connect( "Driver={SQL Server};Server=".$params['local'].",".$params['port_sql'].";", $params['user_sql'], $params['local_pasw'] );

				$sql = "SELECT login, descripcion, codusuario, password AS clave, activo
						FROM ".$bdes.".dbo.ESUsuarios WHERE LOWER(login)=LOWER('$tusuario')
						AND password = '$tclave'";

				$sql = odbc_exec( $connec, $sql );
				if(!$sql) print("Error en Consulta SQL (".odbc_errormsg($connec).")");

				$row = odbc_fetch_array($sql);

				if($row) {
					if($row['activo']!=1) {
						session_destroy();
						session_commit();
						session_start();
						session_id($_SESSION['id']);
						session_destroy();
						session_commit();
						session_start();
						$_SESSION['error'] = 2;
						header("Location: " . $idpara);
					} else {
						session_start();
						$_SESSION['id']         = session_id();
						$_SESSION['url']        = $idpara;
						$_SESSION['usuario']    = strtolower($row['login']);
						$_SESSION['nomusuario'] = ucwords(strtolower($row['descripcion']));
						$_SESSION['error']      = 0;
						header("Location: " . $idpara . "inicio.php");
					}
				} else {
					session_start();
					session_id($_SESSION['id']);
					session_destroy();
					session_commit();
					session_start();
					$_SESSION['error'] = 1;
					header("Location: " . $idpara);
				}
				break;

			case 'cerrar_sesion':
				session_start();
				session_id($_SESSION['id']);
				session_destroy();
				session_commit();
				header("Location: " . $_SESSION['url']);
				exit();
				break;

			case 'cedulasid':
				$sql = "SELECT id, descripcion, predeterminado
						FROM BDES.dbo.ESCedulasId
						ORDER BY predeterminado DESC";

				$sql = odbc_exec( $connec, $sql );
				if(!$sql) print("Error en Consulta SQL (".odbc_errormsg($connec).")");

				$datos = [];
				while ($row = odbc_fetch_array($sql)) {
					$datos[] = [
						'id'             => $row['id'],
						'descripcion'    => $row['descripcion'],
						'predeterminado' => $row['predeterminado'],
					];
				}

				echo json_encode($datos);
				break;

			case 'buscarEmpresa':
				$sql = "SELECT TOP 1 b.id_empresa, b.nom_empresa
						FROM BDES_POS.dbo.DBBonos b
						WHERE b.id_empresa = '$idpara'";

				$sql = odbc_exec( $connec, $sql );
				if(!$sql) print("Error en Consulta SQL (".odbc_errormsg($connec).")");

				$row = odbc_fetch_array($sql);
				$id_empresa  = $row['id_empresa'];
				$nom_empresa = utf8_encode($row['nom_empresa']);

				echo json_encode(
					array(
						'id_empresa'   => $id_empresa,
						'nom_empresa'  => $nom_empresa,
					));
				break;

			case 'buscarBen':
				$idpara = explode('¬', $idpara);
				$sql = "SELECT cl.RAZON, SUM(saldo) AS saldo, consumo
						FROM (
							SELECT DBH.id_beneficiario, DBB.consumo,
								(CASE WHEN DBH.tipo = 1 THEN SUM(DBH.monto) ELSE SUM(DBH.monto)*(-1) END) AS saldo
							FROM BDES_POS.dbo.DBBonos_H DBH
							INNER JOIN BDES_POS.dbo.DBBonos DBB ON DBB.id_beneficiario = DBH.id_beneficiario
								AND DBB.id_empresa = DBH.id_empresa
								WHERE DBH.id_beneficiario = '$idpara[0]' AND DBH.id_empresa = '$idpara[1]'
							GROUP BY DBH.id_beneficiario, DBH.tipo, DBB.consumo
						) AS ben
						INNER JOIN BDES_POS.dbo.ESCLIENTESPOS AS cl ON cl.RIF = ben.id_beneficiario
						GROUP BY cl.RAZON, consumo";

				$sql = odbc_exec( $connec, $sql );

				if(!$sql) {
					print("Error en Consulta SQL (".odbc_errormsg($connec).")");
					$saldo   = 0;
					$nom_ben = '';
					$consumo = 0;
				} else if(odbc_num_rows($sql)==0) {
					$saldo   = 0;
					$nom_ben = '';
					$consumo = 0;
				} else {
					$row     = odbc_fetch_array($sql);
					$saldo   = $row['saldo']*1;
					$nom_ben = utf8_encode($row['RAZON']);
					$consumo = $row['consumo']*1;
				}

				echo json_encode(
					array(
						'nom_ben' => $nom_ben,
						'saldo'   => number_format($saldo, 2),
						'monto'   => $saldo,
						'consumo' => $consumo,
					));
				break;

			case 'validarArchivoCsv':
				$target_path = "../tmp/";
				$archivoreal = basename($_FILES['archivo']['name']);
				$extension = explode('.', $archivoreal);
				$extension = end($extension);
				$datosret = [];
				$table = "Hubo un error, Por favor revise el archivo y trate de nuevo!(" . $archivoreal . ")";
				if($extension == 'csv') {
					$archivoreal = bin2hex(random_bytes(10)) . '.' . $extension;
					$archivotemp = $_FILES['archivo']['tmp_name'];
					$target_path = $target_path . $archivoreal;
					if(move_uploaded_file($archivotemp, $target_path)) {
						$delimiter = getFileDelimiter($target_path);
						$table = '<table width="100%" cellpadding="2" cellspacing="2" class="table-bordered">';
						//Abrimos nuestro archivo
						$archivo = fopen($target_path, "r");

						// recorremos el archivo
						while(($datos = fgetcsv($archivo, null, $delimiter)) == true) {
							$datosret[] = $datos;
							$table .= '<tr>';
							for($i=0;$i<count($datos);$i++) {
								if($datos[$i]!=='') {
									$table .= '<td>' . utf8_encode($datos[$i]) . '</td>';
								}
							}
							$table .= '</tr>';
						}
						$table.'</table>';

						unset($datosret[0]);

						$datosret = array_values($datosret);

						//Cerramos el archivo
						fclose($archivo);
						unlink($target_path);
					}
				}

				foreach($datosret as &$dato) {
					$dato[1] = utf8_encode($dato[1]);
					$dato[3] = utf8_encode($dato[3]);
				}

				echo json_encode(array('tabla' => $table, 'datos' => $datosret));
				break;

			default:
				# code...
				break;
		}

		// Se cierra la conexion
		$connec = null;

	} catch (PDOException $e) {
		echo "Error : " . $e->getMessage() . "<br/>";
		die();
	}

	function getFileDelimiter($file, $checkLines = 2){
		$file = new SplFileObject($file);
		$delimiters = array( ',', '\t', ';', '|', ':' );
		$results = array();
		$i = 0;
		while($file->valid() && $i <= $checkLines){
			$line = $file->fgets();
			foreach ($delimiters as $delimiter){
				$regExp = '/['.$delimiter.']/';
				$fields = preg_split($regExp, $line);
				if(count($fields) > 1){
					if(!empty($results[$delimiter])){
						$results[$delimiter]++;
					} else {
						$results[$delimiter] = 1;
					}
				}
			}
			$i++;
		}
		$results = array_keys($results, max($results)); return $results[0];
	}
?>
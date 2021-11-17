<?php
	include_once("barcode.php");
	require_once '../impresion/autoload.php';
	use Mike42\Escpos\Printer;
	use Mike42\Escpos\EscposImage;
	use Mike42\Escpos\CapabilityProfile;
	use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

	try {
		date_default_timezone_set('America/Bogota');
		// Se capturan las opciones por Post
		extract($_POST);

		// Se establece la conexion con la BBDD
		$params = parse_ini_file('../dist/config.ini');

		if ($params === false) {
			throw new \Exception("Error reading database configuration file");
		}

		// connect to the sql server database
		if($params['instance']!='') {
			$conStr = sprintf("Driver={SQL Server};Server=%s\%s;",$params['host_sql'],$params['instance']);
		} else {
			$conStr = sprintf("Driver={SQL Server};Server=%s;",$params['host_sql']);
		}

		$connec = odbc_connect( $conStr, $params['user_sql'], $params['password_sql'] );

		$moneda      = $params['moneda'];
		$simbolo     = $params['simbolo'];

		$datos = [];
		$sql = "SELECT SUM(saldo) AS saldo FROM
				(SELECT (CASE WHEN tipo = 1 THEN SUM(monto) ELSE SUM(monto)*(-1) END) AS saldo
				FROM BDES_POS.dbo.DBBonos_H
				WHERE id_beneficiario = '$id_ben' AND id_empresa = '$id_emp'
				GROUP BY tipo) AS ben";

		$sql = odbc_exec( $connec, $sql );
		if(!$sql) print("Error en Consulta SQL (".odbc_errormsg($connec).")");

		$row = odbc_fetch_array($sql);
		if($row) {
			$saldo = $row['saldo']*1;
		} else {
			$saldo = 0;
		}

		if($saldo>0 && $saldo>=$montob) {
			$veces = 1;
			$largo = strlen($nomemp);
			$nom_emp = '';
			if($largo>48) {
				$veces = $largo / 48;
			}
			$j = 0;
			for ($i=1; $i <= $veces; $i++) {
				$nom_emp .= substr($nomemp, $j, 48) . "\n";
				$j += 48;
			}
			$veces = 1;
			$largo = strlen($nomben);
			$nom_ben = '';
			if($largo>48) {
				$veces = $largo / 48;
			}
			$j = 0;
			for ($i=1; $i <= $veces; $i++) {
				$nom_ben .= substr($nomben, $j, 48) ."\n";
				$j += 48;
			}

			$referencia = (bin2hex(random_bytes(7)));

			barcode('../tmp/'.$referencia.'.png', $referencia, 80, 'horizontal', 'code128', false, 2);
			$code = '../tmp/'.$referencia.'.png';

			barcode('../tmp/'.$id_ben.'.png', $id_ben, 80, 'horizontal', 'code128', false, 2);
			$code_imp = '../tmp/'.$id_ben.'.png';

			// Se inicializa el nombre de la impresora, la cual debe estar compartida
			$nprinter = "TM-T20II";

			try {
				$fecha = date("d-m-Y H:i:s");
				$sql = "INSERT INTO BDES_POS.dbo.DBBonos_H
							(id_empresa, id_beneficiario, fecha, monto, tipo, usuario,
							movimiento, referencia)
						VALUES
							('$id_emp', '$id_ben', '$fecha', $montob, 0, '$usrnom',
							'Generacion e Impresion del Ticket', '$referencia')";

				$sql = odbc_exec( $connec, $sql );
				if(!$sql) {
					throw new \Exception("Error inserting generated record bonus");
				} else {
					$sql = "SELECT id
							FROM BDES_POS.dbo.DBBonos_H
							WHERE referencia = '$referencia'";

					$sql = odbc_exec( $connec, $sql );
					$row = odbc_fetch_array($sql);
					$codigosegu = $row['id'];

					// Se crea la instancia de conexion de la impresora
					$connector = new WindowsPrintConnector($nprinter);
					$printer = new Printer($connector);

					// Alinear al centro lo que que se imprima de aqui en adelante
					$printer->setJustification(Printer::JUSTIFY_CENTER);

					// Ahora vamos a imprimir un encabezado
					$logo = EscposImage::load("../dist/img/logobonos.png", false);
					$printer->bitImage($logo);

					$printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_DOUBLE_WIDTH);
					$logo = EscposImage::load($code_imp, true);
					$printer->bitImage($logo);
					$printer->selectPrintMode();
					$printer->text('ID CLIENTE'."\n");

					// Alinear al izquierda lo que que se imprima de aqui en adelante
					$printer->setJustification(Printer::JUSTIFY_LEFT);
					$printer->text(str_repeat("═", 48)."\n");
					$printer->text("FECHA: ".$fecha.'    '.$codigosegu."\n");
					$printer->text(str_repeat("═", 48) . "\n");
					$printer->text("ID EMPRESA: ");
					$printer->selectPrintMode(Printer::MODE_EMPHASIZED);
					$printer->text($id_emp."\n");
					$printer->text($nom_emp);
					$printer->selectPrintMode();
					$printer->text("ID BENEFICIARIO: ");
					$printer->selectPrintMode(Printer::MODE_EMPHASIZED);
					$printer->text($id_ben."\n");
					$printer->text($nom_ben);
					$printer->text("MONTO: COP$ ");
					$printer->setTextSize(2, 1);
					$printer->text(number_format($montob, 2)."\n");
					$printer->setTextSize(1, 1);
					$printer->text(str_repeat("═", 48) . "\n");
					$printer->setJustification(Printer::JUSTIFY_CENTER);

					// Pie de página
					$printer->setJustification(Printer::JUSTIFY_CENTER);
					$printer->text("Muchas gracias por su compra\n");
					$printer->text(str_repeat("═", 48) . "\n\n");
					$printer->text("Firma......:".str_repeat("_", 36)."\n\n");
					$printer->text("Nro. Cédula:".str_repeat("_", 36)."\n\n");

					$printer->selectPrintMode(Printer::MODE_DOUBLE_HEIGHT | Printer::MODE_DOUBLE_WIDTH);
					$printer->setJustification(Printer::JUSTIFY_CENTER);
					$logo = EscposImage::load($code, true);
					$printer->bitImage($logo);
					$printer->selectPrintMode();
					$printer->text("REFRENCIA V1"."\n");

					// Alimentamos el papel 3 veces
					$printer->feed(1);

					// Cortamos el papel
					$printer->cut();

					// Para imprimir realmente, cerrar la instancia de la impresora
					$printer->close();

					unlink($code);
					unlink($code_imp);

					echo 1;
				}
			} catch(Exception $e) {
				echo "Error : " . $e->getMessage() . "<br/>";
			}
		} else {
			echo 0;
		}
	} catch (PDOException $e) {
		echo "Error : " . $e->getMessage() . "<br/>";
		die();
	}
?>
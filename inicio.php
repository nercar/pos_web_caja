<?php
	session_start();
	$params = parse_ini_file('dist/config.ini');
	if ($params === false) {
		$titulo = '';
	}
	$titulo = $params['title'];
	if (!isset($_SESSION['usuario']) || $_SESSION['usuario']==='') {
		header("Location: /");
	} else {
?>
<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<title><?php echo $titulo; ?></title>
		<!-- Tell the browser to be responsive to screen width -->
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<!-- Icon Favicon -->
		<link rel="shortcut icon" href="dist/img/favicon.png">
		
		<!-- Font Awesome -->
		<link rel="stylesheet" href="plugins/fontawesome/css/all.css">
		
		<!-- Theme style -->
		<link rel="stylesheet" href="dist/css/adminlte.css">

		<style>
			.loader {
				background-image:linear-gradient(#06C90F 0%, #D5D800 100%);
				width:50px;
				height:50px;
				border-radius: 50%;
				margin: 0px;
				padding: 0px;
				-webkit-animation: spin 1s linear infinite;
				animation: spin 1s linear infinite;
				opacity: 1;
				filter:alpha(opacity=100);
			}

			/* Safari */
			@-webkit-keyframes spin {
				0% { -webkit-transform: rotate(0deg); }
				100% { -webkit-transform: rotate(360deg); }
			}

			@keyframes spin {
				0% { transform: rotate(0deg); }
				100% { transform: rotate(360deg); }
			}

			.bgtransparent{
				position:fixed;
				left:0;
				top:0;
				background-color:#000;
				opacity:0.6;
				filter:alpha(opacity=60);
				z-index: 8886;
				display: none;
				width: 1px;
				height: 1px;
			}

			.bgmodal{
				position:fixed;
				font-family:arial;
				font-size:1em;
				border:0.05em solid black;
				overflow:auto;
				background-color:#FFFFFF;
			}

			::-webkit-input-placeholder { /* Chrome/Opera/Safari */
				font-style: italic;
				font-size: 80%;
				text-transform: initial;
			}

			::-moz-placeholder { /* Firefox 19+ */
				font-style: italic;
				font-size: 80%;
				text-transform: initial;
			}

			:-ms-input-placeholder { /* IE 10+ */
				font-style: italic;
				font-size: 80%;
				text-transform: initial;
			}
			
			:-moz-placeholder { /* Firefox 18- */
				font-style: italic;
				font-size: 80%;
				text-transform: initial;
			}

			.swal2-container {
				display: -webkit-box;
				display: flex;
				position: fixed;
				z-index: 300000 !important;
			}

			input[type=number]::-webkit-inner-spin-button, 
			input[type=number]::-webkit-outer-spin-button { 
				-webkit-appearance: none; 
				margin: 0; 
			}
			input[type=number] { -moz-appearance:textfield; }
			sub { top: 0px; font-weight: bolder; }
		</style>
	</head>
	<body class="hold-transition" onload="cargarcontenido('imprimir_bono_pos');">
		<input type="hidden" id="uinombre" value="<?php echo $_SESSION['nomusuario'] ?>">
		<input type="hidden" id="uilogin" value="<?php echo $_SESSION['usuario'] ?>">
		<!-- Navbar -->
		<div class="navbar navbar-expand border-bottom navbar-dark bg-dark elevation-2">
			<img src="dist/img/solologo.png" class="m-0 p-0 bg-transparent imgmain" height="45px">
			<span id="titulo" class="align-items-center m-0 p-0 ml-2 h4">Información <?php echo substr($titulo, strpos($titulo, '#')); ?></span>
			<div class="col"></div>
			<div class="user-panel m-0 p-0 d-flex align-items-center justify-content-end">
				<div class="image m-1 p-1">
					<img src="dist/img/favicon.png" class="brand-image" alt="Logo">
				</div>
				<div class="info mt-0 w-100 text-center pt-0">
					<span class="badge badge-success w-100 font-weight-normal">
						<?php echo substr($_SESSION['nomusuario'], 0, 30) ?>
					</span>
					<div class="d-flex pt-2">
						<form action="app/DBProcs.php" method="post" class="w-100 m-0 p-0 text-right">
							<input type="hidden" name="idpara" id="idpara" value="<?php echo $_SESSION['url'] ?>">
							<input type="hidden" name="opcion" id="opcion" value="cerrar_sesion">
							<button id="cerrarSesion" class="btn btn-outline-warning btn-sm p-0 m-0 pl-1 pr-1 w-100">
								Cerrar Sesión
							</button>
						</form>
					</div>
				</div>
			</div>
		</div>
		<div class="content" id="contenido_ppal">
			
		</div>

		<!-- Modal Cargando-->
		<div class="modal" id="loading" tabindex="-1" role="dialog" aria-labelledby="ModalLoading" aria-hidden="true">
			<div class="modal-dialog modal-sm modal-dialog-centered" role="document">
				<div class="modal-content align-items-center align-content-center border-0 elevation-0 bg-transparent">
					<div class="loader"></div>
					<button class="btn btn-sm btn-danger m-3 p-1"
						onclick="if(tomar_datos!=='') { tomar_datos.abort(); cargando('hide'); }">
						Cancelar Consulta
					</button>
				</div>
			</div>
		</div>

		<!-- jQuery -->
		<script src="plugins/jquery/jquery.min.js"></script>
		<!-- jQuery UI 1.12.1 -->
		<script src="plugins/jQueryUI/jquery-ui.min.js"></script>
		<!-- Bootstrap 4 -->
		<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

		<!-- SweetAlert2@9 -->
		<script src="plugins/sweetalert2/sweetalert2.all.min.js"></script>

		<!-- InputMask -->
		<script src="plugins/input-mask/jquery.inputmask.js"></script>
		<script src="plugins/input-mask/jquery.inputmask.date.extensions.js"></script>

		<!-- moment-with-locals.min.js -->
		<script src="dist/js/moment.min.js"></script>
		<!-- AdminLTE App -->
		<script src="dist/js/adminlte.min.js"></script>
		<!-- JS propias app -->
		<script src="app/js/app.js"></script>
		
		<script>
			moment.locale('es')
			moment.updateLocale('es', { week: {	dow: 0 } });
			var tomar_datos = '';

			// Resolve conflict in jQuery UI tooltip with Bootstrap tooltip
			$.widget.bridge('uibutton', $.ui.button)

			$('.modal').modal({backdrop: 'static', keyboard: false, show: false});

			const msg = Swal.mixin({
				customClass: {
					popup         : 'p-2 bg-dark border border-warning',
					title         : 'text-warning bg-transparent pl-3 pr-3',
					closeButton   : 'btn btn-sm btn-danger',
					content       : 'bg-white border border-warning rounded p-1',
					confirmButton : 'btn btn-sm btn-success m-1',
					cancelButton  : 'btn btn-sm btn-danger m-1',
					input         : 'border border-dark text-center',
				},
				onOpen            : function() { $('.swal2-confirm').focus() },
				buttonsStyling    : false,
				cancelButtonText  : 'Cancelar',
				confirmButtonText : 'Aceptar',
				showCancelButton  : false,
				allowOutsideClick : false,
				allowEnterKey     : true,
				allowEscapeKey    : true,
			})
		</script>
	</body>
</html>
<?php } ?>
<!DOCTYPE html>
<html>
<head>
	<title>REGISTRO</title>
	<link rel="stylesheet" type="text/css" href="css/login_registro.css">
</head>
<body>
     <form action="includes/validarR.php" method="post">
     	<h2>REGISTRO</h2>
     	<?php if (isset($_GET['error'])) { ?>
     		<p class="error"><?php echo $_GET['error']; ?></p>
     	<?php } ?>
     	<label for="nombre">Nombre:</label>
     	<input type="text" id="nombre" name="nombre" required>

     	<label for="email">Email:</label>
     	<input type="email" id="email" name="email" required>

     	<label for="password">Contraseña:</label>
     	<input type="password" id="password" name="password" required>

     	<button type="submit">Registrar</button>
     </form>
</body>
</html>
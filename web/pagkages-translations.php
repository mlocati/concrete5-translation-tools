<?php
if(!@ini_get('session.auto_start')) {
	session_start();
}
?><!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Manage concrete5 translatable packages</title>
<meta name="robots" content="noindex">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="css/bootstrap.min.css">
<link rel="stylesheet" href="css/main.css">
</head>
<body>
<body>

<div class="navbar navbar-default navbar-fixed-top">
	<div class="container">
		<div class="navbar-header">
			<button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
				<span class="sr-only">Toggle navigation</span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
				<span class="icon-bar"></span>
			</button>
			<a href="javascript:void(0)" class="navbar-brand">concrete5 translatable packages</a>
		</div>
		<div class="collapse navbar-collapse">
			<ul class="nav navbar-nav loggedin-yes">
				<li><a href="javascript:void(0)" id="packages-reload">Reload all</a></li>
				<li><a href="javascript:void(0)" id="logout">Logout</a></li>
			</ul>
			<div class="navbar-form navbar-right loggedin-yes">
				<span class="label label-success pull-right" id="my-name"></span>
			</div>
		</div>
	</div>
</div>

<div id="working"><div><div><div id="working-text" class="alert alert-info">Loading page components</div></div></div></div>

<div class="container loggedin-no" id="login">
	<form>
		<div class="form-group">
			<label for="login-username">Login</label>
			<input type="text" class="form-control" id="login-username" placeholder="Username" required<?php echo (array_key_exists('c5tt_username', $_COOKIE) && is_string($_COOKIE['c5tt_username'])) ? (' value="' . htmlspecialchars($_COOKIE['c5tt_username']) . '"') : ''; ?>>
		</div>
		<div class="form-group">
			<label for="login-password">Password</label>
			<input type="password" class="form-control" id="login-password" placeholder="Password" required>
		</div>
		<button type="submit" class="btn btn-default">Submit</button>
	</form>
</div>

<div class="container loggedin-yes" id="packages">
	<table id="packages-list" class="table table-striped">
		<thead><tr>
			<th>Package</th>
			<th>In DB?</th>
			<th>In Transifex?</th>
		</tr></thead>
		<tbody></tbody>
		<tfoot><tr>
			<td colspan="3"><button class="btn btn-success pull-right" id="package-create">New package</button>
		</tr></tfoot>
	</table>
</div>

<div class="modal" id="modal-package">
	<form class="form-horizontal">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<button type="button" class="close" data-dismiss="modal">&times;</button>
					<h4 class="modal-title">Package details</h4>
				</div>
				<div class="modal-body">
					<div class="form-group">
						<label for="package-name" class="col-sm-3 control-label">Usage</label>
						<div class="col-sm-4">
							<div class="radio">
								<label>
									<input type="radio" name="package-in-db" id="package-in-db-yes" value="1">
									In DB
								</label>
							</div>
							<div class="radio">
								<label>
									<input type="radio" name="package-in-db" id="package-in-db-yes-disabled" value="-1">
									In DB (but disabled)
								</label>
							</div>
							<div class="radio">
								<label>
									<input type="radio" name="package-in-db" id="package-in-db-no" value="0">
									Not in DB
								</label>
							</div>
						</div>
						<div class="col-sm-4">
							<div class="checkbox">
								<label>
									<input type="checkbox" id="package-in-tx"> In Transifex
								</label>
							</div>
						</div>
					</div>
					<div class="form-group">
						<label for="package-handle" class="col-sm-3 control-label">Handle</label>
						<div class="col-sm-9">
							<input type="text" class="form-control" id="package-handle" placeholder="Handle" required maxlength="100" pattern="^[a-z]([a-z\-_]*[a-z])?$">
						</div>
					</div>
					<div class="form-group">
						<label for="package-name" class="col-sm-3 control-label">Name</label>
						<div class="col-sm-9">
							<input type="text" class="form-control" id="package-name" placeholder="Name" required maxlength="100">
						</div>
					</div>
					<div class="form-group">
						<label for="package-name" class="col-sm-3 control-label">Source code</label>
						<div class="col-sm-9">
							<input type="url" class="form-control" id="package-sourceurl" placeholder="URL to source code" maxlength="250">
						</div>
					</div>
					<div class="form-group">
						<label for="package-handle" class="col-sm-3 control-label">Initial .pot file</label>
						<div class="col-sm-9">
							<input type="file" class="form-control" id="package-potfile" placeholder=".pot file">
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
					<button type="submit" class="btn btn-primary" id="package-save">Save</button>
				</div>
			</div>
		</div>
	</form>
</div>

<script>
window.onerror = function(message, file, line) {
	var s;
	if(message && message.length) {
		s = message;
	}
	else {
		s = 'Uncaught error';
	}
	if(file && file.length) {
		s += '\n\nFile: ' + file.replace(/\?v=\d+$/, '');
		if(line) {
			s += ' @ ' + line;
		}
	}
	alert(s);
};
</script>
<script src="js/jquery.min.js"></script>
<script src="js/bootstrap.min.js"></script>
<script src="js/locale.js"></script>
<script>
var C5TT = {
	me: <?php echo array_key_exists('user', $_SESSION) ? json_encode($_SESSION['user']) : 'null'; ?>
};
</script>
<script src="js/main.js"></script>

</body>
</html>
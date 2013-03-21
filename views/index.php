<?php
/**
*	Main site template.
*	Examples for $this->data[] values (view specific data):
*		- Title
*		- Styles
*		- Scripts
*		- Content
*/
?>
<!doctype html>
<html>
<head>
	<title><?php echo $data->title; ?></title>
	<link rel="stylesheet" type="text/css" href="assets/bootstrap/css/bootstrap.min.css"/>
	<link rel="stylesheet" type="text/css" href="assets/style.css"/>
</head>

<body>
	<div class="container">
		<div class="hero-unit">
			<h1><?php echo $data->title; ?></h1>
		</div>

		<article>
			<?php echo $data->content; ?>
		</article>
	</div>

	<script type="text/javascript" src="assets/jquery.min.js"></script>
	<script type="text/javascript" src="assets/script.js"></script>
</body>
</html>
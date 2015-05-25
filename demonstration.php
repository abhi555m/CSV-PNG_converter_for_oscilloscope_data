 <!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en-US">
<head>
	<title>Demonstration</title> 
</head>
<body>
<h1>Demonstration page</h1>
<?php
	include(realpath($_SERVER["DOCUMENT_ROOT"])."/source/csvtoimagestuff.php");
	csvfileupload();
?>
</body>
</html>
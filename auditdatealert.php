<html>
<head>
	<title>
		<?php
		include("title.php");
		?>
	</title>
<?php
include("links.php");
?>

</head>
<body>
	<table border="0" width="100%" height="100%">
		<tr><td colspan="3" valign="top" style="background-color:#444">
			<h2 style="padding:10px;background-color:#444;color:orange;margin-bottom:-20px;">AUDIT DATE ALERT!</h2>
		</td></tr>
	<tr>	
	<td width="25%" valign="top" style="background-color:white">

	</td>
	<td width="50%" valign="top" style="background-color:white">
		<?php
		//echo $_SESSION["Message"];
		?>
		<br/>
		<form id="formID" method="post" action="index.php">
		<div style="margin:20px">
		<div class="form-entry" id="modal-form" align="center">
			<p style="color:red">AUDIT DATE IS CHANGED. PLEASE CONTACT THE ADMINISTRATOR</p>
			<button style="background-color:red;color:white;padding:10px;border:none;cursor:pointer"><i class="fa fa-power-off"></i> OK</button>
		</div>
	</div>
	</form>
	</td>
	<td width="25%" style="color:darkblue;background-color:#fff;border-left:1px solid white;padding:10px;" valign="top">
		
	</td>

		</tr>

	</table>

</body>
</html>
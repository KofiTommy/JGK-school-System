<div class="form-entry">
<?php
session_start();
	include("dbstring.php");
	@$_Student =trim($_GET['search-item']);

	$sql="SELECT * FROM tblsystemuser itm WHERE (itm.firstname LIKE '%$_Student%' AND itm.systemtype='Student') OR (itm.surname LIKE '%$_Student%' AND itm.systemtype='Student') OR (itm.othernames LIKE '%$_Student%' AND itm.systemtype='Student') OR  (itm.userid LIKE '%$_Student%' AND itm.systemtype='Student')  ORDER BY itm.firstname ASC";
	$result =mysqli_query($con,$sql);

	echo "<table border='1'>";
  echo "<caption>Students Found</caption>";
    echo "<thead>";
    echo "<th colspan='1'>Action</th><th>Index Number</th><th>Full Name</th><th>Home Address</th><th>Next of Kin</th><th>Contact</th>";
    echo "</thead>";
    echo "<tbody>";
    while($row=mysqli_fetch_array($result,MYSQLI_ASSOC)){
      echo "<tr>";
      echo "<td align='center'>";
      echo "<a  href='payments.php?userid=$row[userid]' title='Add ".$row['firstname']."'><i class='fa fa-plus' style='color:royalblue'></i> </a>";          
      echo "</td>";


      echo "<td align='center'>";
      echo $row['userid'];
      echo "</td>";

      echo "<td>";
      echo $row['firstname']." ".$row['othernames']." ".$row['surname'];
      echo "</td>";

            echo "<td>";
      echo $row['homeaddress'];
      echo "</td>";

      echo "<td>";
      echo $row['nextofkin_fullname'];
      echo "</td>";

      echo "<td align='center'>";
      echo $row['nextofkin_contact'];
      echo "</td>";


      
      echo "</tr>";
     }
    echo "</tbody>";
    echo "</table>";
	mysqli_close($con);
	?>
</div><br/>
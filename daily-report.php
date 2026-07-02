<?php
session_start();
$_SESSION['Message']="";
?>
<?php
@$Overall_total = 0;

$tomorrow = mktime(0,0,0,date("m"),date("d"),date("Y"));
$today = date("Y-m-d",$tomorrow);

  @$from_date = $today;
  @$to_date = $today;
  
  @$_PaymentDate="";
  @$_GrandTotal=0;

  @$date1=date_create($from_date);
  @$date2=date_create($to_date);

  @$totaldays = date_diff($date1,$date2);
  @$totaldays->format("%a");
  @$kw=$totaldays->days;

if(isset($_POST["button_print"]))
{
      include("dbstring.php");
      include("config.php");
      include("company.php");
                
$i=0;      
require('fpdf181/fpdf.php');
//ob_start();

$pdf = new FPDF();
$pdf->AddPage();

$width_cell=array(10,80,50,40,20,20,20);
$pdf->SetFont('Arial','B',10);
//Background color of header//
//Heading of the pdf
// Logo
$k=8;
$pdf->Image("logo/".$_Logo,$width_cell[0]+$width_cell[2]+$width_cell[3],3,22);

//$pdf->Image('images/logo.jpeg',$width_cell[0]+$width_cell[2]+$width_cell[3],3,22);
$pdf->Ln(15);

$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,$_CompanyName,0,0,'C',true);
$pdf->Ln($k);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'Address: '.$_Address,0,0,'C',true);
$pdf->Ln($k);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'Location:'.$_Location,0,0,'C',true);
$pdf->Ln($k);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'Tel:'.$_Telephone1.' '.$_Telephone2,0,0,'C',true);
$pdf->Ln($k);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'DAILY REPORT ON :'.$from_date,0,0,'C',true);
$pdf->Ln($k);


$pdf->SetFillColor(193,229,252);


//$pdf->SetMargin(100);
$pdf->SetFont('Arial','',7);
//Header starts //
$pdf->Cell($width_cell[0],10,'*',1,0,'C',true);

//First header column //
//$pdf->Cell($width_cell[1],10,'STUDENT',1,0,'C',true);

//Second header column //
$pdf->Cell($width_cell[1],10,'RECEIPT #',1,0,'C',true);
//Third header column //
$pdf->Cell($width_cell[2],10,'ITEM',1,0,'C',true);
//Fourth header column
$pdf->Cell($width_cell[3],10,'TOTAL',1,0,'C',true);
//Fifth header column
//$pdf->Cell($width_cell[5],10,'Change Amount',1,0,'C',true);
//Sixth header column
//$pdf->Cell($width_cell[6],10,'Balance',1,0,'C',true);

///header ends///
$pdf->SetFont('Arial','',8);
//Background color of header //
$pdf->SetFillColor(235,236,236);
//to give alternate background fill color to rows//
$fill =false;
$pdf->Ln(10);
@$serial=0;
                 

/*        while($i<=$kw)
        {
          $ydate= DateTime::createFromFormat("Y-m-d",$from_date);
          $mdate= DateTime::createFromFormat("Y-m-d",$from_date);
          $ddate= DateTime::createFromFormat("Y-m-d",$from_date);

          $_SourceDate =mktime(0,0,0,$mdate->format("m"),$ddate->format("d")+$i,$ydate->format("Y"));
          $_PaymentDate= date("Y-m-d", $_SourceDate);
*/
  //$_SQLD=mysqli_query($con,"SELECT * FROM tblsystemuser WHERE systemtype='Student'");
  $_SQLD=mysqli_query($con,"SELECT * FROM tblsystemuser su INNER JOIN tblpayment pmt ON su.userid=pmt.userid 
      WHERE date_format(pmt.datetimepayment,'%Y-%m-%d')=date_format(NOW(),'%Y-%m-%d') 
      AND su.systemtype='Student' GROUP BY pmt.userid ORDER BY pmt.userid");
    
  while($rowd=mysqli_fetch_array($_SQLD,MYSQLI_ASSOC))
  {  
          $pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3],10, $rowd['firstname']." ".$rowd['othernames']." ".$rowd['surname']."(". $rowd['userid'].")",1,0,'L',$fill);
           $pdf->Ln(10);                

          $sql1 = "SELECT * FROM tbltransaction tsc INNER JOIN tblsystemuser su ON tsc.userid=su.userid
                 INNER JOIN tblpayment pm ON pm.transactionid=tsc.transactionid
                 INNER JOIN tblbilling bi ON bi.billid=pm.billid
                 INNER JOIN tblitemprice ip ON ip.itempriceid=bi.itempriceid
                 INNER JOIN tblitem itm ON itm.itemid=ip.itemid
                 WHERE pm.userid='$rowd[userid]' AND date_format(pm.datetimepayment,'%Y-%m-%d')=date_format(NOW(),'%Y-%m-%d') AND date_format(bi.datetimebilled,'%Y-%m-%d')<=date_format(NOW(),'%Y-%m-%d')";

//                WHERE date_format(tsc.datetimepayment,'%Y-%m-%d')='$_PaymentDate' AND date_format(bi.datetimebilled,'%Y-%m-%d')='$_PaymentDate' ";

                 $result=mysqli_query($con,$sql1);
                 $count = mysqli_num_rows( $result);
                 @$serial =0;
                 @$_Total=0;
                 
                   if($count>0)
                   {
                    //Date Ordered
                    //$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,$_PaymentDate,1,0,'L',true);
                    //$pdf->Ln(10); 
                       while($row=mysqli_fetch_array($result,MYSQLI_ASSOC))
                       {
                        $_Total=$_Total+ $row['payment'];                                                        
                        $pdf->Cell($width_cell[0],10,$serial=$serial+1,1,0,'C',$fill);
                        $pdf->Cell($width_cell[1],10,$row['paymentid'],1,0,'L',$fill);
                        $pdf->Cell($width_cell[2],10,$row['itemname'],1,0,'L',$fill);
                        $pdf->Cell($width_cell[3],10,$row['payment'],1,0,'C',$fill);
     
                        $fill = !$fill;
                        $pdf->Ln(10);                 
                      }  
                      $pdf->SetFillColor(203,225,202);
                       $Overall_total = $Overall_total + $_Total;                     
                     
                     // $pdf->Cell($width_cell[0],10,'',1,0,'C',true);
                      $pdf->Cell($width_cell[0],10,'',1,0,'R',true);
                      $pdf->Cell($width_cell[1],10,'',1,0,'R',true);
                      $pdf->Cell($width_cell[2],10,'Sub Total:',1,0,'R',true);
                      $pdf->Cell($width_cell[3],10,$_Total,1,0,'C',true);
                      
                      $pdf->SetFillColor(255,255,255);
                       $pdf->Ln(10);                    
                    }
                  }
          //  $i++;

        //}

      $pdf->SetFillColor(203,205,232);
      //$pdf->Cell($width_cell[0],10,'',1,0,'C',true);
      $pdf->Cell($width_cell[0],10,'',1,0,'C',true);
      $pdf->Cell($width_cell[1],10,'',1,0,'C',true);
      
      $pdf->Cell($width_cell[2],10,'GRAND TOTAL:',1,0,'R',true);
      $pdf->Cell($width_cell[3],10,$_SESSION['SYMBOL']. $Overall_total,1,0,'C',true);
      
/// end of records ///
$pdf->Output();
mysqli_close($con);
}
?>

<?php
include("dbstring.php");
include("code.php");

@$_ItemPriceId=$code;
@$_Price=$_POST['price'];
@$_Itemid=$_POST['itemid'];
@$_Recordedby=$_SESSION['USERID'];
@$_Class=$_POST['class'];
@$_Term=$_POST['term'];
@$_Batch=$_POST['batch'];

if(isset($_POST['price_item_entry'])){
$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblitemprice(itempriceid,class_entryid,term,batch,itemid,price,datetimeprice,status,recordedby)
VALUES('$_ItemPriceId','$_Class','$_Term','$_Batch','$_Itemid','$_Price',NOW(),'active','$_Recordedby')");
if($_SQL_EXECUTE){
  $_SESSION['Message']="<div style='color:green;text-align:center;background-color:white'>Class Successfully Priced</div>";
  }
  else{
    $_Error=mysqli_error($con);
    $_SESSION['Message']="<div style='color:red'>Class failed to price,$_Error</div>";
  }
}
?>


<html>
<head>
<?php
include("links.php");
?>

</head>
<body>

  <div class="header">
    <!--<img src="images/logo.png" width="100px" height="100px" alt="logo"/>-->
  <?php
  include("menu.php");
  ?>    
  </div>
<br/>
<div class="main-platform" style="">
  <table width="100%">
  <tr>
      <td width="100%" align="center">
  <div class="form-entry">
        <form method="post">
    <button class="button-print" id="button_print" name="button_print"><i class="fa fa-print"></i> Print Report</button>
  </form>
    
  <?php
          @$Overall_total = 0;
          @$_PaymentDate="";

         //Connection to database
         include'dbstring.php';

         //Check connection
         if(mysqli_connect_errno()){
         echo "Failed to connect to MySQL:" .mysqli_connect_error();
         }

           

                      //echo "<div align='center' style='text-transform:uppercase;'><b>All Orders</b></div><br/>";
            echo"<form id='formID'  method='post'  enctype='multipart/form-data'>";             
            
            echo "<table border='1' style='background-color:white'>";
            echo "<caption>";
            echo "Daily Report:".date("d")."-".date("M")."-".date("Y");
            echo "</caption>";
            echo "<thead>";
            echo "<th>*</th><th>RECEIPT#</th><th>ITEM</th><th>PAYMENT</th>";
            echo "</thead>";

    $_SQLD=mysqli_query($con,"SELECT *,pmt.userid FROM tblsystemuser su INNER JOIN tblpayment pmt ON su.userid=pmt.userid 
      WHERE date_format(pmt.datetimepayment,'%Y-%m-%d')=date_format(NOW(),'%Y-%m-%d') 
      AND su.systemtype='Student' GROUP BY pmt.userid ORDER BY pmt.userid");
    while($rowd=mysqli_fetch_array($_SQLD,MYSQLI_ASSOC))
    {        
     echo "<tr>";
     echo "<td colspan='4'>";
     echo $rowd['firstname']." ". $rowd['othernames']." ". $rowd['surname']."(". $rowd['userid'].")";       
     echo "</td>";
     echo "</tr>";
                       

                 $sql1 = "SELECT * FROM tbltransaction tsc INNER JOIN tblsystemuser su ON tsc.userid=su.userid
                 INNER JOIN tblpayment pm ON pm.transactionid=tsc.transactionid
                 INNER JOIN tblbilling bi ON bi.billid=pm.billid
                 INNER JOIN tblitemprice ip ON ip.itempriceid=bi.itempriceid
                 INNER JOIN tblitem itm ON itm.itemid=ip.itemid
                WHERE pm.userid='$rowd[userid]' AND date_format(pm.datetimepayment,'%Y-%m-%d')=date_format(NOW(),'%Y-%m-%d') AND date_format(bi.datetimebilled,'%Y-%m-%d')<=date_format(NOW(),'%Y-%m-%d')";

                 $result=mysqli_query($con,$sql1);
                 $count = mysqli_num_rows( $result);
                 @$serial =0;
                 @$_Total=0;
                 
                if($count>0)
                 {
                        while($row=mysqli_fetch_array($result,MYSQLI_ASSOC))
                       {
                        
                      $_Total=$_Total+ $row['payment'];
                        
                
                        echo "<tr>";
                        echo "<td style='min-width:5%;' align='center'>";
                        echo $serial=$serial+1;
                        echo "</td>";                                               
                      
                        
                        echo "<td align='left'>";
                        echo $row['paymentid'];
                        echo "</td>";                         
                        

                        echo "<td align='left'>";
                        echo $row['itemname'];
                        echo "</td>";                         
                        

                        echo "<td align='center'>";
                        echo $row['payment'];
                        echo "</td>";                         
                        echo "</tr>";                                           
                      }  
                      echo "<tr>";
                      echo "<td colspan='2' style='background-color:#ddd;'>";

                      echo "</td>";
                      echo "<td style='background-color:#ddd;' align='right'>";
                       echo "Sub Total:";
                      echo "</td>";
                      echo "<td style='background-color:#ddd;' align='left'>";
                      echo $_Total;
                      $Overall_total = $Overall_total + $_Total; 
                       
                      echo "</td>";
                      
                      echo "</tr>";                        
                 }
            }
           
      echo "<tr style='background-color:lavender'>";
      echo "<td colspan='2' align='center'><font color='black'> </font></td>";
      echo "<td align='right'><font color='black'> GRAND TOTAL:  </font></td>";
      echo "<td align='left'><font color='black'>$_SESSION[SYMBOL] $Overall_total  </font></td>";
      echo "</tr>";
      echo "</table>";
      echo "</form>";
      mysqli_close($con);
  //  }
//}
?>
</td>
</tr>
</table>
</div>
</div>
</body>
</html>
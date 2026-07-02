<?php
session_start();
$_SESSION['Message']="";
?>
<?php
@$Overall_total = 0;
  @$from_date = date("Y-m-d",strtotime($_POST['fromdate']));
  @$to_date = date("Y-m-d",strtotime($_POST['todate']));
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

$width_cell=array(10,70,50,40,20,20,20);
$pdf->SetFont('Arial','B',10);
//Background color of header//
//Heading of the pdf
// Logo
$k=8;
$pdf->Image("logo/".$_Logo,$width_cell[0]+$width_cell[2]+$width_cell[3],3,22);
$pdf->Ln(17);

$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,$_CompanyName,0,0,'C',true);
$pdf->Ln($k);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'Address: '.$_Address,0,0,'C',true);
$pdf->Ln($k);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'Location:'.$_Location,0,0,'C',true);
$pdf->Ln($k);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'Tel:'.$_Telephone1.' '.$_Telephone2,0,0,'C',true);
$pdf->Ln($k);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'PAYMENT ANALYSIS FROM :'.$from_date. ' TO:'.$to_date,0,0,'C',true);
$pdf->Ln($k);


$pdf->SetFillColor(193,229,252);


//$pdf->SetMargin(100);
$pdf->SetFont('Arial','',7);
//Header starts //
$pdf->Cell($width_cell[0],10,'*',1,0,'C',true);

//First header column //
$pdf->Cell($width_cell[1],10,'STUDENT',1,0,'C',true);

//Second header column //
$pdf->Cell($width_cell[2],10,'RECEIPT #',1,0,'C',true);
//Third header column //
$pdf->Cell($width_cell[3],10,'ITEM',1,0,'C',true);
//Fourth header column
$pdf->Cell($width_cell[4],10,'TOTAL',1,0,'C',true);
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
                 

        while($i<=$kw)
        {
          $ydate= DateTime::createFromFormat("Y-m-d",$from_date);
          $mdate= DateTime::createFromFormat("Y-m-d",$from_date);
          $ddate= DateTime::createFromFormat("Y-m-d",$from_date);

          $_SourceDate =mktime(0,0,0,$mdate->format("m"),$ddate->format("d")+$i,$ydate->format("Y"));
          $_PaymentDate= date("Y-m-d", $_SourceDate);

          $sql1 = "SELECT * FROM tbltransaction tsc INNER JOIN tblsystemuser su ON tsc.userid=su.userid
                 INNER JOIN tblpayment pm ON pm.transactionid=tsc.transactionid
                 INNER JOIN tblbilling bi ON bi.billid=pm.billid
                 INNER JOIN tblitemprice ip ON ip.itempriceid=bi.itempriceid
                 INNER JOIN tblitem itm ON itm.itemid=ip.itemid
                WHERE date_format(pm.datetimepayment,'%Y-%m-%d')='$_PaymentDate' AND date_format(bi.datetimebilled,'%Y-%m-%d')<='$_PaymentDate' ";

                 $result=mysqli_query($con,$sql1);
                 $count = mysqli_num_rows( $result);
                 @$serial =0;
                 @$_Total=0;
                 
                   if($count>0)
                   {
                    //Date Ordered
                    $pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,$_PaymentDate,1,0,'L',true);
                    $pdf->Ln(10); 
                       while($row=mysqli_fetch_array($result,MYSQLI_ASSOC))
                       {
                        $_Total=$_Total+ $row['payment'];                                                        
                        $pdf->Cell($width_cell[0],10,$serial=$serial+1,1,0,'C',$fill);
                        $pdf->Cell($width_cell[1],10, $row['firstname']." ".$row['othernames']." ".$row['surname']."(". $row['userid'].")",1,0,'L',$fill);
                        $pdf->Cell($width_cell[2],10,$row['paymentid'],1,0,'L',$fill);
                        $pdf->Cell($width_cell[3],10,$row['itemname'],1,0,'L',$fill);
                        $pdf->Cell($width_cell[4],10,$row['payment'],1,0,'C',$fill);
     
                        $fill = !$fill;
                        $pdf->Ln(10);                 
                      }  
                      $pdf->SetFillColor(203,225,202);
                       $Overall_total = $Overall_total + $_Total;                     
                     
                      $pdf->Cell($width_cell[0],10,'',1,0,'C',true);
                      $pdf->Cell($width_cell[1],10,'',1,0,'R',true);
                      $pdf->Cell($width_cell[2],10,'',1,0,'R',true);
                      $pdf->Cell($width_cell[3],10,'Sub Total:',1,0,'R',true);
                      $pdf->Cell($width_cell[4],10,$_Total,1,0,'C',true);
                      
                      $pdf->SetFillColor(255,255,255);
                       $pdf->Ln(10);                    
                    }
            $i++;

        }

      $pdf->SetFillColor(203,205,232);
      $pdf->Cell($width_cell[0],10,'',1,0,'C',true);
      $pdf->Cell($width_cell[1],10,'',1,0,'C',true);
      $pdf->Cell($width_cell[2],10,'',1,0,'C',true);
      
      $pdf->Cell($width_cell[3],10,'GRAND TOTAL:',1,0,'R',true);
      $pdf->Cell($width_cell[4],10,$_SESSION['SYMBOL']. $Overall_total,1,0,'C',true);
      
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
<div class="form-entry" style="">
  <table width="100%" style='background-color:white'>
  <tr>
      <td width="100%">
        <script type="text/javascript">
            function show_alert()
            {
               alert("Please select Date Time Picker");
            }
            </script>
            <script src="scripts/datetimepicker_css.js"></script>

        <?php
         $tomorrow = mktime(0,0,0,date("m")+1,date("d"),date("Y"));
          $tdate= date("d/m/Y", $tomorrow);
         ?>
      <input type="hidden" name="todate" id="todate" value="<?php echo $tdate; ?>">

      <form id='formID' method='post' enctype='multipart/form-data'>
         <h3 align='center' style='color:royalblue;'> PAYMENT REPORT</h3>
            <table  height=10px;>
              <caption>Choose first date and last date to show report</caption>
              <tr>
                  <td align="right"> 
                       <label>From:</label>
                     </td>
                     <td align="right">
            <input type="text" maxlength="25" size="25" onclick="javascript:NewCssCal ('from-date','ddMMyyyy','','','','','')" id="from-date" name="from-date" value="" readonly   onchange="ValidateFromDate()"/>
     
                   </td>
                  <td> 
                       <label>To:</label>
                     </td>
                     <td>

            <input type="text" maxlength="25" size="25" onclick="javascript:NewCssCal ('to-date','ddMMyyyy','','','','','')" id="to-date" name="to-date" value="" readonly   onchange="ValidateToDate()"/>
                   </td>
                  <td>
                      <button class="button-pay" id="search-record" name="search-record"><i class="fa fa-search"></i> Search</button>
                  </td> 
              </tr>
            </table>           
         </form>


  <?php
          @$Overall_total = 0;
          //@$Overall_amount = 0;
          //@$Overall_changeamount = 0;
          //@$Overall_balance = 0;
          //@$Overall_amountadded = 0;
          @$from_date = date("Y-m-d",strtotime($_POST['from-date']));
          @$to_date = date("Y-m-d",strtotime($_POST['to-date']));
          //@$_TotalBalance =0;
          @$_PaymentDate="";

         //Connection to database
         include'dbstring.php';

         //Check connection
         if(mysqli_connect_errno()){
         echo "Failed to connect to MySQL:" .mysqli_connect_error();
         }

if(isset($_POST['search-record']))
      {   

        if($from_date=='1970-01-01'||$to_date=='1970-01-01')
        {
        echo "<script>alert('Please select dates')</script>";
        }
        else
        {
          //Display item bought for each date from date to date selected
            //$sqlbd="SELECT DateTimePayment FROM tbltransaction WHERE tbltransaction.DateTimePayment>='$from_date' AND tbltransaction.DateTimePayment<='$to_date'";
          //  $resultbd = mysqli_query($con,$sqlbd);
           // $countbd = mysqli_num_rows($resultbd);

          //if($countbd>0){    
            echo "<form method='post' action='payment-analysis.php' >";
            echo "<input type='hidden' id='fromdate' name='fromdate' value='$from_date'/>";
            echo "<input type='hidden' id='todate' name='todate' value='$to_date'/>";
            echo "<button class='button-print' id='button_print' name='button_print'><i class='fa fa-print'></i> Print Report</button> ";
            echo "</form>";
           // }

            //echo "<div align='center' style='text-transform:uppercase;'><b>All Orders</b></div><br/>";
            echo"<form id='formID'  method='post'  enctype='multipart/form-data'>";             
             
        $date1=date_create($from_date);
         $date2=date_create($to_date);

        $totaldays = date_diff($date1,$date2);
        $totaldays->format("%a");
        $k=$totaldays->days;
        $i=0;

            echo "<table border='1'>";
            echo "<caption>";
            echo "From: $from_date  To: $to_date";
            echo "</caption>";
            echo "<thead>";
            echo "<th>*</th><th>STUDENT</th><th>RECEIPT #</th><th>ITEM</th><th>TOTAL</th>";
            echo "</thead>";

       // while($rowbd=mysqli_fetch_array($resultbd,MYSQLI_ASSOC))
           // 

        while($i<=$k)
        {
          $ydate= DateTime::createFromFormat("Y-m-d",$from_date);
          $mdate= DateTime::createFromFormat("Y-m-d",$from_date);
          $ddate= DateTime::createFromFormat("Y-m-d",$from_date);

          $_SourceDate =mktime(0,0,0,$mdate->format("m"),$ddate->format("d")+$i,$ydate->format("Y"));
          $_PaymentDate= date("Y-m-d", $_SourceDate);

                 $sql1 = "SELECT * FROM tbltransaction tsc INNER JOIN tblsystemuser su ON tsc.userid=su.userid
                 INNER JOIN tblpayment pm ON pm.transactionid=tsc.transactionid
                 INNER JOIN tblbilling bi ON bi.billid=pm.billid
                 INNER JOIN tblitemprice ip ON ip.itempriceid=bi.itempriceid
                 INNER JOIN tblitem itm ON itm.itemid=ip.itemid
                 

              
                WHERE date_format(pm.datetimepayment,'%Y-%m-%d')='$_PaymentDate' AND date_format(bi.datetimebilled,'%Y-%m-%d')<='$_PaymentDate'";

                 $result=mysqli_query($con,$sql1);
                 $count = mysqli_num_rows( $result);
                 @$serial =0;
                 @$_Total=0;
                 
                   if($count>0)
                   {
                        echo "<tr>";
                        echo "<td colspan='6'>";
                        echo $_PaymentDate;
                        echo "</td>";
                        echo "</tr>";
                       while($row=mysqli_fetch_array($result,MYSQLI_ASSOC))
                       {
                        
                      $_Total=$_Total+ $row['payment'];
                        
                
                        echo "<tr>";
                        echo "<td style='min-width:5%;' align='center'>";
                        echo $serial=$serial+1;
                        echo "</td>";                                               
                      
                        echo "<td>";
                        echo $row['firstname']." ". $row['othernames']." ". $row['surname']."(". $row['userid'].")";
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
                      echo "<td colspan='3' style='background-color:#ddd;'>";

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
            $i++;

        }
      echo "<tr style='background-color:lavender'>";
      echo "<td colspan='3' align='center'><font color='black'> </font></td>";
      echo "<td align='right'><font color='black'> GRAND TOTAL:  </font></td>";
      echo "<td align='left'><font color='black'>$_SESSION[SYMBOL] $Overall_total  </font></td>";
      echo "</tr>";
      echo "</table>";
      echo "</form>";
      mysqli_close($con);
    }
}
?>
</td>
</tr>
</table>
</div>
</body>
</html>
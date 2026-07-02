<?php 
session_start();
?>

<?php
  @$Overall_amount = 0;
  @$from_date = date("Y-m-d",strtotime($_POST['fromdate']));
  @$to_date = date("Y-m-d",strtotime($_POST['todate']));
  @$_OrderedDate="";

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

$width_cell=array(15,90,30,30,20,13,17,17,17,25);
$pdf->SetFont('Arial','B',10);
//Background color of header//
//Heading of the pdf
// Logo
$k=8;
     // $pdf->Image('images/ike.png',5,3,13);
      $pdf->Ln($k);

$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,$_CompanyName,0,0,'C',true);
$pdf->Ln($k);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'Address: '.$_Address,0,0,'C',true);
$pdf->Ln($k);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'Location: '.$_Location,0,0,'C',true);
$pdf->Ln($k);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'Tel: '.$_Telephone1.'  '.$_Telephone2,0,0,'C',true);
$pdf->Ln($k);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'SALE ANALYSIS FROM :'.$from_date. ' TO:'.$to_date,0,0,'C',true);
$pdf->Ln($k);


$pdf->SetFillColor(193,229,252);


//$pdf->SetMargin(100);
$pdf->SetFont('Arial','',7);
//Header starts //
$pdf->Cell($width_cell[0],10,'*',1,0,'C',true);

//First header column //
$pdf->Cell($width_cell[1],10,'Item',1,0,'C',true);
//Third header column //
$pdf->Cell($width_cell[2],10,'Qty',1,0,'C',true);
//Fourth header column
$pdf->Cell($width_cell[3],10,'Unit Price',1,0,'C',true);
//Fifth header column
$pdf->Cell($width_cell[4],10,'Total',1,0,'C',true);

///header ends///
$pdf->SetFont('Arial','',10);
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
          $_OrderedDate= date("Y-m-d", $_SourceDate);

                 $sql1 = "SELECT tblpos.CustomerID,tblpos.clientid,tblclient.firstname,tblclient.surname,tblclient.othernames, 
                 tblpos.OrderID,tblpos.Status, tblpos.ItemID,SUM(tblpos.Quantity) Quantity,tblpos.UnitPrice,date_format(tblpos.OrderedDateTime,'%Y-%m-%d') AS DateTimeOrd,tblitem.ItemName 
                 FROM tblpos INNER JOIN tblitem ON tblpos.ItemID=tblitem.ItemID INNER JOIN tblclient ON tblpos.clientid=tblclient.clientid
                WHERE date_format(tblpos.OrderedDateTime,'%Y-%m-%d')='$_OrderedDate' AND tblpos.branch='$_SESSION[branch]'  GROUP BY tblpos.ItemID,tblpos.UnitPrice";

                 $result=mysqli_query($con,$sql1);
                 $count = mysqli_num_rows( $result);
                 @$serial =0;
                 @$_Total=0;

                   if($count>0)
                   {
                    //Date Ordered
                    $pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,$_OrderedDate,1,0,'L',true);
                    $pdf->Ln(10); 
                       while($row=mysqli_fetch_array($result,MYSQLI_ASSOC))
                       {
                        $Overall_amount = $Overall_amount + $row['Quantity'] * $row['UnitPrice'];

                        $pdf->Cell($width_cell[0],10,$serial=$serial+1,1,0,'C',$fill);
                        $pdf->Cell($width_cell[1],10,$row['ItemName'],1,0,'C',$fill);
                        $pdf->Cell($width_cell[2],10,$row['Quantity'],1,0,'C',$fill);
                        $pdf->Cell($width_cell[3],10,$row['UnitPrice'],1,0,'C',$fill);
                        $pdf->Cell($width_cell[4],10,$row['Quantity']*$row['UnitPrice'],1,0,'C',$fill);
                        $_Total=$_Total+$row['Quantity'] * $row['UnitPrice'];
                       
                        //$_SubTotal = $_SubTotal + $row['Quantity']*$row['UnitPrice'];
                        
                        $fill = !$fill;
                        $pdf->Ln(10);                 
                      }  
                      $pdf->SetFillColor(203,225,202);
                      $pdf->Cell($width_cell[0],10,'',1,0,'C',true);
                      $pdf->Cell($width_cell[1],10,'',1,0,'C',true);
                     // $pdf->Cell($width_cell[2],10,'',1,0,'C',true);
                      $pdf->Cell($width_cell[2]+$width_cell[3],10,'Total: Ghc',1,0,'C',true);
                      $pdf->Cell($width_cell[4],10,$_Total,1,0,'C',true);  
                       $pdf->SetFillColor(255,255,255);
                      $pdf->Ln(10); 
                    }
            $i++;

        }

      $pdf->SetFillColor(203,205,232);
      $pdf->Cell($width_cell[0],10,'',1,0,'C',true);
      $pdf->Cell($width_cell[1],10,'',1,0,'C',true);
     // $pdf->Cell($width_cell[2],10,'',1,0,'C',true);
      $pdf->Cell($width_cell[2]+$width_cell[3],10,'Grand Total: Ghc',1,0,'C',true);
      $pdf->Cell($width_cell[4],10,$Overall_amount,1,0,'C',true);

$pdf->Ln(10);

//Balance payments
  $i=0;
  while($i<=$kw)
        {
          $ydate= DateTime::createFromFormat("Y-m-d",$from_date);
          $mdate= DateTime::createFromFormat("Y-m-d",$from_date);
          $ddate= DateTime::createFromFormat("Y-m-d",$from_date);

          $_SourceDate =mktime(0,0,0,$mdate->format("m"),$ddate->format("d")+$i,$ydate->format("Y"));
          $_PaymentDate= date("Y-m-d", $_SourceDate);


      @$serial=0;
      @$_Total_Balance_Payment=0;

      $sqlbal="SELECT cl.clientid,cl.firstname,cl.surname,cl.othernames,bl.AmountReceived,bl.datetimepaid
        FROM tblbalance bl INNER JOIN tblclient cl ON bl.clientid=cl.clientid 
       WHERE date_format(bl.datetimepaid,'%Y-%m-%d')='$_PaymentDate' AND bl.branch='$_SESSION[branch]'";
      
      $resultbal=mysqli_query($con,$sqlbal);
      $countbal = mysqli_num_rows($resultbal);

      if($countbal>0)
      {
         $pdf->SetFont('Arial','',12);
      $pdf->SetFillColor(255,255,255);
      $pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[5]+$width_cell[6]+$width_cell[7]+$width_cell[8]+$width_cell[9],10,'BALANCE PAYMENTS',0,0,'C',true);
      $pdf->Ln(10);

      $pdf->SetFont('Arial','',7);
//Header starts //
      $pdf->Cell($width_cell[0],10,'*',1,0,'C',true);
                    //First header column //
        $pdf->Cell($width_cell[1],10,'Client',1,0,'C',true);
        //Second header column //
        $pdf->Cell($width_cell[2],10,'Amount Received',1,0,'C',true);
        //Third header column //
        $pdf->Cell($width_cell[5]+$width_cell[6]+$width_cell[7],10,'Date/Time',1,0,'C',true);
        ///header ends///
        $pdf->SetFont('Arial','',10);
        //Background color of header //
        $pdf->SetFillColor(235,236,236);
        //to give alternate background fill color to rows//
        $fill =false;
        $pdf->Ln(10);

        while($rowbal=mysqli_fetch_array($resultbal,MYSQLI_ASSOC))
        {
          $_Total_Balance_Payment =$_Total_Balance_Payment + $rowbal['AmountReceived'];

          $pdf->Cell($width_cell[0],10,$serial=$serial+1,1,0,'C',$fill);
          $pdf->Cell($width_cell[1],10, $rowbal['firstname']." ".$rowbal['othernames']." ".$rowbal['surname']."(...".substr($rowbal['clientid'],10) .")",1,0,'C',$fill);
          $pdf->Cell($width_cell[2],10,$rowbal['AmountReceived'],1,0,'C',$fill);
          $pdf->Cell($width_cell[5]+$width_cell[6]+$width_cell[7],10,$rowbal['datetimepaid'],1,0,'C',$fill);
           $pdf->Ln(10);
        }     
      }
      $i++;
    }
$pdf->Ln(10);  
         //Client payments

  $i=0;
  while($i<=$kw)
        {
          $ydate= DateTime::createFromFormat("Y-m-d",$from_date);
          $mdate= DateTime::createFromFormat("Y-m-d",$from_date);
          $ddate= DateTime::createFromFormat("Y-m-d",$from_date);

          $_SourceDate =mktime(0,0,0,$mdate->format("m"),$ddate->format("d")+$i,$ydate->format("Y"));
          $_PaymentDate= date("Y-m-d", $_SourceDate);

          @$serial=0;
          @$_Total_Client_Payment=0;

          $sqlclient="SELECT cl.clientid,cl.firstname,cl.surname,cl.othernames,bl.AmountReceived,bl.datetimepaid
          FROM tblpayclient bl INNER JOIN tblclient cl ON bl.clientid=cl.clientid 
           WHERE date_format(bl.datetimepaid,'%Y-%m-%d')='$_PaymentDate' AND bl.branch='$_SESSION[branch]'";
      
          $resultclient=mysqli_query($con,$sqlclient);
          $countclient = mysqli_num_rows($resultclient);

          if($countclient>0)
          {
             $pdf->SetFont('Arial','',12);
        $pdf->SetFillColor(255,255,255);
        $pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[5]+$width_cell[6]+$width_cell[7]+$width_cell[8]+$width_cell[9],10,'CLIENT PAYMENTS',0,0,'C',true);
         $pdf->Ln(10);  

         $pdf->SetFont('Arial','',7);
//Header starts //
        $pdf->Cell($width_cell[0],10,'*',1,0,'C',true);   
                        //First header column //
            $pdf->Cell($width_cell[1],10,'Client',1,0,'C',true);

            //Second header column //
            $pdf->Cell($width_cell[2],10,'Amount Paid',1,0,'C',true);
            //Third header column //
            $pdf->Cell($width_cell[5]+$width_cell[6]+$width_cell[7],10,'Date/Time',1,0,'C',true);
            ///header ends///
            $pdf->SetFont('Arial','',10);
            //Background color of header //
            $pdf->SetFillColor(235,236,236);
            //to give alternate background fill color to rows//
            $fill =false;
            $pdf->Ln(10);

            while($rowclient=mysqli_fetch_array($resultclient,MYSQLI_ASSOC))
            {
            $_Total_Client_Payment = $_Total_Client_Payment + $rowclient['AmountReceived'];
            $pdf->Cell($width_cell[0],10,$serial=$serial+1,1,0,'C',$fill);
            $pdf->Cell($width_cell[1],10, $rowclient['firstname']." ".$rowclient['othernames']." ".$rowclient['surname']."(...".substr($rowclient['clientid'],10) .")",1,0,'C',$fill);
            $pdf->Cell($width_cell[2],10,$rowclient['AmountReceived'],1,0,'C',$fill);
            $pdf->Cell($width_cell[5]+$width_cell[6]+$width_cell[7],10,$rowclient['datetimepaid'],1,0,'C',$fill);
             $pdf->Ln(10);
            }
          }
          $i++;
        }
             $pdf->Ln(10);
          if($countbal>0 or $countclient>0){
            $_Overall_Sale = $Overall_amount + $_Total_Balance_Payment - $_Total_Client_Payment;
            //$pdf->Cell($width_cell[0],10,'',1,0,'C',true);
            $pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[4],10,'Overall Amount after balance and paying clients:',1,0,'C',true);
            //$pdf->Cell($width_cell[3]+$width_cell[4],10,'',1,0,'C',true);
            $pdf->SetFillColor(200,236,236);
            $pdf->Cell($width_cell[5]+$width_cell[6]+$width_cell[7],10,$_Overall_Sale,1,0,'C',true);
          }


/// end of records ///
$pdf->Output();
mysqli_close($con);
}

?>

<?php
include("validation/header.php"); 
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
  <?php
  include("side-menu.php");

  ?>
  </div>


<br/>
<br/>

<br/>
<br/>
<br/>
<br/>
<div class="main" style="">
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
         <h3 align='center' style='margin-top:-7px;font-family:Trebuchet MS;color:gray;'> Bills Report </h3>
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
                      <button class="button-pay" id="search-record" name="search-record"><i class="fa fa-search" style="color:white"></i> Search</button>
                  </td> 
              </tr>
            </table>           
         </form>


<div id="stock-item-style21">
<?php
  @$Overall_amount = 0;
  @$from_date = date("Y-m-d",strtotime($_POST['from-date']));
  @$to_date = date("Y-m-d",strtotime($_POST['to-date']));
  @$_OrderedDate="";

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
            echo "<form method='post' action='sale-analysis.php'>";
            echo "<input type='hidden' id='fromdate' name='fromdate' value='$from_date'/>";
            echo "<input type='hidden' id='todate' name='todate' value='$to_date'/>";
            echo "<button class='print-button' id='button_print' name='button_print'><i class='fa fa-print' style='color:white;''></i> Print Report</button> ";
            echo "</form>";
            
            echo "<div align='center' style='text-transform:uppercase;'><b>All Bills</b></div><br/>";
            echo"<form id='formID'  method='post'  enctype='multipart/form-data'>";             
             
        $date1=date_create($from_date);
        $date2=date_create($to_date);

        $totaldays = date_diff($date1,$date2);
        $totaldays->format("%a");
        $k=$totaldays->days;
        $i=0;

            echo "<table border='1'>";
            echo "<caption style='color:blue;font-family:Trebuchet MS;font-size:18;'>";
            echo "From: $from_date  To: $to_date";
            echo "</caption>";
            echo "<thead>";
            echo "<th>*</th><th>ITEM</th><th>COST </th>";
            echo "</thead>";

        while($i<=$k)
        {
          $ydate= DateTime::createFromFormat("Y-m-d",$from_date);
          $mdate= DateTime::createFromFormat("Y-m-d",$from_date);
          $ddate= DateTime::createFromFormat("Y-m-d",$from_date);

          $_SourceDate =mktime(0,0,0,$mdate->format("m"),$ddate->format("d")+$i,$ydate->format("Y"));
          $_OrderedDate= date("Y-m-d", $_SourceDate);

                 $sql1 = "SELECT * FROM tblbilling bi INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
                 INNER JOIN tblitem itm ON ip.itemid=itm.itemid INNER JOIN tblsystemuser su ON bi.userid=su.userid
                WHERE date_format(bi.datetimebilled,'%Y-%m-%d')='$_OrderedDate'  GROUP BY ip.itemid,bi.cost";

                 $result=mysqli_query($con,$sql1);
                 $count = mysqli_num_rows( $result);
                 @$serial =0;
                 @$_Total=0;

                   if($count>0)
                   {
                        echo "<tr>";
                        echo "<td colspan='3'>";
                        echo $_OrderedDate;
                        echo "</td>";
                        echo "</tr>";
                       while($row=mysqli_fetch_array($result,MYSQLI_ASSOC))
                       {
                        $Overall_amount = $Overall_amount + $row['cost'] ;
                
                        echo "<tr>";
                        echo "<td style='min-width:5%;'>";
                        echo $serial=$serial+1;
                        echo "</td>";

                        echo "<td style='min-width:30%;'>";
                        echo $row['itemname'];
                        echo "</td>";


                        echo "<td style='min-width:10%;'>";
                        echo $row['cost'];
                        $_Total=$_Total+$row['cost'];
                        echo "</td>";                        
                        echo "</tr>";                      
                      }   

                       echo "<tr>";
                      
                      echo "<td  style='background-color:lightblue;'>";
                     
                      echo "</td>";
                      echo "<td  style='background-color:lightblue;'>";
                      
                      echo "</td>";
                      echo "<td  style='background-color:lightblue;'>";
                      echo $_Total;
                      echo "</td>";
                      echo "</tr>";                        
                    }
            $i++;

        }
         echo "<tr>";
              echo "<td colspan='2' align='right'><font color='black'> GRAND TOTAL:</font></td>";
              echo "<td><font color='blue'> $Overall_amount  </font></td>";
              echo "</tr>";
              echo "</table>";        

              echo "</form>";

              mysqli_close($con);
    }
}
?>
      </div>   
      <div id="stock-item-style21">

          <?php
          @$Overall_summary_amount = 0;

          @$from_date2 = date("Y-m-d",strtotime($_POST['from-date']));
          @$to_date2 = date("Y-m-d",strtotime($_POST['to-date']));

         //Connection to database
         include'dbstring.php';

         //Check connection
         if(mysqli_connect_errno()){
         echo "Failed to connect to MySQL:" .mysqli_connect_error();
         }

        if(isset($_POST['search-record']))
        {   
         //$sql1="SELECT tblpos.OrderID, SUM(tblpos.Quantity) AS Quantity, tblpos.OrderedDateTime,tblitem.ItemID,tblitem.ItemName,tblitemPricing.UnitPrice,tblitemPricing.PriceType FROM tblpos INNER JOIN tblitem ON tblpos.ItemID = tblitem.ItemID INNER JOIN tblitemPricing ON tblpos.ItemID = tblitemPricing.ItemID AND tblpos.SaleType=tblitemPricing.PriceType WHERE tblpos.OrderedDateTime >='$from_date' AND tblpos.OrderedDateTime <='$to_date' GROUP BY tblitem.ItemName DESC";
        
$sql1 = "SELECT ip.itemid,itm.itemname,SUM(bi.cost) AS cost FROM tblbilling bi INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
                 INNER JOIN tblitem itm ON ip.itemid=itm.itemid INNER JOIN tblsystemuser su ON bi.userid=su.userid
                WHERE date_format(bi.datetimebilled,'%Y-%m-%d') >='$from_date2' AND date_format(bi.datetimebilled,'%Y-%m-%d') <='$to_date2'
              GROUP BY itm.itemname,bi.cost DESC";  
       

        $result=mysqli_query($con,$sql1);
       @$serial=0;

         $count = mysqli_num_rows( $result);

         if($count>0)
         {
          echo "<div align='center' style='text-transform:uppercase;'><b>Summary Of Bills</b></div><br/>";
          
          echo "<table border='1'>";
           echo "<caption>";
                    
          echo "From: $from_date  To: $to_date";
          echo " </caption>";
          echo "<thead>";
          echo "<th>*</th><th>ITEM </th><th>TOTAL</th>";
          echo "</thead>";
         while($row=mysqli_fetch_array($result,MYSQLI_ASSOC))
         {
           $Overall_summary_amount = $Overall_summary_amount + $row['cost'];

          echo"<form id='formID'  method='post'  enctype='multipart/form-data'>";
          echo "<tr>";

          echo "<td style='min-width:5%;'>";
          echo $serial=$serial+1;
          echo "</td>";


          echo "<td style='min-width:40%;'>";
          echo $row['itemname'];
          echo "<input type='hidden' id='item-id' name='item-id' value='".$row['itemid']."' />";
          echo "</td>";

          
          echo "<td style='min-width:10%;' align='center'>";
          echo $row['cost'];
          echo "</td>";

          
          echo "</tr>";
          echo "</form>";
          }
          echo "<tr>";
          echo "<td colspan='2' align='right'><font color='black'> GRAND TOTAL:</font></td>";
          echo "<td><font color='blue'> $Overall_summary_amount  </font></td>";
          echo "</tr>";
          echo "</table>";


        }
         mysqli_close($con);
       }
?>

      </div>         
    </td>
            </tr>
         </tbody>
      </table>

   </div> <!--End of pos-inner-board-->
</div> <!--End of pos-main-board -->
 
</body>
</html>
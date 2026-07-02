<?php
session_start();
$_SESSION['Message']="";

if(!function_exists('account_statement_resolve_school_logo_path')){
    function account_statement_resolve_school_logo_path($logoValue){
        $logoValue = trim((string)$logoValue);
        $candidates = array();

        if($logoValue !== ''){
            $candidates[] = "images/logo/".$logoValue;
            $candidates[] = "images/".$logoValue;
            $candidates[] = "logo/".$logoValue;
            $candidates[] = $logoValue;
        }

        $candidates[] = "images/logo.png";
        $candidates[] = "images/logo.jpeg";
        $candidates[] = "logo/logo.png";
        $candidates[] = "logo/logo.jpeg";

        foreach($candidates as $candidate){
            $candidate = str_replace("\\", "/", trim((string)$candidate));
            if($candidate === ''){
                continue;
            }
            $fullPath = __DIR__.DIRECTORY_SEPARATOR.str_replace("/", DIRECTORY_SEPARATOR, $candidate);
            if(file_exists($fullPath)){
                return $fullPath;
            }
        }

        return '';
    }
}
?>

<?php
//Declare the variables
@$_TransactionId_Bi="";
//@$_Receivedby=$_SESSION['USERID'];
//@$_PaymentDate=$_POST['batch_month']." ".$_POST['batch_year'];
if(isset($_POST["print_all_bill"]))
{
      include("dbstring.php");
      include("company.php");
      //Get all the ordered items



require('fpdf181/fpdf.php');
//ob_start();

$pdf = new FPDF();
$pdf->AddPage();

$_SQL_SU="SELECT * FROM tblsystemuser WHERE userid='$_SESSION[USERID]'";

$width_cell=array(10,80,40,50,30);
$pdf->SetFont('Arial','B',18);
//if(mysqli_num_rows(mysqli_query($con,$_SQL_EXECUTE_SP))>0)
//{	

//Background color of header//
//Heading of the pdf
// Logo
$statementLogoPath = account_statement_resolve_school_logo_path(isset($_Logo) ? $_Logo : '');
if($statementLogoPath !== ''){
     $pdf->Image($statementLogoPath,$width_cell[0]+$width_cell[1],3,22);
}
     $pdf->Ln(15);

$p=10;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3],10,$_CompanyName,0,0,'C',true);
$pdf->Ln($p);
//$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,"C.D.C",0,0,'C',true);
//$pdf->Ln($p);

$pdf->SetFont('Arial','B',12);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3],10,$_Address." ".$_Location,0,0,'C',true);
$pdf->Ln($p);

//$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'LOCATION: OYOKO ROUNABOUT, KOFORIDUA',0,0,'C',true);
//$pdf->Ln($p);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3],10,'Tel:'. $_Telephone1. " ". $_Telephone2,0,0,'C',true);
$pdf->Ln($p);
$pdf->SetFont('Arial','B',14);

  $text_height = 5;
  $text_length = 70;
  $n=9;

      $pdf->Cell($text_length,$text_height,'PAYMENT DETAILS',0,0,'L',true);
      //$pdf->SetTextColor(0);
     $pdf->SetFont('Arial','B',12);

      $pdf->Ln($n);
 	$_Result=mysqli_query($con,$_SQL_SU);

      if($row_ps=mysqli_fetch_array($_Result,MYSQLI_ASSOC)){
      	@$_StaffName=$row_ps['firstname']." ".$row_ps['othernames']." ".$row_ps['surname']." (".$row_ps['userid'].")";
      	    $pdf->Cell($text_length,$text_height,'NAME:'.$_StaffName,0,0,'L',true);
      $pdf->Ln(10);
      }
  

 @$_Class_ID=$_POST['class_id'];
@$_Term_ID=$_POST['term_id'];
@$_Batch_ID=$_POST['batch_id'];

$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce WHERE ce.class_entryid='$_Class_ID' ORDER BY ce.class_name ASC");
while($row_class=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC))
{
@$_Total_Amount=0;
@$_Total_Balance=0;
@$_Total_Amount_Paid=0;

$pdf->SetFont('Arial','B',12);
$pdf->Cell($text_length,$text_height,strtoupper($row_class['class_name']),0,0,'L',true);
$pdf->Ln($n);

$_SQL_TERM=mysqli_query($con,"SELECT * FROM tbltermregistry tr WHERE tr.class_entryid='$_Class_ID' AND tr.termname='$_Term_ID' 
	AND tr.userid='$_SESSION[USERID]' AND tr.batchid='$_Batch_ID' ORDER BY tr.termname ASC");


$pdf->SetFillColor(255,255,255);

$pdf->SetFont('Arial','B',12);
//Header starts //
$pdf->Cell($width_cell[0],10,'*',1,0,'C',true);

//First header column //
$pdf->Cell($width_cell[1],10,'ITEM',1,0,'C',true);

//$pdf->Cell($width_cell[2],10,'AMOUNT',1,0,'C',true);
$pdf->Cell($width_cell[2],10,'PAYMENT',1,0,'C',true);

$pdf->Cell($width_cell[3],10,'PAYMENT DATE/TIME',1,0,'C',true);
$pdf->Ln($n);

@$_Total_Amount_Paid=0;
@$_Total_amount_To_Pay=0;

while($row_tr=mysqli_fetch_array($_SQL_TERM,MYSQLI_ASSOC))
{
//$pdf->Cell($text_length,$text_height,"Term: ".$row_tr['termname'],0,0,'L',true);
$pdf->Cell($width_cell[0]+$width_cell[1],10,"Term: ".$row_tr['termname'],1,0,'L',true);
//$pdf->Ln(10);

$_SQL_BIL=mysqli_query($con,"SELECT SUM(ip.price) AS Total_Amount,transactionid FROM tblbilling bi INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid WHERE bi.userid='$_SESSION[USERID]' AND ip.class_entryid='$row_tr[class_entryid]' AND ip.term='$row_tr[termname]' AND ip.batch='$row_tr[batchid]'");
if($row_b=mysqli_fetch_array($_SQL_BIL,MYSQLI_ASSOC)){
$pdf->Cell($width_cell[2]+$width_cell[3],10,"AMOUNT TO PAY: $_SESSION[SYMBOL] ".$row_b['Total_Amount'],1,0,'R',true);
$_Total_amount_To_Pay=$row_b['Total_Amount'];
$_TransactionId_Bi=$row_b['transactionid'];
}

      //$pdf->SetTextColor(0);
$pdf->SetFont('Arial','B',12);
$pdf->Ln(10);

$_SQL_EXECUTE_3="SELECT * FROM tblbilling bi 
INNER JOIN tblsystemuser su ON bi.userid=su.userid 
INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
INNER JOIN tblitem itm ON ip.itemid=itm.itemid
INNER JOIN tblclassentry ce ON ip.class_entryid=ce.class_entryid
INNER JOIN tblbatch b ON ip.batch=b.batchid
INNER JOIN tblpayment pm ON pm.billid=bi.billid
WHERE bi.userid='$_SESSION[USERID]' AND ip.class_entryid='$row_class[class_entryid]' AND ip.term='$row_tr[termname]' AND ip.batch='$row_tr[batchid]' ORDER BY pm.datetimepayment DESC";
//$_SQL_EXECUTE_3_r=$_SQL_EXECUTE_3;


@$serial=0;
@$_Total_Amount_single=0;
@$_Total_Amount_Paid_Single=0;
@$_Total_Balance_Single=0;

//while($row_p=mysqli_fetch_array($_SQL_EXECUTE_3,MYSQLI_ASSOC)){


///header ends///
$pdf->SetFont('Arial','',10);
//Background color of header //
$pdf->SetFillColor(255,255,255);
//to give alternate background fill color to rows//
$fill =false;

@$_AdditionalPrice=0;

@$serial=0;
//each record is one row //
while($row_p=mysqli_fetch_array(mysqli_query($con,$_SQL_EXECUTE_3),MYSQLI_ASSOC))
{

$pdf->Cell($width_cell[0],10,$serial=$serial+1,1,0,'C',$fill);
//$pdf->Ln(10);
  //$pdf->Cell($width_cell[0],10,"Gross Salary",1,0,'C',$fill);
$pdf->Cell($width_cell[1],10,$row_p['itemname'],1,0,'L',$fill);
//$pdf->Ln(10);

//$_Total_Amount=$_Total_Amount+$row_p['price'];
//$_Total_Amount_single=$_Total_Amount_single+$row_p['price'];
//$pdf->Cell($width_cell[2],10,$row_p['price'],1,0,'C',$fill);
//$pdf->Ln(10);
				
$pdf->Cell($width_cell[2],10,$row_p['payment'],1,0,'C',$fill);
$_Total_Amount_Paid_Single=@$_Total_Amount_Paid_Single + $row_p['payment'];
//$pdf->Ln(10);
$pdf->Cell($width_cell[3],10,$row_p['datetimepayment'],1,0,'C',$fill);

/*$_Balance=$row_p['price']-$row_p['payment'];
$_Total_Balance=$_Total_Balance+$_Balance;
$_Total_Balance_Single=$_Total_Balance_Single+$_Balance;
$pdf->Cell($width_cell[4],10,$_Balance,1,0,'C',$fill);
*/
$fill = !$fill;
$pdf->Ln(10);
}

//Footer of the table
 //$pdf->Cell($width_cell[0],10,'',1,0,'C',true);
// $pdf->Cell($width_cell[0],10,'',1,0,'C',true);
$pdf->Cell($width_cell[0]+$width_cell[1],10,'Sub Total:',1,0,'R',true);
//$pdf->Cell($width_cell[2],10,$_Total_Amount_single,1,0,'C',true);
$pdf->Cell($width_cell[2],10,$_Total_Amount_Paid_Single,1,0,'C',true);
$_Total_Amount_Paid=$_Total_Amount_Paid+$_Total_Amount_Paid_Single;

$pdf->Cell($width_cell[3],10,"",1,0,'C',true);
$pdf->Ln(10); 
}
$pdf->SetFont('Arial','B',10);
$pdf->Cell($width_cell[0]+$width_cell[1],10,'GRAND TOTAL:',1,0,'R',true);
//$pdf->Cell($width_cell[2],10,$_SESSION['SYMBOL']." ".$_Total_Amount,1,0,'C',true);
$pdf->Cell($width_cell[2],10,$_SESSION['SYMBOL']." ".$_Total_Amount_Paid,1,0,'C',true);
$pdf->Cell($width_cell[3],10,"BALANCE: ".$_SESSION['SYMBOL']." ".($_Total_amount_To_Pay-$_Total_Amount_Paid),1,0,'C',true);
$pdf->Ln(15); 
}
/*$pdf->Cell($width_cell[2]+$width_cell[3],10,'Grand Total: Ghc',1,0,'C',true);
$pdf->Cell($width_cell[4],10,$_GrandTotal,1,0,'C',true);
*/
$pdf->SetFont('Arial','',10);

$tomorrow = mktime(0,0,0,date("m"),date("d"),date("Y"));
$tdate= date("d/m/Y", $tomorrow);
$pdf->SetFillColor(0,0,0);
//$pdf->PutLink("http://www.braintechconsult.com","BTC");
//$pdf->Ln(10);                    //$pdf->SetStyle('I',true);       
//$pdf->Cell(0,10,'Print Date/Time: '.$tdate .','.$todayTime,0);
$pdf->Cell(0,10,'Print Date/Time: '.$tdate,0);

//$pdf->Ln(10);                    //$pdf->SetStyle('I',true);       
//$pdf->Cell(0,10,'Paid By: '.$_Receivedby,0);
 $pdf->Ln(10); 

 $_SQL_AC=mysqli_query($con,"SELECT * FROM tbltransaction tr 
  INNER JOIN tblsystemuser su ON tr.recordedby=su.userid 
  WHERE tr.transactionid='$_TransactionId_Bi'");
 if($row_bi=mysqli_fetch_array($_SQL_AC,MYSQLI_ASSOC)){
 $pdf->Cell(0,10,'ADMINISTRATOR:'. strtoupper($row_bi['firstname']." ".$row_bi['othernames']." ".$row_bi['surname']),0);
 
 $pdf->Ln(10); 
 $pdf->Cell(0,10,'SIGNATURE:'.strtoupper($row_bi['firstname']." ".$row_bi['othernames']." ".$row_bi['surname']),0);
 //$pdf->Ln(5); 
 
//$pdf->Cell(0,3,$pdf->Image('accountants/'.$row_bi['signature']),0,0,'L',false);
 

}
 
 //$pdf->Ln(8); 

//$pdf->SetFont('Arial','B',8);

 /*$pdf->Ln(14); 
 $pdf->Cell(0,10,'Developed by: Brainstorm Technologies Consult',0);
 $pdf->Ln(8); 
 $pdf->Cell(0,10,'Accra,Takoradi,Koforidua - 0342-292-121',0);
/// end of records ///
 $pdf->Ln(50);
*/
//}
$__pdfName = 'student-record.pdf';
if (ob_get_length()) { ob_end_clean(); }
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$__pdfName.'"');
$pdf->Output('I', $__pdfName);
exit();
 //ob_end_flush(); 
 //}
}
?>


<?php
//Declare the variables
@$_Receivedby=$_SESSION['USERID'];
@$_PaymentDate=$_POST['batch_month']." ".$_POST['batch_year'];
if(isset($_POST["print_pay_slip"]))
{
      include("dbstring.php");
      include("company.php");
      //Get all the ordered items

require('fpdf181/fpdf.php');
//ob_start();

$pdf = new FPDF();
$pdf->AddPage();

$_SQL_EXECUTE_3=mysqli_query($con,"SELECT * FROM tblbilling bi INNER JOIN tblsystemuser su 
ON bi.userid=su.userid INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
INNER JOIN tblitem itm ON ip.itemid=itm.itemid
INNER JOIN tblclassentry ce ON ip.class_entryid=ce.class_entryid
INNER JOIN tblbatch b ON ip.batch=b.batchid
WHERE bi.userid='$_SESSION[USERID]' ORDER BY ip.term ASC");
$_SQL_EXECUTE_3_r=$_SQL_EXECUTE_3;

	if($row_p_r=mysqli_fetch_array($_SQL_EXECUTE_3_r,MYSQLI_ASSOC)){
				echo "<tr>";
				echo "<td colspan='1' style='background-color:#eed;border-bottom:1px solid orange;color:blue;font-weight:bold' align='right'>";				
				echo "Class:";
				echo "</td>";
				echo "<td colspan='1' style='background-color:#eed;border-bottom:1px solid orange;color:blue;font-weight:bold'>";
				//echo $row_p_r['firstname']." ".$row_p_r['othernames']." ".$row_p_r['surname']." (". $row_p_r['userid'].")";
				echo $row_p_r['class_name'];
				echo "</td>";
				echo "<td colspan='1' style='background-color:#eed;border-bottom:1px solid orange;color:blue;font-weight:bold' align='right'>";				
				echo "Term:";
				echo "</td>";
				echo "<td colspan='1' style='background-color:#eed;border-bottom:1px solid orange;color:blue;font-weight:bold'>";
				echo $row_p_r['term'];
				echo "</td>";
				echo "<td colspan='1' style='background-color:#eed;border-bottom:1px solid orange;color:blue;font-weight:bold' align='right'>";				
				echo "Batch:";
				echo "</td>";
				echo "<td colspan='2' style='background-color:#eed;border-bottom:1px solid orange;color:blue;font-weight:bold'>";				
				echo $row_p_r['batch'];
				echo "</td>";	

				echo "</tr>";
}
				

$_SQL_SU=mysqli_query($con,"SELECT * FROM tblsystemuser");
while($row_su=mysqli_fetch_array($_SQL_SU,MYSQLI_ASSOC))
{
$_SQL_EXECUTE_SP ="SELECT * FROM tblsalarypayment sp
INNER JOIN tblsystemuser su ON sp.userid=su.userid
INNER JOIN tblsalarydetails sd ON sd.salarydetailid=sp.salarydetailid
WHERE sp.paymentdate='$_PaymentDate' AND sp.userid='$row_su[userid]'"; 

$width_cell=array(65,55,15,15,20);
$pdf->SetFont('Arial','B',18);
if(mysqli_num_rows(mysqli_query($con,$_SQL_EXECUTE_SP))>0)
{	

//Background color of header//
//Heading of the pdf
// Logo
$statementLogoPath = account_statement_resolve_school_logo_path(isset($_Logo) ? $_Logo : '');
if($statementLogoPath !== ''){
     $pdf->Image($statementLogoPath,10,8,18);
}
      $pdf->Ln(15);

$p=10;
$pdf->SetFillColor(255,255,255);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,$_CompanyName,0,0,'C',true);
$pdf->Ln($p);
//$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,"C.D.C",0,0,'C',true);
//$pdf->Ln($p);

$pdf->SetFont('Arial','B',12);
$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,$_Address." ".$_Location,0,0,'C',true);
$pdf->Ln($p);

//$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'LOCATION: OYOKO ROUNABOUT, KOFORIDUA',0,0,'C',true);
//$pdf->Ln($p);

$pdf->Cell($width_cell[0]+$width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'Tel:'. $_Telephone1. " ". $_Telephone2,0,0,'C',true);
$pdf->Ln($p);
$pdf->SetFont('Arial','B',20);

  $text_height = 5;
  $text_length = 70;
  $n=9;
  $pdf->SetFont('Arial','U',14);

      $pdf->Cell($text_length,$text_height,'SLIP DETAILS',0,0,'L',true);
      //$pdf->SetTextColor(0);
     $pdf->SetFont('Arial','B',12);

      $pdf->Ln($n);
 	$_Result=mysqli_query($con,$_SQL_EXECUTE_SP);

      if($row_ps=mysqli_fetch_array($_Result,MYSQLI_ASSOC)){
      	@$_StaffName=$row_ps['firstname']." ".$row_ps['othernames']." ".$row_ps['surname']." (".$row_ps['userid'].")";
      	    $pdf->Cell($text_length,$text_height,'Staff Name:'.$_StaffName,0,0,'L',true);
      $pdf->Ln($n);
       $pdf->Cell($text_length,$text_height,'Salary Id:'.$row_ps['salaryid'],0,0,'L',true);
      $pdf->Ln($n);

       $pdf->Cell($text_length,$text_height,'Payment Date:'.$row_ps['paymentdate'],0,0,'L',true);
      $pdf->Ln(10);
      }
  

$pdf->SetFillColor(255,255,255);

$pdf->SetFont('Arial','B',12);
//Header starts //
//$pdf->Cell($width_cell[0],10,'*',1,0,'C',true);

//First header column //
$pdf->Cell($width_cell[0],10,'ITEM',1,0,'C',true);

$pdf->Cell($width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'AMOUNT',1,0,'C',true);
///header ends///
$pdf->SetFont('Arial','B',10);
//Background color of header //
$pdf->SetFillColor(255,255,255);
//to give alternate background fill color to rows//
$fill =false;
$pdf->Ln(10);

@$_AdditionalPrice=0;

@$serial=0;
//each record is one row //
while($row=mysqli_fetch_array(mysqli_query($con,$_SQL_EXECUTE_SP),MYSQLI_ASSOC))
{

 // $pdf->Cell($width_cell[0],10,$serial=$serial+1,1,0,'C',$fill);
  $pdf->Cell($width_cell[0],10,"Gross Salary",1,0,'C',$fill);
 $pdf->Cell($width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,$row['grosssalary'],1,0,'C',$fill);
  $pdf->Ln(10);
  $pdf->Cell($width_cell[0],10,"Allowance",1,0,'C',$fill);
  $pdf->Cell($width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,$row['allowance'],1,0,'C',$fill);
   $pdf->Ln(10);
   $pdf->Cell($width_cell[0],10,"Total Deduction",1,0,'C',$fill);
  $pdf->Cell($width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,$row['totaldeduction'],1,0,'C',$fill);
     $pdf->Ln(10);
   $pdf->Cell($width_cell[0],10,"Net Pay",1,0,'C',$fill);
  $pdf->Cell($width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,$row['netpay'],1,0,'C',$fill);

  $fill = !$fill;
  $pdf->Ln(10);
}
//Footer of the table
 //$pdf->Cell($width_cell[0],10,'',1,0,'C',true);
// $pdf->Cell($width_cell[0],10,'',1,0,'C',true);
//$pdf->Cell($width_cell[0],10,'',1,0,'C',true);
//$pdf->Cell($width_cell[1]+$width_cell[2]+$width_cell[3]+$width_cell[4],10,'',1,0,'C',true);
/*$pdf->Cell($width_cell[2]+$width_cell[3],10,'Grand Total: Ghc',1,0,'C',true);
$pdf->Cell($width_cell[4],10,$_GrandTotal,1,0,'C',true);
*/
$tomorrow = mktime(0,0,0,date("m"),date("d"),date("Y"));
$tdate= date("d/m/Y", $tomorrow);
$pdf->SetFillColor(0,0,0);
//$pdf->PutLink("http://www.braintechconsult.com","BTC");
$pdf->Ln(15);                    //$pdf->SetStyle('I',true);       
//$pdf->Cell(0,10,'Print Date/Time: '.$tdate .','.$todayTime,0);
$pdf->Cell(0,10,'Print Date/Time: '.$tdate,0);

//$pdf->Ln(10);                    //$pdf->SetStyle('I',true);       
//$pdf->Cell(0,10,'Paid By: '.$_Receivedby,0);
 $pdf->Ln(14); 
 $pdf->Cell(0,10,'ACCOUNTANT:',0);
 
 $pdf->Ln(14); 
 $pdf->Cell(0,10,'Signature:.........................................................................',0);
 $pdf->Ln(8); 

$pdf->SetFont('Arial','B',8);

 $pdf->Ln(14); 
 $pdf->Cell(0,10,'Developed by: Brainstorm Technologies Consult',0);
 $pdf->Ln(8); 
 $pdf->Cell(0,10,'Accra,Takoradi,Koforidua - 0202311659/0242602522',0);
/// end of records ///
 $pdf->Ln(50);
}
}
$__pdfName = 'student-record.pdf';
if (ob_get_length()) { ob_end_clean(); }
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="'.$__pdfName.'"');
$pdf->Output('I', $__pdfName);
exit();
 //ob_end_flush(); 
 //}
}
?>



<?php
include("dbstring.php");
if(isset($_POST['pay_bill']))
{
	$_Amount_Paid=$_POST['payment'];
	$_User_ID=$_POST['userid'];
	$_ItemPriceId=$_POST['item-price-id'];
	$_Transaction_Id=$_POST['transactioncode'];

//Check if balance is zero
$_SQL_CHECK_BALANCE_1=mysqli_query($con,"SELECT * FROM tblbilling 
WHERE userid='$_User_ID' AND itempriceid='$_ItemPriceId'");	
if($row_1=mysqli_fetch_array($_SQL_CHECK_BALANCE_1,MYSQLI_ASSOC)){
@$_Bill_Payment=$row_1['payment']+$_Amount_Paid;
//echo "Bill P".$_Bill_Payment ."<br/>";
}
$_SQL_CHECK_BALANCE_2=mysqli_query($con,"SELECT * FROM tblitemprice 
WHERE itempriceid='$_ItemPriceId'");	
if($row_2=mysqli_fetch_array($_SQL_CHECK_BALANCE_2,MYSQLI_ASSOC)){
@$_Actual_Payment=$row_2['price'];
@$_ItemId=$row_2['itemid'];
//echo "Actual".$_Actual_Payment;
}
if($_Bill_Payment>$_Actual_Payment){
$_SQL_Item=mysqli_query($con,"SELECT * FROM tblitem WHERE itemid='$_ItemId'");
if($row_item=mysqli_fetch_array($_SQL_Item,MYSQLI_ASSOC)){
@$_ItemName=$row_item['itemname'];
}
$_SESSION['Message']=$_SESSION['Message']."<div style='color:red;background-color:white;padding:8px;' align='left'><i class='fa fa-times' style='color:red'></i> Student has finished paying for the $_ItemName or Amount entered is more than the balance</div>";
}
else
{
$_SQL_Item_2=mysqli_query($con,"SELECT * FROM tblitem WHERE itemid='$_ItemId'");
if($row_item_2=mysqli_fetch_array($_SQL_Item_2,MYSQLI_ASSOC)){
@$_ItemName_2=$row_item_2['itemname'];
}

	$_SQL_Bill_Pay=mysqli_query($con,"UPDATE tblbilling SET payment=payment+'$_Amount_Paid' 
	WHERE userid='$_User_ID' AND itempriceid='$_ItemPriceId' AND transactionid='$_Transaction_Id'");
	if($_SQL_Bill_Pay){
	$_SQL_Payment=mysqli_query($con,"UPDATE tblpayment SET payment=payment+'$_Amount_Paid' 
		WHERE userid='$_User_ID' AND transactionid='$_Transaction_Id'");	
	if($_SQL_Payment){
	$_SESSION['Message']="<div style='color:green;text-align:left;background-color:white;padding:8px;'><i class='fa fa-check' style='color:green'></i> Amount of $_Amount_Paid $_SESSION[CURRENCY] Successfully Paid for $_ItemName_2</div>";
	
	}
	else{
		$_Error_1=mysqli_error($con);
	$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>Failed to update bill record,$_Error_1</div>";
	
	}
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>Failed to pay,$_Error</div>";
	
	}
	}
}
?>
<?php
include("dbstring.php");
//@$_ClassId=$_POST['classid'];
@$_UserId=$_POST['userid'];
@$_Class=$_POST['class'];
@$_Batch=$_POST['batch'];
@$_Term=$_POST['term'];
@$_Recordedby=$_SESSION['USERID'];
//echo $_SESSION['USERID'];

if(isset($_POST['bill_student'])){
//Create payment container
include("code.php");
@$_Payment_Id=$code;
@$_Transaction_Code=$transaction_id;

$_SQL_Payment=mysqli_query($con,"INSERT INTO tblpayment(paymentid,userid,transactionid,payment,datetimepayment,recordedby,status)
	VALUES('$_Payment_Id','$_UserId','$_Transaction_Code',0,NOW(),'$_SESSION[USERID]','active')");
if($_SQL_Payment){
@$_TransId=$_Transaction_Code;
}

$_SQL_EXECUTE_2=mysqli_query($con,"SELECT * FROM tbltermregistry tr 
				INNER JOIN tblitemprice ip ON tr.class_entryid=ip.class_entryid AND tr.termname=ip.term
				INNER JOIN tblitem itm ON ip.itemid=itm.itemid
				INNER JOIN tblclassentry ce ON ce.class_entryid=tr.class_entryid
				WHERE tr.userid='$_UserId' AND ip.class_entryid='$_Class' AND ip.term='$_Term' AND ip.batch='$_Batch'");

while($row_b=mysqli_fetch_array($_SQL_EXECUTE_2,MYSQLI_ASSOC))
{
include("code.php");
@$_BillId=$code;

$_SQL_EXECUTE=mysqli_query($con,"INSERT INTO tblbilling(billid,userid,itempriceid,transactionid,datetimebilled,recordedby,status)
	VALUES('$_BillId','$_UserId','$row_b[itempriceid]','$_TransId',NOW(),'$_Recordedby','active')");
if($_SQL_EXECUTE){
	$_SESSION['Message']=$_SESSION['Message']."<div style='color:green;text-align:center;background-color:white'>$_BillId Successfully Created</div>";
	}
	else{
		$_Error=mysqli_error($con);
		$_SESSION['Message']=$_SESSION['Message']."<div style='color:red'>$_BillId failed to create,$_Error</div>";
	}
}
}
?>

<?php
if(!function_exists('account_stmt_esc')){
    function account_stmt_esc($value){
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if(!function_exists('account_stmt_money')){
    function account_stmt_money($amount){
        $symbol = (isset($_SESSION['SYMBOL']) && trim((string)$_SESSION['SYMBOL']) !== '') ? trim((string)$_SESSION['SYMBOL']) : 'GHC';
        return $symbol.' '.number_format((float)$amount, 2);
    }
}

if(!function_exists('account_stmt_datetime')){
    function account_stmt_datetime($value){
        $timestamp = strtotime((string)$value);
        return $timestamp ? date('d M Y, H:i', $timestamp) : (string)$value;
    }
}

$accountStatementUserId = isset($_SESSION['USERID']) ? trim((string)$_SESSION['USERID']) : '';
$accountStatementUserIdEsc = mysqli_real_escape_string($con, $accountStatementUserId);
$accountStatementFlash = trim((string)$_SESSION['Message']);
$accountStatementProfile = array(
    'name' => $accountStatementUserId,
    'userid' => $accountStatementUserId
);
$accountStatementSummary = array(
    'classes' => 0,
    'periods' => 0,
    'billed' => 0.0,
    'paid' => 0.0,
    'balance' => 0.0,
    'latest_payment' => ''
);
$accountStatementGroups = array();

if($accountStatementUserId !== ''){
    $_SQL_STUDENT_PROFILE = mysqli_query($con, "SELECT firstname, othernames, surname, userid FROM tblsystemuser WHERE userid='$accountStatementUserIdEsc' LIMIT 1");
    if($_SQL_STUDENT_PROFILE && $rowStudentProfile = mysqli_fetch_array($_SQL_STUDENT_PROFILE, MYSQLI_ASSOC)){
        $studentNameParts = array();
        foreach(array('firstname', 'othernames', 'surname') as $nameField){
            $nameValue = isset($rowStudentProfile[$nameField]) ? trim((string)$rowStudentProfile[$nameField]) : '';
            if($nameValue !== ''){
                $studentNameParts[] = $nameValue;
            }
        }
        $accountStatementProfile['name'] = trim(implode(' ', $studentNameParts)) !== '' ? trim(implode(' ', $studentNameParts)) : $accountStatementUserId;
        $accountStatementProfile['userid'] = trim((string)$rowStudentProfile['userid']) !== '' ? trim((string)$rowStudentProfile['userid']) : $accountStatementUserId;
    }

    $_SQL_CLASSES = mysqli_query($con, "SELECT c.class_entryid, c.batchid, c.datetimeentry, ce.class_name, bt.batch
        FROM tblclass c
        LEFT JOIN tblclassentry ce ON ce.class_entryid=c.class_entryid
        LEFT JOIN tblbatch bt ON bt.batchid=c.batchid
        WHERE c.userid='$accountStatementUserIdEsc'
        ORDER BY c.datetimeentry DESC");

    if($_SQL_CLASSES){
        while($rowClassReg = mysqli_fetch_array($_SQL_CLASSES, MYSQLI_ASSOC)){
            $classEntryId = isset($rowClassReg['class_entryid']) ? trim((string)$rowClassReg['class_entryid']) : '';
            $batchId = isset($rowClassReg['batchid']) ? trim((string)$rowClassReg['batchid']) : '';
            if($classEntryId === '' || $batchId === ''){
                continue;
            }

            $classKey = $classEntryId.'|'.$batchId;
            if(!isset($accountStatementGroups[$classKey])){
                $accountStatementGroups[$classKey] = array(
                    'class_name' => trim((string)$rowClassReg['class_name']) !== '' ? trim((string)$rowClassReg['class_name']) : 'Class Record',
                    'batch_label' => trim((string)$rowClassReg['batch']),
                    'class_entryid' => $classEntryId,
                    'batchid' => $batchId,
                    'statements' => array()
                );
            }

            $classEntryIdEsc = mysqli_real_escape_string($con, $classEntryId);
            $batchIdEsc = mysqli_real_escape_string($con, $batchId);
            $_SQL_TERMS = mysqli_query($con, "SELECT DISTINCT tr.termname, tr.class_entryid, tr.batchid, bt.batch
                FROM tbltermregistry tr
                LEFT JOIN tblbatch bt ON bt.batchid=tr.batchid
                WHERE tr.class_entryid='$classEntryIdEsc'
                  AND tr.userid='$accountStatementUserIdEsc'
                  AND tr.batchid='$batchIdEsc'
                ORDER BY tr.termname ASC");

            if(!$_SQL_TERMS){
                continue;
            }

            while($rowTerm = mysqli_fetch_array($_SQL_TERMS, MYSQLI_ASSOC)){
                $termName = isset($rowTerm['termname']) ? trim((string)$rowTerm['termname']) : '';
                if($termName === ''){
                    continue;
                }
                $termNameEsc = mysqli_real_escape_string($con, $termName);
                $amountToPay = 0.0;
                $subtotalPaid = 0.0;
                $latestPayment = '';
                $paymentRows = array();
                $transactionCount = 0;
                $statementItems = array();

                $_SQL_BILLED = mysqli_query($con, "SELECT COALESCE(SUM(ip.price),0) AS Total_Amount
                    FROM tblbilling bi
                    INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
                    WHERE bi.userid='$accountStatementUserIdEsc'
                      AND ip.class_entryid='$classEntryIdEsc'
                      AND ip.term='$termNameEsc'
                      AND ip.batch='$batchIdEsc'");
                if($_SQL_BILLED && $rowBilled = mysqli_fetch_array($_SQL_BILLED, MYSQLI_ASSOC)){
                    $amountToPay = (float)$rowBilled['Total_Amount'];
                }

                $_SQL_PAYMENTS = mysqli_query($con, "SELECT itm.itemname, pm.payment, pm.datetimepayment
                    FROM tblbilling bi
                    INNER JOIN tblsystemuser su ON bi.userid=su.userid
                    INNER JOIN tblitemprice ip ON bi.itempriceid=ip.itempriceid
                    INNER JOIN tblitem itm ON ip.itemid=itm.itemid
                    INNER JOIN tblclassentry ce ON ip.class_entryid=ce.class_entryid
                    INNER JOIN tblbatch b ON ip.batch=b.batchid
                    INNER JOIN tblpayment pm ON pm.billid=bi.billid
                    WHERE bi.userid='$accountStatementUserIdEsc'
                      AND ip.class_entryid='$classEntryIdEsc'
                      AND ip.term='$termNameEsc'
                      AND ip.batch='$batchIdEsc'
                    ORDER BY pm.datetimepayment DESC");

                if($_SQL_PAYMENTS){
                    while($rowPayment = mysqli_fetch_array($_SQL_PAYMENTS, MYSQLI_ASSOC)){
                        $paymentAmount = isset($rowPayment['payment']) ? (float)$rowPayment['payment'] : 0.0;
                        $paymentDate = isset($rowPayment['datetimepayment']) ? trim((string)$rowPayment['datetimepayment']) : '';
                        $paymentItem = isset($rowPayment['itemname']) ? trim((string)$rowPayment['itemname']) : 'Billing Item';
                        $paymentRows[] = array(
                            'itemname' => $paymentItem,
                            'payment' => $paymentAmount,
                            'datetimepayment' => $paymentDate
                        );
                        $subtotalPaid += $paymentAmount;
                        $transactionCount++;
                        if($paymentDate !== '' && ($latestPayment === '' || strtotime($paymentDate) > strtotime($latestPayment))){
                            $latestPayment = $paymentDate;
                        }
                        $statementItems[] = strtolower($paymentItem);
                    }
                }

                $balance = $amountToPay - $subtotalPaid;
                if($balance < 0){
                    $balance = 0.0;
                }

                if($latestPayment !== '' && ($accountStatementSummary['latest_payment'] === '' || strtotime($latestPayment) > strtotime($accountStatementSummary['latest_payment']))){
                    $accountStatementSummary['latest_payment'] = $latestPayment;
                }

                $statusTone = 'pending';
                $statusLabel = 'No Payment';
                if($amountToPay > 0 && $balance <= 0){
                    $statusTone = 'settled';
                    $statusLabel = 'Settled';
                }elseif($subtotalPaid > 0){
                    $statusTone = 'partial';
                    $statusLabel = 'Part Paid';
                }

                $accountStatementGroups[$classKey]['statements'][] = array(
                    'termname' => $termName,
                    'batchid' => $batchId,
                    'batch_label' => trim((string)$rowTerm['batch']) !== '' ? trim((string)$rowTerm['batch']) : trim((string)$rowClassReg['batch']),
                    'amount_to_pay' => $amountToPay,
                    'subtotal_paid' => $subtotalPaid,
                    'balance' => $balance,
                    'transaction_count' => $transactionCount,
                    'latest_payment' => $latestPayment,
                    'payment_rows' => $paymentRows,
                    'search_terms' => implode(' ', $statementItems),
                    'status_tone' => $statusTone,
                    'status_label' => $statusLabel
                );

                $accountStatementSummary['periods']++;
                $accountStatementSummary['billed'] += $amountToPay;
                $accountStatementSummary['paid'] += $subtotalPaid;
                $accountStatementSummary['balance'] += $balance;
            }
        }
    }
}

$accountStatementGroups = array_values(array_filter($accountStatementGroups, function($group){
    return !empty($group['statements']);
}));
$accountStatementSummary['classes'] = count($accountStatementGroups);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include("links.php"); ?>
<link rel="stylesheet" type="text/css" href="css/account-statements.css">
</head>
<body class="account-statements-page">
    <div class="header">
    <?php include("menu.php"); ?>
    </div>

    <main class="account-shell">
        <?php if($accountStatementFlash !== ''){ ?>
        <div class="account-flash"><?php echo $_SESSION['Message']; ?></div>
        <?php } ?>

        <section class="account-hero">
            <div class="account-hero__copy">
                <span class="account-hero__eyebrow">Account Statement</span>
                <h1>Account Statements</h1>
                <p>Check billed items, payments, and balances for each class and semester.</p>
            </div>
            <aside class="account-hero__profile">
                <span class="account-hero__label">Student Record</span>
                <strong><?php echo account_stmt_esc($accountStatementProfile['name']); ?></strong>
                <small><?php echo account_stmt_esc($accountStatementProfile['userid']); ?></small>
                <div class="account-hero__meta">
                    <div><span>Classes</span><strong><?php echo number_format((int)$accountStatementSummary['classes']); ?></strong></div>
                    <div><span>Statements</span><strong><?php echo number_format((int)$accountStatementSummary['periods']); ?></strong></div>
                </div>
            </aside>
        </section>

        <section class="account-summary-grid">
            <article class="account-summary-card account-summary-card--billed">
                <span>Total Billed</span>
                <strong><?php echo account_stmt_esc(account_stmt_money($accountStatementSummary['billed'])); ?></strong>
            </article>
            <article class="account-summary-card account-summary-card--paid">
                <span>Total Paid</span>
                <strong><?php echo account_stmt_esc(account_stmt_money($accountStatementSummary['paid'])); ?></strong>
            </article>
            <article class="account-summary-card account-summary-card--balance">
                <span>Outstanding</span>
                <strong><?php echo account_stmt_esc(account_stmt_money($accountStatementSummary['balance'])); ?></strong>
            </article>
            <article class="account-summary-card account-summary-card--latest">
                <span>Latest Payment</span>
                <strong><?php echo account_stmt_esc($accountStatementSummary['latest_payment'] !== '' ? account_stmt_datetime($accountStatementSummary['latest_payment']) : 'No payment yet'); ?></strong>
            </article>
        </section>

        <section class="account-toolbar">
            <div class="account-toolbar__field">
                <label for="account_statement_search">Search statements</label>
                <input type="search" id="account_statement_search" placeholder="Search class, semester, batch, item, or status" data-account-search>
            </div>
            <div class="account-toolbar__hint">
                <strong data-account-visible-count><?php echo number_format((int)$accountStatementSummary['periods']); ?></strong>
                <span>statement<?php echo ((int)$accountStatementSummary['periods'] === 1 ? '' : 's'); ?> shown</span>
            </div>
        </section>

        <?php if(empty($accountStatementGroups)){ ?>
        <section class="account-empty-state">
            <h2>No account statement has been generated yet</h2>
            <p>Once your school bills and payments are recorded, your class and semester statements will appear here.</p>
        </section>
        <?php } else { ?>
            <?php foreach($accountStatementGroups as $classGroup){ ?>
            <section class="account-class-section" data-account-class-section>
                <div class="account-class-section__header">
                    <div>
                        <span class="account-class-section__eyebrow">Class</span>
                        <h2><?php echo account_stmt_esc($classGroup['class_name']); ?></h2>
                        <p><?php echo count($classGroup['statements']); ?> semester statement<?php echo (count($classGroup['statements']) === 1 ? '' : 's'); ?><?php echo trim((string)$classGroup['batch_label']) !== '' ? ' in '.account_stmt_esc($classGroup['batch_label']) : ''; ?>.</p>
                    </div>
                </div>

                <div class="account-statement-grid">
                    <?php foreach($classGroup['statements'] as $statement){ ?>
                    <?php
                        $statementSearch = strtolower(trim($classGroup['class_name'].' '.$statement['termname'].' '.$statement['batch_label'].' '.$statement['status_label'].' '.$statement['search_terms']));
                    ?>
                    <article class="account-statement-card account-statement-card--<?php echo account_stmt_esc($statement['status_tone']); ?>" data-account-statement-card data-search="<?php echo account_stmt_esc($statementSearch); ?>">
                        <div class="account-statement-card__header">
                            <div>
                                <span class="account-statement-card__eyebrow">Semester <?php echo account_stmt_esc($statement['termname']); ?></span>
                                <h3><?php echo account_stmt_esc($classGroup['class_name']); ?></h3>
                                <p><?php echo account_stmt_esc($statement['batch_label'] !== '' ? $statement['batch_label'] : 'Academic year not set'); ?></p>
                            </div>
                            <span class="account-status-pill account-status-pill--<?php echo account_stmt_esc($statement['status_tone']); ?>"><?php echo account_stmt_esc($statement['status_label']); ?></span>
                        </div>

                        <div class="account-meta-grid">
                            <div class="account-meta-card">
                                <span>Amount Billed</span>
                                <strong><?php echo account_stmt_esc(account_stmt_money($statement['amount_to_pay'])); ?></strong>
                            </div>
                            <div class="account-meta-card">
                                <span>Total Paid</span>
                                <strong><?php echo account_stmt_esc(account_stmt_money($statement['subtotal_paid'])); ?></strong>
                            </div>
                            <div class="account-meta-card">
                                <span>Balance</span>
                                <strong><?php echo account_stmt_esc(account_stmt_money($statement['balance'])); ?></strong>
                            </div>
                            <div class="account-meta-card">
                                <span>Payment Entries</span>
                                <strong><?php echo number_format((int)$statement['transaction_count']); ?></strong>
                            </div>
                        </div>

                        <?php if(!empty($statement['payment_rows'])){ ?>
                        <div class="account-table-wrap">
                            <table class="account-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Item</th>
                                        <th>Paid</th>
                                        <th>Date / Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($statement['payment_rows'] as $index => $paymentRow){ ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo account_stmt_esc($paymentRow['itemname']); ?></td>
                                        <td><?php echo account_stmt_esc(account_stmt_money($paymentRow['payment'])); ?></td>
                                        <td><?php echo account_stmt_esc(account_stmt_datetime($paymentRow['datetimepayment'])); ?></td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                        <?php } else { ?>
                        <div class="account-empty-inline">
                            <p>No payment has been recorded for this semester yet.</p>
                        </div>
                        <?php } ?>

                        <div class="account-statement-card__footer">
                            <div class="account-statement-card__latest">
                                <span>Latest Payment</span>
                                <strong><?php echo account_stmt_esc($statement['latest_payment'] !== '' ? account_stmt_datetime($statement['latest_payment']) : 'No payment yet'); ?></strong>
                            </div>
                            <form method="post" action="account-statements.php" class="account-print-form">
                                <input type="hidden" name="class_id" value="<?php echo account_stmt_esc($classGroup['class_entryid']); ?>">
                                <input type="hidden" name="term_id" value="<?php echo account_stmt_esc($statement['termname']); ?>">
                                <input type="hidden" name="batch_id" value="<?php echo account_stmt_esc($statement['batchid']); ?>">
                                <button class="account-print-btn" type="submit" name="print_all_bill"><i class="fa fa-print"></i> Print Semester Statement</button>
                            </form>
                        </div>
                    </article>
                    <?php } ?>
                </div>
            </section>
            <?php } ?>
        <?php } ?>
    </main>

    <script src="scripts/account-statements.js"></script>
</body>
</html>

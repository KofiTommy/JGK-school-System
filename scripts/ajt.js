/*function idleLogout(){
  var t;
  window.onload = resetTimer;
  //DOM Events
  window.onmousemove = resetTimer;
  window.onmousedown = resetTimer;
  window.onkeypress = resetTimer;
  window.ontouchstart = resetTimer;
  window.onclick = resetTimer;

  function logout(){
    alert("You are now logged out.");
    //location.href='logout.php';
  }
  function resetTimer(){
    clearTimeout(t);
    t=setTimeout(logout,10000);
    //1000 millisecond  = 1 sec
  }
}
idleLogout();
*/
function timer(){

var d = new Date();
 document.getElementById("timer1").innerHTML = d.toLocaleTimeString();

setTimeout(function(){timer();},200);
}



function checkFirstChar(str)
    {
    var fn = document.getElementById(str).value;
    var firstChar = fn.charAt(0).toUpperCase();
    var subText = fn.substr(1,fn.length).toLowerCase();
    var finalText = firstChar + subText;
     document.getElementById(str).value =finalText;   
    }

function CheckDate()
  {
var str1  = document.getElementById("schoolcloses-date");
var str2  = document.getElementById("todate");
var string1 = str1.value;
var string2 = str2.value;

var arrfromdate = string1.split("-");
var fdate = arrfromdate[0];
var fmonth = arrfromdate[1];
var fyear = arrfromdate[2]; 

var arrtodate = string2.split("/");
var tdate = arrtodate[0];
var tmonth= arrtodate[1];
var tyear = arrtodate[2];

var date1 = new Date(fyear, fmonth, fdate); 
var date2 = new Date(tyear, tmonth, tdate);
var dayNames = new Array("Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday");


var dmonth = fmonth-1;

var date3 = new Date(fyear, dmonth, fdate); 
var dayname  = dayNames[date3.getDay()];

 
 }


function CheckToDate()
  {
var str1  = document.getElementById("to-date");
var str2  = document.getElementById("todate");
var string1 = str1.value;
var string2 = str2.value;

var arrfromdate = string1.split("-");
var fdate = arrfromdate[0];
var fmonth = arrfromdate[1];
var fyear = arrfromdate[2]; 

var arrtodate = string2.split("/");
var tdate = arrtodate[0];
var tmonth= arrtodate[1];
var tyear = arrtodate[2];

var date1 = new Date(fyear, fmonth, fdate); 
var date2 = new Date(tyear, tmonth, tdate);
var dayNames = new Array("Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday");


var dmonth = fmonth-1;

var date3 = new Date(fyear, dmonth, fdate); 
var dayname  = dayNames[date3.getDay()];

 
 }

function ValidateFromDate()
  {
var str1  = document.getElementById("from-date");
var str2  = document.getElementById("todate");
var string1 = str1.value;
var string2 = str2.value;

var arrfromdate = string1.split("-");
var fdate = arrfromdate[0];
var fmonth = arrfromdate[1];
var fyear = arrfromdate[2]; 

var arrtodate = string2.split("/");
var tdate = arrtodate[0];
var tmonth= arrtodate[1];
var tyear = arrtodate[2];

var date1 = new Date(fyear, fmonth, fdate); 
var date2 = new Date(tyear, tmonth, tdate);
var dayNames = new Array("Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday");


var dmonth = fmonth-1;

var date3 = new Date(fyear, dmonth, fdate); 
var dayname  = dayNames[date3.getDay()];
 }

function ValidateToDate()
  {
var str1  = document.getElementById("to-date");
var str2  = document.getElementById("todate");
var string1 = str1.value;
var string2 = str2.value;

var arrfromdate = string1.split("-");
var fdate = arrfromdate[0];
var fmonth = arrfromdate[1];
var fyear = arrfromdate[2]; 

var arrtodate = string2.split("/");
var tdate = arrtodate[0];
var tmonth= arrtodate[1];
var tyear = arrtodate[2];

var date1 = new Date(fyear, fmonth, fdate); 
var date2 = new Date(tyear, tmonth, tdate);
var dayNames = new Array("Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday");


var dmonth = fmonth-1;

var date3 = new Date(fyear, dmonth, fdate); 
var dayname  = dayNames[date3.getDay()];
 }
function ValidateDate(str)
  {
var str1  = document.getElementById(str);
var str2  = document.getElementById("todate");
var string1 = str1.value;
var string2 = str2.value;

var arrfromdate = string1.split("-");
var fdate = arrfromdate[0];
var fmonth = arrfromdate[1];
var fyear = arrfromdate[2]; 

var arrtodate = string2.split("/");
var tdate = arrtodate[0];
var tmonth= arrtodate[1];
var tyear = arrtodate[2];

var date1 = new Date(fyear, fmonth, fdate); 
var date2 = new Date(tyear, tmonth, tdate);
var dayNames = new Array("Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday");


var dmonth = fmonth-1;

var date3 = new Date(fyear, dmonth, fdate); 
var dayname  = dayNames[date3.getDay()];

 
 }


function getProfit()
{
  var sale_price = document.getElementById("item-price").value;
  var cost_price = document.getElementById("cost-price").value;
  
  var profitable= (+sale_price) - (+cost_price);
  document.getElementById("profit").value =profitable.toPrecision(3);

}
function getCalculation()
{
  //Local variables declaration
  var sale_price =  document.getElementById("item-price").value;
  var new_quantity =  document.getElementById("new-quantity").value;
  var cost_price =  document.getElementById("cost-price").value;

//Compute each term
  var total_sale =  (+sale_price) * (+new_quantity);
  var total_cost =  (+cost_price) * (+new_quantity);

  var total_profit = total_sale - total_cost;

//Assign to each field
   document.getElementById("total-sales").value =total_sale;
    document.getElementById("total-profit").value = total_profit;

  getStockID();
}

var rnd;
function getStockID()
{
rnd=Math.floor( Math.random()*100000000);
document.getElementById("stock-id").value=rnd;
}


function getTotalSales()
{
  var avail_quantity =  document.getElementById("quantity").value;
  var qty =  document.getElementById("required-quantity").value;
  var unit_price =  document.getElementById("unit-price").value;

 
if ((+qty)>(+avail_quantity))
{
  document.getElementById("error_msg").innerHTML = "<div style='background-color:red;color:white;text-align:center;padding:0px;font-family:tahoma verdana arial;'>Quantity entered cannot be more than available quantity</div>";
 document.getElementById("required-quantity").value ="";
}
  else
  {
   var total_sale =  (+unit_price) * (+qty);
    document.getElementById("error_msg").innerHTML = "<div style='background-color:white'> </div>";
    document.getElementById("total-sales-made").value = total_sale;
  }

getOrderID();
getCustomerID();
}

var rndo;
function getOrderID()
{
rndo=Math.floor( Math.random()*100000000);
document.getElementById("order-id").value=rndo;
}

var rndcu;
function getCustomerID()
{
rndcu=Math.floor( Math.random()*100000000);
document.getElementById("customer-id").value=rndcu;
}

function getChangeAmountReturn()
{
 var change_amount;
var amount;
var balance_amount;

  var amount_received = document.getElementById("show-amount-received").value;
  var total_sale = document.getElementById("get-total-sale").value;
  var total_added = document.getElementById("amountadded").value;

  amount =  (+total_sale) - (+amount_received)+(+total_added);

    if(amount>0)
    {
        balance_amount = amount;
      change_amount=0;

   
    }
    else if(amount <0)
    {
         change_amount = - amount;
           //change_amount = - amount
      balance_amount=0;
    
    }
    else if(amount==0)
    {
      balance_amount =0;
      change_amount =0;
    }

     /* if(amount_received>total_sale)
      {
      change_amount = (+amount_received) - (+total_sale);
      }
      else if(amount_received==total_sale)
      {
      change_amount =0;
      }
      else if(amount_received < total_sale)
      {
        change_amount =(+total_sale) - (+amount_received);
      }
      */
  document.getElementById("amount-received").value = (+amount_received)-(+total_added);
document.getElementById("change-amount").value = change_amount;
document.getElementById("balance-amount").value = balance_amount;

getPaymentID();

}


function getChangeAmount()
{
 var change_amount;
var amount;
var balance_amount;

  var amount_received = document.getElementById("show-amount-received").value;
  var total_sale = document.getElementById("get-total-sale").value;
  var total_added = document.getElementById("amountadded").value;

  amount =  (+total_sale) - (+amount_received)+(+total_added);

    if(amount>0)
    {
        balance_amount = amount;
      change_amount=0;

   
    }
    else if(amount <0)
    {
         change_amount = - amount;
           //change_amount = - amount
      balance_amount=0;
    
    }
    else if(amount==0)
    {
      balance_amount =0;
      change_amount =0;
    }

     /* if(amount_received>total_sale)
      {
      change_amount = (+amount_received) - (+total_sale);
      }
      else if(amount_received==total_sale)
      {
      change_amount =0;
      }
      else if(amount_received < total_sale)
      {
        change_amount =(+total_sale) - (+amount_received);
      }
      */
  document.getElementById("amount-received").value = (+amount_received)-(+total_added);
document.getElementById("change-amount").value = change_amount;
document.getElementById("balance-amount").value = balance_amount;

getPaymentID();

}

var rndp;
function getPaymentID()
{
rndp=Math.floor( Math.random()*100000000);
document.getElementById("payment-id").value=rndp;

document.getElementById("payment-customer-id").value=document.getElementById("gen-cust").value;
}

var rndphoto;
function getPhotoID()
{
  rndphoto=Math.floor( Math.random()*100000000);
document.getElementById("item-photo-id").value=rndphoto;
}

var rndcontact;
function getContactID()
{
  rndcontact=Math.floor( Math.random()*100000000);
document.getElementById("contact-id").value=rndcontact;
}

function getCheckQuantity()
{
  var avail_qty =document.getElementById("avail-quantity").value;
    var qty =document.getElementById("quantity").value;

    qty_left = (+avail_qty) - (+qty);
    if(qty_left<0)
    {
      document.getElementById("msg_error").innerHTML ="<div style='background-color:whitesmoke;color:red;border:1px solid maroon;'>Please, we don't have enough quantity,call us. Thank you </div>";
       document.getElementById("quantity").value="";

    }
    else
    {
      document.getElementById("msg_error").innerHTML="";
    }
}


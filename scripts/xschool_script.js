 function CheckDateOfBirth()
  {
var str1  = document.getElementById("birthday");
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

var oneDay = 24 * 60 * 60 * 1000;
var oneYear = oneDay * 365;

var diffDays  = Math.round(Math.abs(date1.getTime() -  date2.getTime()) / oneYear);

if(diffDays<1)
{
    alert("You can't choose date less than 1 years");
    document.getElementById("age").style.backgroundColor="#FFFFE0"; 
    document.getElementById("age").value ="";
    document.getElementById("birthday").value ="";
    return false;
}
else
{
document.getElementById("age").value = diffDays;  
}

 if(date1 > date2)
 {
  alert("You can't choose  this date..");
   document.getElementById("birthday").style.backgroundColor="#FFFFE0"; 
  document.getElementById("birthday").value = "";
 return false;
 }
 }

function CheckSchoolResume()
  {
var str1  = document.getElementById("schoolresumes");
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

 if(dayname =="Sunday" || dayname=="Saturday")
 {
  alert("Saturday or Sunday is Holiday..");
   document.getElementById("schoolresumes").style.backgroundColor="#FFFFE0"; 
  document.getElementById("schoolresumes").value = "";
 return false;
 }
 }


 function CheckSchoolCloses()
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

 if(dayname =="Sunday" || dayname=="Saturday")
 {
  alert("Saturday or Sunday is Holiday..");
   document.getElementById("schoolcloses-date").style.backgroundColor="#FFFFE0"; 
  document.getElementById("schoolcloses-date").value = "";
 return false;
 }
 }

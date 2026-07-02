<?php

include("dbstring.php");
$_SQL_USER=mysqli_query($con,"SELECT * FROM tblsystemuser su WHERE su.userid='$_SESSION[USERID]' AND su.systemtype='Student'  ORDER BY su.userid");

sqli_fetch_array($_SQL_USER,MYSQLI_ASSOC))
{

$_SQL_SU=mysqli_query($con,"SELECT * FROM tblsubject");
while($row_rsu=mysqli_fetch_array($_SQL_SU,MYSQLI_ASSOC)){

$_SQL_CLASS=mysqli_query($con,"SELECT * FROM tblclassentry ce INNER JOIN tbltermregistry tr 
	ON ce.class_entryid=tr.class_entryid GROUP BY tr.class_entryid");
if(mysqli_num_rows($_SQL_CLASS)==0){

}else{
while($row_ce=mysqli_fetch_array($_SQL_CLASS,MYSQLI_ASSOC)){
for($k=1;$k<=3;$k++)
{
	$_SQL_EXECUTE=mysqli_query($con,"SELECT *,su.userid FROM tblmark mk 
		INNER JOIN tblsystemuser su ON mk.userid=su.userid
		INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
		INNER JOIN tblsubjectclassification sc ON sa.classificationid=sc.classificationid
		INNER JOIN tblclassentry ce ON sc.classid=ce.class_entryid
		INNER JOIN tblsubject sub ON sc.subjectid=sub.subjectid 
		WHERE su.userid='$row_us[userid]' AND sub.subjectid='$row_rsu[subjectid]' 
		AND ce.class_entryid='$row_ce[class_entryid]' AND sa.termname='$k'
		ORDER BY su.userid ASC");

if(mysqli_num_rows($_SQL_EXECUTE)==0){

}else{

	@$_TotalMark=0;
	@$_getAssignment_Id=0;
	
	
	@$serial=0;
	while($row=mysqli_fetch_array($_SQL_EXECUTE,MYSQLI_ASSOC))
	{
	$_getAssignment_Id=$row['assignmentid'];

	$_TotalMark=$_TotalMark+$row['mark'];

	
	 @$_Final_Position=0;

	$_position_obj_1->setPosition($_getAssignment_Id,$_TotalMark);
	$_Final_Position= $_position_obj_1->getPosition();
	
	}
	}
	}
}
}
}
?>

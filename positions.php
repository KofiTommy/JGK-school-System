<?php


class Position{
	//$score[]=array();

	var $_position;
	var $_Position_Ends;

	public function setPosition($assignmentid,$totalscore){

		include("dbstring.php");
		$_SQL=mysqli_query($con,"SELECT  SUM(mk.mark) AS TotalMark FROM tblmark mk 
		INNER JOIN tblsystemuser su ON mk.userid=su.userid
		WHERE mk.assignmentid='$assignmentid' AND su.systemtype='Student' GROUP BY su.userid");
		
		$count=mysqli_num_rows($_SQL);
		$k=0;
		while($row=mysqli_fetch_array($_SQL,MYSQLI_ASSOC)){
			$score[$k]=$row['TotalMark'];
			//echo $score[$k]."<br/>";
			$k++;
		}

//Sorting in Ascending order
		@$_Temp=0;
		$count=count($score);
		for($j=0;$j<$count;$j++)
		{
			for($i=0;$i<$count;$i++)
			{
				if($score[$j]>$score[$i])
				{
				$_Temp=$score[$j];
				$score[$j]=$score[$i];
				$score[$i]=$_Temp;
				}
		  }
		}
		

		for($t=0;$t<$count;$t++){
			//echo $score[$t]." ". ($t+1) ."<br/>";
			if($totalscore==$score[$t]){
				$this->_position=($t+1);
				$_final_position=$this->_position;
				$this->_Position_Ends = $this->getPositionSuffix($_final_position);
				return $this->_Position_Ends;
			}
		}

		// If the position was not found, default to 0th (though this case shouldn't be needed)
		$this->_Position_Ends = "0th";
		return $this->_Position_Ends;
	}
	
	// Function to get position suffix (handles "st", "nd", "rd", "th")
	private function getPositionSuffix($position) {
		if ($position % 100 >= 11 && $position % 100 <= 13) {
			return $position . 'th';
		}
		switch ($position % 10) {
			case 1: return $position . 'st';
			case 2: return $position . 'nd';
			case 3: return $position . 'rd';
			default: return $position . 'th';
		}
	}

	public function getPosition(){
		return $this->_Position_Ends;
	}
}

?>
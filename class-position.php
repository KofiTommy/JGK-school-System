<?php

class ClassPosition{
	var $_position = 0;
	var $_Position_Ends = "0th";
	var $_classCount = 0;

	public function setClassPosition($batchid, $totalscore, $termid = "", $classid = "", $academicyear = "", $userid = ""){
		include("dbstring.php");
		include_once("semester-registry-utils.php");

		$batchid = mysqli_real_escape_string($con, (string)$batchid);
		$termid = mysqli_real_escape_string($con, (string)$termid);
		$classid = mysqli_real_escape_string($con, (string)$classid);
		$academicyear = mysqli_real_escape_string($con, trim((string)$academicyear));
		$userid = mysqli_real_escape_string($con, trim((string)$userid));
		$targetScore = (float)$totalscore;
		$filters = array(
			"sa.batchid='$batchid'",
			"su.systemtype='Student'"
		);

		if($termid !== ""){
			$filters[] = "sa.termname='$termid'";
		}
		if($classid !== ""){
			$filters[] = "sa.classid='$classid'";
		}
		if($academicyear !== ""){
			$filters[] = semester_registry_assignment_year_sql("sa")."='$academicyear'";
		}

		$sql = "SELECT mk.userid, SUM(mk.mark) AS TotalMark
			FROM tblmark mk
			INNER JOIN tblsystemuser su ON mk.userid=su.userid
			INNER JOIN tblsubjectassignment sa ON mk.assignmentid=sa.assignmentid
			WHERE ".implode(" AND ", $filters)."
			GROUP BY mk.userid
			ORDER BY TotalMark DESC";
		$_SQL = mysqli_query($con, $sql);

		if(!$_SQL){
			$this->_position = 0;
			$this->_Position_Ends = "0th";
			$this->_classCount = 0;
			return $this->_Position_Ends;
		}

		$this->_classCount = mysqli_num_rows($_SQL);
		$currentRank = 0;
		$index = 0;
		$prevScore = null;
		$matched = false;
		while($row = mysqli_fetch_array($_SQL, MYSQLI_ASSOC)){
			$index++;
			$currentScore = (float)$row['TotalMark'];
			if($prevScore === null || $currentScore < $prevScore){
				$currentRank = $index;
			}
			if($userid !== ""){
				if(trim((string)$row['userid']) === $userid){
					$matched = true;
					$this->_position = $currentRank;
					break;
				}
			}else{
				if(abs($currentScore - $targetScore) < 0.00001){
					$matched = true;
					$this->_position = $currentRank;
					break;
				}
			}
			$prevScore = $currentScore;
		}

		if(!$matched){
			$this->_position = 0;
			$this->_Position_Ends = "0th";
			return $this->_Position_Ends;
		}

		$this->_Position_Ends = $this->getPositionSuffix($this->_position);
		return $this->_Position_Ends;
	}

	private function getPositionSuffix($position){
		if($position % 100 >= 11 && $position % 100 <= 13){
			return $position.'th';
		}
		switch($position % 10){
			case 1:
				return $position.'st';
			case 2:
				return $position.'nd';
			case 3:
				return $position.'rd';
			default:
				return $position.'th';
		}
	}

	public function getClassPosition(){
		return $this->_Position_Ends;
	}

	public function getClassCount(){
		return (int)$this->_classCount;
	}
}

?>

<?php
class Remark
{
	var $_Remark="";
	public function setMark($mark)
	{
		if($mark>=85 && $mark<=100){
			$this->_Remark="Excellent";
		}
		else if($mark>=75 && $mark<=84){
			$this->_Remark="Very Good";
		}
		else if($mark>=65 && $mark<=74){
			$this->_Remark="Good";
		}
		else if($mark>=55 && $mark<=64){
			$this->_Remark="Fairly Good";
		}
		else if($mark>=40 && $mark<=54){
			$this->_Remark="Credit";
		}
		else if($mark>=30 && $mark<=39){
			$this->_Remark="Pass";
		}
		else if($mark>=20 && $mark<=29){
			$this->_Remark="Weak Pass";
		}
		else if($mark>=0 && $mark<=19){
			$this->_Remark="Fair";
		}
	}
	public function getMark()
	{
		return $this->_Remark;		
	}
}
?>

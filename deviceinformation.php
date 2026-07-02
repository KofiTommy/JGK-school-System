<?php
class DeviceInformation{
	var $_IPaddr;
	var $_MACAddr;
	var $_OS;
	var $_Browser;

	//FUNCTION DEFINTIONS
	public function setIPaddr($ipad){

		// Function to get the client IP address
	if($ipad==1){
    if (getenv('HTTP_CLIENT_IP'))
        $this->_IPaddr= getenv('HTTP_CLIENT_IP');
    else if(getenv('HTTP_X_FORWARDED_FOR'))
       $this->_IPaddr= getenv('HTTP_X_FORWARDED_FOR');
    else if(getenv('HTTP_X_FORWARDED'))
        $this->_IPaddr= getenv('HTTP_X_FORWARDED');
    else if(getenv('HTTP_FORWARDED_FOR'))
        $this->_IPaddr= getenv('HTTP_FORWARDED_FOR');
    else if(getenv('HTTP_FORWARDED'))
       $this->_IPaddr= getenv('HTTP_FORWARDED');
    else if(getenv('REMOTE_ADDR'))
        $this->_IPaddr= getenv('REMOTE_ADDR');
    else
        $this->_IPaddr= 'UNKNOWN';
	}
	elseif($ipad==0){
		$this->_IPaddr="UNKNOWN";
	}
	}
	public function getIPaddr(){
	return $this->_IPaddr;
	}

public function setMAC($ipad)
{
	if($ipad==1)
	{
	// Turn on output buffering  
	ob_start();  
	//Get the ipconfig details using system commond  
	system('ipconfig /all');  
	// Capture the output into a variable  
	$mycomsys=ob_get_contents();  
	// Clean (erase) the output buffer  
	ob_clean();  
	$find_mac = "Physical"; 
	//find the "Physical" & Find the position of Physical text  
	$pmac = strpos($mycomsys, $find_mac);  
	// Get Physical Address  
	$macaddress=substr($mycomsys,($pmac+36),17);  
	//Display Mac Address  
	$this->_MACAddr=$macaddress;
	}
}

public function getMAC(){
	return $this->_MACAddr;
}
	
public function setOS($bol_os){
	if($bol_os==1){
		$user_agent=$_SERVER['HTTP_USER_AGENT'];
		$machine_os='';
		 $os_array     = array(
                          '/windows nt 10/i'      =>  'Windows 10',
                          '/windows nt 6.3/i'     =>  'Windows 8.1',
                          '/windows nt 6.2/i'     =>  'Windows 8',
                          '/windows nt 6.1/i'     =>  'Windows 7',
                          '/windows nt 6.0/i'     =>  'Windows Vista',
                          '/windows nt 5.2/i'     =>  'Windows Server 2003/XP x64',
                          '/windows nt 5.1/i'     =>  'Windows XP',
                          '/windows xp/i'         =>  'Windows XP',
                          '/windows nt 5.0/i'     =>  'Windows 2000',
                          '/windows me/i'         =>  'Windows ME',
                          '/win98/i'              =>  'Windows 98',
                          '/win95/i'              =>  'Windows 95',
                          '/win16/i'              =>  'Windows 3.11',
                          '/macintosh|mac os x/i' =>  'Mac OS X',
                          '/mac_powerpc/i'        =>  'Mac OS 9',
                          '/linux/i'              =>  'Linux',
                          '/ubuntu/i'             =>  'Ubuntu',
                          '/iphone/i'             =>  'iPhone',
                          '/ipod/i'               =>  'iPod',
                          '/ipad/i'               =>  'iPad',
                          '/android/i'            =>  'Android',
                          '/blackberry/i'         =>  'BlackBerry',
                          '/webos/i'              =>  'Mobile'
                    );

		foreach ($os_array as $regex => $value)
        if (preg_match($regex, $user_agent))
            $machine_os = $value;

	    $this->_OS=$machine_os;
	}

	
}
public function getOS(){
	return $this->_OS;
}

public function setBrowser($bol_br){
	if($bol_br==1)
	{
		$user_agent=$ua = strtolower($_SERVER['HTTP_USER_AGENT']);
	    $browser = '';
	        $browser_array = array(
                            '/msie/i'      => 'Internet Explorer',
                            '/firefox/i'   => 'Firefox',
                            '/safari/i'    => 'Safari',
                            '/chrome/i'    => 'Chrome',
                            '/edge/i'      => 'Edge',
                            '/opera/i'     => 'Opera',
                            '/netscape/i'  => 'Netscape',
                            '/maxthon/i'   => 'Maxthon',
                            '/konqueror/i' => 'Konqueror',
                            '/mobile/i'    => 'Handheld Browser'
                     );

    foreach ($browser_array as $regex => $value)
        if (preg_match($regex, $user_agent))
            $browser = $value;

	    $this->_Browser=$browser;
	}
}
public function getBrowser(){
	return $this->_Browser;
}
	
}
?>
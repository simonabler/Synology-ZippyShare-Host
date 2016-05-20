<?php


	/** Copyright (c) 2016 Simon Abler. All rights reserved. 
	 *	
	 *
	 */

if (!defined('DOWNLOAD_STATION_USER_AGENT')) {
    define('DOWNLOAD_STATION_USER_AGENT', 'Mozilla/5.0 (Windows NT 6.1; rv:44.0) Gecko/20100101 Firefox/44.0');
}

class ZippyShare
{
    private $Url;
    private $Username;
    private $Password;
    private $HostInfo;

    private $cookieFileName;
    private $fileId;

    const DEBUGMODE = true;
 
 	/**
	 * constructor 
     */
    public function __construct($Url, $Username, $Password, $HostInfo)
    {
        $this->Url = $Url;
        $this->Username = $Username;
        $this->Password = $Password;
        $this->HostInfo = $HostInfo;

        $this->cookieFileName = tempnam('/tmp', "zs_cookie_");
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        ini_set('error_log', 'tmp/php_error.log');
        ini_set('error_reporting', E_ALL);


    }
	

	/**
	 * Function called by Synology
     * @param --
     * @return USER_IS_FREE
     */
	public function Verify($ClearCookie)
	{
		
	        if ($ClearCookie && file_exists($this->cookieFileName)) {
            	unlink($this->cookieFileName);
        	}

        	return USER_IS_FREE;

	}
	/**
	 * Function called by Synology
     * @param --
     * @return DownloadInfo for Synology
     */
	public function GetDownloadInfo()
    {
		return $this->zippy_fetch_dl();
		
	}
	/**
     * @param --
     * @return Content from URL
     */
	public function DownloadParsePage()
	{
	$Option = array();
	$curl = curl_init();
	
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt($curl, CURLOPT_USERAGENT,DOWNLOAD_STATION_USER_AGENT);
	curl_setopt($curl, CURLOPT_COOKIEFILE,$this->cookieFileName);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($curl, CURLOPT_VERBOSE, 1);
	curl_setopt($curl, CURLOPT_URL, $this->Url);

	$ret = curl_exec($curl);

	curl_close($curl);
	return $ret;
	}

	 /**
     * @param --
     * @return DownloadInfo for Synology
	 * Thanks to Ivan Jelenić (Quirinus) @ GitHub
	 * https://github.com/Quirinus/Zippyshare-batch-download-PHP-cURL
     */
	
	public function zippy_fetch_dl()
	{

		preg_match('/\/v\/([^\n\\/]+)\/file\./i',$this->Url, $zippy_url_number);
		$downloadINFO = FALSE;
		$p_error = '';
		$zippy_page = $this->DownloadParsePage();

		if (($p_error !== '')||($zippy_page === false))
		{
			return false;
		}
	
		
		if (!(preg_match('/<title>([^\n\<]*)<\/title>/i', $zippy_page, $title)))
			return $downloadINFO[DOWNLOAD_ERROR] = ERR_FILE_NO_EXIST;
		
		if (stripos($title[1],'Zippyshare.com - ') === false)
			return $downloadINFO[DOWNLOAD_ERROR] = ERR_FILE_NO_EXIST;
		
		if (stripos($zippy_page,'File does not exist on this server') !== false)
			return $downloadINFO[DOWNLOAD_ERROR] = ERR_FILE_NO_EXIST;
		
		
		$algorithm_script_code = explode('document.getElementById(\'fimage\').href',$zippy_page);
		$algorithm_script_code = explode('<script type="text/javascript">',$algorithm_script_code[0]);
		$algorithm_script_code = end($algorithm_script_code);
		
		$algorithm_variables_code = explode('document.getElementById(\'dlbutton\').href', $algorithm_script_code)[0];
		
		if (stripos($algorithm_variables_code,'Math') !== false)
			return $downloadINFO[DOWNLOAD_ERROR] = ERR_FILE_NO_EXIST;
		
		if (!(preg_match('/\/\s*([^\n\/]*)"\s*\+\s*([^\n]+)\s*\+\s*"([^\n\/]*)\//i',$algorithm_script_code, $algorithm_number_code)))
			return $downloadINFO[DOWNLOAD_ERROR] = ERR_FILE_NO_EXIST;
		
		if (stripos($algorithm_variables_code,'var ') !== false)
		{
			if (!(preg_match_all('/var ([^\n \$\=]+) \=/i',$algorithm_variables_code, $algorithm_variable_names, PREG_PATTERN_ORDER)))
				return $downloadINFO[DOWNLOAD_ERROR] = ERR_FILE_NO_EXIST;
			
			$algorithm_variable_names = $algorithm_variable_names[1];
			$algorithm_variable_names_dollar = $algorithm_variable_names;
			array_walk($algorithm_variable_names_dollar, function(&$value, $key) {$value = "$$value";}); //add $ in front of variable names
			$algorithm_number_code[2] = str_replace($algorithm_variable_names,$algorithm_variable_names_dollar,$algorithm_number_code[2]); //add $ to variable names in code
			$algorithm_variables_code = str_replace($algorithm_variable_names,$algorithm_variable_names_dollar,$algorithm_variables_code);
			$algorithm_variables_code = str_replace('var ','',$algorithm_variables_code);
			eval($algorithm_variables_code);
		}
		if (!(preg_match('/www([0-9]*)\./i',$this->Url, $zippy_page_server)))
			return $downloadINFO[DOWNLOAD_ERROR] = ERR_FILE_NO_EXIST;
		
		if (!(preg_match("/\+\s*\"[^\n\/]*\/([^\n\"]+)\";/i",$algorithm_script_code, $zippy_dl_url_name)))
			return $downloadINFO[DOWNLOAD_ERROR] = ERR_FILE_NO_EXIST;
		
				eval('$mod_check = "'.$algorithm_number_code[1].'".'.$algorithm_number_code[2].'."'.$algorithm_number_code[3].'";');
		
		if (!($mod_check))
			return $downloadINFO[DOWNLOAD_ERROR] = ERR_FILE_NO_EXIST;
		

		$dl_url = "http://www{$zippy_page_server[1]}.zippyshare.com/d/{$zippy_url_number[1]}/$mod_check/{$zippy_dl_url_name[1]}";
		
		$downloadINFO[DOWNLOAD_URL] = $dl_url;
		$downloadINFO[INFO_NAME] = $zippy_dl_url_name[1];
		
		return $downloadINFO;
		
	}


	
}

?>

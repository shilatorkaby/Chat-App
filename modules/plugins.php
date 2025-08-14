<?php

	function plugins_is_plugin_loaded($name=null){
		if(!$name){
			error_log("YOURE RUNNING FUNCTION ".__FUNCTION__." WITHOUT name: ".$name);
			return false;			
		}
		
		if(isset($GLOBALS["plugins"])){
			foreach($GLOBALS["plugins"] as $plugin_name){
				if($name === $plugin_name){
					return true;
				}
			}
		}
		
		return false;
	}
	
?>
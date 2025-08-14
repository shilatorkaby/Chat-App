<?php
	function is_a_valid_json($json){
		if(!is_string($json)){
			return false;
		}

		json_decode($json);
		return (json_last_error() === JSON_ERROR_NONE);
	}
?>
<?php
	class str_validation{
		public static function only_hebrew($str) {
			return preg_match('/^[\p{Hebrew}]+$/u', $str) === 1;
		}

		public static function only_english_or_spaces($str) {
			return preg_match('/^[a-zA-Z\s]+$/', $str) === 1;
		}

		public static function ends_with_space($str) {
			return preg_match('/\s$/', $str);
		}

		public static function only_numbers($str) {
			return preg_match('/^\d+$/', $str) === 1;
		}

		public static function is_a_valid_email($str) {
			return filter_var($str, FILTER_VALIDATE_EMAIL) !== false;
		}

		public static function hebrew_and_numbers($str) {
			return preg_match('/^[\p{Hebrew}0-9]+$/u', $str) === 1;
		}

		public static function hebrew_dush_spaces_and_quotations($str) {
			return preg_match('/^[\p{Hebrew}\s\-"\''.
				'”„”“״׳’]+$/u', $str) === 1;
		}

		public static function has_spaces($str) {
			return strpos($str, ' ') !== false;
		}

		public static function is_valid_url($str) {
			return filter_var($str, FILTER_VALIDATE_URL) !== false;
		}
		
		public static function min_length($str, $min) {
			return mb_strlen($str) >= $min;
		}

		public static function max_length($str, $max) {
			return mb_strlen($str) <= $max;
		}
		
	}
?>
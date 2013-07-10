<?php
class apc_custom_validation {
	function validate_comma_numeric($field, $value, $apc) {
		$value_array = explode(',', $value);
		foreach($value_array as &$single_value) {
			if (!is_numeric($single_value)) {
				$apc->errors_flag = true;
				$apc->errors[$field['id']]['name'] = $field['name'];
				$apc->errors[$field['id']]['m'][] = __('Invalid numeric comma field ', WP-RETINA-ADAPTIVE-TEXTDOMAIN);
				return false;
			}
		}
		return true;
	}
	function validate_path_exists($field, $value, $apc) {
		$path = ABSPATH . '/' . $value;
		if (!is_dir($path)) {
			$apc->errors_flag = true;
			$apc->errors[$field['id']]['name'] = $field['name'];
			$apc->errors[$field['id']]['m'][] = __('Path \'' . $path . '\' doesn\'t exist, please create it', WP-RETINA-ADAPTIVE-TEXTDOMAIN);
			return false;
		}
		if (!is_writable($path)) {
			$apc->errors_flag = true;
			$apc->errors[$field['id']]['name'] = $field['name'];
			$apc->errors[$field['id']]['m'][] = __('Path \'' . $path . '\' isn\'t writeable, please adapt its permissions', WP-RETINA-ADAPTIVE-TEXTDOMAIN);
			return false;
		}
		return true;
	}
}

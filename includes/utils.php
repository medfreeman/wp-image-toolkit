<?php
/* Mobile detection 
   NOTE: only used in the event a cookie isn't available. */
function is_mobile() {
  $userAgent = strtolower($_SERVER['HTTP_USER_AGENT']);
  return strpos($userAgent, 'mobile');
}
function get_screen_properties($resolutions) {
	/* Check to see if a valid cookie exists */
	if (isset($_COOKIE['resolution'])) {
	  $cookie_value = $_COOKIE['resolution'];

	  // does the cookie look valid? [whole number, comma, potential floating number]
	  if (! preg_match("/^[0-9]+[,]*[0-9\.]+$/", "$cookie_value")) { // no it doesn't look valid
		setcookie("resolution", "$cookie_value", time()-100); // delete the mangled cookie
	  }
	  else { // the cookie is valid, do stuff with it
		$cookie_data   = explode(",", $_COOKIE['resolution']);
		$client_width  = (int) $cookie_data[0]; // the base resolution (CSS pixels)
		$pixel_density = 1; // set a default, used for non-retina style JS snippet
		if (isset($cookie_data[1]) && $cookie_data[1]) { // the device's pixel density factor (physical pixels per CSS pixel)
		  $pixel_density = $cookie_data[1];
		}

		rsort($resolutions); // make sure the supplied break-points are in descending size order
		$resolution = $resolutions[0]; // by default use the largest supported break-point -- 980

		foreach ($resolutions as $break_point) { // filter down
		  if ($client_width <= $break_point) {
			$resolution = $break_point;
		  }
		}
	  }
	}
	
	if (!$resolution) {
	  // We send the lowest resolution for mobile-first approach, and highest otherwise
	  $resolution = is_mobile() ? min($resolutions) : max($resolutions);
	}
	if (!$pixel_density) {
		$pixel_density = 1;
	}
	return array('width' => $client_width, 'breakpoint' => $resolution,  'pixel_density' => $pixel_density);
}

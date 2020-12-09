<?php

// ------------------------------------------------------------------------
// ---	@name	removeEmptyLines
// ---	@desc 	removes any empty lines in multi-line text
// ------------------------------------------------------------------------
if(!function_exists('removeEmptyLines')) {
	function removeEmptyLines($string) {
		return preg_replace("!^\s+(\D)!m", "\\1", $string);
	}
}

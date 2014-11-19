<?php

/**
	* The TinysouError Exception class
	*
	* An instance of this class is thrown any time the Tinysou Client encounters an error
	* making calls to the remote service.
	*
	* @author  Tairy <tairyguo@gmail.com>
	*
	* @since 1.0
	*
	*/

class TinysouError extends Exception {
	public function isInvalidAuthentication() {
		return $this->code == 401;
	}
}

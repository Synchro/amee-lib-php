<?php

/*
 * This file provides the Services_AMEE_API class. Please see the class
 * documentation for full details.
 *
 * PHP Version 5
 *
 * @category Web Services
 * @package Services_AMEE
 * @author Andrew Hill <andrew.hill@amee.com>
 * @copyright 2010 AMEE UK Limited
 * @license http://www.opensource.org/licenses/mit-license.html MIT License
 * @link http://pear.php.net/package/Services_AMEE
 *
 * @TODO Restore cookie based auth!
 */

require_once 'Services/AMEE/Exception.php';

/**
 * A constant that defines the timeout value for the AMEE REST API authorisation
 * timeout. Currently 30 minutes (i.e. 30 * 60 = 1800 seconds).
 */
define('AMEE_API_AUTH_TIMEOUT', '1800');

/**
 * The Services_AMEE_API class provides connection and communication management
 * for the AMEE REST API.
 *
 * There is no need to ever use this class yourself; it is intended to be an
 * API communications wrapper class that used by other classes in the
 * Services_AMEE package.
 *
 * @category Web Services
 * @package Services_AMEE
 * @author Andrew Hill <andrew.hill@amee.com>
 * @copyright 2010 AMEE UK Limited
 * @license http://www.opensource.org/licenses/mit-license.html MIT License
 * @link http://pear.php.net/package/Services_AMEE
 */
class Services_AMEE_API
{

    /**
     * @var <string> $sAuthToken The AMEE API authorisation token.
     */
    private $sAuthToken;

    /**
     * @var <iteger> $iAuthExpires The time (in seconds since Unix Epoch) that
     *      the AMEE API authorisation token will expire.
     */
    private $iAuthExpires;

    /**
     * @var <array> $aPostPathOpenings An array of opening path strings that
     *      are valid for AMEE REST API post operations (in Perl Regex format,
     *      excluding the opening ^ limiter).
     */
    protected $aPostPathOpenings = array(
        '/auth'
    );

    /**
     * @var <array> $aPutPathOpenings An array of opening path strings that
     *      are valid for AMEE REST API put operations (in Perl Regex format,
     *      excluding the opening ^ limiter).
     */
    protected $aPutPathOpenings = array(
        '/profiles/[A-F0-9]{12}/'
    );

    /**
     * @var <array> $aGetPathOpenings An array of opening path strings that
     *      are valid for AMEE REST API get operations (in Perl Regex format,
     *      excluding the opening ^ limiter).
     */
    protected $aGetPathOpenings = array(
        '/profiles',
        '/data'
    );

    /**
     * @var <array> $aDeletePathOpenings An array of opening path strings that
     *      are valid for AMEE REST API delete operations (in Perl Regex format,
     *      excluding the opening ^ limiter).
     */
    protected $aDeletePathOpenings = array(
        '/profiles/[A-F0-9]{12}/'
    );

    /**
     * A wrapper method to simplify the process of sending POST requests to the
     * AMEE REST API.
     *
     * @param <string> $sPath The AMEE REST API query path.
     * @param <array> $aParams An optional associative array of parameters to be
     *      passed to the AMEE REST API as part of the POST request. The exact
     *      parameters will depend on the type of request being made, as defined
     *      by the query path.
     * @return <mixed> The AMEE REST API JSON response string on success; an
     *      Exception object otherwise.
     */
    public function post($sPath, array $aParams = array())
    {
        try {
            // Test to ensure that the path at least as a valid opening
            $this->_validPath($sPath, 'post');
            // Send the AMEE REST API post request
            $aResult =  $this->_sendRequest("POST $sPath", http_build_query($aParams, NULL, '&'));
            // Return the JSON data string
            return $aResult[0];
        } catch (Exception $oException) {
            throw $oException;
        }
    }

    /**
     * A wrapper method to simplify the process of sending PUT requests to the
     * AMEE REST API.
     *
     * @param <string> $sPath The AMEE REST API query path.
     * @param <array> $aParams An optional associative array of parameters to be
     *      passed to the AMEE REST API as part of the PUT request. The exact
     *      parameters will depend on the type of request being made, as defined
     *      by the query path.
     * @return <mixed> The AMEE REST API JSON response string on success; an
     *      Exception object otherwise.
     */
    public function put($sPath, array $aParams = array())
    {
        try {
            // Test to ensure that the path at least as a valid opening
            $this->_validPath($sPath, 'put');
            // Send the AMEE REST API put request
            $aResult = $this->_sendRequest("PUT $sPath", http_build_query($aParams, NULL, '&'));
            // Return the JSON data string
            return $aResult[0];
        } catch (Exception $oException) {
            throw $oException;
        }
    }

    /**
     * A wrapper method to simplify the process of sending GET requests to the
     * AMEE REST API.
     *
     * @param <string> $sPath The AMEE REST API query path.
     * @param <array> $aParams An optional associative array of parameters to be
     *      passed to the AMEE REST API as part of the GET request. The exact
     *      parameters will depend on the type of request being made, as defined
     *      by the query path.
     * @return <mixed> The AMEE REST API JSON response string on success; an
     *      Exception object otherwise.
     */
    public function get($sPath, array $aParams = array())
    {
        try {
            // Test to ensure that the path at least as a valid opening
            $this->_validPath($sPath, 'get');
            // Send the AMEE REST API get request
            if (count($aParams) > 0) {
                $aResult = $this->_sendRequest("GET $sPath?" . http_build_query($aParams, NULL, '&'));
            } else {
                $aResult = $this->_sendRequest("GET $sPath");
            }
            // Return the JSON data string
            return $aResult[0];
        } catch (Exception $oException) {
            throw $oException;
        }
    }

    /**
     * A wrapper method to simplify the process of sending DELETE requests to
     * the AMEE REST API.
     *
     * @param <string> $sPath The AMEE REST API query path.
     * @return <mixed> The AMEE REST API JSON response string on success; an
     *      Exception object otherwise.
     */
    public function delete($sPath)
    {
        try {
            // Test to ensure that the path at least as a valid opening
            $this->_validPath($sPath, 'delete');
            // Send the AMEE REST API delete request
            $aResult = $this->_sendRequest("DELETE $sPath");
            // Return the JSON data string
            return $aResult[0];
        } catch (Exception $oException) {
            throw $oException;
        }
    }

    /**
     * A protected method to determine if a supplied AMEE REST API path at least
     * as an opening path that is valid, according to the type of method being
     * called.
     *
     * @param <string> $sPath The path being called.
     * @param <string> $sType The type of method call being made. One of "post",
     *      "put", "get" or "delete".
     * @return <mixed> True if the path is valid; an Exception object otherwise.
     */
    protected function _validPath($sPath, $sType)
    {
        // Ensure the type has the correct formatting
        $sFormattedType = ucfirst(strtolower($sType));
        // Prepare the path opening array variable name
        $aPathOpenings = 'a' . $sFormattedType . 'PathOpenings';
        // Convert the path opening array into a preg_match suitable pattern
        $sPathPattern = '#^(' . implode($this->{$aPathOpenings}, "|") . ')#';
        // Test the path to see if it matches one of the valid path openings
        // for the method type
        if (!preg_match($sPathPattern, $sPath)) {
            throw new Services_AMEE_Exception(
                'Invalid AMEE REST API ' . strtoupper($sType) . ' path specified: ' . $sPath
            );
        }
        return true;
    }

    /**
     * A protected method to take care of sending AMEE REST API method call
     * requests.
     *
     * @param <string> $sPath The full AMEE REST API method request path.
     * @param <string> $sBody The option body of the AMEE REST API method call
     *      (used for POST and PUT method calls).
     * @param <boolean> $bReturnHeaders Return the headers as well as the JSON
     *      resonse data? Optional; false by default.
     * @param <boolean> $bRepeat If, after ensuring a connection exists, the
     *      AMEE REST API returns a "401 UNAUTH" message, should the methood
     *      try to re-authorise and send the API call again? Optional; true by
     *      default.
     * @return <mixed> An array containing the (successful) result of the AMEE
     *      REST API call, where the array contains a single row being the JSON
     *      data (if $bReturnHeaders was false), or where the array contains
     *      multiple rows, with each row containing a single line of the
     *      response headers and the final row containing the JSON data
     *      (if $bReturnHeaders was true); an Exception object otherwise.
     */
    protected function _sendRequest($sPath, $sBody = null, $bReturnHeaders = false, $bRepeat = true)
    {
        // Ensure that the request is a valid type
        if (!preg_match('/^(GET|POST|PUT|DELETE)/', $sPath)) {
            throw new Services_AMEE_Exception(
                'Invalid AMEE REST API method specified: ' . $sPath
            );
        }
        // Is this an authorisation request?
        $bAuthRequest = false;
        if (preg_match('#^POST /auth#', $sPath)) {
            $bAuthRequest = true;
        }
        // Ensure that there is a connection to the AMEE REST API open, so long
        // as this is NOT a "POST /auth" request!
        if (!$this->_connected() && !$bAuthRequest) {
            try {
                $this->_connect();
            } catch (Exception $oException) {
                throw $oException;
            }
        }
        // Prepare the HTTP request string
        $sRequest =
            $sPath . " HTTP/1.1\n" .
            "Accept: application/json\n";
            // Add existing authorisation items to the HTTP request string, if
            // this is not a new authorisation request
            if (!$bAuthRequest) {
                $sRequest .=
                "Cookie: authToken=" . $this->sAuthToken . "\n";
            }
            // Complete the HTTP request string
            $sRequest .=
            "Host: " . AMEE_API_URL . "\n";
            // Add the body, if required
            if (strlen($sBody) > 0) {
                $sRequest .= 
                "Content-Type: application/x-www-form-urlencoded\n" .
                "Content-Length: " . strlen($sBody) . "\n" .
                "\n" .
                $sBody;
            } else {
                $sRequest .=
                "\n\n";
            }
        // Connect to the AMEE REST API and send the request
        $iError;
        $sError;
        if ($bAuthRequest && extension_loaded('openssl')) {
            // Connect over SSL to protect the AMEE REST API username/password
            $rSocket = fsockopen('ssl://' . AMEE_API_URL, AMEE_API_PORT_SSL, $iError, $sError);
        } else {
            $rSocket = fsockopen(AMEE_API_URL, AMEE_API_PORT, $iError, $sError);
        }
        if ($rSocket === false) {
            throw new Services_AMEE_Exception(
                'Unable to connect to the AMEE REST API: ' . $sError
            );
        }
        $iResult = fwrite($rSocket, $sRequest, strlen($sRequest));
		if ($iResult === false || $iResult != strlen($sRequest)) {
            throw new Services_AMEE_Exception(
                'Error sending the AMEE REST API request'
            );
        }
        // Obtain the AMEE REST API response
        $aResponseLines = array();
        $iCounter = 0;
        $aResponseLines[$iCounter] = '';
        $aJSON = array();
        while (!feof($rSocket)) {
            $sLine = fgets($rSocket);
            $aResponseLines[] = $sLine;
            if (preg_match('/^{/', $sLine)) {
                // The line is a JSON response line, store it separately
                $aJSON[] = $sLine;
            }
        }
        fclose($rSocket);
        // Check that the request was authorised
        if (strpos($aResponseLines[0], '401 UNAUTH') !== false){
            // Authorisation failed
			if ($bRepeat){
                // Try once more
                $this->_reconnect();
				return $this->_sendRequest($sPath, $sBody, $bReturnHeaders, false);
			} else {
                // Not going to try once more, raise an Exception
                throw new Services_AMEE_Exception(
                    'The AMEE REST API returned an authorisation failure result.'
                );
            }
		}
        // Update the authorisation time (now + authorisation timeout)
        $this->iAuthExpires = time() + AMEE_API_AUTH_TIMEOUT;
        // Return the AMEE REST API's results
		if($bReturnHeaders) {
			return $aResponseLines;
		} else {
			return $aJSON;
		}
    }

    /**
     * A protected method to determine if a connection to the AMEE REST API
     * already exists or not.
     *
     * @return <boolean> True if a connection to the AMEE REST API exists; false
     *      otherwise.
     */
    protected function _connected()
    {
        // Are we already connected via this object?
		if (!empty($this->sAuthToken)
                && !empty($this->iAuthExpires)
                && $this->iAuthExpires > time()) {
			return true;
		}
        // No connection could be found
        return false;
    }

    /**
     * A protected method to create a new connection to the AMEE REST API.
     *
     * @return <mixed> True if a connection to the AMEE REST API was
     *      successfully created; an Exception object otherwise.
     */
    protected function _connect()
    {
        // Ensure that the required definitions to make a connection are present
        if (!defined('AMEE_API_PROJECT_KEY')) {
            throw new Services_AMEE_Exception(
                'Cannot connect to the AMEE REST API: No project key defined.'
            );
        }
        if (!defined('AMEE_API_PROJECT_PASSWORD')) {
            throw new Services_AMEE_Exception(
                'Cannot connect to the AMEE REST API: No project password defined.'
            );
        }
        if (!defined('AMEE_API_URL')) {
            throw new Services_AMEE_Exception(
                'Cannot connect to the AMEE REST API: No API URL defined.'
            );
        }
        if (!defined('AMEE_API_PORT')) {
            // Assume port 80
            define('AMEE_API_PORT', '80');
        }
        if (!defined('AMEE_API_PORT_SSL')) {
            // Assume port 443
            define('AMEE_API_PORT_SSL', '443');
        }        
        // Prepare the parameters for the AMEE REST API post method
        $sPath = '/auth';
        $aOptions = array(
            'username' => AMEE_API_PROJECT_KEY,
            'password' => AMEE_API_PROJECT_PASSWORD
        );
        // Call the AMEE REST API post method
        try {
            $aResult =  $this->_sendRequest("POST $sPath", http_build_query($aOptions, NULL, '&'), true, false);
        } catch (Exception $oException) {
            throw $oException;
        }
        // Connection was made!
        $bFoundAuth = false;
        foreach ($aResult as $sLine) {
            if (preg_match('/^authToken: (.+)/', $sLine, $aMatches)) {
                $this->sAuthToken = $aMatches[1];
                $bFoundAuth = true;
                break;
            }
        }
        if (!$bFoundAuth) {
            // Oh dear, no authorisation token found, connection wasn't
            // really made!
            throw new Services_AMEE_Exception(
                'Authentication error: No authToken returned by the AMEE REST API.'
            );
        }
        return true;
    }

    /**
     * A protected method to close the current AMEE REST API connection (if one
     * exists) by dropping all current session authentication tokens.
     */
    protected function _disconnect()
    {
        // Unset this object's connection
        unset($this->sAuthToken);
        unset($this->iAuthExpires);
    }

    /**
     * A protected method to close the current AMEE REST API connection (if one
     * exists) and then to reconnect to the AMEE REST API.
     *
     * @return <mixed> True if a connection to the AMEE REST API was
     *      successfully created; an Exception object otherwise.
     */
    protected function _reconnect()
    {
        try {
            $this->_disconnect();
            $this->_connect();
        } catch (Exception $oException) {
            throw $oException;
        }
    }

}

?>
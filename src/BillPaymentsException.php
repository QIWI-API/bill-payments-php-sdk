<?php
/**
 * BillPayments.php
 *
 * @package   Qiwi\Api
 * @author    Yaroslav <yaroslav@wannabe.pro>
 * @copyright 2019 (c) QIWI JSC
 * @license   MIT https://raw.githubusercontent.com/QIWI-API/bill-payments-php-sdk/master/LICENSE
 */

namespace Qiwi\Api;

use Exception;

/**
 * Exception of API request.
 *
 * @property resource $curl The request is get-only.
 */
class BillPaymentsException extends Exception
{
    /**
     * The request.
     *
     * @var resource
     */
    protected $internalCurl;


    /**
     * BillPaymentsException constructor.
     *
     * @param resource|null  $curl     The request.
     * @param string         $message  The error message.
     * @param int            $code     The error code.
     * @param Exception|null $previous The previous error
     */
    public function __construct($curl=null, $message="", $code=0, Exception $previous=null)
    {
        $this->internalCurl = $curl;
        if (true === isset($curl)) {
            if (true === empty($message)) {
                $message = curl_error($curl);
            }

            if ($code === 0) {
                $code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            }
        }

        parent::__construct($message, $code, $previous);

    }//end __construct()


    /**
     * Setter.
     *
     * @param string $name  The property name.
     * @param mixed  $value The property value.
     *
     * @return Curl|null
     *
     * @throws Exception
     */
    public function __set($name, $value)
    {
        switch ($name) {
        case 'curl':
            throw new Exception('Not acceptable property '.$name.'.');
        default:
            throw new Exception('Undefined property '.$name.'.');
        }

    }//end __set()


    /**
     * Getter.
     *
     * @param string $name The property name.
     *
     * @return mixed The property value.
     *
     * @throws Exception Throw on unexpected property get.
     */
    public function __get($name)
    {
        switch ($name) {
        case 'curl':
            return $this->internalCurl;
        default:
            throw new Exception('Undefined property '.$name.'.');
        }

    }//end __get()


    /**
     * Checker.
     *
     * @param string $name The property name.
     *
     * @return bool Property set or not.
     *
     * @throws Exception Throw on unexpected property check
     */
    public function __isset($name)
    {
        switch ($name) {
        case 'curl':
            return isset($this->internalCurl);
        default:
            return false;
        }

    }//end __isset()


}//end class

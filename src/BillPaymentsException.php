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

use Curl\Curl;
use Exception;
use Throwable;

/**
 * Exception of API request.
 *
 * @property Curl $curl The request is get-only.
 */
class BillPaymentsException extends Exception
{
    /**
     * The request.
     *
     * @var Curl
     */
    protected $internalCurl;


    /**
     * BillPaymentsException constructor.
     *
     * @param Curl|null      $curl     The request.
     * @param string         $message  The error message.
     * @param int            $code     The error code.
     * @param Throwable|null $previous The previous error
     */
    public function __construct(Curl $curl=null, $message="", $code=0, Throwable $previous=null)
    {
        $this->internalCurl = $curl;
        if (true === isset($curl)) {
            if (true === empty($message)) {
                //phpcs:disable Squiz.NamingConventions.ValidVariableName -- Because dependency has incompatible coding style.
                $message = $curl->error_message;
                //phpcs:enable Squiz.NamingConventions.ValidVariableName
            }

            if ($code === 0) {
                //phpcs:disable Squiz.NamingConventions.ValidVariableName -- Because dependency has incompatible coding style.
                $code = $curl->error_code;
                //phpcs:enable Squiz.NamingConventions.ValidVariableName
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

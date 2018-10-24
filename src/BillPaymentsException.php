<?php
/**
 * BillPayments.php
 * @copyright Copyright (c) QIWI JSC
 * @license MIT
 */

namespace Qiwi\Api;

use Curl\Curl;
use Exception;
use Throwable;

/**
 * Exception of API request.
 *
 * @property Curl $curl The request is get-only
 */
class BillPaymentsException extends Exception
{
    /** @var Curl The request */
    protected $internalCurl;

    /**
     * BillPaymentsException constructor.
     *
     * @param Curl|null $curl The request
     * @param string $message The error message
     * @param int $code The error code
     * @param Throwable|null $previous The previous error
     */
    public function __construct(Curl $curl = null, $message = "", $code = 0, Throwable $previous = null)
    {
        $this->internalCurl = $curl;
        if (isset($curl)) {
            if (empty($message)) {
                $message = $curl->error_message;
            }
            if ($code === 0) {
                $code = $curl->error_code;
            }
        }
        parent::__construct($message, $code, $previous);
    }

    /**
     * Getter.
     *
     * @param string $name The property name
     * @return mixed The property value
     * @throws Exception Throw on unexpected property get
     */
    public function __get($name)
    {
        if ($name === 'curl') {
            return $this->internalCurl;
        }
        throw new Exception("Undefined property {$name}");
    }

    /**
     * Checker.
     *
     * @param string $name The property name
     * @return bool Property set or not
     * @throws Exception Throw on unexpected property check
     */
    public function __isset($name)
    {
        if ($name === 'curl') {
            return !empty($this->internalCurl);
        }
        throw new Exception("Undefined property {$name}");
    }
}

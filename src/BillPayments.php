<?php
/**
 * QIWI bill payments SDK.
 *
 * @package   Qiwi\Api
 * @author    Yaroslav <yaroslav@wannabe.pro>
 * @copyright 2019 (c) QIWI JSC
 * @license   MIT https://raw.githubusercontent.com/QIWI-API/bill-payments-php-sdk/master/LICENSE
 */

namespace Qiwi\Api;

use Exception;
use ErrorException;
use DateTime;

if (false === defined('CLIENT_NAME')) {
    //phpcs:disable Squiz.Commenting -- Because contingent constant definition.
    /**
     * The client name fingerprint.
     *
     * @const string
     */
    define('CLIENT_NAME', 'php_sdk');
    //phpcs:enable Squiz.Commenting
}

if (false === defined('CLIENT_VERSION')) {
    //phpcs:disable Squiz.Commenting -- Because contingent constant definition.
    /**
     * The client version fingerprint.
     *
     * @const string
     */
    define(
        'CLIENT_VERSION',
        @json_decode(
            file_get_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'composer.json'),
            true
        )['version']
    );
    //phpcs:enable Squiz.Commenting
}

/**
 * Class for rest v3.
 *
 * @see https://developer.qiwi.com/en/bill-payments API Documentation.
 *
 * @property string   $key  The secret key is set-only.
 * @property resource $curl The request is get-only.
 */
class BillPayments
{
    /**
     * The default separator.
     *
     * @const string
     */
    const VALUE_SEPARATOR = '|';

    /**
     * The default hash algorithm.
     *
     * @const string
     */
    const DEFAULT_ALGORITHM = 'sha256';

    /**
     * The API datetime format.
     *
     * @const string
     */
    const DATETIME_FORMAT = 'Y-m-d\TH:i:sP';

    /**
     * The API get method.
     *
     * @const string
     */
    const GET = 'GET';

    /**
     * The API post method.
     *
     * @const string
     */
    const POST = 'POST';

    /**
     * The API put method.
     *
     * @const string
     */
    const PUT = 'PUT';

    /**
     * The URL to bill form.
     *
     * @const string
     */
    const CREATE_URI = 'https://oplata.qiwi.com/create';

    /**
     * The URL to API.
     *
     * @const string
     */
    const BILLS_URI = 'https://api.qiwi.com/partner/bill/v1/bills/';

    /**
     * The secret key.
     *
     * @var string
     */
    protected $secretKey;

    /**
     * The request.
     *
     * @var resource
     */
    protected $internalCurl;


    /**
     * BillPayments constructor.
     *
     * @param string $key     The secret key.
     * @param array  $options The dictionary of request options.
     *
     * @throws ErrorException Throw on Curl extension missed.
     */
    public function __construct($key='', array $options=[])
    {
        $this->secretKey    = (string) $key;
        $this->internalCurl = curl_init();
        curl_setopt_array(
            $this->internalCurl,
            ($options + [
                CURLOPT_USERAGENT => CLIENT_NAME.'-'.CLIENT_VERSION,
            ])
        );

    }//end __construct()


    /**
     * Setter.
     *
     * @param string $name  The property name.
     * @param mixed  $value The property value.
     *
     * @return void
     *
     * @throws Exception Throw on unexpected property set.
     */
    public function __set($name, $value)
    {
        switch ($name) {
        case 'key':
            $this->secretKey = (string) $value;
            break;
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
        case 'key':
            throw new Exception('Not acceptable property '.$name.'.');
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
     * @throws Exception Throw on unexpected property check.
     */
    public function __isset($name)
    {
        switch ($name) {
        case 'key':
            return !empty($this->secretKey);
        case 'curl':
            return !empty($this->internalCurl);
        default:
            return false;
        }

    }//end __isset()


    /**
     * Checks notification data signature.
     *
     * @param string       $signature        The signature.
     * @param object|array $notificationBody The notification body.
     * @param string       $merchantSecret   The merchant key for validating signature.
     *
     * @return bool Signature is valid or not.
     */
    public function checkNotificationSignature($signature, array $notificationBody, $merchantSecret)
    {
        // Preset required fields.
        $notificationBody = array_replace_recursive(
            [
                'bill' => [
                    'billId' => null,
                    'amount' => [
                        'value'    => null,
                        'currency' => null,
                    ],
                    'siteId' => null,
                    'status' => ['value' => null],
                ],
            ],
            $notificationBody
        );

        $processedNotificationData = [
            'billId'          => (string) $notificationBody['bill']['billId'],
            'amount.value'    => $this->normalizeAmount($notificationBody['bill']['amount']['value']),
            'amount.currency' => (string) $notificationBody['bill']['amount']['currency'],
            'siteId'          => (string) $notificationBody['bill']['siteId'],
            'status'          => (string) $notificationBody['bill']['status']['value'],
        ];
        ksort($processedNotificationData);
        $processedNotificationDataKeys = join(self::VALUE_SEPARATOR, $processedNotificationData);
        $hash = hash_hmac(self::DEFAULT_ALGORITHM, $processedNotificationDataKeys, $merchantSecret);

        return $hash === $signature;

    }//end checkNotificationSignature()


    /**
     * Generate lifetime in format.
     *
     * @param int $days Days of lifetime.
     *
     * @return string Lifetime in ISO8601.
     *
     * @throws Exception
     */
    public function getLifetimeByDay($days=45)
    {
        $dateTime = new DateTime();
        return $this->normalizeDate($dateTime->modify('+'.max(1, $days).' days'));

    }//end getLifetimeByDay()


    /**
     * Normalize date in api format.
     *
     * @param DateTime $date Date object.
     *
     * @return string Date in api format.
     */
    public function normalizeDate($date)
    {
        return $date->format(self::DATETIME_FORMAT);

    }//end normalizeDate()


    /**
     * Normalize amount.
     *
     * @param string|float|int $amount The value.
     *
     * @return string The API value.
     */
    public function normalizeAmount($amount=0)
    {
        return number_format(round(floatval($amount), 2, PHP_ROUND_HALF_DOWN), 2, '.', '');

    }//end normalizeAmount()


    /**
     * Generate id.
     *
     * @return string Return uuid v4.
     *
     * @throws Exception Trow on uuid4 algorithm break.
     */
    public function generateId()
    {
        $bytes = '';
        for ($i = 1; $i <= 16; $i++) {
            $bytes .= chr(mt_rand(0, 255));
        }

        $hash = bin2hex($bytes);

        return sprintf(
            '%08s-%04s-%04s-%02s%02s-%012s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            str_pad(dechex(hexdec(substr($hash, 12, 4)) & 0x0fff & ~(0xf000) | 0x4000), 4, '0', STR_PAD_LEFT),
            str_pad(dechex(hexdec(substr($hash, 16, 2)) & 0x3f & ~(0xc0) | 0x80), 2, '0', STR_PAD_LEFT),
            substr($hash, 18, 2),
            substr($hash, 20, 12)
        );

    }//end generateId()


    /**
     * Get pay URL witch success URL param.
     *
     * @param array  $bill       The bill data:
     *                           + payUrl {string} Payment URL.
     * @param string $successUrl The success URL.
     *
     * @return string
     */
    public function getPayUrl(array $bill, $successUrl)
    {
        // Preset required fields.
        $bill = array_replace(
            ['payUrl' => null],
            $bill
        );

        $payUrl = parse_url((string) $bill['payUrl']);
        if (true === array_key_exists('query', $payUrl)) {
            parse_str($payUrl['query'], $query);
            $query['successUrl'] = $successUrl;
        } else {
            $query = ['successUrl' => $successUrl];
        }

        $payUrl['query'] = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return $this->buildUrl($payUrl);

    }//end getPayUrl()


    /**
     * Creating checkout link.
     *
     * @param array $params The parameters:
     *                      + billId     {string|number} - The bill identifier;
     *                      + publicKey  {string}        - The publicKey;
     *                      + amount     {string|number} - The amount;
     *                      + successUrl {string}        - The success url.
     *
     * @return string Return result
     */
    public function createPaymentForm(array $params)
    {
        $params = array_replace_recursive(
            [
                'billId'       => null,
                'publicKey'    => null,
                'amount'       => null,
                'successUrl'   => null,
                'customFields' => [],
            ],
            $params
        );

        $params['amount'] = $this->normalizeAmount($params['amount']);
        $params['customFields']['apiClient']        = CLIENT_NAME;
        $params['customFields']['apiClientVersion'] = CLIENT_VERSION;

        return self::CREATE_URI.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    }//end createPaymentForm()


    /**
     * Creating bill.
     *
     * @param string|number $billId The bill identifier.
     * @param array         $params The parameters:
     *                              + amount             {string|number} The amount;
     *                              + currency           {string}        The currency;
     *                              + comment            {string}        The bill comment;
     *                              + expirationDateTime {string}        The bill expiration datetime (ISOstring);
     *                              + phone              {string}        The phone;
     *                              + email              {string}        The email;
     *                              + account            {string}        The account;
     *                              + successUrl         {string}        The success url;
     *                              + customFields       {array}         The bill custom fields.
     *
     * @return array Return result.
     *
     * @throws BillPaymentsException Throw on API return invalid response.
     */
    public function createBill($billId, array $params)
    {
        $params = array_replace_recursive(
            [
                'amount'             => null,
                'currency'           => null,
                'comment'            => null,
                'expirationDateTime' => null,
                'phone'              => null,
                'email'              => null,
                'account'            => null,
                'successUrl'         => null,
                'customFields'       => [
                    'apiClient'        => CLIENT_NAME,
                    'apiClientVersion' => CLIENT_VERSION,
                ],
            ],
            $params
        );

        $bill = $this->requestBuilder(
            $billId,
            self::PUT,
            array_filter(
                [
                    'amount'             => array_filter(
                        [
                            'currency' => (string) $params['currency'],
                            'value'    => $this->normalizeAmount($params['amount']),
                        ]
                    ),
                    'comment'            => (string) $params['comment'],
                    'expirationDateTime' => (string) $params['expirationDateTime'],
                    'customer'           => array_filter(
                        [
                            'phone'   => (string) $params['phone'],
                            'email'   => (string) $params['email'],
                            'account' => (string) $params['account'],
                        ]
                    ),
                    'customFields'       => array_filter($params['customFields']),
                ]
            )
        );
        if (false === empty($bill['payUrl']) && false === empty($params['successUrl'])) {
            $bill['payUrl'] = $this->getPayUrl($bill, $params['successUrl']);
        }

        return $bill;

    }//end createBill()


    /**
     * Getting bill info.
     *
     * @param string|number $billId The bill identifier.
     *
     * @return array Return result.
     *
     * @throws BillPaymentsException Throw on API return invalid response.
     */
    public function getBillInfo($billId)
    {
        return $this->requestBuilder($billId);

    }//end getBillInfo()


    /**
     * Cancelling unpaid bill.
     *
     * @param string|number $billId The bill identifier.
     *
     * @return array Return result.
     *
     * @throws BillPaymentsException Throw on API return invalid response.
     */
    public function cancelBill($billId)
    {
        return $this->requestBuilder($billId.'/reject', self::POST);

    }//end cancelBill()


    /**
     * Refund paid bill.
     * Method is not available for individuals.
     *
     * @param string|number $billId   The bill identifier.
     * @param string|number $refundId The refund identifier.
     * @param string|number $amount   The amount.
     * @param string        $currency The currency.
     *
     * @return array|bool Return result.
     *
     * @throws BillPaymentsException Throw on API return invalid response.
     */
    public function refund($billId, $refundId, $amount='0', $currency='RUB')
    {
        return $this->requestBuilder(
            $billId.'/refunds/'.$refundId,
            self::PUT,
            [
                'amount' => [
                    'currency' => (string) $currency,
                    'value'    => $this->normalizeAmount($amount),
                ],
            ]
        );

    }//end refund()


    /**
     * Getting refund info.
     * Method is not available for individuals.
     *
     * @param string|number $billId   The bill identifier.
     * @param string|number $refundId The refund identifier.
     *
     * @return array Return result.
     *
     * @throws BillPaymentsException  Throw on API return invalid response.
     */
    public function getRefundInfo($billId, $refundId)
    {
        return $this->requestBuilder($billId.'/refunds/'.$refundId);

    }//end getRefundInfo()


    /**
     * Build request.
     *
     * @param string $uri    The url.
     * @param string $method The method.
     * @param array  $body   The body.
     *
     * @return bool|array Return response.
     *
     * @throws Exception Throw on unsupported $method use.
     * @throws BillPaymentsException Throw on API return invalid response.
     */
    protected function requestBuilder($uri, $method=self::GET, array $body=[])
    {
        $curl    = curl_copy_handle($this->internalCurl);
        $url     = self::BILLS_URI.$uri;
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer '.$this->secretKey,
        ];
        if (true !== empty($body) && self::GET !== $method) {
            $body    = json_encode($body, JSON_UNESCAPED_UNICODE);
            $headers = array_merge(
                $headers,
                [
                    'Content-Type: application/json;charset=UTF-8',
                    'Content-Length: '.strlen($body),
                ]
            );
        }

        curl_setopt_array(
            $curl,
            [
                CURLOPT_URL            => $url,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_RETURNTRANSFER => 1,
            ]
        );
        $response = curl_exec($curl);

        if (false === $response) {
            throw new BillPaymentsException($curl, curl_error($curl), curl_getinfo($curl, CURLINFO_RESPONSE_CODE));
        }

        if (false === empty($response)) {
            $json = json_decode($response, true);
            if (null === $json) {
                throw new BillPaymentsException($curl, json_last_error_msg(), json_last_error());
            }

            if (true === isset($json['errorCode'])) {
                if (true === isset($json['description'])) {
                    throw new BillPaymentsException($curl, $json['description']);
                }

                throw new BillPaymentsException($curl, $json['errorCode']);
            }

            return $json;
        }

        return true;

    }//end requestBuilder()


    /**
     * Build URL.
     *
     * @param array $parsedUrl The parsed URL.
     *
     * @return string
     */
    protected function buildUrl(array $parsedUrl)
    {
        if (true === isset($parsedUrl['scheme'])) {
            $scheme = $parsedUrl['scheme'].'://';
        } else {
            $scheme = '';
        }

        if (true === isset($parsedUrl['host'])) {
            $host = $parsedUrl['host'];
        } else {
            $host = '';
        }

        if (true === isset($parsedUrl['port'])) {
            $port = ':'.$parsedUrl['port'];
        } else {
            $port = '';
        }

        if (true === isset($parsedUrl['user'])) {
            $user = (string) $parsedUrl['user'];
        } else {
            $user = '';
        }

        if (true === isset($parsedUrl['pass'])) {
            $pass = ':'.$parsedUrl['pass'];
        } else {
            $pass = '';
        }

        if (false === empty($user) || false === empty($pass)) {
            $host = '@'.$host;
        }

        if (true === isset($parsedUrl['path'])) {
            $path = (string) $parsedUrl['path'];
        } else {
            $path = '';
        }

        if (true === isset($parsedUrl['query'])) {
            $query = '?'.$parsedUrl['query'];
        } else {
            $query = '';
        }

        if (true === isset($parsedUrl['fragment'])) {
            $fragment = '#'.$parsedUrl['fragment'];
        } else {
            $fragment = '';
        }

        return $scheme.$user.$pass.$host.$port.$path.$query.$fragment;

    }//end buildUrl()


}//end class

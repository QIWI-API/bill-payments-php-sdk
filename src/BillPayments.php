<?php
/**
 * BillPayments.php
 * @copyright Copyright (c) QIWI JSC
 * @license MIT
 */

namespace Qiwi\Api;

use Curl\Curl;
use Ramsey\Uuid\Uuid;
use Exception;
use DateTime;

/** @var string CLIENT_NAME The client name */
if (!defined('CLIENT_NAME')) {
    define('CLIENT_NAME', 'php_sdk');
}

/** @var string CLIENT_VERSION The client version */
if (!defined('CLIENT_VERSION')) {
    define('CLIENT_VERSION', @json_decode(
        file_get_contents(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'composer.json'),
        true
    )['version']);
}

/**
 * Class for rest v 3.
 * @see https://developer.qiwi.com/en/bill-payments
 *
 * @property string $key The secret key is set-only
 * @property Curl $curl The request is get-only
 */
class BillPayments
{
    /** @var string The default separator */
    const VALUE_SEPARATOR = '|';

    /** @var string The default hash algorithm */
    const DEFAULT_ALGORITHM = 'sha256';

    /** @var string The default hash encoding */
    const DEFAULT_ENCODING = 'hex';

    /** @var string The API get method */
    const GET = 'GET';

    /** @var string The API post method */
    const POST = 'POST';

    /** @var string The API put method */
    const PUT = 'PUT';

    /** @var string The URL to bill form */
    const CREATE_URI = 'https://oplata.qiwi.com/create';

    /** @var string The URL to API */
    const BILLS_URI = 'https://api.qiwi.com/partner/bill/v1/bills/';


    /** @var string The secret key */
    protected $secretKey;

    /** @var Curl The request */
    protected $internalCurl;

    /** @var array The dictionary of request options */
    protected $options;


    /**
     * BillPayments constructor.
     *
     * @param string $key The secret key
     * @param array $options The dictionary of request options
     * @throws \ErrorException Throw on Curl extension missed
     */
    public function __construct($key = '', array $options = [])
    {
        $this->secretKey = (string) $key;
        $this->options = $options;
        $this->internalCurl = new Curl();
    }

    /**
     * Setter.
     *
     * @param string $name The property name
     * @param mixed $value The property value
     * @throws Exception Throw on unexpected property set
     */
    public function __set($name, $value)
    {
        switch ($name) {
            case 'key':
                $this->secretKey = (string)$value;
                break;
            case 'curl':
                throw new Exception("Not acceptable property {$name}");
            default:
                throw new Exception("Undefined property {$name}");
        }
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
		switch ($name) {
			case 'key':
				throw new Exception("Not acceptable property {$name}");
			case 'curl':
				return $this->internalCurl;
			default:
				throw new Exception("Undefined property {$name}");
		}
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
		switch ($name) {
			case 'key':
				return !empty($this->secretKey);
			case 'curl':
				return !empty($this->internalCurl);
			default:
				throw new Exception("Undefined property {$name}");
		}
    }


    /**
     * Checks notification data signature.
     *
     * @param string $signature The signature
     * @param object|array $notificationBody The notification body
     * @param string $merchantSecret The merchant key for validating signature
     * @return bool Signature is valid or not
     */
    public function checkNotificationSignature($signature, $notificationBody, $merchantSecret)
    {
        $processedNotificationData = [
            'billId' => (string) isset($notificationBody['bill']['billId'])
                ? $notificationBody['bill']['billId']
                : '',
            'amount.value' => (string) isset($notificationBody['bill']['amount']['value'])
                ? $this->normalizeAmount($notificationBody['bill']['amount']['value'])
                : 0,
            'amount.currency' => (string) isset($notificationBody['bill']['amount']['currency'])
                ? $notificationBody['bill']['amount']['currency']
                : '',
            'siteId' => (string) isset($notificationBody['bill']['siteId'])
                ? $notificationBody['bill']['siteId']
                : '',
            'status' => (string) isset($notificationBody['bill']['status']['value'])
                ? $notificationBody['bill']['status']['value']
                : ''
        ];
        ksort($processedNotificationData);
        $processedNotificationDataKeys = join(self::VALUE_SEPARATOR, $processedNotificationData);
        $hash = hash_hmac(self::DEFAULT_ALGORITHM, $processedNotificationDataKeys, $merchantSecret);
        return $hash === $signature;
    }

    /**
     * Generate lifetime in format.
     *
     * @param int $days Days of lifetime
     * @return string Lifetime in ISO8601
     */
    public function getLifetimeByDay($days = 45)
    {
        $date = new DateTime('NOW');
        return $this->normalizeDate($date->modify("+{$days} days"));
    }

    /**
     * Normalize date in api format.
     *
     * @param DateTime $date Date object
     * @return string Date in api format
     */
    public function normalizeDate($date)
    {
        return $date->format(DateTime::W3C);
    }

    /**
     * Normalize amount.
     *
     * @param string|float|int $amount The value
     * @return string The API value
     */
    public function normalizeAmount($amount = 0)
    {
        return number_format(round(floatval($amount), 2, PHP_ROUND_HALF_DOWN), 2, '.', '');
    }

    /**
     * Generate id.
     *
     * @return string Return uuid v4
     * @throws Exception Trow on uuid4 algorithm break
     */
    public function generateId()
    {
        return Uuid::uuid4()->toString();
    }

    /**
     * Get pay URL witch success URL param.
     *
     * @param array $bill The bill
     * @param string $successUrl The success URL
     * @return string
     */
    public function getPayUrl($bill, $successUrl)
    {
        $payUrl = parse_url($bill['payUrl']);
        if (array_key_exists('query', $payUrl)) {
            parse_str($payUrl['query'], $query);
            $query['successUrl'] = $successUrl;
        } else {
            $query = [
                'successUrl' => $successUrl
            ];
        }
        $payUrl['query'] = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        return $this->buildUrl($payUrl);
    }

    /**
     * Creating checkout link.
     *
     * @param array $params The parameters
     *   ['billId']     string|number The bill identifier
     *   ['publicKey']  string        The publicKey
     *   ['amount']     string|number The amount
     *   ['successUrl'] string        The success url
     * @return string Return result
     */
    public function createPaymentForm($params)
    {
        $params['amount'] = isset($params['amount']) ? $this->normalizeAmount($params['amount']) : null;
        $params['customFields'] = isset($params['customFields']) ? $params['customFields'] : [];
        $params['customFields']['apiClient'] = CLIENT_NAME;
        $params['customFields']['apiClientVersion'] = CLIENT_VERSION;
        return self::CREATE_URI . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Creating bill.
     *
     * @param string|number $billId The bill identifier
     * @param array $params The parameters
     *   ['amount']             string|number The amount
     *   ['currency']           string        The currency
     *   ['comment']            string        The bill comment
     *   ['expirationDateTime'] string        The bill expiration datetime (ISOstring)
     *   ['extra']              array         The bill custom fields
     *   ['extra']              array         The bill custom fields (deprecated, will be removed soon)
     *   ['phone']              array         The phone
     *   ['email']              string        The email
     *   ['account']            string        The account
     *   ['successUrl']         string        The success url
     * @return array Return result
     * @throws BillPaymentsException Throw on API return invalid response
     */
    public function createBill($billId, $params)
    {
        $bill = $this->requestBuilder($billId, self::PUT, array_filter([
            'amount' => array_filter([
                'currency' => isset($params['currency']) ? $params['currency'] : null,
                'value' => isset($params['amount']) ? $this->normalizeAmount($params['amount']) : null
            ]),
            'comment' => isset($params['comment']) ? $params['comment'] : null,
            'expirationDateTime' => isset($params['expirationDateTime']) ? $params['expirationDateTime'] : null,
            'customer' => array_filter([
                'phone' => isset($params['phone']) ? $params['phone'] : null,
                'email' => isset($params['email']) ? $params['email'] : null,
                'account' => isset($params['account']) ? $params['account'] : null,
            ]),
            'customFields' => array_merge_recursive(
                isset($params['extra']) ? $params['extra'] : [], // extra is deprecated, will be removed in next minor update
                isset($params['customFields']) ? $params['customFields'] : [],
                [
                    'apiClient' => CLIENT_NAME,
                    'apiClientVersion' => CLIENT_VERSION,
                ]
            ),
        ]));
        if (!empty($bill['payUrl']) && !empty($params['successUrl'])) {
            $bill['payUrl'] = $this->getPayUrl($bill, $params['successUrl']);
        }
        return $bill;
    }

    /**
     * Getting bill info.
     *
     * @param string|number $billId The bill identifier
     * @return array Return result
     * @throws BillPaymentsException Throw on API return invalid response
     */
    public function getBillInfo($billId)
    {
        return $this->requestBuilder($billId);
    }

    /**
     * Cancelling unpaid bill.
     *
     * @param string|number $billId The bill identifier
     * @return array Return result
     * @throws BillPaymentsException Throw on API return invalid response
     */
    public function cancelBill($billId)
    {
        return $this->requestBuilder("${billId}/reject", self::POST);
    }

    /**
     * Refund paid bill.
     *
     * @param string|number $billId The bill identifier
     * @param string|number $refundId The refund identifier
     * @param string|number $amount The amount
     * @param string $currency The currency
     * @return array|bool Return result
     * @throws BillPaymentsException  Throw on API return invalid response
     */
    public function refund($billId, $refundId, $amount = '0', $currency = 'RUB')
    {
        return $this->requestBuilder("${billId}/refunds/${refundId}", self::PUT, [
            'amount' => [
                'currency' => $currency,
                'value' => $this->normalizeAmount($amount)
            ]
        ]);
    }

    /**
     * Getting refund info.
     *
     * @param string|number $billId The bill identifier
     * @param string|number $refundId The refund identifier
     * @return array Return result
     * @throws BillPaymentsException  Throw on API return invalid response
     */
    public function getRefundInfo($billId, $refundId)
    {
        return $this->requestBuilder("${billId}/refunds/${refundId}");
    }


    /**
     * Build request.
     *
     * @param string $uri The url
     * @param string $method The method
     * @param array $body The body
     * @return bool|array Return response
     * @throws Exception Throw on unsupported $method use
     * @throws BillPaymentsException Throw on API return invalid response
     */
    protected function requestBuilder($uri, $method = self::GET, array $body = array())
    {
        $this->internalCurl->reset();
        foreach ($this->options as $option => $value) {
            $this->internalCurl->setOpt($option, $value);
        }
        $url = self::BILLS_URI . $uri;
        $this->internalCurl->setHeader('Accept', 'application/json');
        $this->internalCurl->setHeader('Authorization', "Bearer {$this->secretKey}");
        switch ($method) {
            case self::GET:
                $this->internalCurl->get($url);
                break;
            case self::POST:
                $this->internalCurl->setHeader('Content-Type', 'application/json;charset=UTF-8');
                $this->internalCurl->post($url, json_encode($body, JSON_UNESCAPED_UNICODE));
                break;
            case self::PUT:
                $this->internalCurl->setHeader('Content-Type', 'application/json;charset=UTF-8');
                $this->internalCurl->put($url, json_encode($body, JSON_UNESCAPED_UNICODE), true);
                break;
            default:
                throw new Exception("Not supported method {$method}.");
        }
        if ($this->internalCurl->error) {
            throw new BillPaymentsException(clone $this->internalCurl);
        }
        return empty($this->internalCurl->response) ? true : json_decode($this->internalCurl->response, true);
    }

    /**
     * Build URL.
     *
     * @param array $parsedUrl The parsed URL
     * @return string
     */
    protected function buildUrl($parsedUrl)
    {
        $scheme   = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] . '://' : '';
        $host     = isset($parsedUrl['host']) ? $parsedUrl['host'] : '';
        $port     = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
        $user     = isset($parsedUrl['user']) ? $parsedUrl['user'] : '';
        $pass     = isset($parsedUrl['pass']) ? ':' . $parsedUrl['pass']  : '';
        $pass     = ($user || $pass) ? "$pass@" : '';
        $path     = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';
        $query    = isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '';
        $fragment = isset($parsedUrl['fragment']) ? '#' . $parsedUrl['fragment'] : '';
        return $scheme . $user . $pass . $host . $port . $path . $query . $fragment;
    }
}

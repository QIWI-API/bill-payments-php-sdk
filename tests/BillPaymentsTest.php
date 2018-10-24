<?php
/**
 * BillPaymentsTest.php
 * @copyright Copyright (c) QIWI JSC
 * @license MIT
 */

namespace Qiwi\Api;

use PHPUnit\Framework\TestCase;

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
 * Class BillPaymentsTest
 * @package Qiwi\Api
 */
class BillPaymentsTest extends TestCase
{
    /** @var array User test configuration */
    protected $config = [ // will be load from config.php if exists
        'merchantPublicKey' => '',
        'merchantSecretKey' => '',
        'billIdForGetRefundInfoTest' => '',
        'billRefundIdForGetRefundInfoTest' => '',
        'billIdForRefundTest' => ''
    ];

    /** @var BillPayments Tests target */
    protected $billPayments;

    /** @var string Generated billId */
    protected $billId;

    /** @var array Example fields for bills */
    protected $fields = [
        'amount' => 200.345,
        'currency' => 'RUB',
        'expirationDateTime' => '', // will be generate
        'providerName' => 'Test',
        'comment' => 'test',
        'phone' => '79999999999',
        'email' => 'test@test.ru',
        'account' => 'user uid on your side',
        'customFields' => [
            'city' => 'Москва',
            'street' => 'Арбат'
        ],
        'successUrl' => 'http://test.ru/'
    ];


    /**
     * Set up tests
     *
     * @throws \Exception
     * @throws \ErrorException
     */
    public function setUp()
    {
        parent::setUp();
        // set up valid root CA certificate
        $options = [];
        if (is_file(__DIR__ . DIRECTORY_SEPARATOR . 'cacert.pem')) {
            $options[CURLOPT_SSL_VERIFYPEER] = true;
            $options[CURLOPT_SSL_VERIFYHOST] = 2;
            $options[CURLOPT_CAINFO] = __DIR__ . DIRECTORY_SEPARATOR . 'cacert.pem';
        }
        // set up user config
        if (is_file(__DIR__ . DIRECTORY_SEPARATOR . 'config.php')) {
            $this->config = require __DIR__ . DIRECTORY_SEPARATOR . 'config.php';
        }
        // init target
        $this->billPayments = new BillPayments($this->config['merchantSecretKey'], $options);
        // Get UUID
        $this->billId = $this->billPayments->generateId();
        // Get expirationDateTime
        $this->fields['expirationDateTime'] = $this->billPayments->getLifetimeByDay();
    }

    /**
     * qiwi api v4
     */
    public function testCreatePaymentForm()
    {
        $uri = BillPayments::CREATE_URI;
        $amount_string = '200.34';
        $amount_number = 200.345;
        $query = http_build_query([
            'publicKey' => $this->config['merchantPublicKey'],
            'amount' => $amount_string,
            'billId' => $this->billId,
            'successUrl' => $this->fields['successUrl'],
            'customFields' => [
                'apiClient' => CLIENT_NAME,
                'apiClientVersion' => CLIENT_VERSION
            ]
        ], '', '&', PHP_QUERY_RFC3986);
        $testLink = "{$uri}?{$query}";
        $result = $this->billPayments->createPaymentForm([
            'publicKey' => $this->config['merchantPublicKey'],
            'amount' => $amount_number,
            'billId' => $this->billId,
            'successUrl' => $this->fields['successUrl']
        ]);
        $this->assertEquals($testLink, $result, 'creates payment form');
    }

    /**
     * util
     */
    public function testCheckNotificationSignature()
    {
        $merchantSecret = 'test-merchant-secret-for-signature-check';
        $notificationData = [
            'bill' => [
                'siteId' => 'test',
                'billId' => 'test_bill',
                'amount' => [
                    'value' => 1,
                    'currency' => 'RUB'
                ],
                'status' => [
                    'value' => 'PAID'
                ]
            ]
        ];
        $this->assertFalse($this->billPayments->checkNotificationSignature(
            'foo',
            $notificationData,
            $merchantSecret
        ), 'should return false on wrong signature');
        $this->assertTrue($this->billPayments->checkNotificationSignature(
            '07e0ebb10916d97760c196034105d010607a6c6b7d72bfa1c3451448ac484a3b',
            $notificationData,
            $merchantSecret
        ), 'should return true on valid signature');
    }

    /**
     * request - bill create
     * @throws BillPaymentsException
     */
    public function subTestCreateBill()
    {
        $bill = $this->billPayments->createBill(
            $this->billId,
            $this->fields
        );
        $testPayUrlQuery = http_build_query(['successUrl' => $this->fields['successUrl']], '', '&', PHP_QUERY_RFC3986);
        $this->assertTrue(is_array($bill) && strpos($bill['payUrl'], $testPayUrlQuery) !== false, 'create bill');
    }

    /**
     * request - bill info
     * @throws BillPaymentsException
     */
    public function subTestGetBillInfo()
    {
        $this->subTestCreateBill();
        $bill = $this->billPayments->getBillInfo($this->billId);
        $testFields = [
            'customFields' => [
                'apiClient' => CLIENT_NAME,
                'apiClientVersion' => CLIENT_VERSION
            ]
        ];
        $this->assertArraySubset($testFields, $bill, 'returns valid bill info');
    }

    /**
     * request - bill cancel
     * @throws BillPaymentsException
     */
    public function subTestCancelBill()
    {
        $bill = $this->billPayments->cancelBill($this->billId);
        $this->assertTrue(is_array($bill), 'cancel unpaid bill');
    }

    /**
     * requests life cycle
     * @throws BillPaymentsException
     */
    public function testRequests()
    {
        $this->subTestCreateBill();
        $this->subTestGetBillInfo();
        $this->subTestCancelBill();
    }

    /**
     * request - refund create
     * @throws BillPaymentsException
     */
    public function testRefund()
    {
        $billRefund = $this->billPayments->refund(
            $this->config['billIdForRefundTest'],
            microtime(),
            '0.01',
            'RUB'
        );
        $this->assertTrue(is_array($billRefund), 'makes refund');
    }

    /**
     * request - refund info
     * @throws BillPaymentsException
     */
    public function testGetRefundInfo()
    {
        $billRefund = $this->billPayments->getRefundInfo(
            $this->config['billIdForGetRefundInfoTest'],
            $this->config['billRefundIdForGetRefundInfoTest']
        );
        $this->assertTrue(is_array($billRefund), 'gets refund info');
    }
}

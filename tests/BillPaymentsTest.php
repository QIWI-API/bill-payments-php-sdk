<?php
/**
 * BillPaymentsTest.php
 * @copyright Copyright (c) QIWI JSC
 * @license MIT
 */

namespace Qiwi\Api;

use PHPUnit\Framework\TestCase;

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
        'comment' => 'test'
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
        $testLink = "{$uri}?publicKey={$this->config['merchantPublicKey']}&amount={$amount_string}&billId={$this->billId}";
        $result = $this->billPayments->createPaymentForm([
            'publicKey' => $this->config['merchantPublicKey'],
            'amount' => $amount_number,
            'billId' => $this->billId
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
    public function testCreateBill()
    {
        $bill = $this->billPayments->createBill(
            $this->billId,
            $this->fields
        );
        $this->assertTrue(is_array($bill),'create bill');
    }

    /**
     * request - bill info
     * @throws BillPaymentsException
     */
    public function testGetBillInfo()
    {
        $this->testCreateBill();
        $bill = $this->billPayments->getBillInfo($this->billId);
        $this->assertTrue(is_array($bill),'returns valid bill info');
    }

    /**
     * request - bill cancel
     * @throws BillPaymentsException
     */
    public function testCancelBill()
    {
        $this->testCreateBill();
        $bill = $this->billPayments->cancelBill($this->billId);
        $this->assertTrue(is_array($bill),'cancel unpaid bill');
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
        $this->assertTrue(is_array($billRefund),'makes refund');
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
        $this->assertTrue(is_array($billRefund),'gets refund info');
    }
}

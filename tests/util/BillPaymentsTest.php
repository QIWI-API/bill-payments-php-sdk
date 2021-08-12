<?php

/**
 * BillPaymentsTest.php
 *
 * @author    Yaroslav <yaroslav@wannabe.pro>
 * @copyright 2019 (c) QIWI JSC
 * @license   MIT https://raw.githubusercontent.com/QIWI-API/bill-payments-php-sdk/master/LICENSE
 */

namespace Qiwi\Api\Util;

use Exception;
use phpmock\phpunit\PHPMock;
use Qiwi\Api\BillPaymentsException;
use Qiwi\Api\TestCase;
use Qiwi\Api\BillPayments;

/**
 * Util test case without real API.
 * No needed configure.
 *
 * @package Qiwi\Api\BillPaymentsTest
 */
class BillPaymentsTest extends TestCase
{
    use PHPMock;

    /**
     * CURL test instance.
     *
     * @var false|resource
     */
    protected $curl = false;


    /**
     * Setup CURL functions mock.
     *
     * @param array        $options Expected CURL options.
     * @param false|string $result  Result to CURL execute.
     *
     * @return void
     */
    protected function setMockResponse(array $options, $result)
    {
        $this->curl = curl_init();
        $this->getFunctionMock('Qiwi\Api', 'curl_copy_handle')->expects($this->once())->willReturnCallback(
            function ($curl) use ($options) {
                $this->assertEquals($this->billPayments->curl, $curl, 'Copy original CURL handler');
                return $this->curl;
            }
        );
        $this->getFunctionMock('Qiwi\Api', 'curl_setopt_array')->expects($this->once())->willReturnCallback(
            function ($curl, $argument) use ($options) {
                $this->assertEquals($this->curl, $curl, 'Use copy of original CURL handler');
                $this->assertArraySubset($options, $argument, 'Receive CURL options set');
            }
        );
        $this->getFunctionMock('Qiwi\Api', 'curl_exec')->expects($this->once())->willReturnCallback(
            function ($curl) use ($result) {
                $this->assertEquals($this->curl, $curl, 'Use copy of original CURL handler');
                return $result;
            }
        );

    }//end setMockResponse()


    /**
     * Setup CURL functions mock witch error result.
     *
     * @param array  $options Expected CURL options.
     * @param string $error   Error message.
     *
     * @return void
     */
    protected function setMockError(array $options, $error)
    {
        $this->setMockResponse($options, false);
        $info = [
            CURLINFO_RESPONSE_CODE => 500,
            CURLOPT_HTTPHEADER     => [],
        ];
        $this->getFunctionMock('Qiwi\Api', 'curl_error')->expects($this->once())->willReturnCallback(
            function ($curl) use ($error) {
                $this->assertEquals($this->curl, $curl, 'Use copy of original CURL handler');
                return $error;
            }
        );
        $this->getFunctionMock('Qiwi\Api', 'curl_getinfo')->expects($this->atLeastOnce())->willReturnCallback(
            function ($curl, $name) use ($info) {
                $this->assertEquals($this->curl, $curl, 'Use copy of original CURL handler');
                $this->assertArrayHasKey($name, $info, 'Get CURL info param');
                return $info[$name];
            }
        );

    }//end setMockError()


    /**
     * Request exception.
     *
     * @return void
     *
     * @throws BillPaymentsException
     */
    public function testRequestException()
    {
        $this->setMockError(
            [
                CURLOPT_URL           => BillPayments::BILLS_URI.'test bill ID',
                CURLOPT_CUSTOMREQUEST => BillPayments::GET,
            ],
            'test CURL error'
        );
        $this->setException(BillPaymentsException::class, 'test CURL error', 500);
        $this->billPayments->getBillInfo('test bill ID');

    }//end testRequestException()


    /**
     * Requests live cycle 1 - create bill.
     *
     * @see https://developer.qiwi.com/ru/bill-payments/#create
     *
     * @return void
     *
     * @throws BillPaymentsException
     */
    public function testCreateBill()
    {
        $testFields = [
            'amount'       => [ 'value' => '1.00' ],
            'customFields' => [
                'apiClient'        => CLIENT_NAME,
                'apiClientVersion' => CLIENT_VERSION,
            ],
        ];
        $this->setMockResponse(
            [
                CURLOPT_URL           => BillPayments::BILLS_URI.$this->billId,
                CURLOPT_CUSTOMREQUEST => BillPayments::PUT,
                CURLOPT_POSTFIELDS    => json_encode($testFields, JSON_UNESCAPED_UNICODE),
            ],
            json_encode(['payUrl' => 'https://oplata.qiwi.com/form/?invoice_uid=d875277b-6f0f-445d-8a83-f62c7c07be77'])
        );
        $bill            = $this->billPayments->createBill(
            $this->billId,
            ($testFields + ['successUrl' => $this->fields['successUrl']])
        );
        $testPayUrlQuery = http_build_query(['successUrl' => $this->fields['successUrl']], '', '&', PHP_QUERY_RFC3986);
        $this->assertTrue(is_array($bill) && strpos($bill['payUrl'], $testPayUrlQuery) !== false, 'create bill');

    }//end testCreateBill()


    /**
     * Requests live cycle 2 - get bill info.
     *
     * @see https://developer.qiwi.com/ru/bill-payments/#invoice-status
     *
     * @return void
     *
     * @throws BillPaymentsException
     */
    public function testGetBillInfo()
    {
        $testFields = [
            'customFields' => [
                'apiClient'        => CLIENT_NAME,
                'apiClientVersion' => CLIENT_VERSION,
            ],
        ];

        $this->setMockResponse(
            [
                CURLOPT_URL           => BillPayments::BILLS_URI.$this->billId,
                CURLOPT_CUSTOMREQUEST => BillPayments::GET,
            ],
            json_encode($testFields)
        );
        $bill = $this->billPayments->getBillInfo($this->billId);
        $this->assertArraySubset($testFields, $bill, 'returns valid bill info');

    }//end testGetBillInfo()


    /**
     * Requests live cycle 3 - cancel bill.
     *
     * @see https://developer.qiwi.com/ru/bill-payments/#cancel
     *
     * @return void
     *
     * @throws BillPaymentsException
     */
    public function testCancelBill()
    {
        $this->setMockResponse(
            [
                CURLOPT_URL           => BillPayments::BILLS_URI.$this->billId.'/reject',
                CURLOPT_CUSTOMREQUEST => BillPayments::POST,
            ],
            '[]'
        );
        $bill = $this->billPayments->cancelBill($this->billId);
        $this->assertTrue(is_array($bill), 'cancel unpaid bill');

    }//end testCancelBill()


    /**
     * Refund bill.
     *
     * @see https://developer.qiwi.com/ru/bill-payments/#refund
     *
     * @return void
     *
     * @throws BillPaymentsException
     */
    public function testRefund()
    {
        $billId   = $this->config['billIdForRefundTest'];
        $refundId = microtime();
        $this->setMockResponse(
            [
                CURLOPT_URL           => BillPayments::BILLS_URI.$billId.'/refunds/'.$refundId,
                CURLOPT_CUSTOMREQUEST => BillPayments::PUT,
                CURLOPT_POSTFIELDS    => json_encode(
                    [
                        'amount' => [
                            'currency' => 'RUB',
                            'value'    => '0.01',
                        ],
                    ]
                ),
            ],
            '[]'
        );
        $billRefund = $this->billPayments->refund(
            $billId,
            $refundId,
            '0.01',
            'RUB'
        );
        $this->assertTrue(is_array($billRefund), 'makes refund');

    }//end testRefund()


    /**
     * Get refund info.
     *
     * @see https://developer.qiwi.com/ru/bill-payments/#refund-status
     *
     * @return void
     *
     * @throws BillPaymentsException
     */
    public function testGetRefundInfo()
    {
        $billId   = $this->config['billIdForGetRefundInfoTest'];
        $refundId = $this->config['billRefundIdForGetRefundInfoTest'];
        $this->setMockResponse(
            [
                CURLOPT_URL           => BillPayments::BILLS_URI.$billId.'/refunds/'.$refundId,
                CURLOPT_CUSTOMREQUEST => BillPayments::GET,
            ],
            '[]'
        );
        $billRefund = $this->billPayments->getRefundInfo($billId, $refundId);
        $this->assertTrue(is_array($billRefund), 'gets refund info');

    }//end testGetRefundInfo()


    /**
     * Create payment form.
     *
     * @see https://developer.qiwi.com/ru/bill-payments/#http
     *
     * @return void
     */
    public function testCreatePaymentForm()
    {
        $uri          = BillPayments::CREATE_URI;
        $amountString = '200.34';
        $amountNumber = 200.345;
        $query        = http_build_query(
            [
                'billId'       => $this->billId,
                'publicKey'    => $this->config['merchantPublicKey'],
                'amount'       => $amountString,
                'successUrl'   => $this->fields['successUrl'],
                'customFields' => [
                    'apiClient'        => CLIENT_NAME,
                    'apiClientVersion' => CLIENT_VERSION,
                ],
            ],
            '',
            '&',
            PHP_QUERY_RFC3986
        );
        $testLink     = $uri.'?'.$query;
        $result       = $this->billPayments->createPaymentForm(
            [
                'publicKey'  => $this->config['merchantPublicKey'],
                'amount'     => $amountNumber,
                'billId'     => $this->billId,
                'successUrl' => $this->fields['successUrl'],
            ]
        );
        $this->assertEquals($testLink, $result, 'creates payment form');

    }//end testCreatePaymentForm()


    /**
     * Properties available by magic methods.
     *
     * @return void
     */
    public function testProperties()
    {
        $this->assertTrue(isset($this->billPayments->key), 'exists key attribute');
        $this->billPayments->key = 'test';
        $this->setException(Exception::class, 'Not acceptable property key.');
        $this->billPayments->key;

        $this->assertTrue(isset($this->billPayments->curl), 'exists curl attribute');
        $this->assertInstanceOf(Curl::class, $this->billPayments->curl, 'correct set curl attribute');
        $this->setException(Exception::class, 'Not acceptable property curl.');
        $this->billPayments->curl = 'test';

        //phpcs:disable Generic,Squiz.Commenting -- Because IDE helper doc block in line.
        /** @noinspection PhpUndefinedFieldInspection */
        $this->assertFalse(isset($this->billPayments->qwerty), 'not exists attribute');
        //phpcs:enable Generic,Squiz.Commenting
        $this->setException(Exception::class, 'Undefined property qwerty.');
        //phpcs:disable Generic,Squiz.Commenting -- Because IDE helper doc block in line.
        /** @noinspection PhpUndefinedFieldInspection */
        $this->billPayments->qwerty = 'test';
        //phpcs:enable Generic,Squiz.Commenting

    }//end testProperties()


    /**
     * Generate ID.
     *
     * @return void
     *
     * @throws Exception
     */
    public function testGenerateId()
    {
        $billId = $this->billPayments->generateId();
        $this->assertRegExp('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $billId, 'UUID v4 format');

    }//end testGenerateId()


    /**
     * Get life time.
     *
     * @return void
     *
     * @throws Exception
     */
    public function testGetLifetimeByDay()
    {
        $lifetime = $this->billPayments->getLifetimeByDay();
        $this->assertRegExp('/^([\+-]?\d{4}(?!\d{2}\b))((-?)((0[1-9]|1[0-2])(\3([12]\d|0[1-9]|3[01]))?|W([0-4]\d|5[0-2])(-?[1-7])?|(00[1-9]|0[1-9]\d|[12]\d{2}|3([0-5]\d|6[1-6])))([T\s]((([01]\d|2[0-3])((:?)[0-5]\d)?|24\:?00)([\.,]\d+(?!:))?)?(\17[0-5]\d([\.,]\d+)?)?([zZ]|([\+-])([01]\d|2[0-3]):?([0-5]\d)?)?)?)?$/', $lifetime, 'ISO 8601 format');

    }//end testGetLifetimeByDay()


    /**
     * CheckNotificationSignature.
     *
     * @see https://developer.qiwi.com/ru/bill-payments/#notification
     *
     * @return void
     */
    public function testCheckNotificationSignature()
    {
        $merchantSecret   = 'test-merchant-secret-for-signature-check';
        $notificationData = [
            'bill' => [
                'siteId' => 'test',
                'billId' => 'test_bill',
                'amount' => [
                    'value'    => 1,
                    'currency' => 'RUB',
                ],
                'status' => ['value' => 'PAID'],
            ],
        ];
        $this->assertFalse(
            $this->billPayments->checkNotificationSignature(
                'foo',
                $notificationData,
                $merchantSecret
            ),
            'should return false on wrong signature'
        );
        $this->assertTrue(
            $this->billPayments->checkNotificationSignature(
                '07e0ebb10916d97760c196034105d010607a6c6b7d72bfa1c3451448ac484a3b',
                $notificationData,
                $merchantSecret
            ),
            'should return true on valid signature'
        );

    }//end testCheckNotificationSignature()


    /**
     * Get pay url.
     *
     * @return void
     */
    public function testGetPayUrl()
    {
        $bill   = ['payUrl' => 'https://oplata.qiwi.com/form/?invoice_uid=d875277b-6f0f-445d-8a83-f62c7c07be77'];
        $payUrl = $this->billPayments->getPayUrl($bill, 'http://test.ru/');
        $this->assertEquals('https://oplata.qiwi.com/form/?invoice_uid=d875277b-6f0f-445d-8a83-f62c7c07be77&successUrl=http%3A%2F%2Ftest.ru%2F', $payUrl, 'witch success URL');

    }//end testGetPayUrl()


}//end class

<?php

/**
 * BillPaymentsTest.php
 *
 * @author    Yaroslav <yaroslav@wannabe.pro>
 * @copyright 2019 (c) QIWI JSC
 * @license   MIT https://raw.githubusercontent.com/QIWI-API/bill-payments-php-sdk/master/LICENSE
 */

namespace Qiwi\Api\Util;

use Qiwi\Api\BillPaymentsException;
use Qiwi\Api\TestCase;
use Qiwi\Api\BillPayments;
use Curl\Curl;
use Exception;
use ReflectionClass;

/**
 * Util test case without real API.
 * No needed configure.
 *
 * @package Qiwi\Api\BillPaymentsTest
 */
class BillPaymentsTest extends TestCase
{


    /**
     * Set up test case.
     *
     * @param string $method Method to call.
     * @param array  $state  State of curl after exec.
     *
     * @return void
     *
     * @throws \ReflectionException
     */
    protected function setMock($method, array $state=[])
    {
        $mock = $this->createMock(Curl::class);
        $mock->expects($this->once())->method($method)->willReturnCallback(
            function () use ($state) {
                foreach ($state as $key => $value) {
                    $this->billPayments->curl->$key = $value;
                }

                return $this->billPayments->curl->error_code;
            }
        );
        $class    = new ReflectionClass($this->billPayments);
        $property = $class->getProperty('internalCurl');
        $property->setAccessible(true);
        $property->setValue($this->billPayments, $mock);
        $property->setAccessible(false);

    }//end setMock()


    /**
     * Request exception.
     *
     * @return void
     *
     * @throws \ReflectionException
     * @throws BillPaymentsException
     */
    public function testRequestException()
    {
        $this->setMock('get', ['error' => true, 'error_code' => 500, 'error_message' => 'test']);
        $this->setExpectedException(BillPaymentsException::class, 'test', 500);
        $this->billPayments->getBillInfo('');

    }//end testRequestException()


    /**
     * Requests live cycle 1 - create bill.
     *
     * @see https://developer.qiwi.com/ru/bill-payments/#create
     *
     * @return void
     *
     * @throws \Qiwi\Api\BillPaymentsException
     * @throws \ReflectionException
     */
    protected function subTestCreateBill()
    {
        $this->setMock('put', ['error' => false, 'response' => json_encode(['payUrl' => 'https://oplata.qiwi.com/form/?invoice_uid=d875277b-6f0f-445d-8a83-f62c7c07be77'])]);
        $bill            = $this->billPayments->createBill(
            $this->billId,
            $this->fields
        );
        $testPayUrlQuery = http_build_query(['successUrl' => $this->fields['successUrl']], '', '&', PHP_QUERY_RFC3986);
        $this->assertTrue(is_array($bill) && strpos($bill['payUrl'], $testPayUrlQuery) !== false, 'create bill');

    }//end subTestCreateBill()


    /**
     * Requests live cycle 2 - get bill info.
     *
     * @see https://developer.qiwi.com/ru/bill-payments/#invoice-status
     *
     * @return void
     *
     * @throws \Qiwi\Api\BillPaymentsException
     * @throws \ReflectionException
     */
    protected function subTestGetBillInfo()
    {
        $testFields = [
            'customFields' => [
                'apiClient'        => CLIENT_NAME,
                'apiClientVersion' => CLIENT_VERSION,
            ],
        ];
        $this->setMock('get', ['error' => false, 'response' => json_encode($testFields)]);
        $bill = $this->billPayments->getBillInfo($this->billId);
        $this->assertArraySubset($testFields, $bill, 'returns valid bill info');

    }//end subTestGetBillInfo()


    /**
     * Requests live cycle 3 - cancel bill.
     *
     * @see https://developer.qiwi.com/ru/bill-payments/#cancel
     *
     * @return void
     *
     * @throws \Qiwi\Api\BillPaymentsException
     * @throws \ReflectionException
     */
    protected function subTestCancelBill()
    {
        $this->setMock('post', ['error' => false, 'response' => '[]']);
        $bill = $this->billPayments->cancelBill($this->billId);
        $this->assertTrue(is_array($bill), 'cancel unpaid bill');

    }//end subTestCancelBill()


    /**
     * Requests life cycle without payment.
     *
     * @return void
     *
     * @throws \Qiwi\Api\BillPaymentsException
     * @throws \ReflectionException
     */
    public function testRequests()
    {
        $this->subTestCreateBill();
        $this->subTestGetBillInfo();
        $this->subTestCancelBill();

    }//end testRequests()


    /**
     * Refund bill.
     *
     * @see https://developer.qiwi.com/ru/bill-payments/#refund
     *
     * @return void
     *
     * @throws \Qiwi\Api\BillPaymentsException
     * @throws \ReflectionException
     */
    public function testRefund()
    {
        $this->setMock('put', ['error' => false, 'response' => '[]']);
        $billRefund = $this->billPayments->refund(
            $this->config['billIdForRefundTest'],
            microtime(),
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
     * @throws \Qiwi\Api\BillPaymentsException
     * @throws \ReflectionException
     */
    public function testGetRefundInfo()
    {
        $this->setMock('get', ['error' => false, 'response' => '[]']);
        $billRefund = $this->billPayments->getRefundInfo(
            $this->config['billIdForGetRefundInfoTest'],
            $this->config['billRefundIdForGetRefundInfoTest']
        );
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
        $this->setExpectedException(Exception::class, 'Not acceptable property key.');
        $this->billPayments->key;

        $this->assertTrue(isset($this->billPayments->curl), 'exists curl attribute');
        $this->assertInstanceOf(Curl::class, $this->billPayments->curl, 'correct set curl attribute');
        $this->setExpectedException(Exception::class, 'Not acceptable property curl.');
        $this->billPayments->curl = 'test';

        //phpcs:disable Generic,Squiz.Commenting -- Because IDE helper doc block in line.
        /** @noinspection PhpUndefinedFieldInspection */
        $this->assertFalse(isset($this->billPayments->qwerty), 'not exists attribute');
        //phpcs:enable Generic,Squiz.Commenting
        $this->setExpectedException(Exception::class, 'Undefined property qwerty.');
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

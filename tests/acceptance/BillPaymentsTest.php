<?php
/**
 * BillPaymentsTest.php
 *
 * @author    Yaroslav <yaroslav@wannabe.pro>
 * @copyright 2019 (c) QIWI JSC
 * @license   MIT https://raw.githubusercontent.com/QIWI-API/bill-payments-php-sdk/master/LICENSE
 */

namespace Qiwi\Api\Acceptance;

use Qiwi\Api\TestCase;

/**
 * Acceptance test case witch real API.
 * Set `tests/config.php` first.
 *
 * @package Qiwi\Api\BillPaymentsTest
 */
class BillPaymentsTest extends TestCase
{


    /**
     * Requests live cycle 1 - create bill.
     *
     * @see https://developer.qiwi.com/ru/bill-payments/#create
     *
     * @return void
     *
     * @throws \Qiwi\Api\BillPaymentsException
     */
    protected function subTestCreateBill()
    {
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
     */
    protected function subTestGetBillInfo()
    {
        $bill       = $this->billPayments->getBillInfo($this->billId);
        $testFields = [
            'customFields' => [
                'apiClient'        => CLIENT_NAME,
                'apiClientVersion' => CLIENT_VERSION,
            ],
        ];
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
     */
    protected function subTestCancelBill()
    {
        $bill = $this->billPayments->cancelBill($this->billId);
        $this->assertTrue(is_array($bill), 'cancel unpaid bill');

    }//end subTestCancelBill()


    /**
     * Requests life cycle without payment.
     *
     * @return void
     *
     * @throws \Qiwi\Api\BillPaymentsException
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

    }//end testRefund()


    /**
     * Get refund info.
     *
     * @see https://developer.qiwi.com/ru/bill-payments/#refund-status
     *
     * @return void
     *
     * @throws \Qiwi\Api\BillPaymentsException
     */
    public function testGetRefundInfo()
    {
        $billRefund = $this->billPayments->getRefundInfo(
            $this->config['billIdForGetRefundInfoTest'],
            $this->config['billRefundIdForGetRefundInfoTest']
        );
        $this->assertTrue(is_array($billRefund), 'gets refund info');

    }//end testGetRefundInfo()


}//end class

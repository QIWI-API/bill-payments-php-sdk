<?php

/**
 * BillPaymentsTest.php
 *
 * @author    Yaroslav <yaroslav@wannabe.pro>
 * @copyright 2019 (c) QIWI JSC
 * @license   MIT https://raw.githubusercontent.com/QIWI-API/bill-payments-php-sdk/master/LICENSE
 */

namespace Qiwi\Api\Util;

use Qiwi\Api\TestCase;
use Qiwi\Api\BillPaymentsException;
use Exception;

/**
 * Util test case without real API.
 * No needed configure.
 *
 * @package Qiwi\Api\BillPaymentsTest
 */
class BillPaymentsExceptionTest extends TestCase
{


    /**
     * Properties available by magic methods.
     *
     * @return void
     *
     * @throws \ErrorException
     */
    public function testProperties()
    {
        $curl = curl_init();
        $billPaymentsException = new BillPaymentsException($curl);

        $this->assertTrue(isset($billPaymentsException->curl), 'exists curl attribute');
        $this->assertSame($curl, $billPaymentsException->curl, 'correct set curl attribute');
        $this->setException(Exception::class, 'Not acceptable property curl.');
        $billPaymentsException->curl = curl_copy_handle($curl);

        //phpcs:disable Generic,Squiz.Commenting -- Because IDE helper doc block in line.
        /** @noinspection PhpUndefinedFieldInspection */
        $this->assertFalse(isset($billPaymentsException->qwerty), 'not exists attribute');
        //phpcs:enable Generic,Squiz.Commenting
        $this->setException(Exception::class, 'Undefined property qwerty.');
        //phpcs:disable Generic,Squiz.Commenting -- Because IDE helper doc block in line.
        /** @noinspection PhpUndefinedFieldInspection */
        $billPaymentsException->qwerty = 'test';
        //phpcs:enable Generic,Squiz.Commenting

    }//end testProperties()


}//end class

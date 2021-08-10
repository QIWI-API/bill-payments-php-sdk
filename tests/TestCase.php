<?php
/**
 * Test case abstraction.
 * Preset test data.
 *
 * @author    Yaroslav <yaroslav@wannabe.pro>
 * @copyright 2019 (c) QIWI JSC
 * @license   MIT https://raw.githubusercontent.com/QIWI-API/bill-payments-php-sdk/master/LICENSE
 */

namespace Qiwi\Api;

use PHPUnit\Framework\TestCase as BaseTestCase;

if (false === defined('CLIENT_NAME')) {
    define('CLIENT_NAME', 'php_sdk');
}

if (false === defined('CLIENT_VERSION')) {
    define(
        'CLIENT_VERSION',
        @json_decode(
            file_get_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'composer.json'),
            true
        )['version']
    );
}

/**
 * Test case preset.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * User test configuration.
     * Will be load from `tests/config.php` on exists.
     *
     * @var array
     */
    protected $config = [
        'merchantPublicKey'                => '2tbp1WQvsgQeziGY9vTLe9vDZNg7tmCymb4Lh6STQokqKrpCC6qrUUKEDZAJ7mvFnzr1yTebUiQaBLDnebLMMxL8nc6FF5zf******',
        'merchantSecretKey'                => 'eyJ2ZXJzaW9uIjoicmVzdF92MyIsImRhdGEiOnsibWVyY2hhbnRfaWQiOjUyNjgxMiwiYXBpX3VzZXJfaWQiOjcxNjI2MTk3LCJzZWNyZXQiOiJmZjBiZmJiM2UxYzc0MjY3YjIyZDIzOGYzMDBkNDhlYjhiNTnONPININONPN090MTg5Z**********************',
        'billIdForGetRefundInfoTest'       => '893794793973',
        'billRefundIdForGetRefundInfoTest' => '899343443',
        'billIdForRefundTest'              => '893794793973',
    ];

    /**
     * Tests target.
     *
     * @var BillPayments
     */
    protected $billPayments;

    /**
     * Generated bill ID.
     *
     * @var string
     */
    protected $billId;

    /**
     * Example fields for bills.
     * The `expirationDateTime` field will be generated.
     *
     * @var array
     */
    protected $fields = [
        'amount'             => 200.345,
        'currency'           => 'RUB',
        'expirationDateTime' => '',
        'providerName'       => 'Test',
        'comment'            => 'test',
        'phone'              => '79999999999',
        'email'              => 'test@test.ru',
        'account'            => 'user uid on your side',
        'customFields'       => [
            'city'   => 'Москва',
            'street' => 'Арбат',
        ],
        'successUrl'         => 'http://test.ru/',
    ];


    /**
     * Set up tests.
     *
     * @return void
     *
     * @throws \Exception
     * @throws \ErrorException
     */
    public function setUp()
    {
        parent::setUp();

        // Set up valid root CA certificate.
        $options = [];
        if (true === is_file(__DIR__.DIRECTORY_SEPARATOR.'cacert.pem')) {
            $options[CURLOPT_SSL_VERIFYPEER] = true;
            $options[CURLOPT_SSL_VERIFYHOST] = 2;
            $options[CURLOPT_CAINFO]         = __DIR__.DIRECTORY_SEPARATOR.'cacert.pem';
        }

        // Set up user config.
        if (true === is_file(__DIR__.DIRECTORY_SEPARATOR.'config.php')) {
            $this->config = include __DIR__.DIRECTORY_SEPARATOR.'config.php';
        }

        // Init target.
        $this->billPayments = new BillPayments($this->config['merchantSecretKey'], $options);

        // Get UUID.
        $this->billId = $this->billPayments->generateId();

        // Get expirationDateTime.
        $this->fields['expirationDateTime'] = $this->billPayments->getLifetimeByDay();

    }//end setUp()


    /**
     * Set expect exception.
     *
     * @param string       $class   Class name.
     * @param null|string  $message Message text.
     * @param null|integer $code    Code number.
     *
     * @return void
     */
    protected function setException($class, $message=null, $code=null)
    {
        if (null !== $code) {
            $this->expectExceptionCode($code);
        }

        if (null !== $message) {
            $this->expectExceptionMessage($message);
        }

        $this->expectException($class);

    }//end setException()


}//end class

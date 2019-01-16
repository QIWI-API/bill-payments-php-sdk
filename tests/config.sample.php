<?php

/**
 * Create `tests\config.php` by this example to perform acceptance tests witch real API.
 * Example commands to perform data set:
 * * curl -X PUT https://api.qiwi.com/partner/bill/v1/bills/893794793973 -H 'Accept: application/json' -H 'Authorization: Bearer MjMyNDQxMjM6NDUzRmRnZDQ0M*******' -H 'Content-Type: application/json' -d '{"amount":{"currency":"RUB","value":"100.00"},"comment":"Text comment","expirationDateTime":"2019-12-31T23:59:59+00:00","customer":{"phone":"+799999999999","email":"example@test.com","account":"test"}}'
 * * curl -X PUT https://api.qiwi.com/partner/bill/v1/bills/893794793973/refunds/899343443 -H 'Accept: application/json' -H 'Authorization: Bearer MjMyNDQxMjM6NDUzRmRnZDQ0M*******' -H 'Content-Type: application/json' -d '{"amount":{"currency":"RUB","value":"0.01"}}'
 *
 * @author    Yaroslav <yaroslav@wannabe.pro>
 * @copyright 2019 (c) QIWI JSC
 * @license   MIT https://raw.githubusercontent.com/QIWI-API/bill-payments-php-sdk/master/LICENSE
 */

return [
    // Enter your merchant public key.
    'merchantPublicKey'                => 'Fnzr1yTebUiQaBLDnebLMMxL8nc6FF5zfmGQnypc*******',
    // Enter your merchant secret key.
    'merchantSecretKey'                => 'MjMyNDQxMjM6NDUzRmRnZDQ0M*******',
    // Enter bill ID to refund.
    'billIdForGetRefundInfoTest'       => '893794793973',
    // Enter refund ID.
    'billRefundIdForGetRefundInfoTest' => '893794793973',
    // Enter bill ID witch refund.
    'billIdForRefundTest'              => '899343443',
];

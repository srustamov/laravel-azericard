<?php

namespace Srustamov\Azericard\Exceptions;


/**
 * Class FailedTransactionException
 * @package Srustamov\Azericard\Exceptions
 */
class FailedTransactionException extends AzericardException
{
    protected array $messages = [
        '-30' => 'System error',
        '-21' => 'Duplicate transaction',
        '-20' => 'Expired transaction',
        '-19' => 'Authentication failed',
        '-18' => 'Error in CVC2 or CVC2 Description fields',
        '-17' => 'Access denied',
        '-16' => 'Terminal is locked, please try again',
        '-15' => 'Invalid Retrieval reference number',
        '-12' => 'Error in merchant terminal field',
        '-11' => 'Error in currency field',
        '-10' => 'Error in amount field',
        '-9' => 'Error in card expiration date field',
        '-8' => 'Error in card number field',
        '-7' => 'Invalid response',
        '-5' => 'Connect failed',
        '-4' => 'Server is not responding',
        '-3' => 'No or Invalid response received',
        '-2' => 'Bad CGI request',
        '-1' => 'Mandatory field is empty',
        '00' => 'Approved',
        '01' => 'Call your bank',
        '02' => 'Call your bank',
        '03' => 'Invalid merchant',
        '04' => 'Your card is restricted',
        '05' => 'Transaction declined',
        '07' => 'Your card is disabled',
        '0' => 'Approved',
        '1' => 'Duplicate',
        '2' => 'Wrong parameter',
        '3' => 'Wrong P_SIGN',
        '4' => 'Your card is restricted',
        '5' => 'Transaction declined',
        '7' => 'Your card is disabled',
        '10' => 'Partially approved',
        '12' => 'Invalid transaction',
        '13' => 'Invalid amount',
        '14' => 'No such card',
        '15' => 'No such card/issuer',
        '20' => 'Invalid response',
        '21' => 'No action taken',
        '25' => 'No such record',
        '30' => 'Format error',
        '32' => 'Completed partially',
        '33' => 'Expired card',
        '34' => 'Suspected fraud',
        '36' => 'Restricted card',
        '37' => 'Call your bank',
        '41' => 'Lost card',
        '43' => 'Stolen card',
        '51' => 'Not sufficient funds',
        '53' => 'No savings account',
        '54' => 'Expired card',
        '55' => 'Incorrect PIN',
        '56' => 'No card record',
        '57' => 'Not permitted to client',
        '58' => 'Not permitted to merchant',
        '59' => 'Suspected fraud',
        '61' => 'Exceeds amount limit',
        '62' => 'Restricted card',
        '63' => 'Security violation',
        '65' => 'Exceeds frequency limit',
        '66' => 'Acceptor call acquirer',
        '68' => 'Reply received too late',
        '77' => 'Wrong Reference No.',
        '78' => 'Reserved',
        '79' => 'Already reversed',
        '80' => 'Network error',
        '81' => 'Foreign network error',
        '82' => 'Time-out at issuer',
        '83' => 'Transaction failed',
        '84' => 'Pre-authorization timed out',
        '85' => 'Account verification required',
        '87' => 'Reserved',
        '88' => 'Cryptographic failure',
        '89' => 'Authentication failure',
        '91' => 'Issuer unavailable',
        '92' => 'Router unavailable',
        '93' => 'Violation of law',
        '95' => 'Reconcile error',
        '96' => 'System malfunction',
        '99' => 'Aborted',
    ];


    /**
     * FailedTransactionException constructor.
     * @param null $code
     */
    public function __construct($code = null, public array $params = [])
    {
        if (array_key_exists($code, $this->messages)) {
            $message = $this->messages[$code];
        } else {
            $message = 'Unknown RC code: ' . $code;
        }

        parent::__construct($message);
    }


    public function getParams(): array
    {
        return $this->params;
    }
}

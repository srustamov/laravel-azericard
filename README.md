# AzeriCard Payment Package for Laravel


## Requirements

- Laravel **^7.0|8|9**
- PHP **7|8**

## Installation

You can install the package via composer:

```bash
composer require srustamov/laravel-azericard
```

```bash
php artisan vendor:publish --provider="Srustamov\Azericard\AzericardServiceProvider" --tag="config"
```

## Credits
- [Samir Rustamov](https://github.com/srustamov)


## Examples

```php

// routes
Route::get('/azericard/get-form-params',[\App\Http\Controllers\AzericardController::class,'getFormData']);
Route::post('/azericard/callback',[\App\Http\Controllers\AzericardController::class,'callback']);
Route::get('/azericard/result/{orderId}',[\App\Http\Controllers\AzericardController::class,'result']);


//controller

use Exception;
use Illuminate\Http\Request;
use Srustamov\Azericard\Azericard;

class AzericardController extends Controller
{


    /**
     * @param Azericard $azericard
     * @param Request $request
     * @return mixed
     */
    public function getFormData(Azericard $azericard, Request $request)
    {
        $order  = $request->get('order','1');
        $amount = $request->get('amount',10); // AZN

        $formParams =  $azericard->order($order)
            ->amount($amount)
            ->setMerchantUrl("/azericard/result/{$order}")
            //->debug($request->has('test'))
            ->getFormParams();

        //return $this->generateHtmlForm($formParams);

        //return $formParams;
    }


    /**
     * @param Azericard $azericard
     * @param Request $request
     */
    public function callback(Azericard $azericard, Request $request)
    {
        try {

            if ($azericard->checkout($request->all())) {

                // payment success
                // update order status or increment User balance
            } else {
                // payment fail
            }

        } catch (Exception $exception) {

        }
    }
    
    /**
     * @param Azericard $azericard
     */
    public function refund(Azericard $azericard)
    {
        $data = [
            'rrn' => 'bank rrn value',
            'int_ref' => 'int_ref value',
            'created_at' => 'payment create datetime. example 2020-01-01 10:00:11'
        ];

        $order  = 1;
        $amount = 1;

        if ($azericard->amount($amount)->order($order)->refund($data)) {
            // amount refund successfully
        } else {
            // fail
        }
    }


    /**
     * @param $orderId
     */
    public function result($orderId)
    {

        /*
        if (statement) {
            return view('payment-success');
        }

        return view('payment-failed');

       */

    }


    //example
    private function generateHtmlForm($formParams): string
    {
        $html = '<form action="'.$formParams['action'].'" method="'.$formParams['method'].'">';

        foreach ($formParams['inputs'] as $name => $value) {
            $html .= '<input type="hidden" name="'.$name.'" value="'.$value.'" />';
        }

        $html .= '</form>';


        return $html;
    }
}

```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

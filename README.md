# Azericard Payment Package for Laravel


## Requirements

- Laravel **^8|^9**
- PHP **^8.1**

## Installation

You can install the package via composer:

```bash
composer require srustamov/laravel-azericard
```

## For Laravel < 8 and PHP < 8

```bash
composer require srustamov/laravel-azericard:^1.0.1
```

```bash
php artisan vendor:publish --provider="Srustamov\Azericard\AzericardServiceProvider" --tag="config"
```

## Credits
- [Samir Rustamov](https://github.com/srustamov)


## Example

```php




```

```php
// routes
Route::prefix('azericard')->group(function () {
    Route::get('/get-form-params',[\App\Http\Controllers\AzericardController::class,'getFormData']);
    Route::post('/callback',[\App\Http\Controllers\AzericardController::class,'callback']);
    Route::get('/result/{orderId}',[\App\Http\Controllers\AzericardController::class,'result']);
});


//controller

use Exception;
use Illuminate\Http\Request;
use Srustamov\Azericard\Azericard;
use Srustamov\Azericard\Exceptions\FailedTransactionException;
use Srustamov\Azericard\Exceptions\AzericardException;

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

        $formParams =  $azericard->setOrder($order)
            ->setAmount($amount)
            ->setMerchantUrl("/azericard/result/{$order}")
            //->debug($request->has('test'))
            ->getFormParams();


        //for ui
        //return $this->generateHtmlForm($formParams);

        //for api
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

        } 
        catch (FailedTransactionException $e) {
            // payment fail
        } 
        catch (AzericardException $e) {
            // payment fail
        } 
        catch (Exception $e) {
            // payment fail
        }
    }
    
    /**
     * @param Azericard $azericard
     */
    public function refund(Azericard $azericard)
    {
        try
        {
            $data = [
                'rrn' => 'bank rrn value',
                'int_ref' => 'int_ref value',
                'created_at' => 'payment create datetime. example 2020-01-01 10:00:11'
            ];
            }
    
            $order  = 1;
            $amount = 1;
    
            if ($azericard->setAmount($amount)->setOrder($order)->refund($data)) {
                // amount refund successfully
            } else {
                // fail
            }
        }
        catch (FailedTransactionException $e) {
            // payment fail
        } 
        catch (AzericardException $e) {
            // payment fail
        } 
        catch (Exception $e) {
            // payment fail
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

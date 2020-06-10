# AzeriCard Payment Package for Laravel


## Requirements

- Laravel **^7.0**
- PHP **7.2**

## Installation

You can install the package via composer:

```bash
composer require srustamov/laravel-azericard
```

```bash
php artisan vendor:publish --provider="Srustamov\Azericard\AzericardServiceProvider" --tag="config"
```

### Override payment forms

```bash
php artisan vendor:publish --provider="Srustamov\Azericard\AzericardServiceProvider" --tag="views"
```

## Credits

- [Elnur Akhundov](https://github.com/elnurxf)
- [Samir Rustamov](https://github.com/srustamov)


## Examples

```php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Srustamov\Azericard\Facade\Azericard;
use Srustamov\Azericard\Exceptions\AzericardException;
use Srustamov\Azericard\Exceptions\FailedTransactionException;


class AzericardController extends Controller
{
    /**
     * @return Illuminate\View\View|string
     */
    public function authorization()
    {
        try {
            $form = Azericard::init([
                'AMOUNT' => '3.70',
                'CURRENCY' => 'AZN',
                'ORDER' => '000001',
                'DESC' => 'Payment for order #000001',
                'TRTYPE' => '0', // 0 = AUTH, 1 = AUTH + CHECKOUT
                'LANG' => 'en',
            ])
            // override template
            // submit button label. in template <button>{{$button_label}}</button>
            ->formWithParams(['button_label' => 'Authorization'])
            ->paymentForm();
        } catch (AzericardException $e) {
            return $e->getMessage();
        }

        return view('authorization', compact('form'));
    }


    /**
     *  @return Illuminate\View\View|string
     */
    public function checkout()
    {
        try {
            $form = Azericard::init([
                'AMOUNT' => '3.70',
                'CURRENCY' => 'AZN',
                'ORDER' => '000001',
                'DESC' => 'Payment for order #000001',
                'TRTYPE' => '1', // 0 = AUTH, 1 = AUTH + CHECKOUT
                'LANG' => 'en',
            ])
            ->paymentForm();
        } catch (AzericardException $e) {
            return $e->getMessage();
        }

        return view('checkout', compact('form'));
    }


    /**
     *  @return Illuminate\View\View|string
     */
    public function reversal()
    {
        try {
            $form = Azericard::init([
               'AMOUNT'       => '5.00',
               'CURRENCY'     => 'AZN',
               'ORDER'        => '001000',
               'RRN'          => '835376720012',
               'INT_REF'      => '87052640AB22C9FA',
               'TRTYPE'       => '22', // 22 = REVERSAL, 24 = CLEARANCE
            ])
            ->reversalForm();
        } catch (AzericardException $e) {
            return $e->getMessage();
        }

        return view('reversal', compact('form'));
    }

    /**
     *  @return Illuminate\View\View|string
     */
    public function clearance()
    {
        try {
            $form = Azericard::init([
              'AMOUNT'       => '3.70',
              'CURRENCY'     => 'AZN',
              'ORDER'        => '000001',
              'RRN'          => '',
              'INT_REF'      => '',
              'TRTYPE'       => '24', // 22 = REVERSAL, 24 = CLEARANCE
            ])->reversalForm();
        } catch (AzericardException $e) {
            return $e->getMessage();
        }

        return view('clearance', compact('form'));
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function callback(Request $request): string
    {
        try {
          $success = Azericard::setCallBackParameters($request->all())
              ->handleCallback()
              ->completeCheckout();
        } catch (FailedTransactionException $e) {
          return 'failed:'.$e->getMessage();
        } catch(AzericardException $e) {
          return 'failed:'.$e->getMessage();
        }

        if($success) {
          // code ...
        }

        // failed code ...
    }
}


```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

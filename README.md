# Azericard Payment Package for Laravel

[![GitHub license](https://img.shields.io/github/license/srustamov/laravel-azericard.svg)](https://github.com/srustamov/laravel-azericard/blob/master/LICENSE.md)
<a href="https://packagist.org/packages/srustamov/laravel-azericard">
<img src="https://img.shields.io/packagist/v/srustamov/laravel-azericard" alt="Latest Stable Version">
</a>

## Requirements

- Laravel **^8|^9**
- PHP **^8.0**

## Installation

You can install the package via composer:

```bash
composer require srustamov/laravel-azericard
```

### For Laravel < 8 and PHP < 8

```bash
composer require srustamov/laravel-azericard:^1.0.1
```

### Publish config file

```bash
php artisan vendor:publish --provider="Srustamov\Azericard\AzericardServiceProvider" --tag="config"
```

## Credits

- [Samir Rustamov](https://github.com/srustamov)
- [Azericard](https://developer.azericard.com/)

## Example

```php
// routes
Route::prefix('azericard')->group(function () {
    Route::get('/create-order',[\App\Http\Controllers\AzericardController::class,'createOrder']);
    Route::post('/callback',[\App\Http\Controllers\AzericardController::class,'callback']);
    Route::get('/result/{orderId}',[\App\Http\Controllers\AzericardController::class,'result']);
});


//controller

use Exception;
use Illuminate\Http\Request;
use Srustamov\Azericard\Azericard;
use Srustamov\Azericard\Exceptions\FailedTransactionException;
use Srustamov\Azericard\Exceptions\AzericardException;
use Illuminate\Support\Facades\DB;
use App\Models\Payment\Transaction;
use Srustamov\Azericard\Options;
use Srustamov\Azericard\RefundData;
use Illuminate\Support\Facades\Event;
use Srustamov\Azericard\Events\OrderCompleted;

class AzericardController extends Controller
{
    public function __construct() 
    {
        Event::listen(OrderCompleted::class, function (OrderCompleted $event) {
            // do something
            //example
            logger()->info('Order completed', ['request' => $event->request]);
        });
    }

    public function createOrder(Azericard $azericard, Request $request)
    {
        $order = auth()->user()->transactions()->create([
            'amount'         => $request->post('amount'),
            'currency'       => 'AZN',
            'status'         => Transaction::PENDING,
            'type'           => Transaction::TYPE_PAYMENT,
            'payment_method' => Transaction::PAYMENT_METHOD_AZERICARD,
        ]);
           

        $formParams = $azericard->setOrder($order->id)
            ->setAmount($order->amount)
            ->setMerchantUrl(route('azericard.result',['order' => $order])
            //->debug($request->has('test'))
            ->createOrder();

        return response()->json($formParams);
    }


    public function callback(Azericard $azericard, Request $request)
    {
       $transaction = Trasaction::findByAzericard($request->get(Options::ORDER));
       
       if(!$transaction->isPending()){
           return response()->json(['message' => 'Order already processed'], 409);
       }
       
       DB::beginTransaction();
       
        try 
        {
            if ($azericard->completeOrder($request->all())) 
            {
                $transaction->update([
                    'status'     => Trasaction::SUCCESS,
                    'rrn'        => $request->get(Options::RRN),
                    'int_ref'    => $request->get(Options::RRN),
                    'process_at' => now(),
                ]); 
                
                $transaction->user->increment('balance', $transaction->amount);
                
                DB::commit();
                
                $transaction->user->notify(new TransactionSuccess($transaction));
                
                return response()->json(['message' => 'Order processed successfully'], 200);
            } 
            else 
            {
                $transaction->update([
                    'status' => Trasaction::FAILED,
                    'process_at' => now(),
                ]); 
                
                DB::commit();
                
                logger()->error('Azericard payment failed', $request->all());
                
                return response()->json(['message' => 'Order processed failed'], 500);
            }
        } 
        catch (FailedTransactionException $e) {
            DB::rollBack();
            
            logger()->error('Azericard | Message: '.$e->getMessage(), $request->all());
            //do something
        } 
        catch (AzericardException $e) {
            DB::rollBack();
            //do something
        } 
        catch (Exception $e) {
            DB::rollBack();
        } 
        finally {
            info('Azericard payment callback called', $request->all());
        }
    }
    
    public function refund(Request $request,Azericard $azericard)
    {
        $transaction = Trasaction::findOrFail($request->post('transaction_id'));
        
        try
        {
            $order = Transaction::createForRefund(
                amount : $amount = $request->post('amount'), 
                parent_id: $transaction->id
            );
            
            $data = new RefundData(
                rrn: $transaction->rrn,
                int_ref: $transaction->int_ref,
                created_at: $transaction->process_at
            );

            if ($azericard->setAmount($amount)->setOrder($order->id)->refund($data)) {
                // refund success
            } else {
                // fail
            }
        }
        catch (FailedTransactionException $e) {
            //info($e->getMessage(),$e->getParams());
        } 
        catch (AzericardException $e) {
            // payment fail
        } 
        catch (Exception $e) {
            // payment fail
        }
    }


    public function result($orderId)
    {
        $transaction = Transaction::findByAzericard($orderId);
        
        if($transaction->isSuccess()){
            return view('payment.success');
        } elseif ($transaction->isPending()){
            return view('payment.pending');
        }
        
        return view('payment.failed');
    }
}

```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

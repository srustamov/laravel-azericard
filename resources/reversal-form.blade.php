<form action="{{$params['URL']}}" name="_azericard" method="POST">
    <input name="AMOUNT" value="{{$params['AMOUNT']}}" type="hidden">
    <input name="CURRENCY" value="{{$params['CURRENCY']}}" type="hidden">
    <input name="ORDER" value="{{$params['ORDER']}}" type="hidden">
    <input name="RRN" value="{{$params['RRN']}}" type="hidden">
    <input name="{{$irKeyName}}" value="{{$irValue}}" type="hidden">
    <input name="TERMINAL" value="{{$params['TERMINAL']}}" type="hidden">
    <input name="TRTYPE" value="{{$params['TRTYPE']}}" type="hidden">
    <input name="TIMESTAMP" value="{{$params['TIMESTAMP']}}" type="hidden">
    <input name="NONCE" value="{{$params['NONCE']}}" type="hidden">
    <input name="P_SIGN" value="{{$params['P_SIGN']}}" type="hidden">
    <button type="submit" class="btn">
        Reversal
    </button>
</form>

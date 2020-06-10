<form action="{{$params['URL']}}" name=" _azericard" method="post">
    <input name="AMOUNT" value="{{$params['AMOUNT']}}" type="hidden">
    <input name="CURRENCY" value="{{$params['CURRENCY']}}" type="hidden">
    <input name="LANG" value="{{$params['LANG']}}" type="hidden">
    <input name="ORDER" value="{{$params['ORDER']}}" type="hidden">
    <input name="DESC" value="{{$params['DESC']}}" type="hidden">
    <input name="MERCH_NAME" value="{{$params['MERCH_NAME']}}" type="hidden">
    <input name="MERCH_URL" value="{{$params['MERCH_URL']}}" type="hidden">
    <input name="TERMINAL" value="{{$params['TERMINAL']}}" type="hidden">
    <input name="EMAIL" value="{{$params['EMAIL']}}" type="hidden">
    <input name="TRTYPE" value="{{$params['TRTYPE']}}" type="hidden">
    <input name="COUNTRY" value="{{$params['COUNTRY']}}" type="hidden">
    <input name="MERCH_GMT" value="{{$params['MERCH_GMT']}}" type="hidden">
    <input name="BACKREF" value="{{$params['BACKREF']}}" type="hidden">
    <input name="TIMESTAMP" value="{{$params['TIMESTAMP']}}" type="hidden">
    <input name="NONCE" value="{{$params['NONCE']}}" type="hidden">
    <input name="P_SIGN" value="{{$params['P_SIGN']}}" type="hidden">
    <button type="submit" class="btn btn-red btn-lg">
        Continue to authorization
    </button>
</form>

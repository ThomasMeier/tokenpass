<h3>Transaction details:</h3>
<table>
    <tr>
        <td><strong>Transaction ID:</strong></td>
        <td>{{ $transactionId }}</td>
    </tr>
    <tr>
        <td><strong>Amount:</strong></td>
        <td>{{ $amount }}</td>
    </tr>
    <tr>
        <td><strong>Asset:</strong></td>
        <td>{{ $asset }}</td>
    </tr>
    <tr>
        <td><strong>Input Addresses:</strong></td>
        <td>
            @foreach($input_addresses as $key => $address)
                {{ $address }}
                @if ($key !== count($input_addresses) - 1)
                    ,
                @endif
            @endforeach
        </td>
    </tr>
    <tr>
        <td><strong>Output Addresses:</strong></td>
        <td>
            @foreach($output_addresses as $key => $address)
                {{ $address }}
                @if ($key !== count($output_addresses) - 1)
                    ,
                @endif
            @endforeach
        </td>
    </tr>
    <tr>
        <td><strong>Time:</strong></td>
        <td>{{ $transactionTime }}</td>
    </tr>
    <tr>
        <td><strong>Info link:</strong></td>
        <td><a href="https://xchain.io/tx/{{$transactionId}}">https://xchain.io/tx/{{$transactionId}}</a></td>
    </tr>
</table>
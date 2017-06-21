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
        <td><strong>Input Address:</strong></td>
        <td>{{ $input_address }}</td>
    </tr>
    <tr>
        <td><strong>Output Address:</strong></td>
        <td>{{ $output_address }}</td>
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
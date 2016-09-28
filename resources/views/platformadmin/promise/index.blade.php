@extends('platformAdmin::layouts.app')

@section('title_name') Token Promises @endsection

@section('body_content')

<div class="container" style="margin-top: 3%">
    <div class="row">
        <h1>Token Promises</h1>
    </div>
    <p>
        <strong># Promises:</strong> {{ number_format(count($models)) }}
    </p>
    <table class="u-full-width">
      <thead>
        <tr>
          <th>Destination</th>
          <th>Quantity</th>
          <th>Asset</th>
          <th>Expires</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($models as $model)
        <tr>
          <td>{{ $model['destination'] }}<br>
            <small>(Client ID {{ $model->client_id }}</small>
            </td>
          <td>{{ Tokenly\CurrencyLib\CurrencyUtil::satoshisToValue($model['quantity']) }}</td>
          <td>{{ $model['asset'] }}</td>
          <td>
              @if($model['expiration'] == 0)
                null
              @else
                {{ date('F j\, Y \a\t g:i A', $model['expiration']) }}
             @endif
         </td>
          <td>
            <a class="button button-primary" href="{{ route('platform.admin.promise.edit', ['id' => $model['id']]) }}">Edit</a>

            {{-- inline delete form --}}
            <form onsubmit="return confirm('Are you sure you want to delete this promise balance?')" action="{{ route('platform.admin.promise.destroy', ['id' => $model['id']]) }}" method="POST" style="margin-bottom: 0; display: inline;">
            {!! csrf_field() !!}
            <input type="hidden" name="_method" value="DELETE">
              <button type="submit" class="button-primary">Delete</button>
            </form>

          </td>
        </tr>
        @endforeach
      </tbody>
    </table>

    <div class="row">
      <a href="{{ route('platform.admin.promise.create') }}" class="button button-primary">Create a New Promise</a>
    </div>
</div>


@endsection


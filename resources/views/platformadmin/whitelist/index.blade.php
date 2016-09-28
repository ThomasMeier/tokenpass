@extends('platformAdmin::layouts.app')

@section('title_name') Token Promise Whitelisted Addresses @endsection

@section('body_content')

<div class="container" style="margin-top: 3%">
    <div class="row">
        <h1>Token Promise Whitelisted Addresses</h1>
    </div>
    <p>
        <strong># Addresses: </strong> {{ number_format(count($models)) }}
    </p>
    <table class="u-full-width">
      <thead>
        <tr>
          <th>Address</th>
          <th>Client ID</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($models as $model)
        <tr>
          <td>{{ $model['address'] }}</td>
          <td>{{ $model->client_id }}</td>
          <td>
            <a class="button button-primary" href="{{ route('platform.admin.whitelist.edit', ['id' => $model['id']]) }}">Edit</a>

            {{-- inline delete form --}}
            <form onsubmit="return confirm('Are you sure you want to delete this whitlisted address?')" action="{{ route('platform.admin.whitelist.destroy', ['id' => $model['id']]) }}" method="POST" style="margin-bottom: 0; display: inline;">
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
      <a href="{{ route('platform.admin.whitelist.create') }}" class="button button-primary">Whitelist New Address</a>
    </div>
</div>


@endsection


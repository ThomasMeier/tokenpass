@extends('platformAdmin::layouts.app')

@section('title_name') Pocket Addresses @endsection

@section('body_content')

<div class="container" style="margin-top: 3%">
    <div class="row">
        <h1>Pocket Addresses</h1>
    </div>
    <p>
        <strong># Addresses:</strong> {{ count($models) }}
    </p>
    <div class="form-search">
        <form action="" method="GET">
            <input type="text" name="username" value="{{ Input::get('username') }}" placeholder="Show by username..." /> 
            <input type="submit" value="Go" />
        </form>
    </div>
    <table class="u-full-width">
      <thead>
        <tr>
          <th>Owner</th>
          <th>Address</th>
          <th>Active</th>
          <th>Verified</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($models as $model)
        <tr>
          <td>{{ $model->user()->username }}</td>
          <td><a href="https://blocktrail.com/BTC/address/{{ $model['address'] }}" target="_blank">{{ $model['address'] }}</a>
              @if(trim($model['label']) != '')
                <br><em>{{ $model['label'] }}</em>
              @endif
          </td>
          <td>@if($model['active_toggle'] == 0) No @else Yes @endif</td>
          <td>@if($model['verified'] == 0) No @else Yes @endif</td>
          <td>
            <a class="button button-primary" href="{{ route('platform.admin.address.edit', ['id' => $model['id']]) }}">Edit</a>

            {{-- inline delete form --}}
            <form onsubmit="return confirm('Are you sure you want to delete this address?')" action="{{ route('platform.admin.address.destroy', ['id' => $model['id']]) }}" method="POST" style="margin-bottom: 0; display: inline;">
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
      <a href="{{ route('platform.admin.address.create') }}" class="button button-primary">Add New Address</a>
    </div>
</div>


@endsection


@extends('platformAdmin::layouts.app')

@section('title_name') OAuth Scopes @endsection

@section('body_content')

<div class="container" style="margin-top: 3%">
    <div class="row">
        <h1>OAuth Permission Scopes</h1>
    </div>
    <p>
        <strong># Scopes:</strong> {{ count($models) }}
    </p>
    <table class="u-full-width">
      <thead>
        <tr>
          <th>Label</th>
          <th>Scope ID</th>
          <th>Description</th>
          <th>Notice Level</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($models as $model)
        <tr>
          <td>{{ $model['label'] }}</td>
          <td>{{ $model['id'] }}</td>
          <td>{{ $model['description'] }}</td>
          <td>{{ $model['notice_level'] }}</td>
          <td>
            <a class="button button-primary" href="{{ route('platform.admin.scopes.edit', ['id' => $model['id']]) }}">Edit</a>

            {{-- inline delete form --}}
            <form onsubmit="return confirm('Are you sure you want to delete this scope?')" action="{{ route('platform.admin.scopes.destroy', ['id' => $model['id']]) }}" method="POST" style="margin-bottom: 0; display: inline;">
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
      <a href="{{ route('platform.admin.scopes.create') }}" class="button button-primary">Create a New Scope</a>
    </div>
</div>


@endsection


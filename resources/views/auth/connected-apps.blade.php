@extends('accounts.base')

@section('htmltitle', 'Integrations')

@section('body_class', 'dashboard integrations')

@section('accounts_content')

<section class="title">
  <span class="heading">Integrations</span>
</section>

<section id="connectionEntriesController">
  <ul v-if="entries.length > 0" class="connection_entries" v-cloak>
    <li v-for="entry in entries" class="connection_entry">
      <div class="primary-details">
        <div class="entry-module client-name">
          <div class="title">Client Name</div>
          <div class="details"><strong><a href="@{{ entry.client.app_link }}" target="@{{ entry.client.link_target }}">@{{ entry.client.name }}</a></strong></div>
        </div>
        <div class="entry-module connection-details">
          <div class="title">Connected On</div>
          <div class="details">@{{ formatDate(entry.connection.created_at) }}</div>
        </div>
        <div class="entry-module client-options">
          <div class="title">Options</div>
          <div class="details">
            <a class="option" href="/auth/revokeapp/@{{ entry.client.uuid }}">
              <i class="material-icons">clear</i>
              Revoke
            </a>
            <button href="" class="option" v-on:click="toggleScopes">
              <i class="material-icons">keyboard_arrow_down</i>
              Permissions
            </button>
          </div>
        </div>
      </div>
      <div class="scopes-details" style="display: none;/* needed for jQuery slide */">
        <ul v-if="entry.scopes.length > 0" class="scopes">
          <li v-for="scope in entry.scopes" class="scope" data-level="@{{ scope.notice_level }}">
            <div class="entry-module label">
              <div class="title">Label</div>
              <div class="details">@{{ (scope.label != null) ? scope.label : scope.id }}</div>
            </div>
            <div class="entry-module description">
              <div class="title">Description</div>
              <div class="details">@{{ scope.description }}</div>
            </div>

          </li>
        </ul>
         <p v-else>This connectected application does not have any permissions attached to it.</p>
      </div>
    </li>
  </ul>
  <div class="panel with-padding" v-if="entries.length == 0">
    <p>You don't have any applications connected yet.  Please login at the application and grant authorization when prompted.</p>
  </div>
</section>

@endsection

@section('page-js')
<script>

// Convert php object of key-value pairs into array of balance objects.
var connection_entries = {!! json_encode($connection_entries) !!};

$.each(connection_entries, function(idx, val){
    connection_entries[idx].client.link_target = '_blank';
    if(val.client.app_link == null){
        connection_entries[idx].client.link_target = '';
        connection_entries[idx].client.app_link = '#';
    }
});

var vm = new Vue({
  el: '#connectionEntriesController',
  data: {
    search: '',
    entries: connection_entries
  },
  methods: {
    formatDate: function(dateString){
      var options = {
        year: "numeric", month: "short", day: "numeric"
      };
      return new Date(dateString).toLocaleDateString('en-us', options);
    },
    hideAllScopes: function(){
      $('.connection_entry .scopes-details').hide();
    },
    toggleScopes: function(e){
      var $entry = $(e.target).closest('.connection_entry');
      var $scopes = $entry.find('.scopes-details');
      $scopes.slideToggle();
    }
  },
  ready:function(){
    $(this.el).find(['v-cloak']).slideDown();
  }
});

</script>
@endsection

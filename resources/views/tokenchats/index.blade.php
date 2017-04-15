@extends('accounts.base')

@section('htmltitle', 'Token Chats')

@section('body_class', 'dashboard tokenchats')

@section('accounts_content')

<div id="chatsController">

<section class="title">
  <span class="heading">Token Chats</span>
  <button v-on:click="setCurrentChat(newChat())" data-modal="addChatModal" class="btn-dash-title add-chat-btn reveal-modal">+ Add Token Chat</button>
</section>

<section>
  <div class="panel with-padding">
    <p>
      Token Chats are chat rooms accessible only by users who hold a token.
    </p>
  </div>

    <h3>My Token Chats</h3>
  <div class="panel with-padding">
    <table class="table table--responsive" v-cloak>
      <thead>
        <tr>
          <th width="15%">Name</th>
          <th width="25%">Required Tokens</th>
          <th width="10%">Status</th>
          <th width="12%">Created</th>
          <th width="38%"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(index, chat) in chats">
          <td width="15%"><strong>@{{ chat.name }}</strong></td>
          <td width="25%">
            <span v-for="(tcrindex, tca_rule) in chat.simple_tca_rules"><template v-if="tcrindex > 0">, </template>@{{ tca_rule.quantity }} @{{ tca_rule.token }}</span>
          </td>
          <td width="10%">
            <span :class="{ active: chat.active, inactive: !chat.active }">@{{ chat.active ? 'Active' : 'Inactive' }}</span>
          </td>
          <td width="12%">@{{ formatDate(chat.created_at) }}</td>
          <td width="38%">
            <button class="reveal-modal" data-modal="viewChatModal" v-on:click="setCurrentChat(chat)" ><i class="material-icons">open_in_browser</i> More Details</button>
          
            <button class="reveal-modal" data-modal="editChatModal" v-on:click="setCurrentChat(chat)" ><i class="material-icons">edit</i> Edit</button>
            <form :id="'DeleteTokenChat_'+index" class="inline" action="/tokenchats/delete/@{{ chat.uuid }}" method="POST">
                {!! csrf_field() !!}
                <input type="hidden" name="_method" value="DELETE">
                <a href="#" v-on:click="submitDeleteForm('DeleteTokenChat_'+index)"><i class="material-icons">delete</i> Delete</a>
            </form>
          </td>
        </tr>
      </tbody>
    </table>
  </div>


  <!-- NEW CHAT MODAL -->
  <div class="modal-container" id="addChatModal">
    <div class="modal-bg"></div>
    <div class="modal-content">
      <h3>Create Token Chat</h3>
      <div class="modal-x close-modal">
        <i class="material-icons">clear</i>
      </div>

      <form class="js-auto-ajax" action="/tokenchats/new" method="POST">
                {!! csrf_field() !!}

            <div class="error-placeholder panel-danger"></div>

        <label for="chat_name">Chat Name:</label>
        <input type="text" name="name" id="chat_name" placeholder="{{ date("F") }} AMA" required/>
                
        <label>Access Tokens (Quantity and Asset Name)</label>
        <template v-for="tca_rule in currentChat.simple_tca_rules">
          <div class="input">
            <input v-model="tca_rule.quantity" class="inline inline-20" type="text" name="quantity"  placeholder="10" />
            <input v-model="tca_rule.token" class="inline inline-80" type="text" name="token" placeholder="MYCOIN" />
          </div>
        </template>
        <input type="hidden" name="tca_rules" v-bind:value="serializeTcaRules">
        <a href="javascript:void(0);" v-on:click="addTcaRule"><small>+ Add Access Token</small></a>
        <div class="spacer1">&nbsp;</div>

        @if ($user->hasPermission('globalChats'))
          <label for="global">Global Chat</label>
          <input type="hidden" name="global" value="0">
          <input type="checkbox" id="global" name="global" value="1" v-bind:checked="currentChat.global == 1">

          <p>Note: Leave Token and Quantity blank for global chats.</p>
        @endif

        <button type="submit" class="">Submit</button>

      </form>
    </div>
  </div> <!-- END NEW CHAT MODAL -->

  <!-- VIEW CHAT MODAL -->
  <div class="modal-container" id="viewChatModal">
    <div class="modal-bg"></div>
    <div class="modal-content">
      <h3>Token Chat Details</h3>
      <div class="modal-x close-modal">
        <i class="material-icons">clear</i>
      </div>

      <div class="input-group">
        <label>Chat Name:</label>
        <div class="name">
          @{{ currentChat.name }}
        </div>
      </div>

            <div class="input-group">
                <label>Access Tokens Assigned:</label>
                <div class="token" v-for="tca_rule in currentChat.simple_tca_rules">
                    @{{ tca_rule.quantity }} @{{ tca_rule.token }}
                </div>
            </div>

    </div>
  </div> <!-- END VIEW CHAT MODAL -->

  <!-- EDIT CHAT MODAL -->
  <div class="modal-container" id="editChatModal">
    <div class="modal-bg"></div>
    <div class="modal-content">
      <h3>Update Token Chat</h3>
      <div class="modal-x close-modal">
        <i class="material-icons">clear</i>
      </div>

      <form class="js-auto-ajax" action="/tokenchats/edit/@{{ currentChat.uuid }}" method="POST">
              {!! csrf_field() !!}
        <div class="error-placeholder panel-danger"></div>


        <label>Access Tokens (Quantity and Asset Name)</label>
        <template v-for="tca_rule in currentChat.simple_tca_rules">
          <div class="input">
            <input v-model="tca_rule.quantity" class="inline inline-20" type="text" name="quantity"  placeholder="10" />
            <input v-model="tca_rule.token" class="inline inline-80" type="text" name="token" placeholder="MYCOIN" />
          </div>
        </template>
        <input type="hidden" name="tca_rules" v-bind:value="serializeTcaRules">
        <a href="javascript:void(0);" v-on:click="addTcaRule"><small>+ Add Access Token</small></a>
        <div class="spacer1">&nbsp;</div>

        <label for="active">Active</label>
        <input type="hidden" name="active" value="0">
        <input type="checkbox" id="active" name="active" value="1" v-bind:checked="currentChat.active == 1">

        @if ($user->hasPermission('globalChats'))
          <label for="global_e">Global Chat</label>
          <input type="hidden" name="global" value="0">
          <input type="checkbox" id="global_e" name="global" value="1" v-bind:checked="currentChat.global == 1">

          <p>Note: Leave Token and Quantity blank for global chats.</p>
        @endif

          <button type="submit">Save</button>
      </form>


    </div>

  </div> <!-- END EDIT CHAT MODAL -->
</section>

</div>
@endsection

@section('page-js')
<script>
(function() {

function formatSatoshis(x) {
    return Math.round(x) / 100000000
}

function applySimpleTcaRulesToChats(chats) {
  for (var x = 0; x < chats.length; x++) {
    var chat = chats[x]
    chat.simple_tca_rules = []
    for (var i = 0; i < chat.tca_rules.length; i++) {
      var tca_rule = chat.tca_rules[i]
      chat.simple_tca_rules.push({quantity: formatSatoshis(tca_rule.amount), token: tca_rule.asset})
    }
  }
  return chats
}

var chats = applySimpleTcaRulesToChats({!! json_encode($chats) !!});

var vm = new Vue({
  el: '#chatsController',
  data: {
    chats: chats,
    currentChat: {}
  },
  methods: {
    bindEvents: function(){
      $('form.js-auto-ajax').on('submit', this.submitFormAjax);
    },
    setCurrentChat: function(chat){
      this.currentChat = chat;
      console.log('setCurrentChat this.currentChat=', this.currentChat);
    },
    newChat: function() {
      return {
        simple_tca_rules: [{quantity: '', token: ''}]
      }
    },
    formatDate: function(dateString){
      var options = {
          year: "numeric", month: "short", day: "numeric"
      };
      return new Date(dateString).toLocaleDateString('en-us', options);
    },
    formatNumber: function(x) {
        return Math.round(x*100000000) / 100000000
    },
    formatSatoshis: function(x) { return formatSatoshis(x); },
    submitDeleteForm: function(formId) {
        if (confirm('Are you sure you want to delete this Chat?')) {
            document.getElementById(formId).submit();
        }
    },
    submitFormAjax: function(e){
      e.preventDefault();
      var $form = $(e.target);
      var formUrl = $form.attr('action');
      var formMethod = $form.attr('method');
      var formString = $form.serialize();
      console.log(formUrl);
      console.log(formMethod);
      console.log(formString);
      var errorTimeout = null;
      // clear the error
      $('.error-placeholder', $form).empty();
      if (errorTimeout) { clearTimeout(errorTimeout); }

      $.ajax({
        type: formMethod,
        url: formUrl,
        data: formString,
        dataType: 'json'
      }).done(function(data) {
        console.log(data);
        // success - redirect
        if (data.redirectUrl != null) {
          window.location = data.redirectUrl;
        }
      }).fail(function(data, status, error) {
        console.log(data);
        console.log(status);
        console.log(error);
        // failure - show an error.
        var errorMsg = '';
        if (data.responseJSON != null && data.responseJSON.error != null) {
          errorMsg = data.responseJSON.error;
        } else {
          errorMsg = 'There was an unknown error';
        }

        // show the error
        $('.error-placeholder', $form).html(errorMsg);
        errorTimeout = setTimeout(function() {
          $('.error-placeholder', $form).empty();
          errorTimeout = null;
        }, 10000);
      });
    },
    addTcaRule: function() {
      if (this.currentChat != null && this.currentChat.simple_tca_rules != null) {
        this.currentChat.simple_tca_rules.push({quantity: '', token: ''})
      }
      console.log('addTcaRule...', this.currentChat.simple_tca_rules);
    }
  },
  computed: {
    serializeTcaRules: function() {
      var out = []
      for (var i = 0; i < this.currentChat.simple_tca_rules.length; i++) {
        var tca_rule = this.currentChat.simple_tca_rules[i]
        out.push({quantity: tca_rule.quantity, token: tca_rule.token})
      }

      return JSON.stringify(out)
    }
  },
  ready:function(){
    this.bindEvents();
    $(this.el).find(['v-cloak']).slideDown();
  }
});

// Initialize new chat modal
var addChatModal = new Modal();
addChatModal.init(document.getElementById('addChatModal'));

// Initialize view chat modal
var viewChatModal = new Modal();
viewChatModal.init(document.getElementById('viewChatModal'));

// Initialize edit chat modal
var editChatModal = new Modal();
editChatModal.init(document.getElementById('editChatModal'));

})();
</script>
@endsection

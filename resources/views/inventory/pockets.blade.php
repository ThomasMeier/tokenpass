@extends('accounts.base')

@section('htmltitle', 'Pockets')

@section('body_class', 'dashboard pockets')

@section('accounts_content')

<div id="pocketsController">
  <div id="verifyPocketModal" class="modal-container">
    <div class="modal-bg"></div>
    <div class="modal-content">
      <h3 class="light">Verify Pocket</h3>
      <div class="modal-x close-modal">
        <i class="material-icons">clear</i>
      </div>
      <p>
        In order for Tokenpass to track your address balances
        and provide access to "Token Controlled Access" features, you must 
        first prove ownership of this bitcoin address. 
      </p>

      <h4 class="light">Pay to verify</h4>
      <p>To verify your bitcoin address, do a payment of any amount to the following address:</p>
      <input type="text" id="verify-sig" readonly value="@{{ currentPocket.verify_address }}" />

      <h4 class="light">Alternative Method</h4>
      <p>
        To verify address ownership, open up your Counterparty compatible Bitcoin wallet
        and use the <strong>Sign Message</strong> feature.
      </p>
      <p>
        Sign the verification code below and submit the resulting
        signature. 
      </p>

      <form class="js-auto-ajax" action="/inventory/address/@{{ currentPocket.address }}/verify" method="POST" id="verify-form-@{{ currentPocket.address }}">
        {!! csrf_field() !!}

        <div class="error-placeholder panel-danger"></div>

        <label for="verify-code">Verification Code</label>
        
        <input type="text" id="verify-code" value="@{{ currentPocket.secure_code }}" onclick="this.select();" readonly>

        <label for="verify-address">BTC Address:</label>
        <input type="text" id="verify-sig" readonly value="@{{ currentPocket.address }}" />
        
<!--         <label for="verify-sig">Enter Message Signature:</label>
        <textarea name="sig" id="verify-sig" rows="8" required onClick="this.select();"></textarea>
         <a href=>Sign with Pockets</a> -->

        <div class="signature__wrapper">
          <textarea name="sig" id="verify-sig" placeholder="cryptographic signature" rows="4"></textarea>
          <a class="signature__cts" href="{{ env('POCKETS_URI') }}:sign?message=@{{ encodeURIComponent(currentPocket.secure_code) }}&label={{ str_replace('+', '%20', urlencode('Prove ownership of pocket address for Tokenpass')) }}&callback=@{{ encodeURIComponent(currentPocket.click_origin + '/inventory/address/' + currentPocket.address + '/click-verify?msg_hash=' + currentPocket.msg_hash) }}">
              <img src="/img/pockets-icon-64-light.png" alt="Pockets Icon" width="36px" style="margin-right: 15px">
            Sign with Pockets
          </a>
        </div>

        <button type="submit">Verify</button>

      </form>
    </div>
  </div> <!-- End Verify Modal  -->

  <div id="addPocketModal" class="modal-container">
    <div class="modal-bg"></div>
    <div class="modal-content">
      <h3 class="light">Register New Address</h3>
      <div class="modal-x close-modal">
        <i class="material-icons">clear</i>
      </div>
      <form class="js-auto-ajax" action="/inventory/address/new" method="POST">
        {!! csrf_field() !!}

        <div class="error-placeholder panel-danger"></div>

        <label for="address">BTC Address *</label>
        <input type="text" name="address" placeholder="1F1tAaz5x1HUXrCNLbtMDqcw6o5GNn4xqX" required>

        <label for="label">Reference Label</label>
        <input type="text" name="label" placeholder="Tokenly Wallet">

        <div class="input-group">
          <label>Make this pocket public? *</label>
          <input id="pocket-new-public" class="toggle toggle-round-flat" type="checkbox" name="public" checked>
          <label for="pocket-new-public"></label>
        </div>

        <button type="submit">Submit</button>
      </form>

      <div class="or-divider-module">
        <div class="divider">.</div>
        <div class="or">or</div>
        <div class="divider">.</div>
      </div>

      <h3 class="light">Register With Mobile</h3>
      <p>
        If you are using a bitcoin wallet on your mobile device (e.g <a href="https://wallet.indiesquare.me/" target="_blank">IndieSquareWallet</a>) which
        supports <strong>Tokenpass Instant Address Verification</strong>, you may scan the 
        <strong>QR code</strong> below to register and 
        verify ownership of your bitcoin address in a single step.
      </p>
      <p class="text-center">
        <?php
        $verify_message = \Tokenpass\Models\Address::getInstantVerifyMessage($user);
        ?>
        <span title="Scan with your mobile device" id="instant-address-qr" data-verify-message="{{ $verify_message }}">
          <?php echo QrCode::size(200)->generate(route('api.instant-verify', $user->username).'?msg='.$verify_message); ?>
        </span>
        <a class="signature__cts" href="pockets:sign?message={{ $verify_message }}&label=Instant Register Address with Tokenpass&callback={{ urlencode(route('api.instant-verify', $user->username).'?msg='.$verify_message) }}">
            <img src="/img/pockets-icon-64-light.png" alt="Pockets Icon" width="36px" style="margin-right: 15px">
            Click To Register
        </a>        
      </p>
    </div>
  </div> <!-- End Add Pocket Modal  -->

  <section class="title">
    <span class="heading">Pockets</span>
    <div class="search-wrapper">
      <input type="text" placeholder="Search for a pocket..." v-model="search">
      <div class="icon"><i class="material-icons">search</i></div>
    </div>
    <button data-modal="addPocketModal" class="btn-dash-title add-pocket-btn reveal-modal">+ Add Pocket</button>
  </section>

  <section id="pocketsList" class="pockets" v-cloak>
    <div class="panel with-padding">
      <p>A Pocket is a Token Compatible Bitcoin Address that can be used to store and use Access Tokens - Once you've verified your pockets, Tokenpass keeps track of what access tokens you own at any given time and passes that information along to websites, integrations and applications each user chooses to authorize.</p>
      <p>Need a Token Compatible Wallet? Visit <a href="http://pockets.tokenly.com" target="_blank">http://pockets.tokenly.com</a> to download yours free today.</p>
    </div>
    <div v-if="pockets.length">
      <div class="pocket" v-for="pocket in pockets | filterBy search" id="pocket-@{{ pocket.address }}" data-pocket-index="@{{ $index }}">
        <div class="pocket-main">
          <div class="pocket-indicator">
            <div class="loading"></div>
            <div class="active-indicator">
              <div v-if="pocket.verified">
                <div v-if="pocket.active_toggle">
                  <i class="material-icons text-success">check</i>
                </div>
                <div v-else>
                  <i class="material-icons text-danger">close</i>
                </div>
              </div>
              <div v-else>
                <i class="material-icons text-warning">warning</i>
              </div>
            </div>
          </div>
          <div class="primary-info">
            <span class="name">
              @{{ pocket.label || 'n/a' }}
            </span>
            <span class="address"><a href="https://blocktrail.com/BTC/address/@{{ pocket.address }}" target="_blank" title="View on Block Explorer">@{{ pocket.address }}</a></span>
          </div>
          <div v-on:click="toggleEdit" class="settings-btn">  
            <i class="material-icons" title="Edit address settings">settings</i>
          </div>
          <div data-modal="verifyPocketModal" v-on:click="setCurrentPocket(pocket, true)" v-show="!pocket.verified" class="verify-btn reveal-modal">
            Verify
          </div>
          <div class="clear"></div>
        </div><!-- End Pocket Information -->
        <div class="pocket-settings">
          <form v-on:submit="editPocket" action="/inventory/address/@{{ pocket.address }}/edit" method="POST">
            {!! csrf_field() !!}

            <div class="error-placeholder panel-danger"></div>

            <label for="">Label</label>
            <input type="text" name="label" v-model="pocket.label" placeholder="Tokenly Wallet">

            <label for="">Address</label>
            <input type="text" name="address" value="@{{ pocket.address }}" readonly>
            
            <label for="">Notes</label>
            <textarea placeholder="Use this field for personal notes about this pocket. This will not affect the pocket in any way." name="notes">@{{ pocket.notes }}</textarea>
            <div class="toggles-container">
              <div class="input-group toggle-field">
                <label>Active?</label>
                <input id="pocket-@{{ $index }}-active" name="active" type="checkbox" class="toggle toggle-round-flat" v-model="pocket.active_toggle" value=1>
                <label for="pocket-@{{ $index }}-active"></label>
              </div>

              <div class="input-group toggle-field">
                <label>Public?</label>
                <input id="pocket-@{{ $index }}-public" name="public" type="checkbox" class="toggle toggle-round-flat" v-model="pocket.public" value=1>
                <label for="pocket-@{{ $index }}-public"></label>
              </div>
              <div class="input-group toggle-field">
                <label>Enable for login?</label>
                <input id="pocket-@{{ $index }}-login" name="login" type="checkbox" class="toggle toggle-round-flat" v-model="pocket.login_toggle" value=1 :disabled="pocket.second_factor_toggle == 1" >
                <label for="pocket-@{{ $index }}-login"></label>
              </div>
              <div class="input-group toggle-field">
                <label>Enable as Second Factor?</label>
                <input id="pocket-@{{ $index }}-second-factor" name="second_factor" type="checkbox" class="toggle toggle-round-flat" v-model="pocket.second_factor_toggle" value=1 :disabled="pocket.login_toggle == 1">
                <label for="pocket-@{{ $index }}-second-factor"></label>
              </div>
              <div class="tooltip-wrapper" data-tooltip="Please choose either Login or 2FA">
                <i class="help-icon material-icons">help_outline</i>
              </div>
            </div>
            <button type="submit"
              class="btn-save">
              Save
            </button>
            <a v-on:click="deletePocket(pocket)" 
              class="btn-delete">
              Delete
            </a>
          </form>
        </div> <!-- End Pocket Settings -->
      </div> <!-- End Pocket List -->
    </div>
  </section>
</div>

@endsection

@section('page-js')
<script>

var pockets = {!! json_encode($addresses) !!};
window.click_origin = window.location.protocol + '//' + window.location.hostname;
var vm = new Vue({
  el: '#pocketsController',
  data: {
    search: '',
    pockets: pockets,
    currentPocket: {}
  },

  methods: {
    bindEvents: function(){
      $('form.js-auto-ajax').on('submit', this.submitFormAjax);
    },
    toggleEdit: function(event){
      var $pocket = $(event.target).closest('.pocket');
      var $settingsButton = $pocket.find('.settings-btn i');
      if ($pocket.hasClass('editing')) {
        $pocket.removeClass('editing');
        $settingsButton.text('settings')
      } else {
        $pocket.addClass('editing');
        $settingsButton.text('cancel')
      }
    },

    setCurrentPocket: function(pocket, for_verify){
      if (typeof for_verify == 'undefined') { for_verify = false; }
      pocket.click_origin = window.click_origin;
      vm.currentPocket = pocket;
      if(for_verify){
          startSigWatch();
      }
    },
    startLoading: function(pocket){
      var $indicator = $('#pocket-' + pocket.address).find('.pocket-indicator');
      $indicator.addClass('is-loading');
    },
    endLoading: function(pocket){
      var $indicator = $('#pocket-' + pocket.address).find('.pocket-indicator');
      $indicator.removeClass('is-loading');
    },

    editPocket: function(e){
      e.preventDefault();

      var $form = $(e.target);
      var pocketIndex = $form.closest('.pocket').attr('data-pocket-index');
      var pocket = this.pockets[pocketIndex];
      this.startLoading(pocket);

      var formUrl = $form.attr('action');
      var formMethod = $form.attr('method');
      var formString = $form.serialize();
      var errorTimeout = null;


      $.ajax({
        type: formMethod,
        url: formUrl,
        data: formString,
        dataType: 'json'
      }).done(function(data) {
        vm.endLoading(pocket);
        console.log(data);
        console.log('Pocket was updated successfully (' + $form.attr('data-address') + ').');
      }).fail(function(data, status, error) {
        vm.endLoading(pocket);
        console.log(data);
        console.log('There was an error updating this pocket (' + $form.attr('data-address') + ').')
      });
    },
    deletePocket: function(pocket){
      this.startLoading(pocket);
      if (confirm('Are you sure you want to delete this pocket?')) {
        var url = '/inventory/address/' + pocket.address + '/delete';
        $.ajax({
          type: 'GET',
          url: url,
          dataType: 'json'
        }).done(function(data) {
          vm.endLoading(pocket);
          vm.pockets.$remove(pocket);
          console.log('Pocket was deleted successfully (' + pocket.address + ').');
        }).fail(function(data, status, error) {
          vm.endLoading(pocket);
          console.log('There was an error deleting this pocket (' + pocket.address + ').')
        });
      } else {
        vm.endLoading(pocket);
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
    }
  },
 ready:function(){
    this.bindEvents();
    $(this.el).find(['v-cloak']).slideDown();
  }
});

// Initialize new address modal
var addPocketModal = new Modal();
addPocketModal.init(document.getElementById(
  'addPocketModal'));

// Initialize verify address modal
var verifyPocketModal = new Modal();
verifyPocketModal.init(document.getElementById(
  'verifyPocketModal'));

window.inventory_refresh_check = false;
$('.add-pocket-btn').click(function(e){
    if(!window.inventory_refresh_check){
        window.inventory_refresh_check = setInterval(function(){
            var url = '/inventory/check-refresh';
            $.get(url, function(data){
                if(data.result){
                    location.reload();
                }
            });
        }, 1000);
    }
});

function startSigWatch(){
    window.checkSigInterval = setInterval(function(){
        $.get('{{ route("inventory.check-sig", array()) }}?current_address='+window.vm.currentPocket.address, function(data){
           if(typeof data.results != 'undefined' && data.results.length > 0){
               var hit = false;
                $.each(data.results, function(idx, val){
                    if(!hit){
                        $('#verify-form-' + val.address).find('textarea[name="sig"]').val(val.signature);
                        $('#verify-form-' + val.address).find('button[type="submit"]').click();
                        var hit = true;
                    }
                });
           } else if(data.success && data.success === true && data.redirectUrl !== null) {
                window.location = data.redirectUrl;
           }
        });
    }, 1000);
}
</script>

@endsection

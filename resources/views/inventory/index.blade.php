@extends('accounts.base')

@section('htmltitle', 'Inventory')

@section('body_class', 'dashboard inventory')

@section('accounts_content')

<div id="tokensController">
  <div id="promiseInfoModal" class="modal-container">
    <div class="modal-bg"></div>
    <div class="modal-content">
      <h3 class="light">What is a Token Promise?</h3>
      <div class="modal-x close-modal">
        <i class="material-icons">clear</i>
      </div>
      <p>A promised balance is made up of tokens you have the benefit of, but don't actually hold in one of your pockets.</p>

      <p>You might have already bought them, but they haven't yet been delivered via the bitcoin network or maybe someone has lent you the use of one of their tokens temporarily.</p>

      <p>Your Promised tokens balance is added with the tokens you possess in your pockets to create your total balance.</p>

      <p>Your Total Balance is what is used for Token Controlled Access (TCA)</p>
      <button class="close-modal">Close</button>
    </div>
  </div> <!-- End promise info modal -->

  <div id="lendTokenModal" class="modal-container">
    <div class="modal-bg"></div>
    <div class="modal-content">
      <h3 class="light">Lend Token Access Rights</h3>
      <div class="modal-x close-modal">
        <i class="material-icons">clear</i>
      </div>
      <p>
        Use this form to lend out your Token Access (TCA) rights to another Tokenpass user for a specified period of time.
        You may lend out only up to your "Real Balance" in each pocket, and your TCA loans will automatically become invalidated
        if your balance falls below the loan total. This is a free service.
      </p>
      <form method="POST" action="/inventory/lend/@{{ currentPocket.address }}/@{{ currentToken.name }}">
        <p>
            <strong>Pocket:</strong> @{{ currentPocket.address }}
        </p>
        <div class="input-group">
          <label for="lendee">Who would you like to lend to? *</label>
          <input type="text" id="lendee" name="lendee" required>
          <div class="sublabel">Tokenpass username or bitcoin address</div>
        </div>

        <div class="outer-container">
          <div class="input-group span-4">
            <label for="quantity">Quantity *</label>
            <input type="text" name="quantity" data-inputmask="'alias': 'numeric', 'groupSeparator': ',', 'autoGroup': true, 'digits': 8, 'digitsOptional': false, 'placeholder': '0'" placeholder="10.5" required>
            <div class="sublabel">You can lend up to @{{ formatQuantity(currentPocket.real) }} @{{ currentToken.name }} in this pocket</div>
          </div>
          <div class="input-group span-8">
            <label for="token">Token To Lend</label>
            <input type="text" value="@{{ currentToken.name }}" disabled readonly>
          </div>
        </div>

        <div class="outer-container">
          <input name="end_date" v-model="lendCreateEndDateTime" type="hidden">

          <div class="input-group span-6">
            <label for="end_date">Expiration Date</label>
            <input v-model="lendCreateEndDate" type="date" placeholder="yyyy-mm-dd" v-on:change="setDateTime('lendCreateEndDateTime', lendCreateEndDate, lendCreateEndTime)">
          </div>

          <div class="input-group span-6">
            <label for="end_time">Expiration Time</label>
            <input v-model="lendCreateEndTime" type="num" value="00:00" placeholder="hh:mm" v-on:change="setDateTime('lendCreateEndDateTime', lendCreateEndDate, lendCreateEndTime)">
            <div class="sublabel">24 Hour Time</div>
          </div>
        </div>

        <div class="input-group">
          <label for="note">Note</label>
          <input type="text" id="note" name="note" placeholder="Check out this new song on Tokenly Music!">
          <div class="sublabel">Reason for lending</div>
        </div>
        <div class="input-group">
          <label for="show_as">Show loan source as:</label>
          <select name="show_as" id="show_as">
            <option value="username">Username</option>
            <option value="address">Pocket Address</option>
          </select>
        </div>
        <button type="submit">Submit</button>
      </form>
    </div>
  </div> <!-- End lend token modal  -->
  
  
  <div id="editLoanModal" class="modal-container">
    <div class="modal-bg"></div>
    <div class="modal-content">
      <h3 class="light">Modify TCA Loan</h3>
      <p>
        <strong>ID:</strong> #@{{ currentLoan.id }}<br>
        <strong>Created:</strong> @{{ currentLoan.created_at }}<br>
        <strong>Updated:</strong> @{{ currentLoan.updated_at }}<br>
        <strong>Expiration:</strong> <em>@{{ utcToDate(currentLoan.expiration) }}</em><br>
        <strong>Source Pocket:</strong> @{{ currentLoan.source }}<br>
        <strong>Lendee:</strong> @{{ currentLoan.destination }}<br>
        <strong>Amount: </strong> @{{ formatQuantity(currentLoan.quantity) }} @{{ currentLoan.asset }}
      </p>
      <p v-if="currentLoan.note != ''">
        <strong>Note:</strong> @{{ currentLoan.note }}
      </p>
      <div class="modal-x close-modal">
        <i class="material-icons">clear</i>
      </div>
      <form method="POST" action="/inventory/lend/@{{ currentLoan.id }}/edit">
        <div class="outer-container">
          <input name="end_date" v-model="lendEditEndDateTime" type="hidden">

          <div class="input-group span-6">
            <label>Expiration Date</label>
            <input v-model="lendEditEndDate" type="date" placeholder="yyyy-mm-dd" v-on:change="setDateTime('lendEditEndDateTime', lendEditEndDate, lendEditEndTime)">
          </div>
          <div class="input-group span-6">
            <label>Expiration Time</label>
            <input v-model="lendEditEndTime" type="num" value="00:00" placeholder="hh:mm" v-on:change="setDateTime('lendEditEndDateTime', lendEditEndDate, lendEditEndTime)">
            <div class="sublabel">24 Hour Time</div>
          </div>
        </div>

        <div class="input-group">
          <label for="show_as">Show loan source as:</label>
          <select name="show_as" id="show_as" v-model="currentLoan.show_as">
            <option value="username">Username</option>
            <option value="address">Pocket Address</option>
          </select>
        </div>
        <button type="submit">Submit</button>
      </form>
    </div>
  </div> <!-- End edit tca loan modal  -->  

	<section class="title">
		<span class="heading">Inventory</span>
	  <div class="search-wrapper">
	    <input type="text" placeholder="Search for a token..." v-model="search">
	    <div class="icon"><i class="material-icons">search</i></div>
	  </div>
	  <a href="/inventory/refresh" class="btn-dash-title">
	  	<i class="material-icons">refresh</i>Refresh Token Balances
		</a>
	</section>
	<section class="tokens" v-cloak>
    <div v-if="tokens.length">
      <div class="panel-pre-heading">
        My Tokens / 
        <span style="font-size: 0.8em" class="reveal-modal click-me" data-modal="promiseInfoModal">
          * contains promised tokens
        </span>
      </div>
  	  <div class="token" v-for="token in tokens | filterBy search">
  	    <!-- TODO: Token's have avatars
      	<div class="avatar"><img src="http://lorempixel.com/25/25/?t=1"></div> 
      	-->

  	    <div class="primary-info">
          <div class="token-indicator">
            <input v-on:change="toggleActive(token)" v-model="token.toggle" class="toggle toggle-round-flat" id="token-@{{ token.name }}" type="checkbox">
            <label for="token-@{{ token.name }}"></label>
          </div>
          <div class="token-info">
            <span class="quantity">
              <span class="quantity-star" v-if="token.hasPromisedTokens || token.hasLoanedTokens">*</span>
              <span class="muted">@{{ formatQuantity(token.balance) }}</span>
            </span>
    	    	<span class="nickname">
              <a href="/token/@{{ token.name }}">@{{ token.name }}</a>
        		</span>
          </div>
          <div class="token-actions">
            <div v-on:click="toggleSecondaryInfo" class="detail-toggle">
              Balance Breakdown
              <i class="material-icons">keyboard_arrow_down</i>
            </div>
          </div>
          <div class="clear"></div>
        </div>

        <div class="secondary-info" style="display: none;/* needed for jQuery slide */">
          <div v-for="pocket in token.balanceAddresses" class="pocket">
            <div class="detail-heading">@{{ pocket.label }}</div>
            <div class="pocket-details-main">
              <!-- Heading -->
              <div class="pocket-heading">
                <span class="muted">Address /</span>
                <a href="https://blocktrail.com/BTC/address/@{{ pocket.address }}" target="_blank">@{{ pocket.address }}</a>              
              </div>
              <div class="pocket-promised-balance">
                <span class="muted">Total /</span>
                @{{ formatQuantity(pocket.total) }}
              </div>
            </div>

            <div class="pocket-details-second">
              <!-- Real Balance -->
              <div class="pocket-real-balance">
                <span class="muted">Real Balance /</span>
                @{{ formatQuantity(pocket.real) }}
                <span v-if="pocket.real > 0">
                    <a v-on:click="setCurrentToken(token, pocket)" class="detail-toggle reveal-modal" data-modal="lendTokenModal" style="cursor: pointer;">
                       Lend
                       <i class="material-icons">share</i>
                    </a>
                </span>                  
              </div>
              <!-- Promised transactions -->
              <div v-if="pocket.provisional.length > 0">
                <!-- <div class="detail-subheading">Promised Transactions</div> -->
                <div class="pocket-promised-balance">
                  <span class="muted">Promised Balance /</span>
                  <span class="text-success">@{{ formatQuantity(pocket.provisional_total) }}</span>
                </div>
                <div class="pocket-promised-table-wrapper">
                  <table class="table">
                    <thead>
                      <tr>
                        <th>Source</th>
                        <th>Amount</th>
                        <th>Expires</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="promise in pocket.provisional">
                        <td class="muted">@{{ promise.source }}</td>
                        <td>@{{ formatQuantity(promise.quantity) }}</td>
                        <td><span title="@{{ formatDate(promise.expiration) }}">@{{ relativeTime(promise.expiration) }}</span></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div v-if="pocket.loans.length > 0">
                <div class="pocket-loaned-balance">
                    <span class="muted">Loaned Balance /</span>
                    <span class="text-danger">@{{ formatQuantity(pocket.loan_total) }}</span>
                </div>
                <div class="pocket-loaned-table-wrapper pocket-promised-table-wrapper">
                  <table class="table">
                    <thead>
                      <tr>
                        <th>Lendee</th>
                        <th>Amount</th>
                        <th>Expires</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="loan in pocket.loans">
                        <td class="muted">@{{ loan.destination }}</td>
                        <td>@{{ formatQuantity(loan.quantity) }}</td>
                        <td><span title="@{{ formatDate(loan.expiration) }}">@{{ relativeTime(loan.expiration) }}</span>
                            <a href="/inventory/lend/@{{ loan.id }}/delete" class="delete-loan"><i class="material-icons text-danger" title="Remove TCA loan">cancel</i></a>                        
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>                
              </div>
            </div>
          </div>
        </div>
  		</div> <!-- END TOKEN -->
    </div>
    <div v-else>  
      <p v-if="getVerifiedPocket()">
        Buy some tokens with pocket
        <span v-if="getVerifiedPocket().label">
          <strong>@{{ getVerifiedPocket().label }}</strong>
          (<span class="muted">@{{ getVerifiedPocket().address }}</span>)
        </span>
        <span v-else>
          <strong>@{{ getVerifiedPocket().address }}</strong>
        </span>
        and they'll show up here.
      </p>
      <p v-else>
        Add a verified pocket to fill your token inventory <a href="/pockets">here</a>.
      </p>
    </div>
	</section>

  <section v-if="loans.length" v-cloak>
    <div class="panel-pre-heading">Token Access Loans / <span class="muted">Active Loans:</span> @{{ loans.length }}</div>
    <div class="panel with-padding">
      <table class="table table--responsive">
        <thead>
          <tr>
            <th>Source Pocket</th>
            <th>Lendee</th>
            <th>Amount</th>
            <th>Expires</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <tr class="loan" v-for="loan in loans">
            <td>@{{ loan.source }}</td>
            <td>@{{ loan.destination }}</td>
            <td>@{{ formatQuantity(loan.quantity) }} @{{ loan.asset }}</td>
            <td><span title="@{{ formatDate(loan.expiration) }}">@{{ relativeTime(loan.expiration) }}</span></td>
            <td>
              <a href="#" class="edit-loan reveal-modal click-me" data-modal="editLoanModal" v-on:click="setCurrentLoan(loan)"><i class="material-icons text-success" title="Modify TCA loan">edit</i></a>
              <a href="/inventory/lend/@{{ loan.id }}/delete" class="delete-loan"><i class="material-icons text-danger" title="Remove TCA loan">cancel</i></a>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</div>
@endsection

@section('page-js')
<script>

$('body').delegate('.edit-loan', 'click', function(e){
    e.preventDefault();
});

$('body').delegate('.delete-loan', 'click', function(e){
   var check = confirm('Are you sure you want to remove this loan?');
   if(!check || check == null){
       e.preventDefault();
   }
});

var instanceVars = {
  balances: {!! json_encode($balances) !!}, 
  balanceAddresses: {!! json_encode($balance_addresses) !!}, 
  disabledTokens: {!! json_encode($disabled_tokens) !!},
  addressLabels: {!! json_encode($address_labels) !!},
  pockets: {!! json_encode($addresses) !!},
  loans: {!! json_encode($loans) !!}
}

Number.prototype.noExponents = function(){
    var data= String(this).split(/[eE]/);
    if(data.length== 1) return data[0]; 

    var  z= '', sign= this<0? '-':'',
    str= data[0].replace('.', ''),
    mag= Number(data[1])+ 1;

    if(mag<0){
        z= sign + '0.';
        while(mag++) z += '0';
        return z + str.replace(/^\-/,'');
    }
    mag -= str.length;  
    while(mag--) z += '0';
    return str + z;
}

// Process tokens for vue consumption
var data = (function(args){
  var BALANCES = args['balances'], 
      BALANCE_ADDRESSES = args['balanceAddresses'], 
      DISABLED_TOKENS = args['disabledTokens'], 
      ADDRESS_LABELS = args['addressLabels'],
      LOANS = args['loans']

  // Convert balances into an array of token objects
  var tokens_arr = [];
  for(var key in BALANCES){
    var balanceAddress = getBalanceAddresses(key);
    if(balanceAddress.length == 0){
        continue;
    }
    tokens_arr.push({
      name: key,
      balance: BALANCES[key],
      balanceAddresses: balanceAddress,
      hasPromisedTokens: hasPromisedTokens(balanceAddress),
      hasLoanedTokens: hasLoanedTokens(balanceAddress),
      toggle: !DISABLED_TOKENS.includes(key)
    });
  }
  
  var loans_arr = [];
  for(var key in LOANS){
      var loan = LOANS[key];
      loans_arr.push(loan);
  }

  // Get array of address balances of each token
  function getBalanceAddresses(token){
    var balance_addresses_arr = [];
    var balances = BALANCE_ADDRESSES[token]
    for (var key in balances){
      var real = parseInt(balances[key]['real']);
      var provisional = balances[key]['provisional'];
      var loans = balances[key]['loans'];

      // total up provisionals
      var provisional_total = 0;
      for (var i = 0; i < provisional.length; i++){
        provisional_total += parseInt(provisional[i].quantity);
      }
      var loan_total = 0;
      for (var i = 0; i < loans.length; i++){
        loan_total += parseInt(loans[i].quantity);
      }
      var total = real + provisional_total - loan_total;
      
      balance_addresses_arr.push({
        address: key,
        label: ADDRESS_LABELS[key],
        provisional: provisional,
        loans: loans,
        loan_total: loan_total,
        provisional_total: provisional_total,
        real: real,
        total: total
      })
    }
    return balance_addresses_arr;
  }

  function hasPromisedTokens(balanceAddresses){
    for (var i = 0; i < balanceAddresses.length; i++) {
      if (balanceAddresses[i]["provisional"].length > 0){
        return true;
      }
    }
    return false;
  }
  
  function hasLoanedTokens(balanceAddresses){
    for (var i = 0; i < balanceAddresses.length; i++) {
      if (balanceAddresses[i]["loans"].length > 0){
        return true;
      }
    }
    return false;
  }
  

  return {
    tokens: tokens_arr,
    loans: loans_arr,
  };

})(instanceVars)

var vm = new Vue({
  el: '#tokensController',
  data: {
    search: '',
    tokens: data.tokens,
    loans: data.loans,
    instanceVars: instanceVars,
    // Dynamically populated by methods
    currentToken: {},
    currentPocket: {},
    currentLoan: {},
    verifiedPocketIndex: null,
    lendCreateEndDate: '',
    lendCreateEndTime: '',
    lendCreateEndDateTime: '',
    lendEditEndDate: '',
    lendEditEndTime: '',
    lendEditEndDateTime: ''
  },
  methods: {
    setCurrentToken: function(token, pocket){
      if (typeof pocket == 'undefined') { pocket = null; }
      this.currentToken = token;
      this.currentPocket = pocket;
    },
  	formatQuantity: function(q){
  		return this.delimitNumbers((q / 100000000).noExponents());
  	},
    setCurrentLoan: function(loan){
      this.currentLoan = loan;
      var date = moment(this.utcToDate(loan.expiration));
      this.lendEditEndDate = date.format('YYYY-MM-DD');
      this.lendEditEndTime = date.format('HH:mm');
      this.lendEditEndDateTime = moment(this.utcToDate(loan.expiration)).format('YYYY-MM-DD HH:mm Z');
    },
    relativeTime: function(t){
        if(t == null || t <= 0){
            return 'n/a';
        }
        var d = this.utcToDate(t);
        var m = moment(d).fromNow();
        return m;
    },
    formatDate: function(t){
        if(t == null || t <= 0){
            return null;
        }
        var d = this.utcToDate(t);
        var full_d = moment(d).format('DD/MM/YYYY HH:MM');
        return full_d;        
    },
    toggleSecondaryInfo: function(event){
      var $token = $(event.target).closest('.token');
      var $secondaryInfo = $token.find('.secondary-info');
      $secondaryInfo.slideToggle();
    },
    hideAllSecondaryInfo: function(){
      $('.token .secondary-info').hide();
    },
    delimitNumbers: function(str) {
      return (str + "").replace(/\b(\d+)((\.\d+)*)\b/g, function(a, b, c) {
        return (b.charAt(0) > 0 && !(c || ".").lastIndexOf(".") ? b.replace(/(\d)(?=(\d{3})+$)/g, "$1,") : b) + c;
      });
    },
    toggleActive: function(token){
      var url = 'inventory/asset/' + token.name + '/toggle';
      $.ajax(url,{
        type: 'POST',
        data: {
          toggle: token.toggle
        },
        success: function(res){
          console.log('' + token.name + ' toggle updated successfully.');
        } 
      });
    },
    getVerifiedPocket: function(){
      if (this.verifiedPocketIndex == null){
        // Pockets haven't been searched
        for(var i = 0; i < this.instanceVars.pockets.length; i++){
          if (this.instanceVars.pockets[i].verified){
            this.verifiedPocketIndex = i;
            return this.instanceVars.pockets[this.verifiedPocketIndex];
          }
        }
      } else if (this.verifiedPocketIndex === -1) {
        // Pockets were searched, no verified pockets found
        return null;
      } else {
        // Pockets already searched, return object with cached index 
        return this.instanceVars.pockets[this.verifiedPocketIndex]; 
      }
      return null;
    },
    getCurrentTimeOffset: function(){
      var offset = new Date().getTimezoneOffset(), o = Math.abs(offset);
      return (offset < 0 ? "+" : "-") + ("00" + Math.floor(o / 60)).slice(-2) + ":" + ("00" + (o % 60)).slice(-2);
    },
    setDateTime: function(datetimeVModel, dateStr, timeStr){
      timeStr = timeStr || '00:00'
      var dateTimeStr = dateStr + 'T' + timeStr + ':00' + this.getCurrentTimeOffset();
      var dateObj = new Date(dateTimeStr);
      this[datetimeVModel] = moment(dateObj).format('YYYY/MM/DD HH:mm:ss Z');
    },
    utcToDate: function(utc){
      return new Date(utc * 1000);
    }
  },
 ready:function(){
    $(this.el).find(['v-cloak']).slideDown();
  }
});

// Initialize lend token modal
var lendTokenModal = new Modal();
lendTokenModal.init(document.getElementById(
  'lendTokenModal'));

// Initialize promise info modal
var promiseInfoModal = new Modal();
promiseInfoModal.init(document.getElementById(
  'promiseInfoModal'));
  
  
// Initialize edit loan modal
var editLoanModal = new Modal();
editLoanModal.init(document.getElementById(
  'editLoanModal'));

// Invokes input masking
$(":input").inputmask();

</script>
@endsection

@extends('accounts.base')

@section('htmltitle', $token_name.' Details')

@section('body_class', 'dashboard token-details')

@section('accounts_content')

<section class="title">
  <span class="heading">Token Information</span>
  <a href="/inventory" class="btn-dash-title">
    View All Tokens
  </a>
</section>

<section class="section__token-details">
  <div class="outer-container">
    <div class="span-6">
      <div class="panel-pre-heading">Primary</div>
      <div class="panel with-padding">
        <div class="primary">
          <div class="primary__avatar"></div>
          <div>
            <div class="primary__name">Bitcoin</div>
            <div class="primary__balance">11.024</div>
          </div>
        </div>
      </div>

      <div class="panel-pre-heading">Analytics</div>
      <div class="panel with-padding">
        charts and graphs go here
      </div>
    </div>

    <div class="span-6">
      <div class="panel-pre-heading">Info</div>
      <div class="panel with-padding">
        <div class="outer-container">
          <div class="info__node">
            <div class="info__node__title span-3">Website</div>
            <div class="span-9"><a href="#" target="_blank">http://bitcoin.org</a></div>
          </div>
          <div class="info__node">
            <div class="info__node__title span-3">Amount</div>
            <div class="span-9">10 million in circulation</div>
          </div>
          <div class="info__node">
            <div class="info__node__title span-3">Sponser</div>
            <div class="span-9">Internation House of Pancakes</div>
          </div>
          <div class="info__node">
            <div class="info__node__title span-3">Vendor</div>
            <div class="span-9"><a href="#" target="_blank">http://bitcoin.org/vend</a></div>
          </div>
          <div class="info__node">
            <div class="info__node__title span-3">Marketplace</div>
            <div class="span-9"><a href="#" target="_blank">http://bitcoin.org/sell</a></div>
          </div>
        </div>
      </div>

      <div class="panel-pre-heading">Holders</div>
      <div class="panel with-padding">
        <div class="outer-container">
          <div class="info__node">
            <div class="info__node__title span-5">@raider</div>
            <div class="span-7">521.2421</div>
          </div>
          <div class="info__node">
            <div class="info__node__title span-5">@adamblevine</div>
            <div class="span-7">7.52</div>
          </div>
          <div class="info__node">
            <div class="info__node__title span-5">@elonmusk</div>
            <div class="span-7">10</div>
          </div>
        </div>
      </div>
    </div>
  </div>
  
</section>

@endsection


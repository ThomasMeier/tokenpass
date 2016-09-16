@extends('accounts.base')

@section('htmltitle', $token_name.' Details')

@section('body_class', 'dashboard token-details')

@section('accounts_content')

<?php
$defaultAvatar = '/img/Tokenly_Logo_Icon_Light.svg' ;

$asset = $bvam['asset']; 
$name = $bvam['metadata']['name'];
$shortName = false;
if(isset($bvam['metadata']['short_name'])){
    $shortName = $bvam['metadata']['short_name'];
}
$description = $bvam['metadata']['description'];
$website = $bvam['metadata']['website'];
$supply = round($bvam['assetInfo']['supply'] / 100000000, 8);
$avatar = null;
if(isset($bvam['metadata']['images'][0])){
    $avatar = $bvam['metadata']['images'][0]['data'];
}

?>

<section class="title">
  <span class="heading">Token Information</span>
  <a href="/inventory" class="btn-dash-title">
    View All Tokens
  </a>
</section>

<section class="section__token-details">
  <div class="outer-container">
    <div class="panel-pre-heading">Asset</div>
    <div class="panel with-padding panel--details">
      <div class="primary">
        <div class="primary__avatar">
            @if($avatar)
                <img src="{{ $avatar }}" alt="{{ $asset }} Avatar">
            @else
                <img src="/img/Tokenly_Logo_Icon_White.svg" alt="No avatar found" />
            @endif
        </div>
        <div>
          <div class="primary__name">{{ $shortName or $name }}</div>
          <div class="primary__sub">{{ $asset }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="outer-container">
    <div class="span-12">
      <div class="panel-pre-heading">Info</div>
      <div class="panel with-padding panel--details">
        <div class="outer-container">
          @if ($description)
          <div class="info__node">
            {{ $description }}
          </div>
          @endif

          @if ($website)
            <div class="info__node">
              <div class="info__node__title span-3">Website</div>
              <div class="span-9">
                <a href="{{ $website }}">{{ $website }}</a>
              </div>
            </div>
          @endif

          @if ($supply)
            <div class="info__node">
              <div class="info__node__title span-3">Global Supply</div>
              <div class="span-9">{{ number_format($supply) }}</div>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
  
</section>

@endsection


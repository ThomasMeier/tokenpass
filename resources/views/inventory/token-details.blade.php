@extends('accounts.base')

@section('htmltitle', $token_name.' Details')

@section('body_class', 'dashboard token-details')

@section('accounts_content')

{{--*/ $defaultAvatar = '/img/Tokenly_Logo_Icon_Light.svg' /*--}}

{{--*/ $asset = $bvam['asset'] ?? null /*--}}
{{--*/ $name = $bvam['metadata']['name'] ?? null /*--}}
{{--*/ $shortName = $bvam['metadata']['shortName'] ?? null /*--}}
{{--*/ $description = $bvam['metadata']['description'] ?? null /*--}}
{{--*/ $website = $bvam['metadata']['website'] ?? null /*--}}
{{--*/ $supply = $bvam['assetInfo']['supply'] ?? null /*--}}
{{--*/ $avatar = $bvam['metadata']['images'][0]['data'] ?? $defaultAvatar /*--}}

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
          <img src="{{ $avatar }}" alt="Asset Avatar">
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
              <div class="info__node__title span-3">Supply</div>
              <div class="span-9">{{ number_format($supply) }}</div>
            </div>
          @endif
        </div>
      </div>
    </div>
  </div>
  
</section>

@endsection

